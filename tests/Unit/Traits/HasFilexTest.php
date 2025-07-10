<?php

namespace DevWizard\Filex\Tests\Unit\Traits;

use DevWizard\Filex\Services\FilexService;
use DevWizard\Filex\Tests\TestCase;
use DevWizard\Filex\Traits\HasFilex;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Mockery;

class HasFilexTest extends TestCase
{
    /**
     * Test class that uses the HasFilex trait
     */
    private $testClass;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a test class that uses the HasFilex trait
        $this->testClass = new class() {
            use HasFilex;
            
            // Expose protected methods for testing
            public function getFilexServicePublic()
            {
                return $this->getFilexService();
            }
            
            public function processFilesPublic($request, $fieldName, $targetDir, $disk = null, $required = false)
            {
                return $this->processFiles($request, $fieldName, $targetDir, $disk, $required);
            }
            
            public function processSingleFilePublic($request, $fieldName, $targetDir, $disk = null, $required = false)
            {
                return $this->processSingleFile($request, $fieldName, $targetDir, $disk, $required);
            }
            
            public function validateFilesPublic($tempPaths)
            {
                return $this->validateFiles($tempPaths);
            }
        };
        
        // Create a real FilexService instance for the trait
        $this->app->singleton(FilexService::class, function() {
            return $this->createPartialMock(FilexService::class, [
                'getTempMeta', 
                'validateTemp', 
                'moveFilesBulk'
            ]);
        });
    }
    
    public function test_get_filex_service_returns_service_instance()
    {
        $service = $this->testClass->getFilexServicePublic();
        expect($service)->toBeInstanceOf(FilexService::class);
    }
    
    public function test_validate_files_returns_valid_results()
    {
        $mockFilexService = $this->mock(FilexService::class);
        
        // Setup the mock service to validate two files - one valid, one invalid
        $mockFilexService->shouldReceive('getTempMeta')
            ->with('temp/valid-file.jpg')
            ->andReturn(['original_name' => 'valid-file.jpg']);
            
        $mockFilexService->shouldReceive('getTempMeta')
            ->with('temp/invalid-file.jpg')
            ->andReturn(null);
        
        $results = $this->testClass->validateFilesPublic([
            'temp/valid-file.jpg',
            'temp/invalid-file.jpg'
        ]);
        
        expect($results)->toBeArray();
        expect($results['valid'])->toContain('temp/valid-file.jpg');
        expect($results['invalid'])->toContain('temp/invalid-file.jpg');
        expect($results['total'])->toBe(2);
        expect($results['valid_count'])->toBe(1);
        expect($results['invalid_count'])->toBe(1);
    }
    
    public function test_process_files_handles_empty_input()
    {
        $request = new Request();
        $request->merge(['files' => []]);
        
        $result = $this->testClass->processFilesPublic($request, 'files', 'uploads');
        expect($result)->toBeArray();
        expect($result)->toBeEmpty();
    }
    
    public function test_process_files_handles_single_file()
    {
        $mockFilexService = $this->mock(FilexService::class);
        
        $mockFilexService->shouldReceive('getTempMeta')
            ->andReturn(['original_name' => 'test.jpg']);
            
        $mockFilexService->shouldReceive('validateTemp')
            ->andReturn(['valid' => true, 'message' => 'Valid file']);
            
        $mockFilexService->shouldReceive('moveFilesBulk')
            ->once()
            ->andReturn([
                [
                    'success' => true,
                    'tempPath' => 'temp/file1.jpg',
                    'finalPath' => 'uploads/file1_123456.jpg'
                ]
            ]);
        
        $request = new Request();
        $request->merge(['file' => 'temp/file1.jpg']);
        
        $result = $this->testClass->processFilesPublic($request, 'file', 'uploads');
        expect($result)->toBeArray();
        expect($result)->toHaveCount(1);
        expect($result[0])->toBe('uploads/file1_123456.jpg');
    }
    
    public function test_process_files_handles_multiple_files()
    {
        $mockFilexService = $this->mock(FilexService::class);
        
        $mockFilexService->shouldReceive('getTempMeta')
            ->andReturn(['original_name' => 'test.jpg']);
            
        $mockFilexService->shouldReceive('validateTemp')
            ->andReturn(['valid' => true, 'message' => 'Valid file']);
            
        $mockFilexService->shouldReceive('moveFilesBulk')
            ->once()
            ->andReturn([
                [
                    'success' => true,
                    'tempPath' => 'temp/file1.jpg',
                    'finalPath' => 'uploads/file1_123456.jpg'
                ],
                [
                    'success' => true,
                    'tempPath' => 'temp/file2.jpg',
                    'finalPath' => 'uploads/file2_123456.jpg'
                ]
            ]);
        
        $request = new Request();
        $request->merge(['files' => ['temp/file1.jpg', 'temp/file2.jpg']]);
        
        $result = $this->testClass->processFilesPublic($request, 'files', 'uploads');
        expect($result)->toBeArray();
        expect($result)->toHaveCount(2);
        expect($result)->toContain('uploads/file1_123456.jpg');
        expect($result)->toContain('uploads/file2_123456.jpg');
    }
    
    public function test_process_single_file_returns_first_path()
    {
        $mockFilexService = $this->mock(FilexService::class);
        
        $mockFilexService->shouldReceive('getTempMeta')
            ->andReturn(['original_name' => 'test.jpg']);
            
        $mockFilexService->shouldReceive('validateTemp')
            ->andReturn(['valid' => true, 'message' => 'Valid file']);
            
        $mockFilexService->shouldReceive('moveFilesBulk')
            ->once()
            ->andReturn([
                [
                    'success' => true,
                    'tempPath' => 'temp/file1.jpg',
                    'finalPath' => 'uploads/file1_123456.jpg'
                ]
            ]);
        
        $request = new Request();
        $request->merge(['avatar' => 'temp/file1.jpg']);
        
        $result = $this->testClass->processSingleFilePublic($request, 'avatar', 'uploads');
        expect($result)->toBeString();
        expect($result)->toBe('uploads/file1_123456.jpg');
    }
    
    public function test_process_single_file_returns_null_when_empty()
    {
        $request = new Request();
        $request->merge(['avatar' => '']);
        
        $result = $this->testClass->processSingleFilePublic($request, 'avatar', 'uploads');
        expect($result)->toBeNull();
    }
    
    public function test_process_files_logs_failures()
    {
        $mockFilexService = $this->mock(FilexService::class);
        
        $mockFilexService->shouldReceive('getTempMeta')
            ->andReturn(['original_name' => 'test.jpg']);
            
        $mockFilexService->shouldReceive('validateTemp')
            ->andReturn(['valid' => true, 'message' => 'Valid file']);
            
        $mockFilexService->shouldReceive('moveFilesBulk')
            ->once()
            ->andReturn([
                [
                    'success' => true,
                    'tempPath' => 'temp/file1.jpg',
                    'finalPath' => 'uploads/file1_123456.jpg'
                ],
                [
                    'success' => false,
                    'tempPath' => 'temp/file2.jpg',
                    'message' => 'Failed to move file'
                ]
            ]);
        
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === 'Some files failed to process' && 
                       $context['field'] === 'files' &&
                       is_array($context['failures']);
            });
        
        $request = new Request();
        $request->merge(['files' => ['temp/file1.jpg', 'temp/file2.jpg']]);
        
        $result = $this->testClass->processFilesPublic($request, 'files', 'uploads');
        expect($result)->toBeArray();
        expect($result)->toHaveCount(1);
        expect($result[0])->toBe('uploads/file1_123456.jpg');
    }
    
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
