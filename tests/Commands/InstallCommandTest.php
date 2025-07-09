<?php

namespace DevWizard\Filex\Tests\Commands;

use DevWizard\Filex\Commands\InstallCommand;
use DevWizard\Filex\Tests\TestCase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;

class InstallCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Clean up any existing published files
        $this->cleanupPublishedFiles();
    }

    protected function tearDown(): void
    {
        // Clean up after tests
        $this->cleanupPublishedFiles();
        
        parent::tearDown();
    }

    /** @test */
    public function it_can_show_help()
    {
        $this->artisan('filex:install --help')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_can_install_with_no_existing_files()
    {
        $this->artisan('filex:install --force')
            ->expectsOutput('🚀 Installing Laravel Filex...')
            ->expectsOutput('🎉 Laravel Filex installation completed!')
            ->assertExitCode(0);

        // Check that config file was published
        $this->assertTrue(File::exists(config_path('filex.php')));
        
        // Check that asset files were published
        $this->assertTrue(File::exists(public_path('vendor/filex/css/dropzone.min.css')));
        $this->assertTrue(File::exists(public_path('vendor/filex/css/filex.css')));
        $this->assertTrue(File::exists(public_path('vendor/filex/js/dropzone.min.js')));
        $this->assertTrue(File::exists(public_path('vendor/filex/js/filex.js')));
    }

    /** @test */
    public function it_can_install_only_config()
    {
        $this->artisan('filex:install --only-config --force')
            ->assertExitCode(0);

        // Check that config file was published
        $this->assertTrue(File::exists(config_path('filex.php')));
        
        // Check that asset files were NOT published
        $this->assertFalse(File::exists(public_path('vendor/filex/css/dropzone.min.css')));
    }

    /** @test */
    public function it_can_install_only_assets()
    {
        $this->artisan('filex:install --only-assets --force')
            ->assertExitCode(0);

        // Check that config file was NOT published
        $this->assertFalse(File::exists(config_path('filex.php')));
        
        // Check that asset files were published
        $this->assertTrue(File::exists(public_path('vendor/filex/css/dropzone.min.css')));
        $this->assertTrue(File::exists(public_path('vendor/filex/css/filex.css')));
        $this->assertTrue(File::exists(public_path('vendor/filex/js/dropzone.min.js')));
        $this->assertTrue(File::exists(public_path('vendor/filex/js/filex.js')));
    }

    /** @test */
    public function it_detects_existing_files()
    {
        // Create a fake config file
        File::put(config_path('filex.php'), '<?php return [];');
        
        // Create fake asset files
        File::ensureDirectoryExists(public_path('vendor/filex/css'));
        File::put(public_path('vendor/filex/css/filex.css'), '/* existing css */');

        $this->artisan('filex:install')
            ->expectsOutput('⚠️  The following files already exist:')
            ->expectsConfirmation('🤔 Do you want to overwrite these files?', 'no')
            ->expectsOutput('❌ Installation cancelled by user.')
            ->assertExitCode(1);
    }

    /** @test */
    public function it_can_install_in_auto_mode()
    {
        $this->artisan('filex:install --auto')
            ->assertExitCode(0);

        // Check that config file was published
        $this->assertTrue(File::exists(config_path('filex.php')));
        
        // Check that asset files were published
        $this->assertTrue(File::exists(public_path('vendor/filex/css/dropzone.min.css')));
        $this->assertTrue(File::exists(public_path('vendor/filex/css/filex.css')));
        $this->assertTrue(File::exists(public_path('vendor/filex/js/dropzone.min.js')));
        $this->assertTrue(File::exists(public_path('vendor/filex/js/filex.js')));
    }

    /**
     * Clean up published files
     */
    protected function cleanupPublishedFiles(): void
    {
        // Remove config file
        if (File::exists(config_path('filex.php'))) {
            File::delete(config_path('filex.php'));
        }

        // Remove asset directory
        if (File::exists(public_path('vendor/filex'))) {
            File::deleteDirectory(public_path('vendor/filex'));
        }
    }
}
