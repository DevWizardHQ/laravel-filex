<?php

namespace DevWizard\Filex\Tests\Unit;

use DevWizard\Filex\Tests\TestCase;

class VisibilityConfigTest extends TestCase
{
    public function test_default_visibility_configuration_exists()
    {
        $config = config('filex.storage.visibility.default');

        expect($config)->toBe('public');
    }

    public function test_visibility_configuration_can_be_overridden()
    {
        config(['filex.storage.visibility.default' => 'private']);

        $config = config('filex.storage.visibility.default');

        expect($config)->toBe('private');
    }

    public function test_visibility_supports_valid_options()
    {
        $validOptions = ['public', 'private'];

        foreach ($validOptions as $option) {
            config(['filex.storage.visibility.default' => $option]);
            $config = config('filex.storage.visibility.default');
            expect($config)->toBe($option);
        }
    }
}
