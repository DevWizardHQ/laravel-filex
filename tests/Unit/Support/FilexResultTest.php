<?php

namespace DevWizard\Filex\Tests\Unit\Support;

use DevWizard\Filex\Support\FilexResult;
use DevWizard\Filex\Tests\TestCase;

class FilexResultTest extends TestCase
{
    public function test_get_path_returns_first_successful_final_path()
    {
        $results = [
            [
                'success' => true,
                'tempPath' => 'temp/file1.txt',
                'finalPath' => 'uploads/file1_12345.txt',
                'metadata' => ['original_name' => 'file1.txt'],
            ],
            [
                'success' => true,
                'tempPath' => 'temp/file2.txt',
                'finalPath' => 'uploads/file2_67890.txt',
                'metadata' => ['original_name' => 'file2.txt'],
            ],
        ];

        $filexResult = new FilexResult($results);
        expect($filexResult->getPath())->toBe('uploads/file1_12345.txt');
    }

    public function test_get_path_returns_null_when_no_successful_operations()
    {
        $results = [
            [
                'success' => false,
                'tempPath' => 'temp/file1.txt',
                'message' => 'File not found',
            ],
        ];

        $filexResult = new FilexResult($results);
        expect($filexResult->getPath())->toBeNull();
    }

    public function test_get_paths_returns_all_successful_final_paths()
    {
        $results = [
            [
                'success' => true,
                'tempPath' => 'temp/file1.txt',
                'finalPath' => 'uploads/file1_12345.txt',
            ],
            [
                'success' => false,
                'tempPath' => 'temp/file2.txt',
                'message' => 'Failed to move',
            ],
            [
                'success' => true,
                'tempPath' => 'temp/file3.txt',
                'finalPath' => 'uploads/file3_67890.txt',
            ],
        ];

        $filexResult = new FilexResult($results);
        $paths = $filexResult->getPaths();

        expect($paths)->toHaveCount(2);
        expect($paths)->toContain('uploads/file1_12345.txt');
        expect($paths)->toContain('uploads/file3_67890.txt');
    }

    public function test_is_success_returns_true_when_any_operation_successful()
    {
        $results = [
            [
                'success' => false,
                'tempPath' => 'temp/file1.txt',
                'message' => 'Failed',
            ],
            [
                'success' => true,
                'tempPath' => 'temp/file2.txt',
                'finalPath' => 'uploads/file2_12345.txt',
            ],
        ];

        $filexResult = new FilexResult($results);
        expect($filexResult->isSuccess())->toBeTrue();
    }

    public function test_is_success_returns_false_when_no_operations_successful()
    {
        $results = [
            [
                'success' => false,
                'tempPath' => 'temp/file1.txt',
                'message' => 'Failed',
            ],
        ];

        $filexResult = new FilexResult($results);
        expect($filexResult->isSuccess())->toBeFalse();
    }

    public function test_is_all_success_returns_true_when_all_operations_successful()
    {
        $results = [
            [
                'success' => true,
                'tempPath' => 'temp/file1.txt',
                'finalPath' => 'uploads/file1_12345.txt',
            ],
            [
                'success' => true,
                'tempPath' => 'temp/file2.txt',
                'finalPath' => 'uploads/file2_67890.txt',
            ],
        ];

        $filexResult = new FilexResult($results);
        expect($filexResult->isAllSuccess())->toBeTrue();
    }

    public function test_is_all_success_returns_false_when_some_operations_fail()
    {
        $results = [
            [
                'success' => true,
                'tempPath' => 'temp/file1.txt',
                'finalPath' => 'uploads/file1_12345.txt',
            ],
            [
                'success' => false,
                'tempPath' => 'temp/file2.txt',
                'message' => 'Failed',
            ],
        ];

        $filexResult = new FilexResult($results);
        expect($filexResult->isAllSuccess())->toBeFalse();
    }

    public function test_get_successful_returns_only_successful_results()
    {
        $results = [
            [
                'success' => true,
                'tempPath' => 'temp/file1.txt',
                'finalPath' => 'uploads/file1_12345.txt',
            ],
            [
                'success' => false,
                'tempPath' => 'temp/file2.txt',
                'message' => 'Failed',
            ],
        ];

        $filexResult = new FilexResult($results);
        $successful = $filexResult->getSuccessful();

        expect($successful)->toHaveCount(1);
        expect($successful[0]['tempPath'])->toBe('temp/file1.txt');
    }

    public function test_get_failed_returns_only_failed_results()
    {
        $results = [
            [
                'success' => true,
                'tempPath' => 'temp/file1.txt',
                'finalPath' => 'uploads/file1_12345.txt',
            ],
            [
                'success' => false,
                'tempPath' => 'temp/file2.txt',
                'message' => 'Failed',
            ],
        ];

        $filexResult = new FilexResult($results);
        $failed = $filexResult->getFailed();

        expect($failed)->toHaveCount(1);
        expect($failed[0]['tempPath'])->toBe('temp/file2.txt');
    }

