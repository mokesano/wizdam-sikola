<?php

declare(strict_types=1);

namespace Wizdam\Services;

use Wizdam\Database\DatabaseManager;
use Wizdam\Library\SecurityHelper;

/**
 * AuthService - Mengelola autentikasi dan sesi pengguna
 */
class AuthService
{
    private DatabaseManager $db;
    
    public function __construct(DatabaseManager $db)
    {
        $this->db = $db;
    }
    
    /**
     * Dapatkan pengguna yang sedang login
     */
    public function currentUser(): ?array
    {
        if (!isset($_SESSION['user_id'])) {
            return null;
        }
        
        $stmt = $this->db->pdo()->prepare("
            SELECT id, username, email, full_name, avatar, role, 
                   is_active, created_at, last_login
            FROM users
            WHERE id = :id AND is_active = 1
        ");
        $stmt->execute(['id' => $_SESSION['user_id']]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return $user ?: null;
    }
    
    /**
     * Login dengan username/email dan password
     */
    public function login(string $username, string $password, bool $remember = false): array
    {
        $stmt = $this->db->pdo()->prepare("
            SELECT id, username, email, password_hash, full_name, avatar, 
                   role, is_active, failed_login_attempts, locked_until
            FROM users
            WHERE (username = :username OR email = :username)
        ");
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        // Cek apakah akun terkunci
        if ($user && $user['locked_until'] && strtotime($user['locked_until']) > time()) {
            return [
                'success' => false,
                'message' => 'Akun Anda terkunci. Silakan coba lagi dalam beberapa menit.',
            ];
        }
        
        // Validasi user dan password
        if (!$user || !password_verify($password, $user['password_hash'])) {
            // Increment failed attempts
            if ($user) {
                $this->recordFailedLogin($user['id']);
            }
            
            return [
                'success' => false,
                'message' => 'Username atau password salah.',
            ];
        }
        
        // Cek apakah akun aktif
        if (!$user['is_active']) {
            return [
                'success' => false,
                'message' => 'Akun Anda belum diaktifkan. Hubungi administrator.',
            ];
        }
        
        // Reset failed attempts dan update last login
        $this->recordSuccessfulLogin($user['id']);
        
        // Set session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        
        // Remember me
        if ($remember) {
            $token = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
            
            $stmt = $this->db->pdo()->prepare("
                INSERT INTO remember_tokens (user_id, token, expires_at)
                VALUES (:user_id, :token, :expires_at)
            ");
            $stmt->execute([
                'user_id' => $user['id'],
                'token' => hash('sha256', $token),
                'expires_at' => $expiresAt,
            ]);
            
            setcookie('remember_token', $token, strtotime('+30 days'), '/', '', true, true);
        }
        
        return [
            'success' => true,
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'full_name' => $user['full_name'],
                'role' => $user['role'],
            ],
        ];
    }
    
    /**
     * Logout
     */
    public function logout(): void
    {
        // Delete remember token jika ada
        if (isset($_COOKIE['remember_token'])) {
            $stmt = $this->db->pdo()->prepare("
                DELETE FROM remember_tokens 
                WHERE token = :token
            ");
            $stmt->execute(['token' => hash('sha256', $_COOKIE['remember_token'])]);
            setcookie('remember_token', '', time() - 3600, '/', '', true, true);
        }
        
        // Destroy session
        session_destroy();
        $_SESSION = [];
    }
    
    /**
     * Cek apakah pengguna adalah admin
     */
    public function isAdmin(): bool
    {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }
    
    /**
     * Cek apakah pengguna adalah researcher
     */
    public function isResearcher(): bool
    {
        return isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'researcher']);
    }
    
    /**
     * Require login - redirect jika belum login
     */
    public function requireLogin(): void
    {
        if (!$this->currentUser()) {
            $_SESSION['flash'] = ['error' => 'Silakan login terlebih dahulu.'];
            header('Location: /login');
            exit;
        }
    }
    
    /**
     * Require admin - redirect jika bukan admin
     */
    public function requireAdmin(): void
    {
        $this->requireLogin();
        
        if (!$this->isAdmin()) {
            $_SESSION['flash'] = ['error' => 'Akses ditolak. Hanya administrator yang dapat mengakses halaman ini.'];
            header('Location: /dashboard');
            exit;
        }
    }
    
    /**
     * Record failed login attempt
     */
    private function recordFailedLogin(int $userId): void
    {
        $stmt = $this->db->pdo()->prepare("
            UPDATE users SET
                failed_login_attempts = failed_login_attempts + 1,
                locked_until = CASE 
                    WHEN failed_login_attempts >= 4 THEN DATE_ADD(NOW(), INTERVAL 15 MINUTE)
                    ELSE locked_until
                END
            WHERE id = :id
        ");
        $stmt->execute(['id' => $userId]);
    }
    
    /**
     * Record successful login
     */
    private function recordSuccessfulLogin(int $userId): void
    {
        $stmt = $this->db->pdo()->prepare("
            UPDATE users SET
                failed_login_attempts = 0,
                locked_until = NULL,
                last_login = NOW(),
                last_ip = :ip
            WHERE id = :id
        ");
        $stmt->execute([
            'id' => $userId,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        ]);
    }
}
