<?php

namespace DevWizard\Filex\Helpers;

use Illuminate\Support\Facades\Blade;

class FilexBladeHelpers
{
    /**
     * Register custom Blade directives
     */
    public static function register(): void
    {
        // @filexUploader directive
        Blade::directive('filexUploader', function ($expression) {
            return "<?php echo view('filex::components.file-uploader', {$expression})->render(); ?>";
        });

        // @filexScripts directive
        Blade::directive('filexScripts', function () {
            return <<<'HTML'
<script src="https://unpkg.com/dropzone@6.0.0-beta.2/dist/dropzone-min.js"></script>
<link rel="stylesheet" href="https://unpkg.com/dropzone@6.0.0-beta.2/dist/dropzone.css" type="text/css" />
HTML;
        });

        // @filexAssets directive (for local assets)
        Blade::directive('filexAssets', function () {
            return <<<'HTML'
<?php if(file_exists(public_path('assets/js/dropzone.min.js'))): ?>
<script src="{{ asset('assets/js/dropzone.min.js') }}"></script>
<link rel="stylesheet" href="{{ asset('assets/css/dropzone.min.css') }}" type="text/css" />
<?php else: ?>
<script src="https://unpkg.com/dropzone@6.0.0-beta.2/dist/dropzone-min.js"></script>
<link rel="stylesheet" href="https://unpkg.com/dropzone@6.0.0-beta.2/dist/dropzone.css" type="text/css" />
<?php endif; ?>
HTML;
        });
    }
}