    public function test_get_error_message_returns_first_error_message()
    {
        $results = [
            [
                'success' => false,
                'tempPath' => 'temp/file1.txt',
                'message' => 'First error',
            ],
            [
                'success' => false,
                'tempPath' => 'temp/file2.txt',
                'message' => 'Second error',
            ],
        ];

        $filexResult = new FilexResult($results);
        expect($filexResult->getErrorMessage())->toBe('First error');
    }

    public function test_get_error_messages_returns_all_error_messages()
    {
        $results = [
            [
                'success' => false,
                'tempPath' => 'temp/file1.txt',
                'message' => 'First error',
            ],
            [
                'success' => true,
                'tempPath' => 'temp/file2.txt',
                'finalPath' => 'uploads/file2_12345.txt',
            ],
            [
                'success' => false,
                'tempPath' => 'temp/file3.txt',
                'message' => 'Second error',
            ],
        ];

        $filexResult = new FilexResult($results);
        $errorMessages = $filexResult->getErrorMessages();

        expect($errorMessages)->toHaveCount(2);
        expect($errorMessages)->toContain('First error');
        expect($errorMessages)->toContain('Second error');
    }

    public function test_get_metadata_returns_metadata_from_first_successful_operation()
    {
        $results = [
            [
                'success' => true,
                'tempPath' => 'temp/file1.txt',
                'finalPath' => 'uploads/file1_12345.txt',
                'metadata' => ['original_name' => 'file1.txt', 'size' => 1024],
            ],
        ];

        $filexResult = new FilexResult($results);
        $metadata = $filexResult->getMetadata();

        expect($metadata)->toBe(['original_name' => 'file1.txt', 'size' => 1024]);
    }

    public function test_get_temp_path_returns_temp_path_from_first_successful_operation()
    {
        $results = [
            [
                'success' => true,
                'tempPath' => 'temp/file1.txt',
                'finalPath' => 'uploads/file1_12345.txt',
            ],
        ];

        $filexResult = new FilexResult($results);
        expect($filexResult->getTempPath())->toBe('temp/file1.txt');
    }

    public function test_get_success_count_returns_correct_count()
    {
        $results = [
            ['success' => true, 'tempPath' => 'temp/file1.txt'],
            ['success' => false, 'tempPath' => 'temp/file2.txt'],
            ['success' => true, 'tempPath' => 'temp/file3.txt'],
        ];

        $filexResult = new FilexResult($results);
        expect($filexResult->getSuccessCount())->toBe(2);
    }

    public function test_get_failed_count_returns_correct_count()
    {
        $results = [
            ['success' => true, 'tempPath' => 'temp/file1.txt'],
            ['success' => false, 'tempPath' => 'temp/file2.txt'],
            ['success' => false, 'tempPath' => 'temp/file3.txt'],
        ];

        $filexResult = new FilexResult($results);
        expect($filexResult->getFailedCount())->toBe(2);
    }

    public function test_to_array_returns_original_results()
    {
        $results = [
            ['success' => true, 'tempPath' => 'temp/file1.txt'],
        ];

        $filexResult = new FilexResult($results);
        expect($filexResult->toArray())->toBe($results);
    }

    public function test_to_json_returns_json_string()
    {
        $results = [
            ['success' => true, 'tempPath' => 'temp/file1.txt'],
        ];

        $filexResult = new FilexResult($results);
        $json = $filexResult->toJson();

        expect($json)->toBeString();
        expect(json_decode($json, true))->toBe($results);
    }

    public function test_array_access_works()
    {
        $results = [
            ['success' => true, 'tempPath' => 'temp/file1.txt'],
            ['success' => false, 'tempPath' => 'temp/file2.txt'],
        ];

        $filexResult = new FilexResult($results);

        expect($filexResult[0])->toBe($results[0]);
        expect($filexResult[1])->toBe($results[1]);
        expect(isset($filexResult[0]))->toBeTrue();
        expect(isset($filexResult[2]))->toBeFalse();
    }

    public function test_iterator_works()
    {
        $results = [
            ['success' => true, 'tempPath' => 'temp/file1.txt'],
            ['success' => false, 'tempPath' => 'temp/file2.txt'],
        ];

        $filexResult = new FilexResult($results);
        $iterated = [];

        foreach ($filexResult as $key => $value) {
            $iterated[$key] = $value;
        }

        expect($iterated)->toBe($results);
    }

    public function test_countable_works()
    {
        $results = [
            ['success' => true, 'tempPath' => 'temp/file1.txt'],
            ['success' => false, 'tempPath' => 'temp/file2.txt'],
        ];

        $filexResult = new FilexResult($results);
        expect(count($filexResult))->toBe(2);
    }

    public function test_magic_get_access()
    {
        $results = [
            ['success' => true, 'tempPath' => 'temp/file1.txt'],
        ];

        $filexResult = new FilexResult($results);
        expect($filexResult->{0})->toBe($results[0]);
    }

    public function test_magic_isset_access()
    {
        $results = [
            ['success' => true, 'tempPath' => 'temp/file1.txt'],
        ];

        $filexResult = new FilexResult($results);
        expect(isset($filexResult->{0}))->toBeTrue();
        expect(isset($filexResult->{1}))->toBeFalse();
    }
}
