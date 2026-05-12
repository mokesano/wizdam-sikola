<?php

declare(strict_types=1);

namespace Wizdam\Database;

use PDO;

/**
 * Database Connector - Singleton pattern untuk koneksi database
 * 
 * Mendukung dependency injection untuk testing dengan menerima konfigurasi secara opsional
 */
class DBConnector
{
    private static ?self $instance = null;
    private ?PDO $pdo = null;
    private array $config = [];

    /**
     * Constructor privat untuk singleton pattern
     * 
     * @param array|null $config Konfigurasi database opsional (untuk testing)
     */
    private function __construct(?array $config = null)
    {
        // Gunakan config dari parameter jika ada (untuk testing),否则 load dari file
        if ($config !== null) {
            $this->config = $config;
        } else {
            // Cek apakah BASE_PATH terdefinisi (production) atau gunakan fallback (testing)
            $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
            $this->config = require $basePath . '/config/database.php';
        }

        $this->initializeConnection();
    }

    /**
     * Inisialisasi koneksi PDO
     */
    private function initializeConnection(): void
    {
        $dsn = sprintf(
            '%s:host=%s;port=%s;dbname=%s;charset=%s',
            $this->config['driver'] ?? 'mysql',
            $this->config['host'] ?? 'localhost',
            $this->config['port'] ?? '3306',
            $this->config['database'] ?? 'wizdam_scola',
            $this->config['charset'] ?? 'utf8mb4'
        );

        $this->pdo = new PDO(
            $dsn,
            $this->config['username'] ?? 'root',
            $this->config['password'] ?? '',
            $this->config['options'] ?? [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    }

    /**
     * Singleton instance dengan dukungan konfigurasi untuk testing
     * 
     * @param array|null $config Konfigurasi database opsional (untuk testing)
     */
    public static function getInstance(?array $config = null): self
    {
        if (self::$instance === null || $config !== null) {
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    /**
     * Reset instance (untuk testing)
     */
    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    public function getPdo(): PDO
    {
        if ($this->pdo === null) {
            throw new \RuntimeException('Database connection not initialized');
        }
        return $this->pdo;
    }

    /** Shortcut: jalankan query dengan parameter terikat. */
    public function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /** Ambil satu baris. */
    public function fetchOne(string $sql, array $params = []): array|false
    {
        return $this->query($sql, $params)->fetch();
    }

    /** Ambil semua baris. */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    /** Insert dan kembalikan lastInsertId. */
    public function insert(string $table, array $data): string
    {
        $cols   = implode(', ', array_keys($data));
        $places = implode(', ', array_fill(0, count($data), '?'));
        $this->query("INSERT INTO `$table` ($cols) VALUES ($places)", array_values($data));
        return $this->pdo->lastInsertId();
    }

    private function __clone() {}
}
