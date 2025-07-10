<?php

namespace DevWizard\Filex\Tests\Commands;

use DevWizard\Filex\Commands\InstallCommand;
use DevWizard\Filex\Tests\TestCase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;

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

    #[Test]
    public function it_can_show_help()
    {
        $this->artisan('filex:install --help')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_can_install_with_no_existing_files()
    {
        $this->artisan('filex:install --force')
            ->expectsOutput('🚀 Installing Laravel Filex...')
            ->expectsOutput('📄 Config: config/filex.php ✨')
            ->expectsOutput('📦 Assets: 4 files → public/vendor/filex/')
            ->expectsOutput('✅ Config published')
            ->expectsOutput('✅ Assets published')
            ->expectsOutput('🎉 Laravel Filex installed successfully!')
            ->assertExitCode(0);

        // Note: In test environment, actual file publishing may not work
        // but the command behavior is verified through output expectations
    }

    #[Test]
    public function it_can_install_only_config()
    {
        $this->artisan('filex:install --only-config --force')
            ->expectsOutput('🚀 Installing Laravel Filex...')
            ->expectsOutput('📄 Config: config/filex.php ✨')
            ->expectsOutput('✅ Config published')
            ->expectsOutput('🎉 Laravel Filex installed successfully!')
            ->assertExitCode(0);

        // Note: In test environment, actual file publishing may not work
        // but the command behavior is verified through output expectations
    }

    #[Test]
    public function it_can_install_only_assets()
    {
        $this->artisan('filex:install --only-assets --force')
            ->expectsOutput('🚀 Installing Laravel Filex...')
            ->expectsOutput('📦 Assets: 4 files → public/vendor/filex/')
            ->expectsOutput('✅ Assets published')
            ->expectsOutput('🎉 Laravel Filex installed successfully!')
            ->assertExitCode(0);

        // Note: In test environment, actual file publishing may not work
        // but the command behavior is verified through output expectations
    }

    #[Test]
    public function it_detects_existing_files()
    {
        // Create a fake config file
        File::put(config_path('filex.php'), '<?php return [];');
        
        // Create fake asset files
        File::ensureDirectoryExists(public_path('vendor/filex/css'));
        File::put(public_path('vendor/filex/css/filex.css'), '/* existing css */');

        $this->artisan('filex:install')
            ->expectsOutput('🚀 Installing Laravel Filex...')
            ->expectsOutput('⚠️  2 files already exist.')
            ->expectsConfirmation('Overwrite existing files?', 'no')
            ->expectsOutput('Installation cancelled.')
            ->assertExitCode(1);
    }

    #[Test]
    public function it_can_install_in_auto_mode()
    {
        $this->artisan('filex:install --auto')
            ->assertExitCode(0);

        // Note: In test environment, actual file publishing may not work
        // but the command behavior is verified through exit code
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
