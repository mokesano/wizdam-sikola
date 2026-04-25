<?php

declare(strict_types=1);

namespace Wizdam\Services\SangiaApi;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Wizdam\Database\Models\ImpactScoreModel;

/**
 * Memicu kalkulasi 4 pilar Impact Score melalui Sangia AI Engine.
 *
 * Pilar: Academic (40%) | Social (25%) | Economic (20%) | SDG (15%)
 */
class ImpactScoreClient
{
    private Client $http;
    private ImpactScoreModel $scoreModel;
    private array $apiCfg;

    public function __construct()
    {
        $this->apiCfg     = require BASE_PATH . '/config/api.php';
        $this->http       = new Client([
            'base_uri' => $this->apiCfg['sangia']['base_url'],
            'timeout'  => $this->apiCfg['sangia']['timeout'],
            'headers'  => [
                'Authorization' => 'Bearer ' . $this->apiCfg['sangia']['api_key'],
                'Accept'        => 'application/json',
            ],
        ]);
        $this->scoreModel = new ImpactScoreModel();
    }

    /**
     * Minta kalkulasi skor untuk satu entitas.
     *
     * @param string $entityType researcher|article|institution|journal
     * @param int    $entityId
     * @return array Skor 4 pilar + komposit
     */
    public function calculate(string $entityType, int $entityId): array
    {
        try {
            $response = $this->http->post('/v1/impact-score/calculate', [
                'json' => [
                    'entity_type' => $entityType,
                    'entity_id'   => $entityId,
                ],
            ]);

            $result = json_decode((string) $response->getBody(), true);

            // Simpan ke database
            $this->scoreModel->saveCalculation(
                entityType: $entityType,
                entityId:   $entityId,
                academic:   (float) ($result['pillar_academic'] ?? 0),
                social:     (float) ($result['pillar_social']   ?? 0),
                economic:   (float) ($result['pillar_economic'] ?? 0),
                sdg:        (float) ($result['pillar_sdg']      ?? 0),
                sdgTags:    $result['sdg_tags'] ?? [],
            );

            return $result;

        } catch (GuzzleException $e) {
            error_log("[ImpactScoreClient] API error: " . $e->getMessage());

            // Fallback: kembalikan skor terakhir dari DB
            $cached = $this->scoreModel->findLatest($entityType, $entityId);
            return $cached ?: ['error' => 'Tidak dapat menghubungi Sangia API.'];
        }
    }

    /** Ambil skor terkini (dari DB, tanpa trigger kalkulasi ulang). */
    public function getLatest(string $entityType, int $entityId): array|false
    {
        return $this->scoreModel->findLatest($entityType, $entityId);
    }

    /** Batch kalkulasi untuk banyak entitas sekaligus. */
    public function calculateBatch(string $entityType, array $entityIds): array
    {
        $results = [];
        foreach ($entityIds as $id) {
            $results[$id] = $this->calculate($entityType, $id);
        }
        return $results;
    }
}
