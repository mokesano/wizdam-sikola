<?php

declare(strict_types=1);

namespace Wizdam\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Wizdam\Http\Router;
use Wizdam\Http\Request;
use Wizdam\Http\Response;

/**
 * Unit Test untuk Router
 * 
 * Menguji routing, pattern matching, dan middleware
 */
class RouterTest extends TestCase
{
    public function testRouteRegistration(): void
    {
        $router = new Router();
        $router->get('/home', fn() => Response::html('Home'));
        
        $this->assertInstanceOf(Router::class, $router);
    }
    
    public function testSimpleRouteDispatch(): void
    {
        $router = new Router();
        $router->get('/home', fn() => Response::html('Welcome Home'));
        
        $request = new Request('GET', '/home');
        $response = $router->dispatch($request);
        
        $this->assertEquals(200, $response->statusCode);
        $this->assertStringContainsString('Welcome Home', $response->body);
    }
    
    public function testRouteNotFound(): void
    {
        $router = new Router();
        $router->get('/home', fn() => Response::html('Home'));
        
        $request = new Request('GET', '/nonexistent');
        $response = $router->dispatch($request);
        
        $this->assertEquals(404, $response->statusCode);
    }
    
    public function testPostRoute(): void
    {
        $router = new Router();
        $router->post('/api/submit', fn() => Response::json(['status' => 'ok']));
        
        $request = new Request('POST', '/api/submit');
        $response = $router->dispatch($request);
        
        $this->assertEquals(200, $response->statusCode);
        $this->assertStringContainsString('application/json', $response->headers['Content-Type']);
    }
    
    public function testMethodNotAllowed(): void
    {
        $router = new Router();
        $router->get('/only-get', fn() => Response::html('GET only'));
        
        $request = new Request('POST', '/only-get');
        $response = $router->dispatch($request);
        
        $this->assertEquals(404, $response->statusCode);
    }
    
    public function testRouteWithParameter(): void
    {
        $router = new Router();
        $router->get('/user/{id}', function(Request $req, string $id) {
            return Response::html("User ID: $id");
        });
        
        $request = new Request('GET', '/user/123');
        $response = $router->dispatch($request);
        
        $this->assertEquals(200, $response->statusCode);
        $this->assertStringContainsString('User ID: 123', $response->body);
    }
    
    public function testRouteWithMultipleParameters(): void
    {
        $router = new Router();
        $router->get('/post/{year}/{month}', function(Request $req, string $year, string $month) {
            return Response::html("Post from $year-$month");
        });
        
        $request = new Request('GET', '/post/2024/05');
        $response = $router->dispatch($request);
        
        $this->assertEquals(200, $response->statusCode);
        $this->assertStringContainsString('Post from 2024-05', $response->body);
    }
    
    public function testRouteWithRegexPattern(): void
    {
        $router = new Router();
        $router->get('/article/{id:\d+}', function(Request $req, string $id) {
            return Response::html("Article $id");
        });
        
        // Should match
        $request = new Request('GET', '/article/456');
        $response = $router->dispatch($request);
        $this->assertEquals(200, $response->statusCode);
        
        // Should not match (non-numeric)
        $request2 = new Request('GET', '/article/abc');
        $response2 = $router->dispatch($request2);
        $this->assertEquals(404, $response2->statusCode);
    }
    
    public function testGlobalMiddleware(): void
    {
        $router = new Router();
        $router->use(function(Request $req, array $params) {
            // Middleware yang menambahkan header
            return null; // lanjutkan ke handler
        });
        
        $router->get('/protected', fn() => Response::html('Protected content'));
        
        $request = new Request('GET', '/protected');
        $response = $router->dispatch($request);
        
        $this->assertEquals(200, $response->statusCode);
    }
    
    public function testMiddlewareCanShortCircuit(): void
    {
        $router = new Router();
        $router->use(function(Request $req, array $params) {
            if ($req->path === '/admin') {
                return Response::error('Unauthorized', 401);
            }
            return null;
        });
        
        $router->get('/admin', fn() => Response::html('Admin panel'));
        
        $request = new Request('GET', '/admin');
        $response = $router->dispatch($request);
        
        $this->assertEquals(401, $response->statusCode);
    }
    
    public function testAnyRouteMatchesAllMethods(): void
    {
        $router = new Router();
        $router->any('/api/data', fn() => Response::json(['data' => 'test']));
        
        // GET should work
        $getReq = new Request('GET', '/api/data');
        $getResponse = $router->dispatch($getReq);
        $this->assertEquals(200, $getResponse->statusCode);
        
        // POST should work
        $postReq = new Request('POST', '/api/data');
        $postResponse = $router->dispatch($postReq);
        $this->assertEquals(200, $postResponse->statusCode);
        
        // PUT should work
        $putReq = new Request('PUT', '/api/data');
        $putResponse = $router->dispatch($putReq);
        $this->assertEquals(200, $putResponse->statusCode);
    }
    
    public function testRouteNormalization(): void
    {
        $router = new Router();
        $router->get('no-slash', fn() => Response::html('Normalized'));
        
        // Router harus menormalisasi path
        $request = new Request('GET', '/no-slash');
        $response = $router->dispatch($request);
        
        $this->assertEquals(200, $response->statusCode);
    }
}
