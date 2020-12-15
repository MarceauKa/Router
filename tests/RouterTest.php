<?php

namespace MarceauKa\Tests;

use MarceauKa\Router;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    protected $router;

    protected function setUp(): void
    {
        $this->router = new Router();
    }

    /** @test */
    public function testDefaultInstance()
    {
        $this->assertInstanceOf(Router::class, $this->router);
        $this->assertEmpty($this->router->routes());
    }

    /** @test */
    public function testGetRouteCreation()
    {
        $this->router->get('hello', function() {
            return 'Hello!';
        });

        $this->assertNotEmpty($this->router->routes());
    }

    /** @test */
    public function testGetRouteListening()
    {
        $this->router->get('hello', function() {
            return 'Hello!';
        });

        $matching = $this->router->listen('hello', 'GET');
        $this->assertEquals('Hello!', $matching);

        $return_without_matching = $this->router->listen('bye', 'GET');
        $this->assertInstanceOf(Router::class, $return_without_matching);
    }

    /** @test */
    public function testNotFoundMatching()
    {
        $this->router
            ->get('hello', 'UnexistingDispatch')
            ->whenNotFound(function() {
                return 'Not found!';
            });

        $output = $this->router->listen('misssing', 'GET');
        $this->assertEquals('Not found!', $output);
    }

    /** @test */
    public function testRouteParametersBindings()
    {
        $this->router
            ->get('foo/{:num}', function ($id) {
                return $id;
            })
            ->get('bar/{:slug}', function ($slug) {
                return $slug;
            })
            ->get('baz/{:any}', function ($custom) {
                return $custom;
            })
            ->get('kux/{:alpha}', function ($name) {
                return $name;
            })
            ->post('form/{:num}/{:any}', function ($id, $token) {
                return $id.$token;
            });

        $id = $this->router->listen('foo/2', 'get');
        $this->assertEquals('2', $id);

        $slug = $this->router->listen('bar/hello-world', 'get');
        $this->assertEquals('hello-world', $slug);

        $custom = $this->router->listen('baz/custom_uri', 'get');
        $this->assertEquals('custom_uri', $custom);

        $name = $this->router->listen('kux/akibatech', 'get');
        $this->assertEquals('akibatech', $name);

        $post = $this->router->listen('form/42/T0k3n', 'post');
        $this->assertEquals('42T0k3n', $post);
    }

    /** @test */
    public function testNamedRoute()
    {
        $this->router
            ->get('homepage', '...', 'home')
            ->get('tag/{:slug}', '...', 'tag')
            ->get('profile/{:num}/{:any}', '...', 'profile');

        $route1 = $this->router->link('home');
        $route2 = $this->router->link('tag', 'wordpress');
        $route3 = $this->router->link('profile', [42, 'JohnDoe']);

        $this->assertEquals($route1, '/homepage');
        $this->assertEquals($route2, '/tag/wordpress');
        $this->assertEquals($route3, '/profile/42/JohnDoe');
    }

    /** @test */
    public function testNamedRouteOnDuplicateException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->router->get('foo', '...', 'route1');
        $this->router->get('bar', '...', 'route1');
    }

    /** @test */
    public function testSerializedRoutes()
    {
        $this->router->get('hello', 'Action@Method', 'name');
        $compiled = $this->router->getCompiled();

        $router = Router::fromCompiled($compiled);

        $this->assertNotEquals(spl_object_hash($router), spl_object_hash($this->router));
        $this->assertInstanceOf(Router::class, $router);
        $this->assertEquals('/hello', $router->link('name'));
    }
}
