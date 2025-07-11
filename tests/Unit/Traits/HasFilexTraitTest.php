<?php

namespace DevWizard\Filex\Tests\Unit\Traits;

use DevWizard\Filex\Services\FilexService;
use DevWizard\Filex\Tests\TestCase;
use DevWizard\Filex\Traits\HasFilex;

class HasFilexTraitTest extends TestCase
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
            public function getFilexServicePublic()
            {
                return $this->getFilexService();
            }
        };
    }

    public function test_trait_can_get_filex_service()
    {
        $service = $this->testClass->getFilexServicePublic();
        expect($service)->toBeInstanceOf(FilexService::class);
    }
}
