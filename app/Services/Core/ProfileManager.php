<?php

declare(strict_types=1);

namespace Wizdam\Services\Core;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Wizdam\Database\Models\ResearcherModel;

/**
 * Menggabungkan identitas peneliti dari berbagai sumber eksternal:
 * ORCID, Publons/WoS Researcher, Scopus Author ID, Google Scholar.
 */
class ProfileManager
{
    private Client $http;
    private ResearcherModel $researcherModel;
    private array $apiCfg;

    public function __construct()
    {
        $this->apiCfg          = require BASE_PATH . '/config/api.php';
        $this->http            = new Client(['timeout' => 20]);
        $this->researcherModel = new ResearcherModel();
    }

    /**
     * Ambil profil lengkap peneliti dari ORCID API.
     *
     * @return array Profil mentah dari ORCID
     */
    public function fetchFromOrcid(string $orcidId): array
    {
        $cfg     = $this->apiCfg['orcid'];
        $apiBase = $cfg['sandbox']
            ? 'https://pub.sandbox.orcid.org/v3.0'
            : $cfg['api_url'];

        try {
            $response = $this->http->get("$apiBase/$orcidId/record", [
                'headers' => ['Accept' => 'application/json'],
            ]);

            $data    = json_decode((string) $response->getBody(), true);
            $person  = $data['person'] ?? [];
            $works   = $data['activities-summary']['works']['group'] ?? [];

            return [
                'orcid'       => $orcidId,
                'name'        => $this->extractName($person),
                'biography'   => $person['biography']['content'] ?? null,
                'keywords'    => $this->extractKeywords($person),
                'works_count' => count($works),
                'affiliations' => $this->extractAffiliations($data),
                'raw'         => $data,
            ];

        } catch (GuzzleException $e) {
            error_log("[ProfileManager] ORCID fetch error ($orcidId): " . $e->getMessage());
            return [];
        }
    }

    /**
     * Sinkronkan data ORCID ke database lokal.
     *
     * @return string ID researcher di database
     */
    public function syncFromOrcid(string $orcidId): string
    {
        $profile = $this->fetchFromOrcid($orcidId);
        if (!$profile) {
            throw new \RuntimeException("Gagal mengambil profil ORCID: $orcidId");
        }

        // Field names sesuai database_schema_full.sql
        return $this->researcherModel->upsert([
            'orcid_id'    => $profile['orcid'],
            'full_name'   => $profile['name'],
            'biography'   => $profile['biography'] ?? null,
            'works_count' => $profile['works_count'] ?? 0,
        ]);
    }

    /** Ambil data author dari Scopus berdasarkan Scopus Author ID. */
    public function fetchFromScopus(string $scopusAuthorId): array
    {
        $apiKey = $this->apiCfg['scopus']['api_key'] ?? '';
        if (!$apiKey) {
            return ['error' => 'Scopus API key tidak dikonfigurasi'];
        }

        try {
            $response = $this->http->get(
                $this->apiCfg['scopus']['base_url'] . '/author/author_id/' . urlencode($scopusAuthorId),
                [
                    'headers' => [
                        'X-ELS-APIKey' => $apiKey,
                        'Accept'       => 'application/json',
                    ],
                    'query' => ['view' => 'ENHANCED'],
                ]
            );

            $data   = json_decode((string) $response->getBody(), true);
            $author = $data['author-retrieval-response'][0] ?? [];

            return [
                'scopus_id'        => $scopusAuthorId,
                'name'             => $author['preferred-name']['indexed-name'] ?? '',
                'h_index'          => $author['h-index'] ?? null,
                'document_count'   => $author['coredata']['document-count'] ?? null,
                'citation_count'   => $author['coredata']['citation-count'] ?? null,
                'cited_by_count'   => $author['coredata']['cited-by-count'] ?? null,
                'affiliation'      => $author['affiliation-current']['affiliation-name'] ?? null,
            ];

        } catch (GuzzleException $e) {
            error_log("[ProfileManager] Scopus fetch error: " . $e->getMessage());
            return [];
        }
    }

    // ── Helper ekstraksi ───────────────────────────────────────────────────

    private function extractName(array $person): string
    {
        $name = $person['name'] ?? [];
        $given  = $name['given-names']['value']  ?? '';
        $family = $name['family-name']['value']   ?? '';
        return trim("$given $family") ?: ($name['credit-name']['value'] ?? '');
    }

    private function extractKeywords(array $person): array
    {
        $kws = $person['keywords']['keyword'] ?? [];
        return array_column($kws, 'content');
    }

    private function extractAffiliations(array $data): array
    {
        $employments = $data['activities-summary']['employments']['affiliation-group'] ?? [];
        $result      = [];

        foreach ($employments as $group) {
            foreach ($group['summaries'] ?? [] as $summary) {
                $emp = $summary['employment-summary'] ?? [];
                $result[] = [
                    'organization' => $emp['organization']['name'] ?? '',
                    'role'         => $emp['role-title'] ?? '',
                    'start_year'   => $emp['start-date']['year']['value'] ?? null,
                    'end_year'     => $emp['end-date']['year']['value'] ?? null,
                ];
            }
        }

        return $result;
    }
}
