<?php

declare(strict_types=1);

namespace Wizdam\Handlers\Api;

use Wizdam\Database\Models\ArticleModel;
use Wizdam\Database\Models\ImpactScoreModel;
use Wizdam\Http\Middleware\CorsMiddleware;
use Wizdam\Http\Request;
use Wizdam\Http\Response;

/**
 * GET /api/v1/articles              → daftar artikel (filter + pagination)
 * GET /api/v1/articles/{id}         → detail artikel + impact pillars
 * GET /api/v1/articles/top          → top 10 untuk widget dashboard
 * GET /api/v1/articles/trends       → data tren per tahun
 */
class ArticleApiHandler
{
    private ArticleModel   $articleModel;
    private ImpactScoreModel $scoreModel;
    private CorsMiddleware $cors;

    public function __construct()
    {
        $this->articleModel = new ArticleModel();
        $this->scoreModel   = new ImpactScoreModel();
        $this->cors         = new CorsMiddleware();
    }

    /** GET /api/v1/articles */
    public function index(Request $request): Response
    {
        $this->cors->sendCorsHeaders();

        $search  = trim($request->getQuery('q', ''));
        $year    = $request->getQuery('year') !== null ? (int) $request->getQuery('year') : null;
        $type    = $request->getQuery('type', 'all');
        $page    = max(1, (int) $request->getQuery('page', 1));
        $perPage = min(50, max(5, (int) $request->getQuery('per_page', 20)));
        $offset  = ($page - 1) * $perPage;

        $rows  = $this->articleModel->getTopByScore($perPage, $offset, $search, $year, $type);
        $total = $this->articleModel->countFiltered($search, $year, $type);

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

    /** GET /api/v1/articles/top?limit=10 */
    public function top(Request $request): Response
    {
        $this->cors->sendCorsHeaders();

        $limit = min(20, max(3, (int) $request->getQuery('limit', 10)));
        $rows  = $this->articleModel->getTopByScore($limit);

        return Response::json([
            'success' => true,
            'data'    => array_map($this->formatRow(...), $rows),
        ]);
    }

    /** GET /api/v1/articles/trends?from=2019&to=2024 */
    public function trends(Request $request): Response
    {
        $this->cors->sendCorsHeaders();

        $from = (int) $request->getQuery('from', date('Y') - 5);
        $to   = (int) $request->getQuery('to', date('Y'));

        $trends = $this->articleModel->getTrendsByYear($from, $to);

        return Response::json([
            'success' => true,
            'data'    => array_map(fn($r) => [
                'year'               => (int) $r['year'],
                'total_publications' => (int) $r['total_publications'],
                'avg_wizdam_score'   => round((float) ($r['avg_wizdam_score'] ?? 0), 2),
                'total_citations'    => (int) ($r['total_citations'] ?? 0),
            ], $trends),
        ]);
    }

    /** GET /api/v1/articles/{id} */
    public function show(Request $request, int $id): Response
    {
        $this->cors->sendCorsHeaders();

        $article = $this->articleModel->findById($id);

        if (!$article) {
            return Response::json(['success' => false, 'message' => 'Artikel tidak ditemukan.'], 404);
        }

        $score   = $this->scoreModel->findLatest('article', $id);
        $history = $this->scoreModel->getHistory('article', $id);

        $sdgTags = [];
        if ($score && !empty($score['sdg_tags'])) {
            $sdgTags = json_decode((string) $score['sdg_tags'], true) ?? [];
        }

        return Response::json([
            'success' => true,
            'data'    => array_merge($this->formatRow($article), [
                'abstract'       => $article['abstract'],
                'publisher'      => $article['publisher'],
                'volume'         => $article['volume'],
                'issue'          => $article['issue'],
                'pages'          => $article['pages'],
                'license'        => $article['license'],
                'pdf_url'        => $article['pdf_url'],
                'fulltext_url'   => $article['fulltext_url'],
                'references_count' => (int) ($article['references_count'] ?? 0),
                'impact_pillars' => $score ? [
                    'academic'  => round((float) ($score['pillar_academic']  ?? 0), 2),
                    'social'    => round((float) ($score['pillar_social']    ?? 0), 2),
                    'economic'  => round((float) ($score['pillar_economic']  ?? 0), 2),
                    'sdg'       => round((float) ($score['pillar_sdg']       ?? 0), 2),
                    'composite' => round((float) ($score['composite_score']  ?? 0), 2),
                ] : null,
                'sdg_tags'      => $sdgTags,
                'score_history' => array_map(fn($h) => [
                    'date'      => $h['calculated_at'],
                    'composite' => round((float) $h['composite_score'], 2),
                ], $history),
            ]),
        ]);
    }

    private function formatRow(array $r): array
    {
        $sdgsGoals = is_string($r['sdgs_goals'] ?? null)
            ? (json_decode($r['sdgs_goals'], true) ?? [])
            : ($r['sdgs_goals'] ?? []);

        return [
            'id'               => (int) $r['id'],
            'doi'              => $r['doi'] ?? null,
            'title'            => $r['title'],
            'authors_list'     => $r['authors_list'] ?? '',
            'journal_title'    => $r['journal_title'] ?? null,
            'publication_year' => (int) ($r['publication_year'] ?? 0),
            'cited_by_count'   => (int) ($r['cited_by_count']  ?? 0),
            'wizdam_score'     => (float) ($r['wizdam_score']   ?? 0),
            'sdgs_goals'       => $sdgsGoals,
            'document_type'    => $r['document_type']  ?? 'article',
            'access_type'      => $r['access_type']    ?? 'subscription',
        ];
    }
}
