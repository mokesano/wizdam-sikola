<?php

declare(strict_types=1);

namespace Wizdam\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Wizdam\Database\DBConnector;

/**
 * Unit Test untuk DBConnector
 * 
 * Menguji singleton pattern dan struktur dasar DBConnector
 */
class DBConnectorTest extends TestCase
{
    public function testSingletonInstance(): void
    {
        // Pastikan getInstance() mengembalikan instance yang sama
        $instance1 = DBConnector::getInstance();
        $instance2 = DBConnector::getInstance();
        
        $this->assertSame($instance1, $instance2, 'DBConnector harus mengikuti singleton pattern');
    }
    
    public function testGetInstanceReturnsDBConnectorType(): void
    {
        $instance = DBConnector::getInstance();
        
        $this->assertInstanceOf(DBConnector::class, $instance);
    }
    
    public function testGetPdoReturnsPDOType(): void
    {
        $instance = DBConnector::getInstance();
        $pdo = $instance->getPdo();
        
        $this->assertInstanceOf(\PDO::class, $pdo);
    }
}
