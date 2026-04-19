<?php

declare(strict_types=1);

namespace Wizdam\Database\Models;

use Wizdam\Database\DBConnector;

/**
 * Merepresentasikan tabel `impact_scores`.
 *
 * 4 Pilar Dampak:
 *   1. Academic  – sitasi, h-index, i10-index
 *   2. Social    – mention media sosial, berita, kebijakan
 *   3. Economic  – adopsi industri, paten yang mengutip
 *   4. SDG       – keterkaitan dengan SDG PBB
 *
 * Kolom: id, entity_type (researcher|article|institution|journal),
 *        entity_id, pillar_academic, pillar_social, pillar_economic,
 *        pillar_sdg, composite_score, sdg_tags (JSON),
 *        calculated_at
 */
class ImpactScoreModel
{
    private DBConnector $db;

    public function __construct()
    {
        $this->db = DBConnector::getInstance();
    }

    public function findLatest(string $entityType, int $entityId): array|false
    {
        return $this->db->fetchOne(
            'SELECT * FROM impact_scores
             WHERE entity_type = ? AND entity_id = ?
             ORDER BY calculated_at DESC
             LIMIT 1',
            [$entityType, $entityId]
        );
    }

    /** Simpan hasil kalkulasi terbaru (insert baru, bukan update). */
    public function saveCalculation(
        string $entityType,
        int    $entityId,
        float  $academic,
        float  $social,
        float  $economic,
        float  $sdg,
        array  $sdgTags = []
    ): string {
        $composite = round(
            ($academic * 0.40) + ($social * 0.25) + ($economic * 0.20) + ($sdg * 0.15),
            4
        );

        return $this->db->insert('impact_scores', [
            'entity_type'     => $entityType,
            'entity_id'       => $entityId,
            'pillar_academic' => $academic,
            'pillar_social'   => $social,
            'pillar_economic' => $economic,
            'pillar_sdg'      => $sdg,
            'composite_score' => $composite,
            'sdg_tags'        => json_encode($sdgTags),
            'calculated_at'   => date('Y-m-d H:i:s'),
        ]);
    }

    /** Ambil riwayat skor untuk grafik tren (12 bulan terakhir). */
    public function getHistory(string $entityType, int $entityId, int $months = 12): array
    {
        return $this->db->fetchAll(
            'SELECT composite_score, pillar_academic, pillar_social,
                    pillar_economic, pillar_sdg, calculated_at
             FROM impact_scores
             WHERE entity_type = ? AND entity_id = ?
               AND calculated_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
             ORDER BY calculated_at ASC',
            [$entityType, $entityId, $months]
        );
    }

    /** Rata-rata pilar untuk semua entitas satu jenis (untuk perbandingan). */
    public function getAveragePillars(string $entityType): array
    {
        return $this->db->fetchOne(
            'SELECT AVG(pillar_academic) AS avg_academic,
                    AVG(pillar_social)   AS avg_social,
                    AVG(pillar_economic) AS avg_economic,
                    AVG(pillar_sdg)      AS avg_sdg,
                    AVG(composite_score) AS avg_composite
             FROM impact_scores
             WHERE entity_type = ?
               AND calculated_at = (
                   SELECT MAX(calculated_at) FROM impact_scores is2
                   WHERE is2.entity_type = impact_scores.entity_type
                     AND is2.entity_id   = impact_scores.entity_id
               )',
            [$entityType]
        ) ?: [];
    }
}
