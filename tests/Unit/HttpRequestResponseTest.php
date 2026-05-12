<?php

declare(strict_types=1);

namespace Wizdam\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Wizdam\Http\Request;
use Wizdam\Http\Response;

/**
 * Unit Test untuk Request dan Response classes
 * 
 * Menguji pembuatan request/response dan helper methods
 */
class HttpRequestResponseTest extends TestCase
{
    public function testRequestConstruction(): void
    {
        $request = new Request('GET', '/test', ['q' => 'search'], [], []);
        
        $this->assertEquals('GET', $request->method);
        $this->assertEquals('/test', $request->path);
        $this->assertEquals('search', $request->getQuery('q'));
        $this->assertNull($request->getQuery('nonexistent'));
    }
    
    public function testRequestWithDefaultValues(): void
    {
        $request = new Request('POST', '/api/data');
        
        $this->assertEquals([], $request->query);
        $this->assertEquals([], $request->body);
        $this->assertEquals([], $request->server);
    }
    
    public function testRequestGetQueryWithDefault(): void
    {
        $request = new Request('GET', '/test');
        
        $this->assertEquals('default', $request->getQuery('missing', 'default'));
    }
    
    public function testRequestGetBodyWithDefault(): void
    {
        $request = new Request('POST', '/api', [], ['name' => 'John']);
        
        $this->assertEquals('John', $request->getBody('name'));
        $this->assertEquals('N/A', $request->getBody('missing', 'N/A'));
    }
    
    public function testResponseHtmlCreation(): void
    {
        $response = Response::html('<h1>Hello</h1>', 200, ['X-Custom' => 'value']);
        
        $this->assertEquals('<h1>Hello</h1>', $response->body);
        $this->assertEquals(200, $response->statusCode);
        $this->assertEquals('text/html; charset=utf-8', $response->headers['Content-Type']);
        $this->assertEquals('value', $response->headers['X-Custom']);
    }
    
    public function testResponseJsonCreation(): void
    {
        $data = ['status' => 'success', 'count' => 42];
        $response = Response::json($data);
        
        $this->assertEquals('application/json', $response->headers['Content-Type']);
        $this->assertEquals(json_encode($data), $response->body);
    }
    
    public function testResponseJsonWithStatus(): void
    {
        $response = Response::json(['error' => 'not found'], 404);
        
        $this->assertEquals(404, $response->statusCode);
    }
    
    public function testResponseRedirect(): void
    {
        $response = Response::redirect('/dashboard', 302);
        
        $this->assertEquals('', $response->body);
        $this->assertEquals(302, $response->statusCode);
        $this->assertEquals('/dashboard', $response->headers['Location']);
    }
    
    public function testResponseNotFound(): void
    {
        $response = Response::notFound('Page missing');
        
        $this->assertEquals(404, $response->statusCode);
        $this->assertEquals('Page missing', $response->body);
    }
    
    public function testResponseError(): void
    {
        $response = Response::error('Server error', 500);
        
        $this->assertEquals(500, $response->statusCode);
        $this->assertEquals('Server error', $response->body);
    }
    
    public function testResponseConstructionWithCustomHeaders(): void
    {
        $response = new Response('content', 201, ['Cache-Control' => 'no-cache']);
        
        $this->assertEquals('content', $response->body);
        $this->assertEquals(201, $response->statusCode);
        $this->assertEquals('no-cache', $response->headers['Cache-Control']);
    }
}
