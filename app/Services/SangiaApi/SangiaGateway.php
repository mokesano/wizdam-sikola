<?php

declare(strict_types=1);

namespace Wizdam\Services\SangiaApi;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Gateway utama ke Sangia API Engine (api.sangia.org).
 *
 * Kontrak:
 *   - Auth : X-API-Key header (format wz_{user_id}_{timestamp}_{hmac16})
 *   - Batch: loop hingga status !== 'processing'
 *   - supplied_data: kirim data dari DB untuk skip external fetch
 *   - raw_data: simpan ke DB saat API fetch dari sumber eksternal
 */
class SangiaGateway
{
    private Client $http;
    private string $baseUrl;
    private string $apiKey;

    /** Delay antar batch request dalam microseconds (300 ms). */
    private const BATCH_DELAY_US = 300_000;

    public function __construct(?string $apiKey = null)
    {
        $cfg           = require BASE_PATH . '/config/api.php';
        $this->baseUrl = rtrim($cfg['sangia']['base_url'] ?? 'https://api.sangia.org', '/');
        $this->apiKey  = $apiKey ?? $cfg['sangia']['api_key'] ?? '';
        $timeout       = (int) ($cfg['sangia']['timeout'] ?? 30);

        $this->http = new Client([
            'base_uri' => $this->baseUrl,
            'timeout'  => $timeout,
            'headers'  => [
                'X-API-Key'    => $this->apiKey,
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'http_errors' => false,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SDG Classification
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Klasifikasi SDG untuk title + abstract.
     *
     * @return array [['sdg' => 7, 'code' => 'SDG7', 'score' => 0.72, 'label' => '...'], ...]
     */
    public function classifySdgByText(string $title, string $abstract = '', string $version = 'v5', array $weights = []): array
    {
        $payload = ['title' => $title, 'abstract' => $abstract];
        if ($weights) {
            $payload['weights'] = $weights;
        }

        $result = $this->post("/api/v1/sdg/{$version}/classify", $payload);
        return $this->normalizeSdgResult($result);
    }

    /**
     * Klasifikasi SDG untuk seorang peneliti berdasarkan ORCID.
     * Menggunakan pola batch dan supplied_works dari DB.
     *
     * @param array $suppliedWorks Karya dari DB Wizdam Sikola
     * @param array $suppliedPerson Person data dari DB
     */
    public function classifySdgByOrcid(
        string $orcid,
        array  $suppliedWorks  = [],
        array  $suppliedPerson = [],
        string $version        = 'v5',
        array  $weights        = []
    ): array {
        $basePayload = ['orcid' => $orcid, 'batch_size' => 20];
        if ($suppliedWorks)  $basePayload['supplied_works']  = $suppliedWorks;
        if ($suppliedPerson) $basePayload['supplied_person'] = $suppliedPerson;
        if ($weights)        $basePayload['weights']         = $weights;

        $result = $this->batchPost("/api/v1/sdg/{$version}/classify", $basePayload);
        return $this->normalizeSdgResult($result);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Impact Score
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Hitung Wizdam Impact Score (4 pilar).
     *
     * @param array $suppliedWorks   Karya dari DB
     * @param array $suppliedPerson  Person data dari DB
     * @param array $suppliedScopus  Scopus data dari DB
     * @param array $social          Social pillar inputs [media_mentions, policy_citations, ...]
     * @param array $economic        Economic pillar inputs [industry_adoption, patents, ...]
     * @param array $weights         Override bobot komposit [academic, social, economic, sdg]
     */
    public function calculateImpact(
        string  $orcid,
        ?string $scopusId      = null,
        array   $suppliedWorks  = [],
        array   $suppliedPerson = [],
        array   $suppliedScopus = [],
        array   $social         = [],
        array   $economic       = [],
        array   $weights        = []
    ): array {
        $payload = ['orcid' => $orcid, 'batch_size' => 20];

        if ($scopusId)       $payload['scopus_id']       = $scopusId;
        if ($suppliedWorks)  $payload['supplied_works']  = $suppliedWorks;
        if ($suppliedPerson) $payload['supplied_person'] = $suppliedPerson;
        if ($suppliedScopus) $payload['supplied_scopus'] = $suppliedScopus;
        if ($social)         $payload['social']          = $social;
        if ($economic)       $payload['economic']        = $economic;
        if ($weights)        $payload['weights']         = $weights;

        return $this->batchPost('/api/v1/impact/calculate', $payload);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ORCID Profile
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Ambil profil peneliti dari ORCID.
     * Kirim supplied_data jika sudah ada di DB untuk skip external fetch.
     */
    public function getOrcidProfile(
        string $orcid,
        array  $suppliedWorks  = [],
        array  $suppliedPerson = [],
        bool   $refresh        = false
    ): array {
        $payload = ['supplied_works' => $suppliedWorks, 'supplied_person' => $suppliedPerson];
        return $this->get('/api/v1/orcid/profile', [
            'orcid'   => $orcid,
            'refresh' => $refresh ? 'true' : 'false',
        ], $payload);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Scopus Author
    // ─────────────────────────────────────────────────────────────────────────

    public function getScopusAuthor(string $authorId, int $count = 10, array $suppliedScopus = []): array
    {
        $body = $suppliedScopus ? ['supplied_scopus' => $suppliedScopus] : [];
        return $this->get('/api/v1/scopus/author', ['authorid' => $authorId, 'count' => $count], $body);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Citation DOI
    // ─────────────────────────────────────────────────────────────────────────

    public function getCitationByDoi(string $doi, int $limit = 15, bool $refresh = false): array
    {
        return $this->get('/api/v1/citation/doi', [
            'doi'     => $doi,
            'limit'   => $limit,
            'refresh' => $refresh ? 'true' : 'false',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Journal Metrics
    // ─────────────────────────────────────────────────────────────────────────

    public function getJournalMetrics(string $issn, bool $refresh = false): array
    {
        return $this->get('/api/v1/journal/metrics', [
            'issn'    => $issn,
            'refresh' => $refresh ? 'true' : 'false',
        ]);
    }

    public function getSintaScore(string $issn, bool $refresh = false): array
    {
        return $this->get('/api/v1/sinta/score', [
            'issn'    => $issn,
            'refresh' => $refresh ? 'true' : 'false',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Trend Analysis
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @param string $analysisType impact_trajectory|sdg_evolution|collaboration_network|citation_growth
     * @param string $timeRange    1y|3y|5y|10y|all
     */
    public function analyzeTrend(
        string $orcid,
        string $analysisType   = 'impact_trajectory',
        string $timeRange      = '5y',
        array  $suppliedWorks  = [],
        array  $suppliedScopus = [],
        ?string $scopusId      = null
    ): array {
        $payload = [
            'orcid'         => $orcid,
            'analysis_type' => $analysisType,
            'time_range'    => $timeRange,
        ];
        if ($suppliedWorks)  $payload['supplied_works']  = $suppliedWorks;
        if ($suppliedScopus) $payload['supplied_scopus'] = $suppliedScopus;
        if ($scopusId)       $payload['scopus_id']       = $scopusId;

        return $this->post('/api/v1/trend/analyze', $payload);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Policy Recommendation
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @param string $stakeholderType government|institution|industry|researcher|community
     */
    public function getPolicyRecommendation(
        string $stakeholderType,
        string $domain,
        string $timeHorizon,
        array  $researchLandscape,
        string $region = 'Indonesia'
    ): array {
        return $this->post('/api/v1/recommendation/policy', [
            'stakeholder_type'    => $stakeholderType,
            'domain'              => $domain,
            'time_horizon'        => $timeHorizon,
            'region'              => $region,
            'research_landscape'  => $researchLandscape,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Admin
    // ─────────────────────────────────────────────────────────────────────────

    public function revokeApiKey(string $key): array
    {
        return $this->post('/api/v1/admin/keys/revoke', ['key' => $key]);
    }

    public function healthCheck(): array
    {
        return $this->get('/health');
    }

    public function getSdgVersions(): array
    {
        return $this->get('/api/v1/sdg/versions');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Internal HTTP helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * GET request. $body dikirim sebagai JSON body (opsional).
     */
    private function get(string $path, array $query = [], array $body = []): array
    {
        $options = [];
        if ($query) {
            $options['query'] = $query;
        }
        if ($body) {
            $options['json'] = $body;
        }

        try {
            $resp = $this->http->get($path, $options);
            return $this->decode($resp->getBody()->getContents());
        } catch (GuzzleException $e) {
            error_log("[SangiaGateway] GET {$path} error: " . $e->getMessage());
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /** POST request — satu request, tidak ada loop. */
    private function post(string $path, array $body = []): array
    {
        try {
            $resp = $this->http->post($path, ['json' => $body]);
            return $this->decode($resp->getBody()->getContents());
        } catch (GuzzleException $e) {
            error_log("[SangiaGateway] POST {$path} error: " . $e->getMessage());
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * POST dengan pola batch anti-timeout.
     * Loop hingga status bukan 'processing', mulai ulang jika HTTP 410 (session expired).
     *
     * @param callable|null $onProgress fn(array $progress): void
     */
    public function batchPost(string $path, array $basePayload, ?callable $onProgress = null): array
    {
        $offset  = 0;
        $retries = 0;

        while (true) {
            $payload = array_merge($basePayload, ['offset' => $offset]);

            try {
                $resp   = $this->http->post($path, ['json' => $payload]);
                $status = $resp->getStatusCode();
                $result = $this->decode($resp->getBody()->getContents());

                // Session expired → restart dari offset 0
                if ($status === 410) {
                    if ($retries++ > 2) {
                        return ['status' => 'error', 'message' => 'Batch session expired berulang kali.'];
                    }
                    $offset = 0;
                    usleep(self::BATCH_DELAY_US);
                    continue;
                }

                if (($result['status'] ?? '') === 'processing') {
                    if ($onProgress) {
                        $onProgress($result['progress'] ?? []);
                    }
                    $offset = $result['next_offset'] ?? ($offset + ($basePayload['batch_size'] ?? 20));
                    usleep(self::BATCH_DELAY_US);
                    continue;
                }

                return $result;

            } catch (GuzzleException $e) {
                error_log("[SangiaGateway] batchPost {$path} error: " . $e->getMessage());
                return ['status' => 'error', 'message' => $e->getMessage()];
            }
        }
    }

    private function decode(string $body): array
    {
        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['status' => 'error', 'message' => 'Invalid JSON response from Sangia API'];
        }
        return $data;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Normalisasi hasil SDG
    // ─────────────────────────────────────────────────────────────────────────

    private function normalizeSdgResult(array $result): array
    {
        if (($result['status'] ?? '') !== 'success') {
            return [];
        }

        $analysis = $result['sdg_analysis'] ?? [];
        $sdgCodes = $analysis['sdgs'] ?? [];
        $confidence = $analysis['sdg_confidence'] ?? [];

        $output = [];
        foreach ($sdgCodes as $code) {
            $num = (int) filter_var($code, FILTER_SANITIZE_NUMBER_INT);
            if ($num < 1 || $num > 17) continue;
            $output[] = [
                'sdg'   => $num,
                'code'  => $code,
                'score' => round((float) ($confidence[$code] ?? 0), 4),
                'label' => SdgIntegrator::getLabel($num),
            ];
        }
        return $output;
    }
}
