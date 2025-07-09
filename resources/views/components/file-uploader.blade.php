{{--
    Laravel Filex - Modern File Upload Component

    A powerful and flexible file upload component built on Dropzone.js
    that provides drag-and-drop uploads, progress tracking, chunked uploads,
    and temporary file handling for Laravel     <!-- File uploader container -->
    <div id="{{ $componentId }}"
         class="filex-uploader {{ $class }} {{ $required ? 'required' : '' }} {{ $disabled ? 'disabled' : '' }}"
         style="{{ $style }}"
         data-component-id="{{ $componentId }}"
         data-config="{{ htmlspecialchars(json_encode($jsConfig), ENT_QUOTES, 'UTF-8') }}"ications.

    Usage: <x-filex-uploader name="files" :multiple="true" />

    Documentation: https://github.com/devwizardhq/laravel-filex
--}}

@props([
    // Basic Laravel form conventions
    'name' => 'files',
    'id' => null,
    'required' => false,
    'disabled' => false,
    'readonly' => false,

    // File upload specific
    'multiple' => false,
    'accept' => null,
    'maxFiles' => null,
    'maxSize' => null,
    'minSize' => null,

    // Validation rules (Laravel style)
    'rules' => [],
    'mimes' => null,
    'extensions' => null,
    'dimensions' => null,
    'clientValidation' => true,

    // UI and behavior
    'label' => null,
    'helpText' => null,
    'placeholder' => null,
    'showProgress' => true,
    'showFileSize' => true,
    'showFileName' => true,
    'allowPreview' => true,
    'previewTemplate' => true,
    'showFileIcons' => true,
    'addRemoveLinks' => true,
    'enableRetry' => true,

    // Upload behavior
    'autoProcess' => true,
    'parallelUploads' => null,
    'chunkSize' => null,
    'retries' => null,
    'timeout' => null,

    // Styling
    'class' => '',
    'style' => '',
    'wrapperClass' => '',

    // Advanced options
    'value' => [],
    'debug' => false,

    // Image processing
    'thumbnailWidth' => 120,
    'thumbnailHeight' => 120,
    'thumbnailMethod' => 'contain',

    // Events (Laravel style naming)
    'onSuccess' => null,
    'onError' => null,
    'onComplete' => null,
    'onFileAdded' => null,
    'onFileRemoved' => null,
    'onUpload' => null,

    // Custom messages
    'messages' => [],
    'errorMessages' => [],

    'dictFileTooBig' => null,
    'dictInvalidFileType' => null,
    'dictResponseError' => null,
    'dictMaxFilesExceeded' => null,
    'dictRemoveFile' => null,
])

