<?php

use DevWizard\Filex\FilexServiceProvider;
use DevWizard\Filex\Services\FilexService;

it('can register the service provider', function () {
    expect(app()->providerIsLoaded(FilexServiceProvider::class))->toBeTrue();
});

it('can resolve the filex service', function () {
    $service = app(FilexService::class);
    expect($service)->toBeInstanceOf(FilexService::class);
});

it('can generate unique filenames', function () {
    $service = app(FilexService::class);
    $filename = $service->generateFileName('test.pdf');

    expect($filename)->toBeString();
    expect($filename)->toContain('.pdf');
    expect($filename)->toContain('test');
});
