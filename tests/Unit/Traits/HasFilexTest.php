<?php

namespace DevWizard\Filex\Tests\Unit\Traits;

use DevWizard\Filex\Facades\Filex;
use DevWizard\Filex\Services\FilexService;
use DevWizard\Filex\Support\FilexResult;
use DevWizard\Filex\Tests\TestCase;
use DevWizard\Filex\Traits\HasFilex;
use Illuminate\Http\Request;
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
        $this->testClass = new class
        {
            use HasFilex;

            // Expose protected methods for testing
            public function moveFilePublic($request, $fieldName, $directory, $disk = null)
            {
                return $this->moveFile($request, $fieldName, $directory, $disk);
            }

            public function moveFilesPublic($request, $fieldName, $directory, $disk = null)
            {
                return $this->moveFiles($request, $fieldName, $directory, $disk);
            }

            public function getFileValidationRulesPublic($fieldName, $required = false)
            {
                return $this->getFileValidationRules($fieldName, $required);
            }

            public function getFilesValidationRulesPublic($fieldName, $required = false)
            {
                return $this->getFilesValidationRules($fieldName, $required);
            }

            public function cleanupTempFilesPublic($tempPaths)
            {
                return $this->cleanupTempFiles($tempPaths);
            }
        };
    }

    public function test_move_file_returns_null_when_no_input()
    {
        $request = new Request;

        $result = $this->testClass->moveFilePublic($request, 'avatar', 'avatars');

        expect($result)->toBeNull();
    }

    public function test_move_file_returns_file_path_when_successful()
    {
        // Mock the Filex facade
        $mockResult = $this->mock(FilexResult::class);
        $mockResult->shouldReceive('getPath')->once()->andReturn('avatars/uploaded-file.jpg');

        Filex::shouldReceive('moveFile')
            ->once()
            ->with('temp/test-file.jpg', 'avatars', null)
            ->andReturn($mockResult);

        $request = new Request;
        $request->merge(['avatar' => 'temp/test-file.jpg']);

        $result = $this->testClass->moveFilePublic($request, 'avatar', 'avatars');

        expect($result)->toBe('avatars/uploaded-file.jpg');
    }

    public function test_move_files_returns_empty_array_when_no_input()
    {
        $request = new Request;

        $result = $this->testClass->moveFilesPublic($request, 'documents', 'uploads');

        expect($result)->toBeArray();
        expect($result)->toBeEmpty();
    }

    public function test_move_files_returns_empty_array_when_input_not_array()
    {
        $request = new Request;
        $request->merge(['documents' => 'not-an-array']);

        $result = $this->testClass->moveFilesPublic($request, 'documents', 'uploads');

        expect($result)->toBeArray();
        expect($result)->toBeEmpty();
    }

    public function test_move_files_returns_file_paths_when_successful()
    {
        // Mock the Filex facade
        $mockResult = $this->mock(FilexResult::class);
        $mockResult->shouldReceive('getPaths')
            ->once()
            ->andReturn(['uploads/file1.jpg', 'uploads/file2.pdf']);

        Filex::shouldReceive('moveFiles')
            ->once()
            ->with(['temp/file1.jpg', 'temp/file2.pdf'], 'uploads', null)
            ->andReturn($mockResult);

        $request = new Request;
        $request->merge(['documents' => ['temp/file1.jpg', 'temp/file2.pdf']]);

        $result = $this->testClass->moveFilesPublic($request, 'documents', 'uploads');

        expect($result)->toBe(['uploads/file1.jpg', 'uploads/file2.pdf']);
    }

    public function test_get_file_validation_rules_returns_nullable_rules_by_default()
    {
        $rules = $this->testClass->getFileValidationRulesPublic('avatar');

        expect($rules)->toBe([
            'avatar' => ['nullable', 'string', 'starts_with:temp/'],
        ]);
    }

    public function test_get_file_validation_rules_returns_required_rules_when_specified()
    {
        $rules = $this->testClass->getFileValidationRulesPublic('avatar', true);

        expect($rules)->toBe([
            'avatar' => ['required', 'string', 'starts_with:temp/'],
        ]);
    }

    public function test_get_files_validation_rules_returns_nullable_rules_by_default()
    {
        $rules = $this->testClass->getFilesValidationRulesPublic('documents');

        expect($rules)->toBe([
            'documents' => ['nullable', 'array'],
            'documents.*' => ['string', 'starts_with:temp/'],
        ]);
    }

    public function test_get_files_validation_rules_returns_required_rules_when_specified()
    {
        $rules = $this->testClass->getFilesValidationRulesPublic('documents', true);

        expect($rules)->toBe([
            'documents' => ['required', 'array'],
            'documents.*' => ['string', 'starts_with:temp/'],
        ]);
    }

    public function test_cleanup_temp_files_returns_count_of_cleaned_files()
    {
        // Mock the Filex service
        $mockService = $this->mock(FilexService::class);
        $mockService->shouldReceive('deleteTemp')
            ->with('temp/file1.jpg')
            ->once()
            ->andReturn(true);
        $mockService->shouldReceive('deleteTemp')
            ->with('temp/file2.pdf')
            ->once()
            ->andReturn(false);

        Filex::shouldReceive('service')->twice()->andReturn($mockService);

        $result = $this->testClass->cleanupTempFilesPublic(['temp/file1.jpg', 'temp/file2.pdf']);

        expect($result)->toBe(1); // Only one file was successfully cleaned
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
