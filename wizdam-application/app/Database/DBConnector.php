<?php

declare(strict_types=1);

namespace Wizdam\Database;

class DBConnector
{
    private static ?self $instance = null;
    private \PDO $pdo;

    private function __construct()
    {
        $cfg = require BASE_PATH . '/config/database.php';

        $dsn = sprintf(
            '%s:host=%s;port=%s;dbname=%s;charset=%s',
            $cfg['driver'],
            $cfg['host'],
            $cfg['port'],
            $cfg['database'],
            $cfg['charset']
        );

        $this->pdo = new \PDO($dsn, $cfg['username'], $cfg['password'], $cfg['options']);
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getPdo(): \PDO
    {
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
