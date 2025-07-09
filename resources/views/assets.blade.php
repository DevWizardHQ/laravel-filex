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
        delete: '{{ url('filex/temp') }}/', // Base URL for delete operations
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
