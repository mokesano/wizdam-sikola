<?php

declare(strict_types=1);

namespace Wizdam\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Wizdam\Core\App;

/**
 * Unit Test untuk App Container
 * 
 * Menguji singleton pattern dan dependency injection container
 */
class AppContainerTest extends TestCase
{
    public function testSingletonInstance(): void
    {
        $instance1 = App::getInstance();
        $instance2 = App::getInstance();
        
        $this->assertSame($instance1, $instance2, 'App harus mengikuti singleton pattern');
    }
    
    public function testGetInstanceReturnsAppType(): void
    {
        $instance = App::getInstance();
        
        $this->assertInstanceOf(App::class, $instance);
    }
    
    public function testAppHasRequiredMethods(): void
    {
        $app = App::getInstance();
        
        $this->assertTrue(method_exists($app, 'bootstrap'));
        $this->assertTrue(method_exists($app, 'getConfig'));
        $this->assertTrue(method_exists($app, 'getDb'));
        $this->assertTrue(method_exists($app, 'getTwig'));
        $this->assertTrue(method_exists($app, 'getAuth'));
        $this->assertTrue(method_exists($app, 'makeHandler'));
    }
    
    public function testMakeHandlerWithNoConstructor(): void
    {
        $app = App::getInstance();
        
        // Buat class test tanpa constructor
        $className = 'Wizdam\\Tests\\Unit\\TestHandlerNoConstructor';
        if (!class_exists($className)) {
            eval('namespace Wizdam\\Tests\\Unit; class TestHandlerNoConstructor {}');
        }
        
        $handler = $app->makeHandler($className);
        
        $this->assertInstanceOf($className, $handler);
    }
    
    public function testMakeHandlerReflectionCapability(): void
    {
        $app = App::getInstance();
        
        // Test bahwa makeHandler menggunakan reflection dengan benar
        $reflection = new \ReflectionClass($app);
        $method = $reflection->getMethod('makeHandler');
        
        $this->assertEquals(1, $method->getNumberOfParameters());
        
        $params = $method->getParameters();
        $this->assertEquals('className', $params[0]->getName());
    }
}
