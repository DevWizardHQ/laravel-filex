<?php

namespace DevWizard\Filex\Tests;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;

class RouteTest extends TestCase
{
    #[Test]
    public function it_has_the_correct_temp_delete_route()
    {
        $this->assertTrue(Route::has('filex.temp.delete'));

        $route = Route::getRoutes()->getByName('filex.temp.delete');
        $this->assertNotNull($route);

        // Test that the route expects a filename parameter
        $this->assertStringContainsString('{filename}', $route->uri());

        // Test that we can generate a URL with a filename
        $url = route('filex.temp.delete', ['filename' => 'test-file.txt']);
        $this->assertStringContainsString('filex/temp/test-file.txt', $url);
    }

    #[Test]
    public function it_has_the_correct_temp_info_route()
    {
        $this->assertTrue(Route::has('filex.temp.info'));

        $route = Route::getRoutes()->getByName('filex.temp.info');
        $this->assertNotNull($route);

        // Test that the route expects a filename parameter
        $this->assertStringContainsString('{filename}', $route->uri());

        // Test that we can generate a URL with a filename
        $url = route('filex.temp.info', ['filename' => 'test-file.txt']);
        $this->assertStringContainsString('filex/temp/test-file.txt/info', $url);
    }

    #[Test]
    public function it_uses_configured_route_prefix()
    {
        // Test default prefix
        $route = Route::getRoutes()->getByName('filex.upload.temp');
        $this->assertNotNull($route);
        $this->assertStringContainsString('filex/upload-temp', $route->uri());

        // Test that all filex routes use the configured prefix
        $filexRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(function ($route) {
                return strpos($route->getName(), 'filex.') === 0;
            });

        $this->assertGreaterThan(0, $filexRoutes->count());

        foreach ($filexRoutes as $route) {
            $this->assertStringContainsString('filex/', $route->uri());
        }
    }

    #[Test]
    public function it_has_all_required_routes()
    {
        $requiredRoutes = [
            'filex.upload.temp',
            'filex.upload.temp.optimized',
            'filex.temp.delete',
            'filex.temp.info',
            'filex.config'
        ];

        foreach ($requiredRoutes as $routeName) {
            $this->assertTrue(Route::has($routeName), "Route {$routeName} should exist");
        }
    }
}
