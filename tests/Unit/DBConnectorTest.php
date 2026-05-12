<?php

declare(strict_types=1);

namespace Wizdam\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Wizdam\Database\DBConnector;

/**
 * Unit Test untuk DBConnector
 *
 * Menguji singleton pattern dan struktur dasar DBConnector
 * 
 * @group db-connector
 */
class DBConnectorTest extends TestCase
{
    protected function setUp(): void
    {
        // Reset singleton instance sebelum setiap test
        DBConnector::resetInstance();
    }

    protected function tearDown(): void
    {
        // Reset setelah test selesai
        DBConnector::resetInstance();
    }

    public function testSingletonInstance(): void
    {
        // Skip jika tidak ada database connection
        if (!$this->isDatabaseAvailable()) {
            $this->markTestSkipped(
                "Database tidak tersedia. Test ini memerlukan koneksi database."
            );
        }
        
        // Pastikan getInstance() mengembalikan instance yang sama
        $instance1 = DBConnector::getInstance();
        $instance2 = DBConnector::getInstance();

        $this->assertSame($instance1, $instance2, 'DBConnector harus mengikuti singleton pattern');
    }

    public function testGetInstanceReturnsDBConnectorType(): void
    {
        if (!$this->isDatabaseAvailable()) {
            $this->markTestSkipped(
                "Database tidak tersedia. Test ini memerlukan koneksi database."
            );
        }
        
        $instance = DBConnector::getInstance();
        $this->assertInstanceOf(DBConnector::class, $instance);
    }

    public function testGetPdoReturnsPDOType(): void
    {
        if (!$this->isDatabaseAvailable()) {
            $this->markTestSkipped(
                "Database tidak tersedia. Test ini memerlukan koneksi database."
            );
        }
        
        $instance = DBConnector::getInstance();
        $pdo = $instance->getPdo();

        $this->assertInstanceOf(\PDO::class, $pdo);
    }

    public function testResetInstance(): void
    {
        if (!$this->isDatabaseAvailable()) {
            $this->markTestSkipped(
                "Database tidak tersedia. Test ini memerlukan koneksi database."
            );
        }
        
        $instance1 = DBConnector::getInstance();
        DBConnector::resetInstance();
        $instance2 = DBConnector::getInstance();

        $this->assertNotSame($instance1, $instance2, 'Setelah reset, instance harus berbeda');
    }

    /**
     * Helper untuk cek apakah database tersedia
     */
    private function isDatabaseAvailable(): bool
    {
        $dbHost = getenv('DB_HOST') ?: 'localhost';
        $dbPort = getenv('DB_PORT') ?: '3306';
        
        // Cek apakah database tersedia
        $connection = @fsockopen($dbHost, (int)$dbPort, $errno, $errstr, 1);
        if ($connection !== false) {
            fclose($connection);
            return true;
        }
        return false;
    }
}
