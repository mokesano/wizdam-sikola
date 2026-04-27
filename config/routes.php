<?php

declare(strict_types=1);

use Wizdam\Http\Router;
use Wizdam\Http\Request;
use Wizdam\Http\Response;
use Wizdam\Core\App;
use Wizdam\Http\Middleware\AuthMiddleware;
use Wizdam\Http\Middleware\AdminMiddleware;

/**
 * Definisi semua route aplikasi.
 * 
 * @param App $app Application container
 * @return Router Configured router instance
 */
return function (App $app): Router {
    $router = new Router();

    // Middleware instances
    $authMiddleware = new AuthMiddleware($app->getAuth());
    $adminMiddleware = new AdminMiddleware($app->getAuth());

    // ─── HELPER: render React shell ───────────────────────────────────────────
    $reactShell = function (Request $request) use ($app): Response {
        $twig       = $app->get(\Twig\Environment::class);
        $authSvc    = $app->get(\Wizdam\Services\AuthService::class);
        $isDev      = ($_ENV['APP_ENV'] ?? 'production') === 'development';
        $apiUrl     = $_ENV['REACT_APP_API_URL'] ?? '/api/v1';

        // Baca Vite manifest (hanya production)
        $manifest = [];
        $manifestPath = BASE_PATH . '/public/app/.vite/manifest.json';
        if (!$isDev && file_exists($manifestPath)) {
            $manifest = json_decode(file_get_contents($manifestPath), true) ?? [];
        }

        $html = $twig->render('layouts/react_shell.twig', [
            'vite_dev'    => $isDev,
            'vite_manifest' => $manifest,
            'api_url'     => $apiUrl,
            'current_user' => $authSvc->currentUser(),
            'csrf_token'  => bin2hex(random_bytes(16)),
            'app_path'    => '/app',
        ]);

        return Response::html($html);
    };

    // ─── PUBLIC ROUTES ────────────────────────────────────────────────────────

    // Halaman Utama - Daftar Peneliti (Twig — SEO friendly)
    $router->get('/', function (Request $request) use ($app) {
        $handler = $app->makeHandler(\Wizdam\Handlers\PublicWeb\ResearcherProfileHandler::class);
        return $handler->indexWithResponse($request);
    });

    // Profil Peneliti: /researcher/{orcid}
    $router->get('/researcher/{orcid}', function (Request $request, string $orcid) use ($app) {
        $handler = $app->makeHandler(\Wizdam\Handlers\PublicWeb\ResearcherProfileHandler::class);
        return $handler->showWithResponse($orcid);
    });

    // Profil Institusi: /institution/{id}
    $router->get('/institution/{id:\d+}', function (Request $request, int $id) use ($app) {
        $handler = $app->makeHandler(\Wizdam\Handlers\PublicWeb\InstitutionProfileHandler::class);
        return $handler->showWithResponse($id);
    });

    // Profil Jurnal: /journal/{issn}
    $router->get('/journal/{issn}', function (Request $request, string $issn) use ($app) {
        $handler = $app->makeHandler(\Wizdam\Handlers\PublicWeb\JournalProfileHandler::class);
        return $handler->showWithResponse($issn);
    });

    // ─── AUTH ROUTES ──────────────────────────────────────────────────────────

    // Login page & handle
    $router->any('/auth/login', function (Request $request) use ($app) {
        $authManager = $app->getAuth();
        return $authManager->handleLoginPageWithResponse($app->getTwig(), $request->method);
    });

    // Logout
    $router->get('/auth/logout', function (Request $request) use ($app) {
        $app->getAuth()->logout();
        return Response::redirect('/');
    });

    // ORCID Callback
    $router->get('/auth/orcid-callback', function (Request $request) use ($app) {
        $app->getAuth()->handleOrcidCallback();
        return Response::redirect('/dashboard');
    });

    // ─── REACT SPA (halaman dinamis — dimuat via Twig React shell) ───────────
    // Semua sub-path /app/* dilayani oleh shell yang sama; React Router
    // menangani navigasi di sisi client.

    $router->get('/app',         $reactShell);
    $router->get('/app/{path}',  $reactShell);

    // ─── PROTECTED ROUTES (Requires Login) ────────────────────────────────────

    // Dashboard User (Twig — server-rendered untuk SEO & first-load cepat)
    $router->get('/dashboard', function (Request $request) use ($app, $authMiddleware) {
        if ($response = $authMiddleware->handle($request, [])) {
            return $response;
        }
        $handler = $app->makeHandler(\Wizdam\Handlers\PrivateWeb\UserDashboardHandler::class);
        return $handler->indexWithResponse($request);
    });

    // Admin Analytics (Admin only)
    $router->get('/admin', function (Request $request) use ($app, $adminMiddleware) {
        if ($response = $adminMiddleware->handle($request, [])) {
            return $response;
        }
        $handler = $app->makeHandler(\Wizdam\Handlers\PrivateWeb\AdminAnalyticsHandler::class);
        return $handler->indexWithResponse($request);
    });

    $router->get('/admin/{path}', function (Request $request, string $path) use ($app, $adminMiddleware) {
        if ($response = $adminMiddleware->handle($request, [])) {
            return $response;
        }
        $handler = $app->makeHandler(\Wizdam\Handlers\PrivateWeb\AdminAnalyticsHandler::class);
        return $handler->indexWithResponse($request);
    });

    // ─── TOOLS ROUTES ─────────────────────────────────────────────────────────

    // Image Resizer
    $router->any('/tools/image-resizer', function (Request $request) use ($app) {
        $handler = $app->makeHandler(\Wizdam\Handlers\Tools\ImageResizerHandler::class);
        return $handler->handleWithResponse($request);
    });

    // PDF Compress
    $router->any('/tools/pdf-compress', function (Request $request) use ($app) {
        $handler = $app->makeHandler(\Wizdam\Handlers\Tools\PdfCompressHandler::class);
        return $handler->handleWithResponse($request);
    });

    // ─── API ROUTES ───────────────────────────────────────────────────────────

    // Crawler Receiver
    $router->any('/api/crawler', function (Request $request) use ($app) {
        $handler = new \Wizdam\Services\Harvesting\CrawlerReceiver();
        return $handler->receiveWithResponse($request);
    });

    // ─── REST API v1 ──────────────────────────────────────────────────────────
    // Semua endpoint di bawah ini mengembalikan JSON dan mendukung CORS
    // sehingga React frontend dapat memanggilnya langsung.

    // Stats — ringkasan dashboard
    $router->get('/api/v1/stats', function (Request $request) {
        $handler = new \Wizdam\Handlers\Api\StatsApiHandler();
        return $handler->index($request);
    });

    // OPTIONS preflight untuk semua /api/v1/*
    $router->any('/api/v1/{path}', function (Request $request) {
        if ($request->method === 'OPTIONS') {
            $cors = new \Wizdam\Http\Middleware\CorsMiddleware();
            $cors->sendCorsHeaders();
            return new \Wizdam\Http\Response('', 204);
        }
        return \Wizdam\Http\Response::json(['success' => false, 'message' => 'Not found'], 404);
    });

    // Researchers
    $router->get('/api/v1/researchers', function (Request $request) {
        $handler = new \Wizdam\Handlers\Api\ResearcherApiHandler();
        return $handler->index($request);
    });

    $router->get('/api/v1/researchers/top', function (Request $request) {
        $handler = new \Wizdam\Handlers\Api\ResearcherApiHandler();
        return $handler->top($request);
    });

    $router->get('/api/v1/researchers/{orcid}', function (Request $request, string $orcid) {
        $handler = new \Wizdam\Handlers\Api\ResearcherApiHandler();
        return $handler->show($request, $orcid);
    });

    // Articles / Publications
    $router->get('/api/v1/articles', function (Request $request) {
        $handler = new \Wizdam\Handlers\Api\ArticleApiHandler();
        return $handler->index($request);
    });

    $router->get('/api/v1/articles/top', function (Request $request) {
        $handler = new \Wizdam\Handlers\Api\ArticleApiHandler();
        return $handler->top($request);
    });

    $router->get('/api/v1/articles/trends', function (Request $request) {
        $handler = new \Wizdam\Handlers\Api\ArticleApiHandler();
        return $handler->trends($request);
    });

    $router->get('/api/v1/articles/{id:\d+}', function (Request $request, int $id) {
        $handler = new \Wizdam\Handlers\Api\ArticleApiHandler();
        return $handler->show($request, $id);
    });

    // Institutions
    $router->get('/api/v1/institutions', function (Request $request) {
        $handler = new \Wizdam\Handlers\Api\InstitutionApiHandler();
        return $handler->index($request);
    });

    $router->get('/api/v1/institutions/map', function (Request $request) {
        $handler = new \Wizdam\Handlers\Api\InstitutionApiHandler();
        return $handler->map($request);
    });

    $router->get('/api/v1/institutions/{id:\d+}', function (Request $request, int $id) {
        $handler = new \Wizdam\Handlers\Api\InstitutionApiHandler();
        return $handler->show($request, $id);
    });

    // Impact Scores
    $router->get('/api/v1/impact-scores/averages/{type}', function (Request $request, string $type) {
        $handler = new \Wizdam\Handlers\Api\ImpactScoreApiHandler();
        return $handler->averages($request, $type);
    });

    $router->get('/api/v1/impact-scores/{type}/{id:\d+}', function (Request $request, string $type, int $id) {
        $handler = new \Wizdam\Handlers\Api\ImpactScoreApiHandler();
        return $handler->show($request, $type, $id);
    });

    $router->post('/api/v1/impact-scores/{type}/{id:\d+}/calculate', function (Request $request, string $type, int $id) {
        $handler = new \Wizdam\Handlers\Api\ImpactScoreApiHandler();
        return $handler->calculate($request, $type, $id);
    });

    $router->get('/api/v1/impact-scores/{type}/{id:\d+}/history', function (Request $request, string $type, int $id) {
        $handler = new \Wizdam\Handlers\Api\ImpactScoreApiHandler();
        return $handler->history($request, $type, $id);
    });

    // SDG Classification
    $router->post('/api/v1/sdg/classify', function (Request $request) {
        $handler = new \Wizdam\Handlers\Api\ImpactScoreApiHandler();
        return $handler->classifySdg($request);
    });

    return $router;
};
