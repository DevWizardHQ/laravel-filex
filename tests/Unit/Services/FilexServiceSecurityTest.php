<?php

namespace DevWizard\Filex\Tests\Unit\Services;

use DevWizard\Filex\Services\FilexService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Config;
use DevWizard\Filex\Tests\TestCase;

class FilexServiceSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected FilexService $filexService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filexService = new FilexService();
        
        // Clear config cache before each test
        FilexService::clearConfigCache();
    }

    public function test_suspicious_detection_can_be_disabled()
    {
        // Disable suspicious detection
        Config::set('filex.security.suspicious_detection.enabled', false);
        FilexService::clearConfigCache(); // Clear cache after config change
        
        $this->assertFalse($this->filexService->isSuspiciousDetectionEnabled());
    }

    public function test_suspicious_detection_is_enabled_by_default()
    {
        // Default should be enabled
        $this->assertTrue($this->filexService->isSuspiciousDetectionEnabled());
    }

    public function test_suspicious_filename_detection_uses_config_patterns()
    {
        // Set custom patterns
        Config::set('filex.security.suspicious_filename_patterns', [
            '/\.test$/i'
        ]);
        FilexService::clearConfigCache(); // Clear cache after config change

        $reflection = new \ReflectionClass($this->filexService);
        $method = $reflection->getMethod('hasSuspiciousFileName');
        $method->setAccessible(true);

        // Test custom pattern
        $this->assertTrue($method->invoke($this->filexService, 'file.test'));
        
        // Test non-matching file
        $this->assertFalse($method->invoke($this->filexService, 'file.txt'));
    }

    public function test_suspicious_content_detection_uses_config_patterns()
    {
        // Create a temporary file with suspicious content
        $tempPath = tempnam(sys_get_temp_dir(), 'filex_test') . '.php';
        file_put_contents($tempPath, '<?php echo "test"; ?>');

        // Set custom patterns
        Config::set('filex.security.suspicious_content_patterns', [
            '/<\?php/i'
        ]);
        Config::set('filex.security.text_extensions_to_scan', ['php']);
        FilexService::clearConfigCache(); // Clear cache after config change

        $reflection = new \ReflectionClass($this->filexService);
        $method = $reflection->getMethod('containsSuspiciousContent');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($this->filexService, $tempPath));

        unlink($tempPath);
    }

    public function test_text_extensions_scanning_uses_config()
    {
        // Create a temporary file
        $tempPath = tempnam(sys_get_temp_dir(), 'filex_test') . '.custom';
        file_put_contents($tempPath, 'test content');

        // Set custom extensions
        Config::set('filex.security.text_extensions_to_scan', ['custom']);
        FilexService::clearConfigCache(); // Clear cache after config change

        $reflection = new \ReflectionClass($this->filexService);
        $method = $reflection->getMethod('containsSuspiciousContent');
        $method->setAccessible(true);

        // Should scan .custom files now
        $this->assertFalse($method->invoke($this->filexService, $tempPath));

        unlink($tempPath);
    }

    public function test_quarantine_can_be_disabled()
    {
        Config::set('filex.security.suspicious_detection.quarantine_enabled', false);
        FilexService::clearConfigCache(); // Clear cache after config change
        
        // Mock a temp disk
        Storage::fake('local');
        
        $result = $this->filexService->quarantineFile('temp/test.txt', 'test reason');
        
        $this->assertFalse($result);
    }

    public function test_cleanup_quarantine_respects_retention_policy()
    {
        Storage::fake('local');
        
        // Set short retention for testing
        Config::set('filex.security.quarantine.retention_days', 1);
        Config::set('filex.security.quarantine.auto_cleanup', true);
        FilexService::clearConfigCache(); // Clear cache after config change
        
        $result = $this->filexService->cleanupQuarantine();
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('cleaned', $result);
        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('cleaned_count', $result);
        $this->assertArrayHasKey('error_count', $result);
    }

    public function test_cleanup_quarantine_can_be_disabled()
    {
        // Disable auto cleanup
        Config::set('filex.security.quarantine.auto_cleanup', false);
        FilexService::clearConfigCache(); // Clear cache after config change
        
        $result = $this->filexService->cleanupQuarantine();
        
        $this->assertEquals(0, $result['cleaned_count']);
        $this->assertEquals(1, $result['error_count']);
        $this->assertContains('Quarantine auto-cleanup is disabled', $result['errors']);
    }

    public function test_validate_secure_falls_back_to_basic_when_disabled()
    {
        // Disable suspicious detection
        Config::set('filex.security.suspicious_detection.enabled', false);
        FilexService::clearConfigCache(); // Clear cache after config change
        
        Storage::fake('local');
        
        // Create a test file
        $testContent = 'test content';
        Storage::disk('local')->put('temp/test.txt', $testContent);
        
        $result = $this->filexService->validateSecure('temp/test.txt', 'test.txt');
        
        // Should not perform security validation
        $this->assertIsArray($result);
        $this->assertArrayHasKey('valid', $result);
        $this->assertArrayHasKey('message', $result);
    }

    protected function tearDown(): void
    {
        // Clear config cache after each test
        FilexService::clearConfigCache();
        
        // Reset config to defaults
        Config::set('filex.security.suspicious_detection.enabled', true);
        Config::set('filex.security.suspicious_detection.quarantine_enabled', true);
        Config::set('filex.security.quarantine.auto_cleanup', true);
        
        parent::tearDown();
    }
}
