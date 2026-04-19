<?php

declare(strict_types=1);

namespace Wizdam\Services\SangiaApi;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Mengecek status indeksasi jurnal/artikel di Scopus, SINTA, dan WoS
 * melalui Sangia API atau langsung ke sumber.
 */
class IndexingIntegrator
{
    private Client $http;
    private array $apiCfg;

    public function __construct()
    {
        $this->apiCfg = require BASE_PATH . '/config/api.php';
        $this->http   = new Client(['timeout' => 20]);
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
            return ['indexed' => false, 'error' => 'API key tidak dikonfigurasi'];
        }

        try {
            $response = $this->http->get(
                $this->apiCfg['scopus']['base_url'] . '/serial/title/issn/' . urlencode($issn),
                ['headers' => ['X-ELS-APIKey' => $key, 'Accept' => 'application/json']]
            );

            $data   = json_decode((string) $response->getBody(), true);
            $entry  = $data['serial-metadata-response']['entry'][0] ?? null;

            if (!$entry || isset($entry['error'])) {
                return ['indexed' => false];
            }

            return [
                'indexed'  => true,
                'sjr'      => $entry['SJRList']['SJR'][0]['$'] ?? null,
                'snip'     => $entry['SNIPList']['SNIP'][0]['$'] ?? null,
                'cite_score' => $entry['citeScoreYearInfoList']['citeScoreCurrentMetric'] ?? null,
                'publisher'  => $entry['dc:publisher'] ?? null,
            ];

        } catch (GuzzleException $e) {
            return ['indexed' => false, 'error' => $e->getMessage()];
        }
    }

    private function checkSinta(string $issn): array
    {
        // SINTA tidak memiliki API publik resmi; gunakan cache Sangia
        try {
            $response = $this->http->get(
                $this->apiCfg['sangia']['base_url'] . '/v1/indexing/sinta',
                [
                    'query'   => ['issn' => $issn],
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->apiCfg['sangia']['api_key'],
                        'Accept'        => 'application/json',
                    ],
                ]
            );

            return json_decode((string) $response->getBody(), true) ?? ['indexed' => false];

        } catch (GuzzleException $e) {
            return ['indexed' => false, 'error' => $e->getMessage()];
        }
    }

    private function checkWos(string $issn): array
    {
        try {
            $response = $this->http->get(
                $this->apiCfg['sangia']['base_url'] . '/v1/indexing/wos',
                [
                    'query'   => ['issn' => $issn],
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->apiCfg['sangia']['api_key'],
                        'Accept'        => 'application/json',
                    ],
                ]
            );

            return json_decode((string) $response->getBody(), true) ?? ['indexed' => false];

        } catch (GuzzleException $e) {
            return ['indexed' => false, 'error' => $e->getMessage()];
        }
    }
}
