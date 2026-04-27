<?php

declare(strict_types=1);

namespace Wizdam\Handlers\PublicWeb;

use Wizdam\Database\DBConnector;
use Wizdam\Database\Models\ResearcherModel;
use Wizdam\Database\Models\ImpactScoreModel;
use Wizdam\Services\Core\AuthManager;
use Wizdam\Services\Core\ProfileManager;
use Wizdam\Services\SangiaApi\ImpactScoreClient;
use Wizdam\Http\Request;
use Wizdam\Http\Response;

class ResearcherProfileHandler
{
    private ResearcherModel  $researcherModel;
    private ImpactScoreModel $scoreModel;

    public function __construct(
        private DBConnector       $db,
        private \Twig\Environment $twig,
        private AuthManager       $auth
    ) {
        $this->researcherModel = new ResearcherModel();
        $this->scoreModel      = new ImpactScoreModel();
    }

    /** Halaman utama: daftar peneliti dengan impact tertinggi. */
    public function indexWithResponse(Request $request): Response
    {
        $field       = $request->getQuery('field', 'all');
        $search      = trim($request->getQuery('q', ''));
        $researchers = $search
            ? $this->researcherModel->search($search)
            : $this->researcherModel->getTopByImpact(50, $field);

        $avgPillars = $this->scoreModel->getAveragePillars('researcher');

        $html = $this->twig->render('pages/public/researcher_list.twig', [
            'researchers' => $researchers,
            'avgPillars'  => $avgPillars,
            'field'       => $field,
            'search'      => $search,
            'pageTitle'   => 'Peneliti Terdampak – Wizdam AI-Sikola',
        ]);

        return Response::html($html);
    }

    /** Profil detail peneliti berdasarkan ORCID. */
    public function showWithResponse(string $orcid): Response
    {
        // Cari di DB dulu — field name sesuai schema full: orcid_id, full_name
        $researcher = $this->db->fetchOne(
            'SELECT r.*, i.name AS institution_name, i.province, i.city
             FROM researchers r
             LEFT JOIN institutions i ON r.institution_id = i.id
             WHERE r.orcid_id = ?',
            [$orcid]
        );

        // Jika belum ada di DB, sync dari ORCID via ProfileManager
        if (!$researcher) {
            try {
                $profileManager = new ProfileManager();
                $id             = $profileManager->syncFromOrcid($orcid);
                $researcher     = $this->db->fetchOne(
                    'SELECT r.*, i.name AS institution_name, i.province, i.city
                     FROM researchers r
                     LEFT JOIN institutions i ON r.institution_id = i.id
                     WHERE r.id = ?',
                    [(int) $id]
                );
            } catch (\Throwable) {
                $html = $this->twig->render('pages/error.twig', [
                    'code'    => 404,
                    'message' => "Peneliti dengan ORCID {$orcid} tidak ditemukan.",
                ]);
                return Response::html($html, 404);
            }
        }

        if (!$researcher) {
            return Response::html($this->twig->render('pages/error.twig', [
                'code' => 404, 'message' => 'Peneliti tidak ditemukan.',
            ]), 404);
        }

        $researcherId = (int) $researcher['id'];

        // Impact score dari DB
        $scoreClient  = new ImpactScoreClient();
        $score        = $scoreClient->getLatest('researcher', $researcherId);
        $scoreHistory = $this->scoreModel->getHistory('researcher', $researcherId);

        // SDG tags
        $sdgTags = [];
        if ($score && !empty($score['sdg_tags'])) {
            $sdgTags = is_string($score['sdg_tags'])
                ? (json_decode($score['sdg_tags'], true) ?? [])
                : $score['sdg_tags'];
        }

        // Artikel terbaru — tabel publications, field sesuai schema full
        $recentArticles = $this->db->fetchAll(
            'SELECT p.id, p.doi, p.title, p.publication_year AS year,
                    p.cited_by_count AS citations, p.wizdam_score AS impact_score,
                    p.journal_title AS journal_name
             FROM publications p
             JOIN publication_authors pa ON pa.publication_id = p.id
             WHERE pa.researcher_id = ?
             ORDER BY p.publication_year DESC, p.cited_by_count DESC
             LIMIT 10',
            [$researcherId]
        );

        $html = $this->twig->render('pages/public/researcher_profile.twig', [
            'researcher'     => $researcher,
            'score'          => $score,
            'scoreHistory'   => $scoreHistory,
            'sdgTags'        => $sdgTags,
            'recentArticles' => $recentArticles,
            // Twig template menggunakan researcher.full_name dan researcher.orcid_id
            'pageTitle'      => ($researcher['full_name'] ?? $orcid) . ' – Wizdam AI-Sikola',
        ]);

        return Response::html($html);
    }
}
