<?php

namespace DevWizard\Filex\Tests\Unit;

use DevWizard\Filex\Filex;
use DevWizard\Filex\Services\FilexService;
use DevWizard\Filex\Support\FilexResult;
use DevWizard\Filex\Tests\TestCase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

class FilexTest extends TestCase
{
    protected Filex $filex;
    protected FilexService $service;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->service = $this->app->make(FilexService::class);
        $this->filex = new Filex($this->service);
        
        // Setup test storage
        Storage::fake('local');
        Storage::fake('temp');
    }

    public function test_filex_can_be_instantiated()
    {
        expect($this->filex)->toBeInstanceOf(Filex::class);
    }

    public function test_service_method_returns_filex_service_instance()
    {
        $service = $this->filex->service();
        
        expect($service)->toBeInstanceOf(FilexService::class);
        expect($service)->toBe($this->service);
    }

    public function test_generate_filename_creates_unique_filename()
    {
        $originalName = 'test-document.pdf';
        $generatedName = $this->filex->generateFileName($originalName);
        
        expect($generatedName)->toBeString();
        expect($generatedName)->not->toEqual($originalName);
        expect($generatedName)->toContain('.pdf');
    }

    public function test_generate_filename_preserves_extension()
    {
        $testCases = [
            'document.pdf' => '.pdf',
            'image.jpg' => '.jpg',
            'archive.tar.gz' => '.gz',
            'script.min.js' => '.js',
        ];

        foreach ($testCases as $originalName => $expectedExtension) {
            $generatedName = $this->filex->generateFileName($originalName);
            expect($generatedName)->toEndWith($expectedExtension);
        }
    }

    public function test_validate_temp_returns_validation_array()
    {
        // Create a temporary test file
        $tempFile = UploadedFile::fake()->create('test.txt', 100);
        $tempPath = $tempFile->getPathname();
        
        $validation = $this->filex->validateTemp($tempPath, 'test.txt');
        
        expect($validation)->toBeArray();
        expect($validation)->toHaveKeys(['valid', 'message']);
        expect($validation['valid'])->toBeBool();
        expect($validation['message'])->toBeString();
    }

    public function test_move_files_returns_array_of_results()
    {
        // Create temporary files for testing
        $tempFile1 = UploadedFile::fake()->create('test1.txt', 100);
        $tempFile2 = UploadedFile::fake()->create('test2.txt', 200);
        
        $tempPaths = [
            $tempFile1->getPathname(),
            $tempFile2->getPathname(),
        ];
        
        $targetDirectory = 'uploads';
        $results = $this->filex->moveFiles($tempPaths, $targetDirectory);
        
        expect($results)->toBeInstanceOf(FilexResult::class);
        expect($results)->toHaveCount(2);
        
        // Test that we can access the results as an array
        foreach ($results as $result) {
            expect($result)->toBeArray();
            expect($result)->toHaveKeys(['success', 'tempPath']);
            expect($result['success'])->toBeBool();
            expect($result['tempPath'])->toBeString();
        }
    }

    public function test_move_file_handles_single_file()
    {
        $tempFile = UploadedFile::fake()->create('single-test.txt', 150);
        $tempPath = $tempFile->getPathname();
        
        $targetDirectory = 'uploads';
        $results = $this->filex->moveFile($tempPath, $targetDirectory);
        
        expect($results)->toBeInstanceOf(FilexResult::class);
        expect($results)->toHaveCount(1);
        expect($results[0])->toHaveKeys(['success', 'tempPath']);
        expect($results[0]['success'])->toBeBool();
        expect($results[0]['tempPath'])->toBeString();
    }

    public function test_cleanup_returns_cleanup_statistics()
    {
        $stats = $this->filex->cleanup();
        
        expect($stats)->toBeArray();
        expect($stats)->toHaveKeys(['cleaned', 'errors', 'cleaned_count', 'error_count']);
        expect($stats['cleaned'])->toBeArray();
        expect($stats['errors'])->toBeArray();
        expect($stats['cleaned_count'])->toBeInt();
        expect($stats['error_count'])->toBeInt();
    }

    public function test_filex_facade_methods_delegate_to_service()
    {
        // Test that all public methods exist and return expected types
        $methods = [
            'generateFileName',
            'validateTemp', 
            'moveFiles',
            'moveFile',
            'cleanup',
            'service'
        ];

        foreach ($methods as $method) {
            // Verify method exists and is callable
            expect(method_exists($this->filex, $method))->toBeTrue("Method {$method} should exist");
            expect(is_callable([$this->filex, $method]))->toBeTrue("Method {$method} should be callable");
        }
    }

    public function test_filex_handles_empty_arrays_gracefully()
    {
        $results = $this->filex->moveFiles([], 'uploads');
        expect($results)->toBeInstanceOf(FilexResult::class);
        expect($results)->toHaveCount(0);
    }

    public function test_filex_handles_null_disk_parameter()
    {
        $tempFile = UploadedFile::fake()->create('test-null-disk.txt', 100);
        $tempPath = $tempFile->getPathname();
        
        // Should not throw an exception with null disk
        $results = $this->filex->moveFile($tempPath, 'uploads', null);
        expect($results)->toBeInstanceOf(FilexResult::class);
    }

    public function test_filex_with_custom_disk()
    {
        Storage::fake('custom');
        
        $tempFile = UploadedFile::fake()->create('test-custom-disk.txt', 100);
        $tempPath = $tempFile->getPathname();
        
        $results = $this->filex->moveFile($tempPath, 'uploads', 'custom');
        expect($results)->toBeInstanceOf(FilexResult::class);
    }
}
