<?php

use DevWizard\Filex\Http\Controllers\FilexController;
use DevWizard\Filex\Http\Middleware\FileUploadSecurityMiddleware;
use Illuminate\Support\Facades\Route;

/*
 * File upload routes for Laravel Filex package
 */

Route::prefix('filex')->name('filex.')->group(function () {
    // Apply security middleware to upload routes
    Route::post('/upload-temp', [FilexController::class, 'uploadTemp'])
        ->name('upload.temp')
        ->middleware(FileUploadSecurityMiddleware::class);
        
    Route::post('/upload-temp-optimized', [FilexController::class, 'uploadTempOptimized'])
        ->name('upload.temp.optimized')
        ->middleware(FileUploadSecurityMiddleware::class);
        
    Route::delete('/temp/{filename}', [FilexController::class, 'deleteTempFile'])->name('temp.delete');
    Route::get('/temp/{filename}/info', [FilexController::class, 'getTempFileInfo'])->name('temp.info');
    Route::get('/config', [FilexController::class, 'getUploadConfig'])->name('config');
});
