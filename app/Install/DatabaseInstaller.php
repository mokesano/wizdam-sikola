<?php

namespace Wizdam\Install;

use PDO;
use Exception;

class DatabaseInstaller
{
    private PDO $db;
    private string $rootPath;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->rootPath = dirname(__DIR__, 2);
    }

    public function install(array $config): array
    {
        try {
            // Buat file .env
            $this->createEnvFile($config);

            // Jalankan skema database
            $this->runSchema();

            // Seed data awal
            $this->seedInitialData($config);

            return ['success' => true, 'message' => 'Instalasi berhasil!'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    private function createEnvFile(array $config): void
    {
        $content = sprintf(
            "APP_NAME=\"%s\"\nAPP_ENV=%s\nAPP_DEBUG=%s\nAPP_URL=%s\n\nDB_HOST=%s\nDB_PORT=%s\nDB_NAME=%s\nDB_USER=%s\nDB_PASS=%s\n\nWIZDAM_API_URL=%s\nWIZDAM_API_KEY=%s\nGEOIP_PATH=storage/geoip/GeoLite2-City.mmdb\n",
            $config['app_name'] ?? 'Wizdam Scola',
            $config['app_env'] ?? 'production',
            $config['app_debug'] ?? 'false',
            $config['app_url'] ?? 'https://www.sangia.org',
            $config['db_host'] ?? 'localhost',
            $config['db_port'] ?? '3306',
            $config['db_name'],
            $config['db_user'],
            $config['db_pass'],
            $config['api_url'] ?? 'https://api.sangia.org',
            $config['api_key'] ?? ''
        );

        file_put_contents($this->rootPath . '/.env', $content);
    }

    private function runSchema(): void
    {
        $schemaFile = $this->rootPath . '/database_schema.sql';
        if (!file_exists($schemaFile)) {
            throw new Exception('File database_schema.sql tidak ditemukan');
        }

        $sql = file_get_contents($schemaFile);
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            fn($s) => !empty($s) && !str_starts_with(trim($s), '--')
        );

        foreach ($statements as $statement) {
            $this->db->exec($statement);
        }
    }

    private function seedInitialData(array $config): void
    {
        // Buat admin default
        $stmt = $this->db->prepare("
            INSERT INTO users (name, email, password, role, is_active, created_at) 
            VALUES (?, ?, ?, 'admin', 1, NOW())
            ON DUPLICATE KEY UPDATE name=VALUES(name)
        ");

        $passwordHash = password_hash($config['admin_password'] ?? 'admin123', PASSWORD_DEFAULT);
        $stmt->execute([
            $config['admin_name'] ?? 'Administrator',
            $config['admin_email'] ?? 'admin@sangia.org',
            $passwordHash
        ]);

        // Buat API Key default jika belum ada
        if (!empty($config['api_key'])) {
            $stmt = $this->db->prepare("
                INSERT INTO api_keys (user_id, name, key, permissions, is_active, expires_at, created_at) 
                SELECT id, ?, ?, '[\"read\",\"write\"]', 1, NULL, NOW() 
                FROM users WHERE email = ?
                ON DUPLICATE KEY UPDATE key=VALUES(key)
            ");
            $stmt->execute([
                'Default API Key',
                $config['api_key'],
                $config['admin_email'] ?? 'admin@sangia.org'
            ]);
        }
    }
}
