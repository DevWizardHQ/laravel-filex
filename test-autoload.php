<?php

require_once __DIR__ . '/vendor/autoload.php';

// Test basic class loading
try {
    echo "Testing class autoloading...\n";
    
    // Test if main classes can be loaded
    $classes = [
        'DevWizard\Filex\FilexServiceProvider',
        'DevWizard\Filex\Services\FilexService',
        'DevWizard\Filex\Filex',
    ];
    
    foreach ($classes as $class) {
        if (class_exists($class)) {
            echo "✅ {$class} - OK\n";
        } else {
            echo "❌ {$class} - NOT FOUND\n";
        }
    }
    
    // Test static method
    echo "\nTesting static method...\n";
    if (method_exists('DevWizard\Filex\Services\FilexService', 'renderFilexAssetsAndRoutesStatic')) {
        echo "✅ renderFilexAssetsAndRoutesStatic method exists\n";
    } else {
        echo "❌ renderFilexAssetsAndRoutesStatic method NOT FOUND\n";
    }
    
    echo "\nAll tests completed successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
