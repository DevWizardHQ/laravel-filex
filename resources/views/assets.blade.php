{{-- Filex CSS Assets --}}
<link rel="stylesheet" href="{{ asset('vendor/filex/css/dropzone.min.css') }}">
<link rel="stylesheet" href="{{ asset('vendor/filex/css/filex.css') }}">

{{-- Filex JavaScript Assets --}}
<script src="{{ asset('vendor/filex/js/dropzone.min.js') }}"></script>
<script src="{{ asset('vendor/filex/js/filex.js') }}"></script>

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
