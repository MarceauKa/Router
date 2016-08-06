<?php

namespace Akibatech\Tests;

use Akibatech\Router;
use PHPUnit\Framework\TestCase;

/**
 * Class RouterTest
 *
 * @package Akibatech\Tests
 */
class RouterTest extends TestCase
{
    /**
     * @var Router
     */
    protected $router;

    //-------------------------------------------------------------------------

    /**
     * SetUp new Router for each test.
     *
     * @param   void
     * @return  void
     */
    protected function setUp()
    {
        $this->router = Router::renewInstance();
    }

    //-------------------------------------------------------------------------

    /**
     * Teste l'instanciation et l'instance créée par défaut.
     *
     * @test
     */
    public function testDefaultInstance()
    {
        $this->assertInstanceOf(Router::class, $this->router);
        $this->assertEmpty($this->router->routes());
        $this->assertEquals($this->router, Router::getInstance());
    }

    //-------------------------------------------------------------------------

    /**
     * Teste la création d'une route en GET.
     *
     * @test
     */
    public function testGetRouteCreation()
    {
        $this->router->get('hello', function() {
            return 'Hello!';
        });

        $this->assertNotEmpty($this->router->routes());
    }

    //-------------------------------------------------------------------------

    /**
     * Teste la capture d'une requête en GET et également le retour quand il n'y a pas de matching.
     *
     * @test
     */
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

    //-------------------------------------------------------------------------

    /**
     * Teste l'action par défaut quand il n'y a aucun retour.
     *
     * @test
     */
    public function testNotFoundMatching()
    {
        $this->router
            ->get('hello', 'UnexistingDispatch')
            ->whenNotFound(function() {
                return 'Not found!';
            });

        $output = $this->router->listen('misssing');
        $this->assertEquals('Not found!', $output);
    }

    //-------------------------------------------------------------------------

    /**
     * Teste les paramètres d'URL.
     *
     * @test
     */
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

        $id = $this->router->listen('foo/2');
        $this->assertEquals('2', $id);

        $slug = $this->router->listen('bar/hello-world');
        $this->assertEquals('hello-world', $slug);

        $custom = $this->router->listen('baz/custom_uri');
        $this->assertEquals('custom_uri', $custom);

        $name = $this->router->listen('kux/akibatech');
        $this->assertEquals('akibatech', $name);

        $post = $this->router->listen('form/42/T0k3n', 'post');
        $this->assertEquals('42T0k3n', $post);
    }

    //-------------------------------------------------------------------------
}