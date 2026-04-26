<?php

declare(strict_types=1);

namespace Wizdam\Handlers\Api;

use Wizdam\Database\Models\ImpactScoreModel;
use Wizdam\Services\SangiaApi\ImpactScoreClient;
use Wizdam\Services\SangiaApi\SdgIntegrator;
use Wizdam\Http\Middleware\CorsMiddleware;
use Wizdam\Http\Request;
use Wizdam\Http\Response;

/**
 * GET  /api/v1/impact-scores/{type}/{id}          → skor terkini dari DB
 * POST /api/v1/impact-scores/{type}/{id}/calculate → trigger kalkulasi ulang via Sangia API
 * GET  /api/v1/impact-scores/{type}/{id}/history   → riwayat skor (12 bulan)
 * POST /api/v1/sdg/classify                        → klasifikasi SDG untuk teks artikel
 *
 * {type}: researcher | article | institution | journal
 */
class ImpactScoreApiHandler
{
    private ImpactScoreModel $scoreModel;
    private CorsMiddleware   $cors;

    public function __construct()
    {
        $this->scoreModel = new ImpactScoreModel();
        $this->cors       = new CorsMiddleware();
    }

    /** GET /api/v1/impact-scores/{type}/{id} */
    public function show(Request $request, string $type, int $id): Response
    {
        $this->cors->sendCorsHeaders();

        if (!$this->isValidType($type)) {
            return Response::json(['success' => false, 'message' => 'Tipe entitas tidak valid.'], 400);
        }

        $score = $this->scoreModel->findLatest($type, $id);

        if (!$score) {
            return Response::json([
                'success' => false,
                'message' => 'Skor belum tersedia. Gunakan endpoint /calculate untuk meminta kalkulasi.',
            ], 404);
        }

        return Response::json([
            'success' => true,
            'data'    => $this->formatScore($score),
        ]);
    }

    /** GET /api/v1/impact-scores/{type}/{id}/history */
    public function history(Request $request, string $type, int $id): Response
    {
        $this->cors->sendCorsHeaders();

        if (!$this->isValidType($type)) {
            return Response::json(['success' => false, 'message' => 'Tipe entitas tidak valid.'], 400);
        }

        $months  = min(24, max(1, (int) $request->getQuery('months', 12)));
        $history = $this->scoreModel->getHistory($type, $id, $months);

        return Response::json([
            'success' => true,
            'data'    => array_map(fn($h) => [
                'date'      => $h['calculated_at'],
                'composite' => round((float) $h['composite_score'], 2),
                'academic'  => round((float) $h['pillar_academic'], 2),
                'social'    => round((float) $h['pillar_social'],   2),
                'economic'  => round((float) $h['pillar_economic'], 2),
                'sdg'       => round((float) $h['pillar_sdg'],      2),
            ], $history),
        ]);
    }

    /** POST /api/v1/impact-scores/{type}/{id}/calculate */
    public function calculate(Request $request, string $type, int $id): Response
    {
        $this->cors->sendCorsHeaders();

        if (!$this->isValidType($type)) {
            return Response::json(['success' => false, 'message' => 'Tipe entitas tidak valid.'], 400);
        }

        try {
            $client = new ImpactScoreClient();
            $result = $client->calculate($type, $id);

            if (isset($result['error'])) {
                return Response::json(['success' => false, 'message' => $result['error']], 503);
            }

            $score = $this->scoreModel->findLatest($type, $id);

            return Response::json([
                'success' => true,
                'message' => 'Kalkulasi berhasil.',
                'data'    => $score ? $this->formatScore($score) : $result,
            ]);
        } catch (\Throwable $e) {
            error_log("[ImpactScoreApiHandler] calculate error: " . $e->getMessage());
            return Response::json(['success' => false, 'message' => 'Gagal menghubungi Sangia API.'], 503);
        }
    }

    /** POST /api/v1/sdg/classify — body: {title, abstract} */
    public function classifySdg(Request $request): Response
    {
        $this->cors->sendCorsHeaders();

        $body = $this->parseJsonBody();

        $title    = trim($body['title']    ?? '');
        $abstract = trim($body['abstract'] ?? '');

        if ($title === '') {
            return Response::json(['success' => false, 'message' => 'Field "title" wajib diisi.'], 422);
        }

        try {
            $integrator = new SdgIntegrator();
            $sdgs       = $integrator->classify($title, $abstract);

            return Response::json([
                'success' => true,
                'data'    => $sdgs,
            ]);
        } catch (\Throwable $e) {
            error_log("[ImpactScoreApiHandler] classifySdg error: " . $e->getMessage());
            return Response::json(['success' => false, 'message' => 'Gagal menghubungi Sangia API.'], 503);
        }
    }

    /** GET /api/v1/impact-scores/averages/{type} */
    public function averages(Request $request, string $type): Response
    {
        $this->cors->sendCorsHeaders();

        if (!$this->isValidType($type)) {
            return Response::json(['success' => false, 'message' => 'Tipe entitas tidak valid.'], 400);
        }

        $avg = $this->scoreModel->getAveragePillars($type);

        return Response::json([
            'success' => true,
            'data'    => [
                'avg_academic'  => round((float) ($avg['avg_academic']  ?? 0), 2),
                'avg_social'    => round((float) ($avg['avg_social']    ?? 0), 2),
                'avg_economic'  => round((float) ($avg['avg_economic']  ?? 0), 2),
                'avg_sdg'       => round((float) ($avg['avg_sdg']       ?? 0), 2),
                'avg_composite' => round((float) ($avg['avg_composite'] ?? 0), 2),
            ],
        ]);
    }

    private function isValidType(string $type): bool
    {
        return in_array($type, ['researcher', 'article', 'institution', 'journal'], true);
    }

    private function formatScore(array $score): array
    {
        return [
            'entity_type'    => $score['entity_type'],
            'entity_id'      => (int) $score['entity_id'],
            'academic'       => round((float) ($score['pillar_academic']  ?? 0), 2),
            'social'         => round((float) ($score['pillar_social']    ?? 0), 2),
            'economic'       => round((float) ($score['pillar_economic']  ?? 0), 2),
            'sdg'            => round((float) ($score['pillar_sdg']       ?? 0), 2),
            'composite'      => round((float) ($score['composite_score']  ?? 0), 2),
            'sdg_tags'       => json_decode((string) ($score['sdg_tags'] ?? '[]'), true) ?? [],
            'calculated_at'  => $score['calculated_at'],
            'weights'        => ['academic' => 40, 'social' => 25, 'economic' => 20, 'sdg' => 15],
        ];
    }

    private function parseJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if (!$raw) {
            return $_POST;
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}
