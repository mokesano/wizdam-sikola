<?php

declare(strict_types=1);

/**
 * Application Entry Point
 * 
 * File ini hanya bertugas sebagai bootstrap aplikasi.
 * Semua logika routing dan bisnis ditangani oleh komponen terpisah.
 */

define('BASE_PATH', dirname(__DIR__));
define('APP_START', microtime(true));

// Autoload
require BASE_PATH . '/vendor/autoload.php';

// Bootstrap aplikasi
$app = \Wizdam\Core\App::getInstance()->bootstrap(BASE_PATH);

// Load routes
$routerFactory = require BASE_PATH . '/config/routes.php';
$router = $routerFactory($app);

// Dispatch request
$request = \Wizdam\Http\Request::fromGlobals();
$response = $router->dispatch($request);

// Jika response adalah array [view, data], render dengan Twig
if (is_array($response) && isset($response['view'])) {
    $twig = $app->get(\Twig\Environment::class);
    $authService = $app->get(\Wizdam\Services\AuthService::class);
    
    // Data global untuk semua view
    $globalData = [
        'user' => $authService->currentUser(),
        'flash' => $_SESSION['flash'] ?? [],
        'csrf_token' => bin2hex(random_bytes(32)),
        'site_config' => $app->get(\Wizdam\Services\PageService::class)->getSiteConfig(),
    ];
    
    // Merge dengan data spesifik route
    $data = array_merge($globalData, $response['data'] ?? []);
    
    // Clear flash message setelah dibaca
    unset($_SESSION['flash']);
    
    echo $twig->render($response['view'], $data);
    exit;
}

// Jika response object, kirim langsung (untuk redirect/json)
if (is_object($response) && method_exists($response, 'send')) {
    $response->send();
    exit;
}

// Fallback 404
http_response_code(404);
$twig = $app->get(\Twig\Environment::class);
echo $twig->render('errors/404.twig', ['message' => 'Halaman tidak ditemukan']);

