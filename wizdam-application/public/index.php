<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
define('APP_START', microtime(true));

require BASE_PATH . '/library/vendor/autoload.php';

// Muat environment variables
$dotenv = Dotenv\Dotenv::createImmutable(BASE_PATH);
$dotenv->safeLoad();

// Muat konfigurasi aplikasi
$appConfig = require BASE_PATH . '/config/app.php';

// Inisialisasi Twig
$loader = new \Twig\Loader\FilesystemLoader(BASE_PATH . '/views');
$twig   = new \Twig\Environment($loader, [
    'cache' => $appConfig['twig_cache'] ? BASE_PATH . '/library/cache/twig' : false,
    'debug' => $appConfig['debug'],
]);
if ($appConfig['debug']) {
    $twig->addExtension(new \Twig\Extension\DebugExtension());
}
$twig->addGlobal('app', $appConfig);

// Koneksi database
$db = \Wizdam\Database\DBConnector::getInstance();

// Inisialisasi AuthManager
$auth = new \Wizdam\Services\Core\AuthManager($db->getPdo());

// Injeksi auth ke semua handler
$handlerArgs = [$db, $twig, $auth];

// ─── ROUTER SEDERHANA ────────────────────────────────────────────────────────
$requestUri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$requestMethod = $_SERVER['REQUEST_METHOD'];

// Hapus base path jika app tidak di root
$basePath = $appConfig['base_path'] ?? '';
if ($basePath && str_starts_with($requestUri, $basePath)) {
    $requestUri = substr($requestUri, strlen($basePath));
}
$requestUri = '/' . ltrim($requestUri, '/');

// Segmen URI
$segments = array_values(array_filter(explode('/', $requestUri)));

// Tambahkan global Twig dari auth
$twig->addGlobal('currentUser', $auth->isLoggedIn() ? $auth->getUserId() : null);

try {
    match (true) {

        // ── Halaman Utama ─────────────────────────────────────────────────────
        $requestUri === '/' =>
            (new \Wizdam\Handlers\PublicWeb\ResearcherProfileHandler(...$handlerArgs))->index(),

        // ── Profil Peneliti: /researcher/{orcid} ─────────────────────────────
        count($segments) === 2 && $segments[0] === 'researcher' =>
            (new \Wizdam\Handlers\PublicWeb\ResearcherProfileHandler(...$handlerArgs))
                ->show($segments[1]),

        // ── Profil Institusi: /institution/{id} ──────────────────────────────
        count($segments) === 2 && $segments[0] === 'institution' =>
            (new \Wizdam\Handlers\PublicWeb\InstitutionProfileHandler(...$handlerArgs))
                ->show((int) $segments[1]),

        // ── Profil Jurnal: /journal/{issn} ───────────────────────────────────
        count($segments) === 2 && $segments[0] === 'journal' =>
            (new \Wizdam\Handlers\PublicWeb\JournalProfileHandler(...$handlerArgs))
                ->show($segments[1]),

        // ── Auth ──────────────────────────────────────────────────────────────
        $segments[0] === 'auth' && ($segments[1] ?? '') === 'login' =>
            (new \Wizdam\Services\Core\AuthManager($db->getPdo()))->handleLoginPage($twig, $requestMethod),

        $segments[0] === 'auth' && ($segments[1] ?? '') === 'logout' =>
            (new \Wizdam\Services\Core\AuthManager($db->getPdo()))->logout(),

        $segments[0] === 'auth' && ($segments[1] ?? '') === 'orcid-callback' =>
            (new \Wizdam\Services\Core\AuthManager($db->getPdo()))->handleOrcidCallback(),

        // ── Dashboard User (Private) ──────────────────────────────────────────
        count($segments) >= 1 && $segments[0] === 'dashboard' =>
            (new \Wizdam\Handlers\PrivateWeb\UserDashboardHandler(...$handlerArgs))->index(),

        // ── Admin Analytics (Private) ─────────────────────────────────────────
        count($segments) >= 1 && $segments[0] === 'admin' =>
            (new \Wizdam\Handlers\PrivateWeb\AdminAnalyticsHandler(...$handlerArgs))->index(),

        // ── Tools ─────────────────────────────────────────────────────────────
        count($segments) === 2 && $segments[0] === 'tools' && $segments[1] === 'image-resizer' =>
            (new \Wizdam\Handlers\Tools\ImageResizerHandler(...$handlerArgs))->handle($requestMethod),

        count($segments) === 2 && $segments[0] === 'tools' && $segments[1] === 'pdf-compress' =>
            (new \Wizdam\Handlers\Tools\PdfCompressHandler(...$handlerArgs))->handle($requestMethod),

        // ── API Endpoints ─────────────────────────────────────────────────────
        $segments[0] === 'api' && ($segments[1] ?? '') === 'crawler' =>
            (new \Wizdam\Services\Harvesting\CrawlerReceiver())->receive($requestMethod),

        // ── 404 ───────────────────────────────────────────────────────────────
        default => http_response_code(404) &&
            print($twig->render('pages/error.twig', ['code' => 404, 'message' => 'Halaman tidak ditemukan.'])),
    };
} catch (\Throwable $e) {
    if ($appConfig['debug']) {
        echo '<pre style="background:#1e1e1e;color:#d4d4d4;padding:20px;font-size:13px;">';
        echo '<b>' . get_class($e) . '</b>: ' . htmlspecialchars($e->getMessage()) . "\n\n";
        echo htmlspecialchars($e->getTraceAsString());
        echo '</pre>';
    } else {
        http_response_code(500);
        echo $twig->render('pages/error.twig', ['code' => 500, 'message' => 'Terjadi kesalahan pada server.']);
    }
}
