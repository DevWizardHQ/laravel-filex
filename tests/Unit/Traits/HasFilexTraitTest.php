<?php

namespace DevWizard\Filex\Tests\Unit\Traits;

use DevWizard\Filex\Tests\TestCase;
use DevWizard\Filex\Traits\HasFilex;

class HasFilexTraitTest extends TestCase
{
    public function test_trait_can_be_used()
    {
        // Create a test class that uses the HasFilex trait
        $testClass = new class
        {
            use HasFilex;
        };

        // Test that the trait is applied
        $traits = class_uses($testClass);
        expect($traits)->toContain(HasFilex::class);
    }

    public function test_trait_methods_exist()
    {
        // Create a test class that uses the HasFilex trait
        $testClass = new class
        {
            use HasFilex;
        };

        // Test that all expected methods exist
        expect(method_exists($testClass, 'moveFile'))->toBeTrue();
        expect(method_exists($testClass, 'moveFiles'))->toBeTrue();
        expect(method_exists($testClass, 'getFileValidationRules'))->toBeTrue();
        expect(method_exists($testClass, 'getFilesValidationRules'))->toBeTrue();
        expect(method_exists($testClass, 'cleanupTempFiles'))->toBeTrue();
    }
}
