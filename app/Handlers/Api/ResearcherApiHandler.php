<?php

declare(strict_types=1);

namespace Wizdam\Handlers\Api;

use Wizdam\Database\DBConnector;
use Wizdam\Database\Models\ArticleModel;
use Wizdam\Database\Models\ImpactScoreModel;
use Wizdam\Http\Middleware\CorsMiddleware;
use Wizdam\Http\Request;
use Wizdam\Http\Response;

/**
 * GET /api/v1/researchers           → daftar peneliti (filter + pagination)
 * GET /api/v1/researchers/{orcid}   → profil detail + impact pillars + publikasi
 * GET /api/v1/researchers/top       → top 10 untuk widget dashboard
 */
class ResearcherApiHandler
{
    private DBConnector    $db;
    private ArticleModel   $articleModel;
    private ImpactScoreModel $scoreModel;
    private CorsMiddleware $cors;

    public function __construct()
    {
        $this->db           = DBConnector::getInstance();
        $this->articleModel = new ArticleModel();
        $this->scoreModel   = new ImpactScoreModel();
        $this->cors         = new CorsMiddleware();
    }

    /** GET /api/v1/researchers */
    public function index(Request $request): Response
    {
        $this->cors->sendCorsHeaders();

        $field    = $request->getQuery('field', 'all');
        $province = $request->getQuery('province', '');
        $search   = trim($request->getQuery('q', ''));
        $page     = max(1, (int) $request->getQuery('page', 1));
        $perPage  = min(50, max(5, (int) $request->getQuery('per_page', 20)));
        $offset   = ($page - 1) * $perPage;

        [$rows, $total] = $this->fetchList($field, $province, $search, $perPage, $offset);

        return Response::json([
            'success' => true,
            'data'    => array_map($this->formatRow(...), $rows),
            'meta'    => [
                'page'     => $page,
                'per_page' => $perPage,
                'total'    => $total,
                'pages'    => (int) ceil($total / $perPage),
            ],
        ]);
    }

    /** GET /api/v1/researchers/top?limit=10 */
    public function top(Request $request): Response
    {
        $this->cors->sendCorsHeaders();

        $limit = min(20, max(3, (int) $request->getQuery('limit', 10)));

        $rows = $this->db->fetchAll(
            'SELECT r.id, r.full_name, r.orcid_id, r.total_publications,
                    r.total_citations, r.h_index, r.wizdam_score, r.sdgs_primary_goals,
                    i.name AS institution_name, i.province
             FROM researchers r
             LEFT JOIN institutions i ON r.institution_id = i.id
             WHERE r.wizdam_score > 0
             ORDER BY r.wizdam_score DESC
             LIMIT ?',
            [$limit]
        );

        return Response::json([
            'success' => true,
            'data'    => array_map($this->formatRow(...), $rows),
        ]);
    }

    /** GET /api/v1/researchers/{orcid} */
    public function show(Request $request, string $orcid): Response
    {
        $this->cors->sendCorsHeaders();

        $researcher = $this->db->fetchOne(
            'SELECT r.*, i.name AS institution_name, i.province, i.city, i.website
             FROM researchers r
             LEFT JOIN institutions i ON r.institution_id = i.id
             WHERE r.orcid_id = ?',
            [$orcid]
        );

        if (!$researcher) {
            return Response::json(['success' => false, 'message' => 'Peneliti tidak ditemukan.'], 404);
        }

        $score        = $this->scoreModel->findLatest('researcher', (int) $researcher['id']);
        $scoreHistory = $this->scoreModel->getHistory('researcher', (int) $researcher['id']);
        $publications = $this->articleModel->getByResearcher((int) $researcher['id'], 15);
        $avgPillars   = $this->scoreModel->getAveragePillars('researcher');

        $sdgTags = [];
        if ($score && !empty($score['sdg_tags'])) {
            $sdgTags = json_decode((string) $score['sdg_tags'], true) ?? [];
        }

        $fieldOfStudy = is_string($researcher['field_of_study'])
            ? (json_decode($researcher['field_of_study'], true) ?? [])
            : ($researcher['field_of_study'] ?? []);

        $sdgsPrimary = is_string($researcher['sdgs_primary_goals'])
            ? (json_decode($researcher['sdgs_primary_goals'], true) ?? [])
            : ($researcher['sdgs_primary_goals'] ?? []);

        return Response::json([
            'success' => true,
            'data'    => [
                'id'               => (int) $researcher['id'],
                'orcid_id'         => $researcher['orcid_id'],
                'full_name'        => $researcher['full_name'],
                'bio'              => $researcher['bio'],
                'position_title'   => $researcher['position_title'],
                'department'       => $researcher['department'],
                'field_of_study'   => $fieldOfStudy,
                'expertise_tags'   => json_decode((string) ($researcher['expertise_tags'] ?? '[]'), true) ?? [],
                'institution_name' => $researcher['institution_name'],
                'province'         => $researcher['province'],
                'city'             => $researcher['city'],
                'website'          => $researcher['website'],
                'total_publications' => (int) ($researcher['total_publications'] ?? 0),
                'total_citations'  => (int) ($researcher['total_citations'] ?? 0),
                'h_index'          => (int) ($researcher['h_index'] ?? 0),
                'i10_index'        => (int) ($researcher['i10_index'] ?? 0),
                'wizdam_score'     => (float) ($researcher['wizdam_score'] ?? 0),
                'wizdam_percentile' => (float) ($researcher['wizdam_percentile'] ?? 0),
                'sdgs_primary_goals' => $sdgsPrimary,
                'sdg_tags'         => $sdgTags,
                'impact_pillars'   => $score ? [
                    'academic'   => round((float) ($score['pillar_academic']  ?? 0), 2),
                    'social'     => round((float) ($score['pillar_social']    ?? 0), 2),
                    'economic'   => round((float) ($score['pillar_economic']  ?? 0), 2),
                    'sdg'        => round((float) ($score['pillar_sdg']       ?? 0), 2),
                    'composite'  => round((float) ($score['composite_score']  ?? 0), 2),
                    'calculated_at' => $score['calculated_at'] ?? null,
                ] : null,
                'avg_pillars'      => $avgPillars,
                'score_history'    => array_map(fn($h) => [
                    'date'      => $h['calculated_at'],
                    'composite' => round((float) $h['composite_score'], 2),
                    'academic'  => round((float) $h['pillar_academic'], 2),
                    'social'    => round((float) $h['pillar_social'], 2),
                    'economic'  => round((float) $h['pillar_economic'], 2),
                    'sdg'       => round((float) $h['pillar_sdg'], 2),
                ], $scoreHistory),
                'recent_publications' => array_map(fn($p) => [
                    'id'               => (int) $p['id'],
                    'doi'              => $p['doi'],
                    'title'            => $p['title'],
                    'journal_title'    => $p['journal_title'],
                    'publication_year' => (int) ($p['publication_year'] ?? 0),
                    'cited_by_count'   => (int) ($p['cited_by_count']   ?? 0),
                    'wizdam_score'     => (float) ($p['wizdam_score']   ?? 0),
                    'sdgs_goals'       => json_decode((string) ($p['sdgs_goals'] ?? '[]'), true) ?? [],
                ], $publications),
            ],
        ]);
    }

