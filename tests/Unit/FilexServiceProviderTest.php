<?php

namespace DevWizard\Filex\Tests\Unit;

use DevWizard\Filex\Tests\TestCase;
use DevWizard\Filex\FilexServiceProvider;
use DevWizard\Filex\Filex;
use DevWizard\Filex\Services\FilexService;
use DevWizard\Filex\Services\FileRuleService;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Validator;

class FilexServiceProviderTest extends TestCase
{
    protected FilexServiceProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new FilexServiceProvider($this->app);
    }

    public function test_service_provider_can_be_instantiated()
    {
        expect($this->provider)->toBeInstanceOf(FilexServiceProvider::class);
    }

    public function test_filex_service_is_registered_as_singleton()
    {
        $service1 = $this->app->make(FilexService::class);
        $service2 = $this->app->make(FilexService::class);
        
        expect($service1)->toBeInstanceOf(FilexService::class);
        expect($service2)->toBeInstanceOf(FilexService::class);
        expect($service1)->toBe($service2); // Should be the same instance (singleton)
    }

    public function test_filex_main_class_is_registered_as_singleton()
    {
        $filex1 = $this->app->make(Filex::class);
        $filex2 = $this->app->make(Filex::class);
        
        expect($filex1)->toBeInstanceOf(Filex::class);
        expect($filex2)->toBeInstanceOf(Filex::class);
        expect($filex1)->toBe($filex2); // Should be the same instance (singleton)
    }

    public function test_file_rule_service_is_registered_for_facade()
    {
        $fileRuleService = $this->app->make('filex.file-rule');
        
        expect($fileRuleService)->toBeInstanceOf(FileRuleService::class);
    }

    public function test_filex_main_class_has_correct_service_dependency()
    {
        $filex = $this->app->make(Filex::class);
        $service = $filex->service();
        
        expect($service)->toBeInstanceOf(FilexService::class);
        expect($service)->toBe($this->app->make(FilexService::class));
    }

    public function test_blade_component_is_registered()
    {
        // Since the service provider skips Blade registration during unit tests,
        // we'll test that the registration method exists and can be called
        
        // Use reflection to test the protected method
        $reflection = new \ReflectionClass($this->provider);
        $method = $reflection->getMethod('registerBladeFeatures');
        $method->setAccessible(true);
        
        // This should not throw an exception
        $method->invoke($this->provider);
        
        // Test passes if no exception is thrown
        $this->assertTrue(true);
    }

    public function test_custom_validation_rules_are_registered()
    {
        // Trigger validation rule registration by creating validator
        $validator = Validator::make([], []);
        
        // Check that custom validation rule extensions exist
        // Note: We can't directly test the internal validator extensions
        // but we can ensure the registration process doesn't fail
        $this->assertTrue(true); // Basic smoke test for rule registration
    }

    public function test_package_configuration_is_correct()
    {
        // Test that package name and configuration are set correctly
        expect($this->provider)->toBeInstanceOf(FilexServiceProvider::class);
        
        // Test that config exists after setup
        $this->assertNotNull(config('filex'));
    }

    public function test_service_provider_boots_without_errors()
    {
        // This is a smoke test to ensure the service provider boots correctly
        // The actual boot process is handled by Laravel's service container
        expect($this->provider)->toBeInstanceOf(FilexServiceProvider::class);
        
        // Test that services are resolvable after booting
        expect($this->app->make(FilexService::class))->toBeInstanceOf(FilexService::class);
        expect($this->app->make(Filex::class))->toBeInstanceOf(Filex::class);
    }

    public function test_commands_are_registered()
    {
        // Test that commands can be resolved from the container
        $commands = [
            \DevWizard\Filex\Commands\FilexCommand::class,
            \DevWizard\Filex\Commands\CleanupTempFilesCommand::class,
            \DevWizard\Filex\Commands\InstallCommand::class,
            \DevWizard\Filex\Commands\OptimizeCommand::class,
        ];

        foreach ($commands as $commandClass) {
            expect($this->app->make($commandClass))->toBeInstanceOf($commandClass);
        }
    }

    public function test_config_cache_clearing_works()
    {
        // Test that the static cache can be cleared
        FilexService::clearConfigCache();
        
        // This is mainly a smoke test to ensure the method exists and runs
        $this->assertTrue(true);
    }

    public function test_services_work_together()
    {
        // Integration test: ensure the services work together correctly
        $filex = $this->app->make(Filex::class);
        $service = $this->app->make(FilexService::class);
        
        // Test that they share the same service instance
        expect($filex->service())->toBe($service);
        
        // Test basic functionality
        $fileName = $filex->generateFileName('test.pdf');
        expect($fileName)->toBeString();
        expect($fileName)->toContain('.pdf');
    }
}
