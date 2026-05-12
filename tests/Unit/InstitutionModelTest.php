<?php

declare(strict_types=1);

namespace Wizdam\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Wizdam\Database\Models\InstitutionModel;

/**
 * Unit Test untuk InstitutionModel
 * 
 * Menguji struktur dan method signatures dari Model
 * Catatan: Test ini hanya menguji struktur, bukan fungsionalitas database
 */
class InstitutionModelTest extends TestCase
{
    public function testModelInstantiation(): void
    {
        $model = new InstitutionModel();
        
        $this->assertInstanceOf(InstitutionModel::class, $model);
    }
    
    public function testModelHasRequiredMethods(): void
    {
        $model = new InstitutionModel();
        
        // Cek bahwa method-method yang diperlukan ada
        $this->assertTrue(method_exists($model, 'findById'));
        $this->assertTrue(method_exists($model, 'findBySintaId'));
        $this->assertTrue(method_exists($model, 'findWithResearcherCount'));
        $this->assertTrue(method_exists($model, 'getResearchers'));
        $this->assertTrue(method_exists($model, 'getAll'));
    }
    
    public function testFindByIdMethodSignature(): void
    {
        $model = new InstitutionModel();
        $reflection = new \ReflectionClass($model);
        $method = $reflection->getMethod('findById');
        
        $this->assertEquals(1, $method->getNumberOfParameters());
    }
    
    public function testFindBySintaIdMethodSignature(): void
    {
        $model = new InstitutionModel();
        $reflection = new \ReflectionClass($model);
        $method = $reflection->getMethod('findBySintaId');
        
        $params = $method->getParameters();
        $this->assertEquals(1, count($params));
        $this->assertEquals('sintaId', $params[0]->getName());
    }
    
    public function testGetResearchersMethodSignature(): void
    {
        $model = new InstitutionModel();
        $reflection = new \ReflectionClass($model);
        $method = $reflection->getMethod('getResearchers');
        
        $params = $method->getParameters();
        $this->assertEquals(2, count($params));
        
        // Parameter pertama: institutionId
        $this->assertEquals('institutionId', $params[0]->getName());
        
        // Parameter kedua: limit dengan default value
        $this->assertEquals('limit', $params[1]->getName());
        $this->assertTrue($params[1]->isDefaultValueAvailable());
        $this->assertEquals(50, $params[1]->getDefaultValue());
    }
    
    public function testGetAllMethodSignature(): void
    {
        $model = new InstitutionModel();
        $reflection = new \ReflectionClass($model);
        $method = $reflection->getMethod('getAll');
        
        $params = $method->getParameters();
        $this->assertEquals(1, count($params));
        $this->assertEquals('province', $params[0]->getName());
        $this->assertEquals('', $params[0]->getDefaultValue());
    }
}
