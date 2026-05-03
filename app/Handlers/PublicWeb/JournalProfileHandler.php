<?php

declare(strict_types=1);

namespace Wizdam\Handlers\PublicWeb;

use Wizdam\Database\DBConnector;
use Wizdam\Database\Models\JournalModel;
use Wizdam\Database\Models\ImpactScoreModel;
use Wizdam\Services\Core\AuthManager;
use Wizdam\Services\SangiaApi\IndexingIntegrator;
use Wizdam\Services\SangiaApi\ImpactScoreClient;
use Wizdam\Http\Request;
use Wizdam\Http\Response;

class JournalProfileHandler
{
    private JournalModel     $journalModel;
    private ImpactScoreModel $scoreModel;

    public function __construct(
        private DBConnector       $db,
        private \Twig\Environment $twig,
        private AuthManager       $auth
    ) {
        $this->journalModel = new JournalModel();
        $this->scoreModel   = new ImpactScoreModel();
    }

    public function show(string $issn): void
    {
        $journal = $this->journalModel->findByIssn($issn);

        if (!$journal) {
            http_response_code(404);
            echo $this->twig->render('pages/error.twig', [
                'code'    => 404,
                'message' => "Jurnal dengan ISSN $issn tidak ditemukan.",
            ]);
            return;
        }

        $articles     = $this->journalModel->getRecentArticles((int) $journal['id']);
        $indexing     = $this->journalModel->getIndexingMetrics((int) $journal['id']);

        $scoreClient  = new ImpactScoreClient();
        $score        = $scoreClient->getLatest('journal', (int) $journal['id']);
        $scoreHistory = $this->scoreModel->getHistory('journal', (int) $journal['id']);

        // Cek indeksasi real-time jika diminta
        $liveIndexing = null;
        if (isset($_GET['check_indexing'])) {
            $integrator   = new IndexingIntegrator();
            $liveIndexing = $integrator->checkByIssn($journal['issn']);
        }

        echo $this->twig->render('pages/public/journal_profile.twig', [
            'journal'      => $journal,
            'articles'     => $articles,
            'indexing'     => $indexing,
            'liveIndexing' => $liveIndexing,
            'score'        => $score,
            'scoreHistory' => $scoreHistory,
            'pageTitle'    => ($journal['title'] ?? "Jurnal ISSN $issn") . ' – Wizdam Sicola',
        ]);
    }

    /** Versi Response object untuk show() - digunakan oleh router baru */
    public function showWithResponse(string $issn): Response
    {
        $journal = $this->journalModel->findByIssn($issn);

        if (!$journal) {
            return Response::error("Jurnal dengan ISSN $issn tidak ditemukan.", 404);
        }

        $articles     = $this->journalModel->getRecentArticles((int) $journal['id']);
        $indexing     = $this->journalModel->getIndexingMetrics((int) $journal['id']);

        $scoreClient  = new ImpactScoreClient();
        $score        = $scoreClient->getLatest('journal', (int) $journal['id']);
        $scoreHistory = $this->scoreModel->getHistory('journal', (int) $journal['id']);

        // Cek indeksasi real-time jika diminta
        $liveIndexing = null;
        if (isset($_GET['check_indexing'])) {
            $integrator   = new IndexingIntegrator();
            $liveIndexing = $integrator->checkByIssn($journal['issn']);
        }

        $html = $this->twig->render('pages/public/journal_profile.twig', [
            'journal'      => $journal,
            'articles'     => $articles,
            'indexing'     => $indexing,
            'liveIndexing' => $liveIndexing,
            'score'        => $score,
            'scoreHistory' => $scoreHistory,
            'pageTitle'    => ($journal['title'] ?? "Jurnal ISSN $issn") . ' – Wizdam Sicola',
        ]);

        return Response::html($html);
    }
}
