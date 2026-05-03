<?php

declare(strict_types=1);

namespace Wizdam\Handlers\PrivateWeb;

use Wizdam\Database\DBConnector;
use Wizdam\Database\Models\ResearcherModel;
use Wizdam\Database\Models\ImpactScoreModel;
use Wizdam\Services\Core\AuthManager;
use Wizdam\Services\SangiaApi\ImpactScoreClient;
use Wizdam\Http\Request;
use Wizdam\Http\Response;

class UserDashboardHandler
{
    public function __construct(
        private DBConnector       $db,
        private \Twig\Environment $twig,
        private AuthManager       $auth
    ) {}

    public function index(): void
    {
        $this->auth->requireLogin();

        $userId = $this->auth->getUserId();

        // Profil peneliti milik user ini
        $researcher = $this->db->fetchOne(
            'SELECT r.* FROM researchers r
             JOIN user_researcher_links url ON url.researcher_id = r.id
             WHERE url.user_id = ?
             LIMIT 1',
            [$userId]
        );

        $score        = null;
        $scoreHistory = [];
        $recentWork   = [];

        if ($researcher) {
            $scoreClient  = new ImpactScoreClient();
            $score        = $scoreClient->getLatest('researcher', (int) $researcher['id']);
            $scoreHistory = (new ImpactScoreModel())->getHistory('researcher', (int) $researcher['id']);

            $recentWork = $this->db->fetchAll(
                'SELECT a.title, a.year, a.citations, a.impact_score, j.title AS journal_name
                 FROM articles a
                 JOIN article_authors aa ON aa.article_id = a.id
                 LEFT JOIN journals j ON a.journal_id = j.id
                 WHERE aa.researcher_id = ?
                 ORDER BY a.year DESC LIMIT 5',
                [$researcher['id']]
            );
        }

        // Notifikasi user
        $notifications = $this->db->fetchAll(
            'SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 10',
            [$userId]
        );

        echo $this->twig->render('pages/private/dashboard.twig', [
            'researcher'    => $researcher,
            'score'         => $score,
            'scoreHistory'  => $scoreHistory,
            'recentWork'    => $recentWork,
            'notifications' => $notifications,
            'pageTitle'     => 'Dashboard Saya – Wizdam Sicola',
        ]);
    }

    /** Versi Response object untuk index() - digunakan oleh router baru */
    public function indexWithResponse(Request $request): Response
    {
        $userId = $this->auth->getUserId();

        // Profil peneliti milik user ini
        $researcher = $this->db->fetchOne(
            'SELECT r.* FROM researchers r
             JOIN user_researcher_links url ON url.researcher_id = r.id
             WHERE url.user_id = ?
             LIMIT 1',
            [$userId]
        );

        $score        = null;
        $scoreHistory = [];
        $recentWork   = [];

        if ($researcher) {
            $scoreClient  = new ImpactScoreClient();
            $score        = $scoreClient->getLatest('researcher', (int) $researcher['id']);
            $scoreHistory = (new ImpactScoreModel())->getHistory('researcher', (int) $researcher['id']);

            $recentWork = $this->db->fetchAll(
                'SELECT a.title, a.year, a.citations, a.impact_score, j.title AS journal_name
                 FROM articles a
                 JOIN article_authors aa ON aa.article_id = a.id
                 LEFT JOIN journals j ON a.journal_id = j.id
                 WHERE aa.researcher_id = ?
                 ORDER BY a.year DESC LIMIT 5',
                [$researcher['id']]
            );
        }

        // Notifikasi user
        $notifications = $this->db->fetchAll(
            'SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 10',
            [$userId]
        );

        $html = $this->twig->render('pages/private/dashboard.twig', [
            'researcher'    => $researcher,
            'score'         => $score,
            'scoreHistory'  => $scoreHistory,
            'recentWork'    => $recentWork,
            'notifications' => $notifications,
            'pageTitle'     => 'Dashboard Saya – Wizdam Sicola',
        ]);

        return Response::html($html);
    }
}
