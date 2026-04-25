<?php

declare(strict_types=1);

namespace Wizdam\Database\Models;

use Wizdam\Database\DBConnector;

/**
 * Proxy tipis ke tabel wizdam_scores.
 * Kalkulasi bobot dilakukan oleh Sangia API, bukan di sini.
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
            'SELECT * FROM wizdam_scores
             WHERE entity_type = ? AND entity_id = ?
             ORDER BY calculated_at DESC
             LIMIT 1',
            [$entityType, $entityId]
        );
    }

    public function save(
        string  $entityType,
        int     $entityId,
        float   $academic,
        float   $social,
        float   $policy,
        float   $practical,
        float   $total,
        array   $sdgSummary = []
    ): string {
        return $this->db->insert('wizdam_scores', [
            'entity_type'        => $entityType,
            'entity_id'          => $entityId,
            'score_academic'     => round($academic,  4),
            'score_social'       => round($social,    4),
            'score_policy'       => round($policy,    4),
            'score_practical'    => round($practical, 4),
            'total_impact_score' => round($total,     4),
            'sdg_summary_json'   => $sdgSummary ? json_encode($sdgSummary) : null,
            'calculated_at'      => date('Y-m-d H:i:s'),
        ]);
    }

    /** Riwayat skor untuk grafik tren (N bulan terakhir). */
    public function getHistory(string $entityType, int $entityId, int $months = 12): array
    {
        return $this->db->fetchAll(
            'SELECT total_impact_score, score_academic, score_social,
                    score_policy, score_practical, calculated_at
             FROM wizdam_scores
             WHERE entity_type = ? AND entity_id = ?
               AND calculated_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
             ORDER BY calculated_at ASC',
            [$entityType, $entityId, $months]
        );
    }

    /** Rata-rata 4 pilar untuk semua entitas sejenis (dipakai sebagai pembanding). */
    public function getAveragePillars(string $entityType): array
    {
        return $this->db->fetchOne(
            'SELECT AVG(score_academic)     AS avg_academic,
                    AVG(score_social)       AS avg_social,
                    AVG(score_policy)       AS avg_policy,
                    AVG(score_practical)    AS avg_practical,
                    AVG(total_impact_score) AS avg_total
             FROM wizdam_scores ws
             WHERE entity_type = ?
               AND calculated_at = (
                   SELECT MAX(calculated_at) FROM wizdam_scores ws2
                   WHERE ws2.entity_type = ws.entity_type
                     AND ws2.entity_id   = ws.entity_id
               )',
            [$entityType]
        ) ?: [];
    }

    /** Distribusi skor untuk histogram admin (bucket per 10 poin). */
    public function getScoreDistribution(string $entityType): array
    {
        return $this->db->fetchAll(
            'SELECT FLOOR(total_impact_score / 10) * 10 AS bucket, COUNT(*) AS count
             FROM wizdam_scores
             WHERE entity_type = ?
             GROUP BY bucket
             ORDER BY bucket ASC',
            [$entityType]
        );
    }
}
