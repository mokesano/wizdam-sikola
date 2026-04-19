<?php

declare(strict_types=1);

namespace Wizdam\Services\Core;

use Delight\Auth\Auth;
use Delight\Auth\Role;
use Delight\Auth\InvalidEmailException;
use Delight\Auth\InvalidPasswordException;
use Delight\Auth\UserAlreadyExistsException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Manajemen autentikasi: login/register lokal + SSO via ORCID OAuth2.
 * Menggunakan Delight-IM/Auth sebagai backend sesi.
 */
class AuthManager
{
    private Auth $auth;
    private array $apiCfg;

    public function __construct(\PDO $pdo)
    {
        $this->auth   = new Auth($pdo);
        $this->apiCfg = require BASE_PATH . '/config/api.php';
    }

    // ── Pengecekan ──────────────────────────────────────────────────────────

    public function isLoggedIn(): bool
    {
        return $this->auth->isLoggedIn();
    }

    public function getUserId(): ?int
    {
        return $this->auth->isLoggedIn() ? $this->auth->getUserId() : null;
    }

    public function isAdmin(): bool
    {
        return $this->auth->isLoggedIn() && $this->auth->hasRole(Role::ADMIN);
    }

    /** Guard: redirect ke /auth/login jika belum login. */
    public function requireLogin(): void
    {
        if (!$this->isLoggedIn()) {
            header('Location: /auth/login');
            exit;
        }
    }

    /** Guard: kembalikan 403 jika bukan admin. */
    public function requireAdmin(): void
    {
        $this->requireLogin();
        if (!$this->isAdmin()) {
            http_response_code(403);
            exit('Akses ditolak.');
        }
    }

    // ── Login / Register Lokal ──────────────────────────────────────────────

    public function loginWithCredentials(string $email, string $password): bool
    {
        try {
            $this->auth->login($email, $password);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function register(string $email, string $password, string $username): int
    {
        try {
            return $this->auth->register($email, $password, $username);
        } catch (UserAlreadyExistsException) {
            throw new \RuntimeException('Email sudah terdaftar.');
        } catch (InvalidEmailException) {
            throw new \RuntimeException('Format email tidak valid.');
        } catch (InvalidPasswordException) {
            throw new \RuntimeException('Password terlalu lemah (minimal 8 karakter).');
        }
    }

    public function logout(): void
    {
        $this->auth->logOut();
        header('Location: /');
        exit;
    }

    // ── ORCID OAuth2 ────────────────────────────────────────────────────────

    /** Arahkan pengguna ke halaman otorisasi ORCID. */
    public function redirectToOrcid(): void
    {
        $cfg   = $this->apiCfg['orcid'];
        $state = bin2hex(random_bytes(16));
        $_SESSION['orcid_state'] = $state;

        $baseUrl = $cfg['sandbox'] ? 'https://sandbox.orcid.org' : 'https://orcid.org';
        $url     = $baseUrl . '/oauth/authorize?' . http_build_query([
            'client_id'     => $cfg['client_id'],
            'response_type' => 'code',
            'scope'         => '/authenticate',
            'redirect_uri'  => $cfg['redirect_uri'],
            'state'         => $state,
        ]);

        header('Location: ' . $url);
        exit;
    }

    /** Tangani callback dari ORCID setelah user memberi izin. */
    public function handleOrcidCallback(): void
    {
        $code  = $_GET['code']  ?? '';
        $state = $_GET['state'] ?? '';

        if (!$code || !hash_equals($_SESSION['orcid_state'] ?? '', $state)) {
            http_response_code(400);
            exit('State tidak cocok. Kemungkinan serangan CSRF.');
        }

        unset($_SESSION['orcid_state']);

        $tokens = $this->exchangeOrcidCode($code);
        if (!$tokens) {
            http_response_code(502);
            exit('Gagal menukar kode ORCID.');
        }

        $orcidId = $tokens['orcid'];
        $name    = $tokens['name'];
        $email   = $orcidId . '@orcid.placeholder';

        // Buat atau login user
        try {
            $userId = $this->register($email, bin2hex(random_bytes(24)), $name);
        } catch (\RuntimeException) {
            // Sudah terdaftar, lakukan force-login
            $userId = $this->auth->admin()->getUserIdByEmail($email);
            $this->auth->admin()->logInAsUserById($userId);
            header('Location: /dashboard');
            exit;
        }

        $this->auth->admin()->logInAsUserById($userId);
        header('Location: /dashboard');
        exit;
    }

    private function exchangeOrcidCode(string $code): ?array
    {
        $cfg     = $this->apiCfg['orcid'];
        $baseUrl = $cfg['sandbox'] ? 'https://sandbox.orcid.org' : 'https://orcid.org';
        $http    = new Client(['timeout' => 15]);

        try {
            $response = $http->post($baseUrl . '/oauth/token', [
                'form_params' => [
                    'client_id'     => $cfg['client_id'],
                    'client_secret' => $cfg['client_secret'],
                    'grant_type'    => 'authorization_code',
                    'code'          => $code,
                    'redirect_uri'  => $cfg['redirect_uri'],
                ],
                'headers' => ['Accept' => 'application/json'],
            ]);

            return json_decode((string) $response->getBody(), true);

        } catch (GuzzleException $e) {
            error_log('[AuthManager] ORCID token exchange error: ' . $e->getMessage());
            return null;
        }
    }

    // ── Halaman Login (render Twig) ─────────────────────────────────────────

    public function handleLoginPage(\Twig\Environment $twig, string $method): void
    {
        if ($this->isLoggedIn()) {
            header('Location: /dashboard');
            exit;
        }

        $error = null;

        if ($method === 'POST') {
            $email    = trim($_POST['email']    ?? '');
            $password = trim($_POST['password'] ?? '');

            if ($this->loginWithCredentials($email, $password)) {
                header('Location: /dashboard');
                exit;
            }
            $error = 'Email atau password salah.';
        }

        // Redirect ke ORCID
        if (isset($_GET['orcid'])) {
            $this->redirectToOrcid();
        }

        echo $twig->render('pages/auth/login.twig', ['error' => $error]);
    }
}
