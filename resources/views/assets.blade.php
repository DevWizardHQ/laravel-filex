{{-- Filex CSS Assets with optimization --}}
@if (config('filex.performance.optimization.lazy_loading', true))
    {{-- Lazy load assets for better performance --}}
    <link rel="preload" href="{{ asset('vendor/filex/css/dropzone.min.css') }}" as="style"
        onload="this.onload=null;this.rel='stylesheet'">
    <link rel="preload" href="{{ asset('vendor/filex/css/filex.css') }}" as="style"
        onload="this.onload=null;this.rel='stylesheet'">
    <noscript>
        <link rel="stylesheet" href="{{ asset('vendor/filex/css/dropzone.min.css') }}">
        <link rel="stylesheet" href="{{ asset('vendor/filex/css/filex.css') }}">
    </noscript>
@else
    <link rel="stylesheet" href="{{ asset('vendor/filex/css/dropzone.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/filex/css/filex.css') }}">
@endif

{{-- Filex JavaScript Assets with optimization --}}
@if (config('filex.performance.optimization.lazy_loading', true))
    {{-- Defer JavaScript loading for better performance --}}
    <script defer src="{{ asset('vendor/filex/js/dropzone.min.js') }}"></script>
    <script defer src="{{ asset('vendor/filex/js/filex.js') }}"></script>
@else
    <script src="{{ asset('vendor/filex/js/dropzone.min.js') }}"></script>
    <script src="{{ asset('vendor/filex/js/filex.js') }}"></script>
@endif

{{-- Filex Routes Configuration --}}
<script>
    window.filexRoutes = {
        upload: '{{ route('filex.upload.temp') }}',
        uploadOptimized: '{{ route('filex.upload.temp.optimized') }}',
        delete: '{{ route('filex.temp.delete', ['filename' => '__FILENAME__']) }}', // Will replace __FILENAME__ in JS
        config: '{{ route('filex.config') }}'
    };

    // Initialize Filex when DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
        // Wait a bit for scripts to fully load
        setTimeout(function() {
            if (typeof window.Filex !== 'undefined' && typeof window.Filex.initialize === 'function') {
                window.Filex.initialize();
            }
        }, 100);
    });
</script>
