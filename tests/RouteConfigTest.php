<?php

namespace DevWizard\Filex\Tests;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;

class RouteConfigTest extends TestCase
{
    #[Test]
    public function it_uses_default_route_configuration()
    {
        // Test that routes use default configuration
        $route = Route::getRoutes()->getByName('filex.upload.temp');
        $this->assertNotNull($route);
        $this->assertStringContainsString('filex/upload-temp', $route->uri());
    }

    #[Test]
    public function it_can_generate_urls_with_configured_prefix()
    {
        // Test URL generation with default prefix
        $url = route('filex.upload.temp');
        $this->assertStringContainsString('/filex/upload-temp', $url);

        $deleteUrl = route('filex.temp.delete', ['filename' => 'test.txt']);
        $this->assertStringContainsString('/filex/temp/test.txt', $deleteUrl);

        $infoUrl = route('filex.temp.info', ['filename' => 'test.txt']);
        $this->assertStringContainsString('/filex/temp/test.txt/info', $infoUrl);
    }

    #[Test]
    public function it_respects_route_name_configuration()
    {
        // All routes should start with the configured name prefix
        $filexRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(function ($route) {
                return strpos($route->getName(), 'filex.') === 0;
            });

        $this->assertGreaterThan(0, $filexRoutes->count());

        foreach ($filexRoutes as $route) {
            $this->assertStringStartsWith('filex.', $route->getName());
        }
    }

    #[Test]
    public function it_can_access_route_configuration()
    {
        $routeConfig = config('filex.routes');

        $this->assertIsArray($routeConfig);
        $this->assertArrayHasKey('prefix', $routeConfig);
        $this->assertArrayHasKey('domain', $routeConfig);
        $this->assertArrayHasKey('middleware', $routeConfig);

        // Test default values
        $this->assertEquals('filex', $routeConfig['prefix']);
        $this->assertNull($routeConfig['domain']);
    }
}