    private function fetchList(
        string $field,
        string $province,
        string $search,
        int    $limit,
        int    $offset
    ): array {
        $conditions = [];
        $params     = [];

        if ($field !== 'all' && $field !== '') {
            $conditions[] = "JSON_CONTAINS(r.field_of_study, JSON_QUOTE(?), '$')";
            $params[]     = $field;
        }

        if ($province !== '') {
            $conditions[] = 'i.province = ?';
            $params[]     = $province;
        }

        if ($search !== '') {
            $conditions[] = 'r.full_name LIKE ?';
            $params[]     = '%' . $search . '%';
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $countParams = $params;
        $total = (int) ($this->db->fetchOne(
            "SELECT COUNT(*) AS cnt
             FROM researchers r
             LEFT JOIN institutions i ON r.institution_id = i.id
             $where",
            $countParams
        )['cnt'] ?? 0);

        $params[] = $limit;
        $params[] = $offset;

        $rows = $this->db->fetchAll(
            "SELECT r.id, r.full_name, r.orcid_id, r.total_publications,
                    r.total_citations, r.h_index, r.i10_index,
                    r.wizdam_score, r.wizdam_percentile, r.sdgs_primary_goals,
                    r.field_of_study, r.expertise_tags, r.profile_image_url,
                    i.name AS institution_name, i.province, i.city
             FROM researchers r
             LEFT JOIN institutions i ON r.institution_id = i.id
             $where
             ORDER BY r.wizdam_score DESC
             LIMIT ? OFFSET ?",
            $params
        );

        return [$rows, $total];
    }

    private function formatRow(array $r): array
    {
        return [
            'id'               => (int) $r['id'],
            'orcid_id'         => $r['orcid_id'] ?? null,
            'full_name'        => $r['full_name'],
            'institution_name' => $r['institution_name'] ?? null,
            'province'         => $r['province'] ?? null,
            'city'             => $r['city'] ?? null,
            'field_of_study'   => is_string($r['field_of_study'] ?? null)
                ? (json_decode($r['field_of_study'], true) ?? [])
                : ($r['field_of_study'] ?? []),
            'expertise_tags'   => is_string($r['expertise_tags'] ?? null)
                ? (json_decode($r['expertise_tags'], true) ?? [])
                : ($r['expertise_tags'] ?? []),
            'sdgs_primary_goals' => is_string($r['sdgs_primary_goals'] ?? null)
                ? (json_decode($r['sdgs_primary_goals'], true) ?? [])
                : ($r['sdgs_primary_goals'] ?? []),
            'total_publications' => (int) ($r['total_publications'] ?? 0),
            'total_citations'  => (int) ($r['total_citations']   ?? 0),
            'h_index'          => (int) ($r['h_index']           ?? 0),
            'i10_index'        => (int) ($r['i10_index']         ?? 0),
            'wizdam_score'     => (float) ($r['wizdam_score']    ?? 0),
            'wizdam_percentile' => (float) ($r['wizdam_percentile'] ?? 0),
            'profile_image_url' => $r['profile_image_url'] ?? null,
        ];
    }
}
