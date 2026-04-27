<?php

declare(strict_types=1);

namespace Wizdam\Services\SangiaApi;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Mengecek status indeksasi jurnal/artikel di Scopus, SINTA, dan WoS.
 * Scopus: langsung ke Elsevier API.
 * SINTA/WoS: melalui SangiaGateway (X-API-Key, /api/v1/...).
 */
class IndexingIntegrator
{
    private SangiaGateway $gateway;
    private array $apiCfg;

    public function __construct()
    {
        $this->apiCfg  = require BASE_PATH . '/config/api.php';
        $this->gateway = new SangiaGateway();
    }

    /**
     * Cek status indeksasi lengkap untuk satu ISSN.
     *
     * @return array{scopus: array, sinta: array, wos: array}
     */
    public function checkByIssn(string $issn): array
    {
        return [
            'scopus' => $this->checkScopus($issn),
            'sinta'  => $this->checkSinta($issn),
            'wos'    => $this->checkWos($issn),
        ];
    }

    private function checkScopus(string $issn): array
    {
        $key = $this->apiCfg['scopus']['api_key'] ?? '';
        if (!$key) {
            return ['indexed' => false, 'error' => 'Scopus API key tidak dikonfigurasi'];
        }

        try {
            $http     = new Client(['timeout' => 20]);
            $response = $http->get(
                $this->apiCfg['scopus']['base_url'] . '/serial/title/issn/' . urlencode($issn),
                ['headers' => ['X-ELS-APIKey' => $key, 'Accept' => 'application/json']]
            );

            $data  = json_decode((string) $response->getBody(), true);
            $entry = $data['serial-metadata-response']['entry'][0] ?? null;

            if (!$entry || isset($entry['error'])) {
                return ['indexed' => false];
            }

            return [
                'indexed'    => true,
                'sjr'        => $entry['SJRList']['SJR'][0]['$'] ?? null,
                'snip'       => $entry['SNIPList']['SNIP'][0]['$'] ?? null,
                'cite_score' => $entry['citeScoreYearInfoList']['citeScoreCurrentMetric'] ?? null,
                'publisher'  => $entry['dc:publisher'] ?? null,
            ];

        } catch (GuzzleException $e) {
            return ['indexed' => false, 'error' => $e->getMessage()];
        }
    }

    private function checkSinta(string $issn): array
    {
        // SINTA tidak memiliki API publik resmi; gunakan cache Sangia (/api/v1/sinta/score)
        $result = $this->gateway->getSintaScore($issn);
        if (($result['status'] ?? '') !== 'success') {
            return ['indexed' => false, 'error' => $result['message'] ?? 'Sangia error'];
        }
        return array_merge(['indexed' => true], $result['data'] ?? []);
    }

    private function checkWos(string $issn): array
    {
        // WoS melalui Sangia endpoint journal/metrics (source=wos)
        $result = $this->gateway->getJournalMetrics($issn);
        if (($result['status'] ?? '') !== 'success') {
            return ['indexed' => false, 'error' => $result['message'] ?? 'Sangia error'];
        }
        $data = $result['data'] ?? [];
        return [
            'indexed'    => !empty($data['wos']),
            'wos_quartile' => $data['wos']['quartile']   ?? null,
            'wos_if'      => $data['wos']['impact_factor'] ?? null,
        ];
    }
}
