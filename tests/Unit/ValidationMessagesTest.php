<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;

beforeEach(function () {
    // Force validation rules registration by creating a validator instance
    // This triggers the lazy loading in the service provider
    Validator::make([], []);
});

it('displays proper error messages for filex validation rules', function () {
    // Create a fake file with wrong mime type
    $file = UploadedFile::fake()->create('test.txt', 100, 'text/plain');
    
    $data = [
        'name' => 'Test File',
        'file' => $file,
    ];
    
    $rules = [
        'name' => 'required|string|max:255',
        'file' => 'filex_mimes:png',
    ];
    
    $validator = Validator::make($data, $rules);
    
    expect($validator->fails())->toBeTrue();
    
    $errors = $validator->errors();
    $fileError = $errors->first('file');
    
    // Should not contain "Validation.filex_mimes"
    expect($fileError)->not->toContain('Validation.filex_mimes');
    
    // Should contain proper error message
    expect($fileError)->toContain('must be a file of type: png');
});

it('displays proper error messages for filex_min validation rule', function () {
    // Create a fake file that's too small
    $file = UploadedFile::fake()->create('test.png', 1, 'image/png'); // 1KB file
    
    $data = [
        'file' => $file,
    ];
    
    $rules = [
        'file' => 'filex_min:100', // Require at least 100KB
    ];
    
    $validator = Validator::make($data, $rules);
    
    expect($validator->fails())->toBeTrue();
    
    $errors = $validator->errors();
    $fileError = $errors->first('file');
    
    // Should not contain "Validation.filex_min"
    expect($fileError)->not->toContain('Validation.filex_min');
    
    // Should contain proper error message
    expect($fileError)->toContain('must be at least 100 kilobytes');
});

it('displays proper error messages for filex_max validation rule', function () {
    // Create a fake file that's too large
    $file = UploadedFile::fake()->create('test.png', 1000, 'image/png'); // 1000KB file
    
    $data = [
        'file' => $file,
    ];
    
    $rules = [
        'file' => 'filex_max:500', // Max 500KB allowed
    ];
    
    $validator = Validator::make($data, $rules);
    
    expect($validator->fails())->toBeTrue();
    
    $errors = $validator->errors();
    $fileError = $errors->first('file');
    
    // Should not contain "Validation.filex_max"
    expect($fileError)->not->toContain('Validation.filex_max');
    
    // Should contain proper error message
    expect($fileError)->toContain('may not be greater than 500 kilobytes');
});