@php
    // Generate component ID
    $componentId = $id ?? 'filex-uploader-' . uniqid();
    $hiddenInputName = $multiple ? $name . '[]' : $name;

    // Handle legacy prop names for backward compatibility
    $maxSize = $maxSize ?? config('filex.max_file_size', 10);
    $autoProcess = $autoProcess ?? ($autoProcessQueue ?? true);

    // Process value prop - can be string, array, or null
    $existingFiles = [];
    if (!empty($value)) {
        if (is_string($value)) {
            $existingFiles = [$value];
        } elseif (is_array($value)) {
            $existingFiles = array_filter($value); // Remove empty values
        }
    }

    // Get configuration values with fallbacks
    $chunkSize = $chunkSize ?? config('filex.chunk.size', 1048576);
    $retries = $retries ?? config('filex.chunk.max_retries', 3);
    $timeout = $timeout ?? config('filex.chunk.timeout', 30000);
    $parallelUploads = $parallelUploads ?? config('filex.performance.parallel_uploads', 2);

    // Process validation rules and build frontend validation config
    $frontendValidation = [
        'rules' => [],
        'messages' => [],
        'enabled' => $clientValidation,
    ];

    // Parse Laravel-style validation rules
    if (!empty($rules)) {
        foreach ($rules as $rule) {
            if (is_string($rule)) {
                // Handle string rules like 'required|mimes:jpeg,png|max:2048'
                $ruleParts = explode('|', $rule);
                foreach ($ruleParts as $rulePart) {
                    $frontendValidation['rules'][] = $rulePart;
                }
            } elseif (is_object($rule)) {
                // Handle validation rule objects (FilexRule, ValidFileUpload, etc.)
                $className = get_class($rule);
                if (str_contains($className, 'FilexImage')) {
                    $frontendValidation['rules'][] = 'image';
                } elseif (str_contains($className, 'FilexMimes')) {
                    $frontendValidation['rules'][] = 'file';
                } elseif (str_contains($className, 'ValidFileUpload')) {
                    $frontendValidation['rules'][] = 'file';
                }
            }
        }
    }

    // Handle shorthand validation props
    if ($mimes) {
        $accept = $accept ?? '.' . str_replace(',', ',.', $mimes);
        $frontendValidation['rules'][] = 'mimes:' . $mimes;
    }

    if ($extensions) {
        $accept = $accept ?? '.' . str_replace(',', ',.', $extensions);
        $frontendValidation['rules'][] = 'mimes:' . $extensions;
    }

    if ($maxSize) {
        $frontendValidation['rules'][] = 'max:' . $maxSize * 1024; // Convert MB to KB for validation
    }

    if ($minSize) {
        $frontendValidation['rules'][] = 'min:' . $minSize * 1024; // Convert MB to KB for validation
    }

    if ($dimensions) {
        if (is_string($dimensions)) {
            $frontendValidation['rules'][] = 'dimensions:' . $dimensions;
        } elseif (is_array($dimensions)) {
            $dimensionParts = [];
            foreach ($dimensions as $key => $value) {
                $dimensionParts[] = $key . '=' . $value;
            }
            $frontendValidation['rules'][] = 'dimensions:' . implode(',', $dimensionParts);
        }
    }

    // Get localized messages
    $defaultMessages = [
        'dictDefaultMessage' => $placeholder ?? __('Drop files here or click to upload'),
        'dictFileTooBig' => $dictFileTooBig ?? __('File is too big (:filesize MB). Max filesize: :maxFilesize MB.'),
        'dictInvalidFileType' => $dictInvalidFileType ?? __('You cannot upload files of this type.'),
        'dictResponseError' => $dictResponseError ?? __('Server responded with :statusCode code.'),
        'dictMaxFilesExceeded' => $dictMaxFilesExceeded ?? __('You cannot upload any more files.'),
        'dictRemoveFile' => $dictRemoveFile ?? '',
    ];

    // Merge custom messages (new Laravel convention)
    $allMessages = array_merge($defaultMessages, $messages, $errorMessages ?? []);

    // Build JavaScript config object
    $jsConfig = [
        'componentId' => $componentId,
        'name' => $name,
        'multiple' => $multiple,
        'required' => $required,
        'disabled' => $disabled,
        'readonly' => $readonly,
        'maxFiles' => $maxFiles,
        'maxSize' => $maxSize,
        'minSize' => $minSize,
        'existingFiles' => $existingFiles,
        'accept' => $accept,
        'autoProcess' => $autoProcess,
        'parallelUploads' => $parallelUploads,
        'chunkSize' => $chunkSize,
        'retries' => $retries,
        'timeout' => $timeout,
        'validation' => $frontendValidation,
        'messages' => $allMessages,
        'debug' => $debug,
        'thumbnailWidth' => $thumbnailWidth,
        'thumbnailHeight' => $thumbnailHeight,
        'thumbnailMethod' => $thumbnailMethod,
        'events' => [
            'onSuccess' => $onSuccess,
            'onError' => $onError,
            'onComplete' => $onComplete,
            'onFileAdded' => $onFileAdded,
            'onFileRemoved' => $onFileRemoved,
            'onUpload' => $onUpload,
        ],
    ];
@endphp

