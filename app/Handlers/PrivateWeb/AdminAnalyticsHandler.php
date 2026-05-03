<?php

declare(strict_types=1);

namespace Wizdam\Handlers\PrivateWeb;

use Wizdam\Database\DBConnector;
use Wizdam\Database\Models\ImpactScoreModel;
use Wizdam\Services\Core\AuthManager;
use Wizdam\Http\Request;
use Wizdam\Http\Response;

class AdminAnalyticsHandler
{
    public function __construct(
        private DBConnector       $db,
        private \Twig\Environment $twig,
        private AuthManager       $auth
    ) {}

    public function index(): void
    {
        $this->auth->requireAdmin();

        // Ringkasan statistik platform
        $stats = [
            'total_researchers'   => $this->db->fetchOne('SELECT COUNT(*) AS n FROM researchers')['n'] ?? 0,
            'total_institutions'  => $this->db->fetchOne('SELECT COUNT(*) AS n FROM institutions')['n'] ?? 0,
            'total_journals'      => $this->db->fetchOne('SELECT COUNT(*) AS n FROM journals')['n'] ?? 0,
            'total_articles'      => $this->db->fetchOne('SELECT COUNT(*) AS n FROM articles')['n'] ?? 0,
            'total_users'         => $this->db->fetchOne('SELECT COUNT(*) AS n FROM users')['n'] ?? 0,
        ];

        // Tren harvesting per hari (7 hari terakhir)
        $harvestTrend = $this->db->fetchAll(
            'SELECT DATE(created_at) AS date, COUNT(*) AS inserted
             FROM articles
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             GROUP BY DATE(created_at)
             ORDER BY date ASC'
        );

        // Distribusi skor dampak
        $scoreDistribution = $this->db->fetchAll(
            'SELECT
               FLOOR(composite_score / 10) * 10 AS bucket,
               COUNT(*) AS count
             FROM impact_scores
             WHERE entity_type = "researcher"
               AND calculated_at = (
                   SELECT MAX(calculated_at) FROM impact_scores i2
                   WHERE i2.entity_type = impact_scores.entity_type
                     AND i2.entity_id   = impact_scores.entity_id
               )
             GROUP BY bucket
             ORDER BY bucket ASC'
        );

        // Top institusi berdasarkan rata-rata skor
        $topInstitutions = $this->db->fetchAll(
            'SELECT i.name, AVG(r.impact_score) AS avg_score, COUNT(r.id) AS researcher_count
             FROM institutions i
             JOIN researchers r ON r.affiliation_id = i.id
             GROUP BY i.id
             ORDER BY avg_score DESC
             LIMIT 10'
        );

        // Log aktivitas terbaru
        $recentLogs = $this->db->fetchAll(
            'SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT 20'
        );

        echo $this->twig->render('pages/private/admin.twig', [
            'stats'             => $stats,
            'harvestTrend'      => $harvestTrend,
            'scoreDistribution' => $scoreDistribution,
            'topInstitutions'   => $topInstitutions,
            'recentLogs'        => $recentLogs,
            'pageTitle'         => 'Admin Analytics – Wizdam Sicola',
        ]);
    }

    /** Versi Response object untuk index() - digunakan oleh router baru */
    public function indexWithResponse(Request $request): Response
    {
        // Ringkasan statistik platform
        $stats = [
            'total_researchers'   => $this->db->fetchOne('SELECT COUNT(*) AS n FROM researchers')['n'] ?? 0,
            'total_institutions'  => $this->db->fetchOne('SELECT COUNT(*) AS n FROM institutions')['n'] ?? 0,
            'total_journals'      => $this->db->fetchOne('SELECT COUNT(*) AS n FROM journals')['n'] ?? 0,
            'total_articles'      => $this->db->fetchOne('SELECT COUNT(*) AS n FROM articles')['n'] ?? 0,
            'total_users'         => $this->db->fetchOne('SELECT COUNT(*) AS n FROM users')['n'] ?? 0,
        ];

        // Tren harvesting per hari (7 hari terakhir)
        $harvestTrend = $this->db->fetchAll(
            'SELECT DATE(created_at) AS date, COUNT(*) AS inserted
             FROM articles
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             GROUP BY DATE(created_at)
             ORDER BY date ASC'
        );

        // Distribusi skor dampak
        $scoreDistribution = $this->db->fetchAll(
            'SELECT
               FLOOR(composite_score / 10) * 10 AS bucket,
               COUNT(*) AS count
             FROM impact_scores
             WHERE entity_type = "researcher"
               AND calculated_at = (
                   SELECT MAX(calculated_at) FROM impact_scores i2
                   WHERE i2.entity_type = impact_scores.entity_type
                     AND i2.entity_id   = impact_scores.entity_id
               )
             GROUP BY bucket
             ORDER BY bucket ASC'
        );

        // Top institusi berdasarkan rata-rata skor
        $topInstitutions = $this->db->fetchAll(
            'SELECT i.name, AVG(r.impact_score) AS avg_score, COUNT(r.id) AS researcher_count
             FROM institutions i
             JOIN researchers r ON r.affiliation_id = i.id
             GROUP BY i.id
             ORDER BY avg_score DESC
             LIMIT 10'
        );

        // Log aktivitas terbaru
        $recentLogs = $this->db->fetchAll(
            'SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT 20'
        );

        $html = $this->twig->render('pages/private/admin.twig', [
            'stats'             => $stats,
            'harvestTrend'      => $harvestTrend,
            'scoreDistribution' => $scoreDistribution,
            'topInstitutions'   => $topInstitutions,
            'recentLogs'        => $recentLogs,
            'pageTitle'         => 'Admin Analytics – Wizdam Sicola',
        ]);

        return Response::html($html);
    }
}
