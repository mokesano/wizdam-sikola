<?php

declare(strict_types=1);

namespace Wizdam\Http\Middleware;

use Wizdam\Http\Request;
use Wizdam\Http\Response;

/**
 * Menangani CORS untuk endpoint /api/* agar React frontend bisa memanggil backend.
 *
 * Origin yang diizinkan dikonfigurasi via APP_CORS_ORIGINS di .env,
 * dipisahkan koma. Contoh: http://localhost:3000,https://www.sangia.org
 */
class CorsMiddleware
{
    private array $allowedOrigins;

    public function __construct()
    {
        $raw = $_ENV['APP_CORS_ORIGINS'] ?? 'http://localhost:3000';
        $this->allowedOrigins = array_map('trim', explode(',', $raw));
    }

    public function handle(Request $request, array $params = []): ?Response
    {
        $origin = $request->getServer('HTTP_ORIGIN', '');

        // Preflight OPTIONS — jawab cepat tanpa meneruskan ke handler
        if ($request->method === 'OPTIONS') {
            $headers = $this->buildHeaders($origin);
            return new Response('', 204, $headers);
        }

        // Untuk request normal, header CORS ditambahkan lewat sendCorsHeaders()
        // yang dipanggil oleh ApiBaseHandler sebelum send().
        $this->sendCorsHeaders($origin);

        return null; // Lanjut ke handler berikutnya
    }

    /** Tambahkan header CORS ke response aktif (dipakai oleh API handlers). */
    public function sendCorsHeaders(string $origin = ''): void
    {
        if ($origin === '') {
            $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        }

        foreach ($this->buildHeaders($origin) as $name => $value) {
            header("$name: $value");
        }
    }

    private function buildHeaders(string $origin): array
    {
        $allowedOrigin = $this->resolveOrigin($origin);

        return [
            'Access-Control-Allow-Origin'      => $allowedOrigin,
            'Access-Control-Allow-Methods'      => 'GET, POST, PUT, DELETE, OPTIONS',
            'Access-Control-Allow-Headers'      => 'Content-Type, Authorization, X-Requested-With, X-API-Key',
            'Access-Control-Allow-Credentials'  => 'true',
            'Access-Control-Max-Age'            => '86400',
            'Vary'                              => 'Origin',
        ];
    }

    private function resolveOrigin(string $origin): string
    {
        if (in_array('*', $this->allowedOrigins, true)) {
            return '*';
        }

        if (in_array($origin, $this->allowedOrigins, true)) {
            return $origin;
        }

        // Fallback ke origin pertama yang dikonfigurasi
        return $this->allowedOrigins[0] ?? 'http://localhost:3000';
    }
}