<div class="filex-uploader-wrapper {{ $wrapperClass }}" data-component-id="{{ $componentId }}">
    @if ($label)
        <label for="{{ $componentId }}" class="form-label">
            {{ $label }}
            @if ($required)
                <span class="text-danger">*</span>
            @endif
        </label>
    @endif

    @if ($helpText)
        <div class="form-text mb-2">{{ $helpText }}</div>
    @endif
    <!-- File uploader container -->
    <div id="{{ $componentId }}"
        class="filex-uploader {{ $class }} {{ $required ? 'required' : '' }} {{ $disabled ? 'disabled' : '' }}"
        style="{{ $style }}" data-component-id="{{ $componentId }}" data-config="{{ json_encode($jsConfig) }}"
        data-max-filesize="{{ $maxSize }}" data-accepted-files="{{ $accept }}"
        data-max-files="{{ $maxFiles }}" data-multiple="{{ $multiple ? 'true' : 'false' }}"
        data-name="{{ $name }}" data-validation-enabled="{{ $clientValidation ? 'true' : 'false' }}"
        data-frontend-rules="{{ json_encode($frontendValidation['rules']) }}">

        <div class="dz-message" data-dz-message>
            <div class="uploader-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48">
                    <path fill="#6c757d"
                        d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z" />
                    <path fill="#6c757d" d="M17,12L12,7V10H8V14H12V17L17,12Z" />
                </svg>
            </div>
            <div class="uploader-text">
                <h5 class="mb-2">{{ $allMessages['dictDefaultMessage'] }}</h5>
                @if ($accept)
                    <small class="text-muted">Accepted files: {{ $accept }}</small><br>
                @endif
                @if ($maxSize)
                    <small class="text-muted">Max file size: {{ $maxSize }}MB</small><br>
                @endif
                @if ($minSize)
                    <small class="text-muted">Min file size: {{ $minSize }}MB</small><br>
                @endif
                @if ($maxFiles)
                    <small class="text-muted">Max files: {{ $maxFiles }}</small>
                @endif
                @if (!empty($frontendValidation['rules']) && $clientValidation)
                    <small class="text-muted">Validation: {{ implode(', ', $frontendValidation['rules']) }}</small>
                @endif
            </div>
        </div>
    </div>

    <!-- Hidden inputs to store file paths -->
    <div id="{{ $componentId }}-hidden-inputs" class="hidden-file-inputs">
        @if ($multiple)
            @foreach ($existingFiles as $file)
                <input type="hidden" name="{{ $name }}[]" value="{{ $file }}"
                    class="existing-file-input">
            @endforeach
        @else
            @if (!empty($existingFiles))
                <input type="hidden" name="{{ $name }}" value="{{ $existingFiles[0] ?? '' }}"
                    class="existing-file-input">
            @endif
        @endif
    </div>

    <!-- Upload status indicator -->
    <div id="{{ $componentId }}-status" class="upload-status" style="display: none;">
        <small class="text-muted">
            <span class="uploading-text">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16"
                    class="spinning-icon">
                    <path fill="currentColor" d="M12,4V2A10,10 0 0,0 2,12H4A8,8 0 0,1 12,4Z" />
                </svg> Uploading files...
            </span>
            <span class="completed-text text-success" style="display: none;">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16">
                    <path fill="currentColor"
                        d="M12,2A10,10 0 0,1 22,12A10,10 0 0,1 12,22A10,10 0 0,1 2,12A10,10 0 0,1 12,2M12,4A8,8 0 0,0 4,12A8,8 0 0,0 12,20A8,8 0 0,0 20,12A8,8 0 0,0 12,4M11,16.5L6.5,12L7.91,10.59L11,13.67L16.59,8.09L18,9.5L11,16.5Z" />
                </svg> All files uploaded successfully
            </span>
            <span class="error-text text-danger" style="display: none;">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16">
                    <path fill="currentColor"
                        d="M12,2L13.09,8.26L22,9L17,14L18.18,23L12,19.77L5.82,23L7,14L2,9L10.91,8.26L12,2Z" />
                    <path fill="white" d="M11,15H13V17H11V15M11,7H13V13H11V7" />
                </svg> <span class="error-message"></span>
            </span>
        </small>
    </div>

    @if ($enableRetry)
        <!-- Retry all failed uploads button -->
        <div id="{{ $componentId }}-retry-section" class="retry-section mt-2" style="display: none;">
            <button type="button" class="btn btn-sm btn-outline-warning"
                onclick="window['{{ $componentId }}_helpers'].retryFailedUploads()">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16">
                    <path fill="currentColor"
                        d="M17.65,6.35C16.2,4.9 14.21,4 12,4A8,8 0 0,0 4,12A8,8 0 0,0 12,20C15.73,20 18.84,17.45 19.73,14H17.65C16.83,16.33 14.61,18 12,18A6,6 0 0,1 6,12A6,6 0 0,1 12,6C13.66,6 15.14,6.69 16.22,7.78L13,11H20V4L17.65,6.35Z" />
                </svg> Retry Failed Uploads
            </button>
        </div>
    @endif
</div>
