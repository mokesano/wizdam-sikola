<?php

declare(strict_types=1);

namespace Wizdam\Handlers\Api;

use Wizdam\Database\DBConnector;
use Wizdam\Http\Middleware\CorsMiddleware;
use Wizdam\Http\Request;
use Wizdam\Http\Response;

/**
 * GET /api/v1/institutions              → daftar institusi dengan distribusi provinsi
 * GET /api/v1/institutions/{id}         → profil detail institusi
 * GET /api/v1/institutions/map          → data koordinat untuk peta
 */
class InstitutionApiHandler
{
    private DBConnector    $db;
    private CorsMiddleware $cors;

    public function __construct()
    {
        $this->db   = DBConnector::getInstance();
        $this->cors = new CorsMiddleware();
    }

    /** GET /api/v1/institutions */
    public function index(Request $request): Response
    {
        $this->cors->sendCorsHeaders();

        $province = trim($request->getQuery('province', ''));
        $search   = trim($request->getQuery('q', ''));
        $page     = max(1, (int) $request->getQuery('page', 1));
        $perPage  = min(100, max(5, (int) $request->getQuery('per_page', 30)));
        $offset   = ($page - 1) * $perPage;

        $conditions = [];
        $params     = [];

        if ($province !== '') {
            $conditions[] = 'province = ?';
            $params[]     = $province;
        }

        if ($search !== '') {
            $conditions[] = '(name LIKE ? OR short_name LIKE ?)';
            $params[]     = '%' . $search . '%';
            $params[]     = '%' . $search . '%';
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $total = (int) ($this->db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM institutions $where",
            $params
        )['cnt'] ?? 0);

        $params[] = $perPage;
        $params[] = $offset;

        $rows = $this->db->fetchAll(
            "SELECT id, name, short_name, type, province, city,
                    latitude, longitude, website, logo_url,
                    total_researchers, total_publications, wizdam_score
             FROM institutions
             $where
             ORDER BY wizdam_score DESC, total_researchers DESC
             LIMIT ? OFFSET ?",
            $params
        );

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

    /** GET /api/v1/institutions/map — data koordinat + skor untuk peta */
    public function map(Request $request): Response
    {
        $this->cors->sendCorsHeaders();

        $rows = $this->db->fetchAll(
            'SELECT id, name, short_name, province, city,
                    latitude, longitude, type,
                    total_researchers, total_publications, wizdam_score
             FROM institutions
             WHERE latitude IS NOT NULL AND longitude IS NOT NULL
             ORDER BY wizdam_score DESC'
        );

        $byProvince = $this->db->fetchAll(
            'SELECT province,
                    COUNT(*)          AS institution_count,
                    SUM(total_researchers) AS researcher_count,
                    AVG(wizdam_score)  AS avg_impact
             FROM institutions
             WHERE province IS NOT NULL AND province != ""
             GROUP BY province
             ORDER BY researcher_count DESC'
        );

        return Response::json([
            'success' => true,
            'data'    => [
                'institutions'    => array_map($this->formatRow(...), $rows),
                'by_province'     => array_map(fn($p) => [
                    'province'          => $p['province'],
                    'institution_count' => (int) $p['institution_count'],
                    'researcher_count'  => (int) ($p['researcher_count'] ?? 0),
                    'avg_impact'        => round((float) ($p['avg_impact'] ?? 0), 2),
                ], $byProvince),
            ],
        ]);
    }

    /** GET /api/v1/institutions/{id} */
    public function show(Request $request, int $id): Response
    {
        $this->cors->sendCorsHeaders();

        $institution = $this->db->fetchOne(
            'SELECT * FROM institutions WHERE id = ?',
            [$id]
        );

        if (!$institution) {
            return Response::json(['success' => false, 'message' => 'Institusi tidak ditemukan.'], 404);
        }

        $topResearchers = $this->db->fetchAll(
            'SELECT id, full_name, orcid_id, wizdam_score, h_index, total_publications
             FROM researchers
             WHERE institution_id = ?
             ORDER BY wizdam_score DESC
             LIMIT 10',
            [$id]
        );

        return Response::json([
            'success' => true,
            'data'    => array_merge($this->formatRow($institution), [
                'address'          => $institution['address'],
                'orcid_id'         => $institution['orcid_id'] ?? null,
                'ror_id'           => $institution['ror_id']   ?? null,
                'top_researchers'  => $topResearchers,
            ]),
        ]);
    }

    private function formatRow(array $i): array
    {
        return [
            'id'                 => (int) $i['id'],
            'name'               => $i['name'],
            'short_name'         => $i['short_name'] ?? null,
            'type'               => $i['type']       ?? null,
            'province'           => $i['province']   ?? null,
            'city'               => $i['city']       ?? null,
            'latitude'           => $i['latitude']   !== null ? (float) $i['latitude']  : null,
            'longitude'          => $i['longitude']  !== null ? (float) $i['longitude'] : null,
            'website'            => $i['website']    ?? null,
            'logo_url'           => $i['logo_url']   ?? null,
            'total_researchers'  => (int) ($i['total_researchers']  ?? 0),
            'total_publications' => (int) ($i['total_publications'] ?? 0),
            'wizdam_score'       => (float) ($i['wizdam_score']     ?? 0),
        ];
    }
}
