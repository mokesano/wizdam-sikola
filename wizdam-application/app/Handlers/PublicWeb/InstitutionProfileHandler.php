<?php

declare(strict_types=1);

namespace Wizdam\Handlers\PublicWeb;

use Wizdam\Database\DBConnector;
use Wizdam\Database\Models\InstitutionModel;
use Wizdam\Database\Models\ImpactScoreModel;
use Wizdam\Services\Core\AuthManager;
use Wizdam\Services\SangiaApi\ImpactScoreClient;

class InstitutionProfileHandler
{
    private InstitutionModel $institutionModel;
    private ImpactScoreModel $scoreModel;

    public function __construct(
        private DBConnector       $db,
        private \Twig\Environment $twig,
        private AuthManager       $auth
    ) {
        $this->institutionModel = new InstitutionModel();
        $this->scoreModel       = new ImpactScoreModel();
    }

    public function show(int $id): void
    {
        $institution = $this->institutionModel->findWithResearcherCount($id);

        if (!$institution) {
            http_response_code(404);
            echo $this->twig->render('pages/error.twig', [
                'code'    => 404,
                'message' => "Institusi dengan ID $id tidak ditemukan.",
            ]);
            return;
        }

        $researchers  = $this->institutionModel->getResearchers($id);

        $scoreClient  = new ImpactScoreClient();
        $score        = $scoreClient->getLatest('institution', $id);
        $scoreHistory = $this->scoreModel->getHistory('institution', $id);

        // Tren publikasi per tahun
        $pubTrend = $this->db->fetchAll(
            'SELECT a.year, COUNT(a.id) AS total_articles, SUM(a.citations) AS total_citations
             FROM articles a
             JOIN article_authors aa ON aa.article_id = a.id
             JOIN researchers r ON aa.researcher_id = r.id
             WHERE r.affiliation_id = ?
             GROUP BY a.year
             ORDER BY a.year ASC',
            [$id]
        );

        echo $this->twig->render('pages/public/institution_profile.twig', [
            'institution'  => $institution,
            'researchers'  => $researchers,
            'score'        => $score,
            'scoreHistory' => $scoreHistory,
            'pubTrend'     => $pubTrend,
            'pageTitle'    => ($institution['name'] ?? "Institusi #$id") . ' – Wizdam AI-Sikola',
        ]);
    }
}
