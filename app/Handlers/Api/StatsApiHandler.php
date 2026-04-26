<?php

declare(strict_types=1);

namespace Wizdam\Handlers\Api;

use Wizdam\Database\DBConnector;
use Wizdam\Http\Middleware\CorsMiddleware;
use Wizdam\Http\Request;
use Wizdam\Http\Response;

/**
 * GET /api/v1/stats
 * Statistik ringkasan untuk dashboard: total peneliti, publikasi,
 * institusi, rata-rata wizdam score, distribusi field, dll.
 */
class StatsApiHandler
{
    private DBConnector $db;
    private CorsMiddleware $cors;

    public function __construct()
    {
        $this->db   = DBConnector::getInstance();
        $this->cors = new CorsMiddleware();
    }

    public function index(Request $request): Response
    {
        $this->cors->sendCorsHeaders();

        $stats = $this->buildStats();

        return Response::json([
            'success' => true,
            'data'    => $stats,
        ]);
    }

    private function buildStats(): array
    {
        $totals = $this->db->fetchOne(
            'SELECT
                (SELECT COUNT(*) FROM researchers)   AS total_researchers,
                (SELECT COUNT(*) FROM publications)  AS total_publications,
                (SELECT COUNT(*) FROM institutions)  AS total_institutions,
                (SELECT COALESCE(AVG(wizdam_score), 0) FROM researchers WHERE wizdam_score > 0) AS avg_wizdam_score,
                (SELECT COALESCE(SUM(cited_by_count), 0) FROM publications) AS total_citations'
        ) ?: [];

        $fieldDistribution = $this->db->fetchAll(
            "SELECT
                COALESCE(JSON_UNQUOTE(JSON_EXTRACT(field_of_study, '$[0]')), 'Lainnya') AS field,
                COUNT(*)                 AS researcher_count,
                AVG(wizdam_score)        AS avg_score
             FROM researchers
             WHERE wizdam_score > 0
             GROUP BY field
             ORDER BY researcher_count DESC
             LIMIT 8"
        );

        $provinceDistribution = $this->db->fetchAll(
            'SELECT i.province,
                    COUNT(r.id)       AS researcher_count,
                    AVG(r.wizdam_score) AS avg_impact
             FROM researchers r
             JOIN institutions i ON r.institution_id = i.id
             WHERE i.province IS NOT NULL
             GROUP BY i.province
             ORDER BY researcher_count DESC
             LIMIT 34'
        );

        $yearlyTrend = $this->db->fetchAll(
            'SELECT publication_year AS year,
                    COUNT(*)           AS total_publications,
                    COALESCE(AVG(wizdam_score), 0) AS avg_score,
                    COALESCE(SUM(cited_by_count), 0) AS total_citations
             FROM publications
             WHERE publication_year BETWEEN YEAR(NOW()) - 5 AND YEAR(NOW())
             GROUP BY publication_year
             ORDER BY publication_year ASC'
        );

        return [
            'total_researchers'    => (int) ($totals['total_researchers']    ?? 0),
            'total_publications'   => (int) ($totals['total_publications']   ?? 0),
            'total_institutions'   => (int) ($totals['total_institutions']   ?? 0),
            'avg_wizdam_score'     => round((float) ($totals['avg_wizdam_score'] ?? 0), 2),
            'total_citations'      => (int) ($totals['total_citations']      ?? 0),
            'field_distribution'   => $fieldDistribution,
            'province_distribution' => $provinceDistribution,
            'yearly_trend'         => $yearlyTrend,
        ];
    }
}
