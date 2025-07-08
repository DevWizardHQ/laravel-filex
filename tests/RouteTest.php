<?php

namespace DevWizard\Filex\Tests;

use Illuminate\Support\Facades\Route;

class RouteTest extends TestCase
{
    /** @test */
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
    
    /** @test */
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
}
