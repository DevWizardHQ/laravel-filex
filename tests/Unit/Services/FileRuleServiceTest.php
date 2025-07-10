<?php

namespace DevWizard\Filex\Tests\Unit\Services;

use DevWizard\Filex\Services\FileRuleService;
use DevWizard\Filex\Tests\TestCase;
use DevWizard\Filex\Rules\ValidFileUpload;

class FileRuleServiceTest extends TestCase
{
    protected FileRuleService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FileRuleService();
    }

    public function test_service_can_be_instantiated()
    {
        expect($this->service)->toBeInstanceOf(FileRuleService::class);
    }

    public function test_for_images_returns_valid_rule()
    {
        $rule = $this->service->forImages(10);
        
        expect($rule)->toBeInstanceOf(ValidFileUpload::class);
        
        // Reflection to check internal state
        $reflection = new \ReflectionClass($rule);
        
        $allowedExtensionsProp = $reflection->getProperty('allowedExtensions');
        $allowedExtensionsProp->setAccessible(true);
        $allowedExtensions = $allowedExtensionsProp->getValue($rule);
        
        $allowedMimeTypesProp = $reflection->getProperty('allowedMimeTypes');
        $allowedMimeTypesProp->setAccessible(true);
        $allowedMimeTypes = $allowedMimeTypesProp->getValue($rule);
        
        $maxFileSizeProp = $reflection->getProperty('maxFileSize');
        $maxFileSizeProp->setAccessible(true);
        $maxFileSize = $maxFileSizeProp->getValue($rule);
        
        // Check that the rule is configured correctly
        expect($allowedExtensions)->toContain('jpg');
        expect($allowedExtensions)->toContain('png');
        expect($allowedMimeTypes)->toContain('image/jpeg');
        expect($allowedMimeTypes)->toContain('image/png');
        expect($maxFileSize)->toBe(10 * 1024 * 1024); // 10MB in bytes
    }

    public function test_for_documents_returns_valid_rule()
    {
        $rule = $this->service->forDocuments(5);
        
        expect($rule)->toBeInstanceOf(ValidFileUpload::class);
        
        // Reflection to check internal state
        $reflection = new \ReflectionClass($rule);
        
        $allowedExtensionsProp = $reflection->getProperty('allowedExtensions');
        $allowedExtensionsProp->setAccessible(true);
        $allowedExtensions = $allowedExtensionsProp->getValue($rule);
        
        $maxFileSizeProp = $reflection->getProperty('maxFileSize');
        $maxFileSizeProp->setAccessible(true);
        $maxFileSize = $maxFileSizeProp->getValue($rule);
        
        // Check that the rule is configured correctly
        expect($allowedExtensions)->toContain('pdf');
        expect($allowedExtensions)->toContain('docx');
        expect($maxFileSize)->toBe(5 * 1024 * 1024); // 5MB in bytes
    }

    public function test_for_archives_returns_valid_rule()
    {
        $rule = $this->service->forArchives();
        
        expect($rule)->toBeInstanceOf(ValidFileUpload::class);
        
        // Reflection to check internal state
        $reflection = new \ReflectionClass($rule);
        
        $allowedExtensionsProp = $reflection->getProperty('allowedExtensions');
        $allowedExtensionsProp->setAccessible(true);
        $allowedExtensions = $allowedExtensionsProp->getValue($rule);
        
        $allowedMimeTypesProp = $reflection->getProperty('allowedMimeTypes');
        $allowedMimeTypesProp->setAccessible(true);
        $allowedMimeTypes = $allowedMimeTypesProp->getValue($rule);
        
        // Check that the rule is configured correctly
        expect($allowedExtensions)->toContain('zip');
        expect($allowedExtensions)->toContain('rar');
        expect($allowedMimeTypes)->toContain('application/zip');
        expect($allowedMimeTypes)->toContain('application/x-rar-compressed');
    }

    public function test_for_audio_returns_valid_rule()
    {
        $rule = $this->service->forAudio(30);
        
        expect($rule)->toBeInstanceOf(ValidFileUpload::class);
        
        // Reflection to check internal state
        $reflection = new \ReflectionClass($rule);
        
        $allowedExtensionsProp = $reflection->getProperty('allowedExtensions');
        $allowedExtensionsProp->setAccessible(true);
        $allowedExtensions = $allowedExtensionsProp->getValue($rule);
        
        $maxFileSizeProp = $reflection->getProperty('maxFileSize');
        $maxFileSizeProp->setAccessible(true);
        $maxFileSize = $maxFileSizeProp->getValue($rule);
        
        // Check that the rule is configured correctly
        expect($allowedExtensions)->toContain('mp3');
        expect($allowedExtensions)->toContain('wav');
        expect($maxFileSize)->toBe(30 * 1024 * 1024); // 30MB in bytes
    }

    public function test_for_video_returns_valid_rule()
    {
        $rule = $this->service->forVideo(200);
        
        expect($rule)->toBeInstanceOf(ValidFileUpload::class);
        
        // Reflection to check internal state
        $reflection = new \ReflectionClass($rule);
        
        $allowedExtensionsProp = $reflection->getProperty('allowedExtensions');
        $allowedExtensionsProp->setAccessible(true);
        $allowedExtensions = $allowedExtensionsProp->getValue($rule);
        
        $maxFileSizeProp = $reflection->getProperty('maxFileSize');
        $maxFileSizeProp->setAccessible(true);
        $maxFileSize = $maxFileSizeProp->getValue($rule);
        
        // Check that the rule is configured correctly
        expect($allowedExtensions)->toContain('mp4');
        expect($allowedExtensions)->toContain('avi');
        expect($maxFileSize)->toBe(200 * 1024 * 1024); // 200MB in bytes
    }

    public function test_for_type_returns_valid_rule()
    {
        $rule = $this->service->forType('csv', 'text/csv', 2);
        
        expect($rule)->toBeInstanceOf(ValidFileUpload::class);
        
        // Reflection to check internal state
        $reflection = new \ReflectionClass($rule);
        
        $allowedExtensionsProp = $reflection->getProperty('allowedExtensions');
        $allowedExtensionsProp->setAccessible(true);
        $allowedExtensions = $allowedExtensionsProp->getValue($rule);
        
        $allowedMimeTypesProp = $reflection->getProperty('allowedMimeTypes');
        $allowedMimeTypesProp->setAccessible(true);
        $allowedMimeTypes = $allowedMimeTypesProp->getValue($rule);
        
        $maxFileSizeProp = $reflection->getProperty('maxFileSize');
        $maxFileSizeProp->setAccessible(true);
        $maxFileSize = $maxFileSizeProp->getValue($rule);
        
        // Check that the rule is configured correctly
        expect($allowedExtensions)->toContain('csv');
        expect($allowedMimeTypes)->toContain('text/csv');
        expect($maxFileSize)->toBe(2 * 1024 * 1024); // 2MB in bytes
    }

    public function test_custom_returns_valid_rule()
    {
        $extensions = ['svg', 'eps'];
        $mimeTypes = ['image/svg+xml', 'application/postscript'];
        
        $rule = $this->service->custom($extensions, $mimeTypes, 15, true);
        
        expect($rule)->toBeInstanceOf(ValidFileUpload::class);
        
        // Reflection to check internal state
        $reflection = new \ReflectionClass($rule);
        
        $allowedExtensionsProp = $reflection->getProperty('allowedExtensions');
        $allowedExtensionsProp->setAccessible(true);
        $allowedExtensions = $allowedExtensionsProp->getValue($rule);
        
        $allowedMimeTypesProp = $reflection->getProperty('allowedMimeTypes');
        $allowedMimeTypesProp->setAccessible(true);
        $allowedMimeTypes = $allowedMimeTypesProp->getValue($rule);
        
        $strictProp = $reflection->getProperty('strict');
        $strictProp->setAccessible(true);
        $strict = $strictProp->getValue($rule);
        
        // Check that the rule is configured correctly
        expect($allowedExtensions)->toBe($extensions);
        expect($allowedMimeTypes)->toBe($mimeTypes);
        expect($strict)->toBeTrue();
    }

    public function test_lenient_returns_non_strict_rule()
    {
        $extensions = ['txt', 'log'];
        $mimeTypes = ['text/plain'];
        
        $rule = $this->service->lenient($extensions, $mimeTypes, 1);
        
        expect($rule)->toBeInstanceOf(ValidFileUpload::class);
        
        // Reflection to check internal state
        $reflection = new \ReflectionClass($rule);
        
        $strictProp = $reflection->getProperty('strict');
        $strictProp->setAccessible(true);
        $strict = $strictProp->getValue($rule);
        
        // Check that the rule is configured correctly
        expect($strict)->toBeFalse();
    }

    public function test_for_web_returns_valid_rule()
    {
        $rule = $this->service->forWeb(5);
        
        expect($rule)->toBeInstanceOf(ValidFileUpload::class);
        
        // Reflection to check internal state
        $reflection = new \ReflectionClass($rule);
        
        $allowedExtensionsProp = $reflection->getProperty('allowedExtensions');
        $allowedExtensionsProp->setAccessible(true);
        $allowedExtensions = $allowedExtensionsProp->getValue($rule);
        
        $strictProp = $reflection->getProperty('strict');
        $strictProp->setAccessible(true);
        $strict = $strictProp->getValue($rule);
        
        // Check that the rule is configured correctly
        expect($allowedExtensions)->toContain('html');
        expect($allowedExtensions)->toContain('css');
        expect($allowedExtensions)->toContain('js');
        expect($strict)->toBeFalse(); // Web files should use lenient validation
    }
}
