{{--
    Laravel Filex - Modern File Upload Component

    A powerful and flexible file upload component built on Dropzone.js
    that provides drag-and-drop uploads, progress tracking, chunked uploads,
    and temporary file handling for Laravel applications.

    Usage: <x-filex-uploader name="files" :multiple="true" />

    Documentation: https://github.com/devwizardhq/laravel-filex
--}}

@props([
    'name' => 'files',
    'multiple' => true,
    'required' => false,
    'maxFiles' => null,
    'maxFilesize' => null, // Will use config default if null
    'acceptedFiles' => null,
    'previewTemplate' => true,
    'showProgress' => true,
    'chunkSize' => null, // Will use config default if null
    'retries' => null, // Will use config default if null
    'timeout' => null, // Will use config default if null
    'dictDefaultMessage' => null,
    'dictFileTooBig' => null,
    'dictInvalidFileType' => null,
    'dictResponseError' => null,
    'dictMaxFilesExceeded' => null,
    'dictRemoveFile' => null,
    'addRemoveLinks' => true,
    'autoProcessQueue' => true,
    'parallelUploads' => null, // Will use config default if null
    'uploadMultiple' => false,
    'onSuccess' => null,
    'onError' => null,
    'onComplete' => null,
    'onAddedFile' => null,
    'onRemovedFile' => null,
    'onUpload' => null,
    'disableFormOnUpload' => true,
    'existingFiles' => [],
    'validation' => [],
    'serverValidation' => true,
    'clientValidation' => true,
    'showFileIcons' => true,
    'showUploadProgress' => true,
    'enableRetry' => true,
    'customErrorMessages' => [],
    'locale' => null,
    'thumbnailWidth' => 120,
    'thumbnailHeight' => 120,
    'thumbnailMethod' => 'contain',
    'resizeQuality' => 0.8,
    'resizeWidth' => null,
    'resizeHeight' => null,
    'resizeMimeType' => null,
    'cloudUpload' => false,
    'disk' => null,
    'targetDirectory' => 'uploads',
    'id' => null,
    'class' => '',
    'style' => '',
    'wrapperClass' => '',
    'helpText' => null,
    'label' => null,
    'showFileSize' => true,
    'showFileName' => true,
    'allowPreview' => true,
    'debug' => false,
])

@php
    $componentId = $id ?? 'filex-uploader-' . uniqid();
    $hiddenInputName = $multiple ? $name . '[]' : $name;

    // Get configuration values with fallbacks
    $maxFilesize = $maxFilesize ?? config('filex.max_file_size', 10);
    $chunkSize = $chunkSize ?? config('filex.chunk.size', 1048576);
    $retries = $retries ?? config('filex.chunk.max_retries', 3);
    $timeout = $timeout ?? config('filex.chunk.timeout', 30000);
    $parallelUploads = $parallelUploads ?? config('filex.performance.parallel_uploads', 2);
    $disk = $disk ?? config('filex.default_disk', 'public');

    // Get localized messages
    $locale = $locale ?? app()->getLocale();
    $messages = [
        'dictDefaultMessage' => $dictDefaultMessage ?? __('Drop files here or click to upload'),
        'dictFileTooBig' =>
            $dictFileTooBig ?? __('File is too big ({{ filesize }}MiB). Max filesize: {{ maxFilesize }}MiB.'),
        'dictInvalidFileType' => $dictInvalidFileType ?? __('You can\'t upload files of this type.'),
        'dictResponseError' => $dictResponseError ?? __('Server responded with {{ statusCode }} code.'),
        'dictMaxFilesExceeded' => $dictMaxFilesExceeded ?? __('You can not upload any more files.'),
        'dictRemoveFile' => '', // Empty string to prevent text from showing
    ];

    // Merge custom error messages
    $messages = array_merge($messages, $customErrorMessages);

    // Process validation rules
    $validationRules = [];
    if (!empty($validation)) {
        foreach ($validation as $rule => $value) {
            if ($rule === 'mimes' && is_array($value)) {
                $validationRules['acceptedFiles'] = '.' . implode(',.', $value);
            } elseif ($rule === 'max' && is_numeric($value)) {
                $validationRules['maxFilesize'] = $value / 1024; // Convert KB to MB
            } elseif ($rule === 'dimensions') {
                // Handle image dimension validation
                if (isset($value['max_width'])) {
                    $validationRules['resizeWidth'] = $value['max_width'];
                }
                if (isset($value['max_height'])) {
                    $validationRules['resizeHeight'] = $value['max_height'];
                }
            }
        }
    }
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
    <div id="{{ $componentId }}" class="filex-uploader {{ $class }} {{ $required ? 'required' : '' }}"
        style="{{ $style }}" data-max-filesize="{{ $maxFilesize }}"
        data-accepted-files="{{ $acceptedFiles }}" data-max-files="{{ $maxFiles }}"
        data-multiple="{{ $multiple ? 'true' : 'false' }}" data-name="{{ $name }}"
        data-disk="{{ $disk }}" data-target-directory="{{ $targetDirectory }}">
        <div class="dz-message" data-dz-message>
            <div class="uploader-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48">
                    <path fill="#6c757d"
                        d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z" />
                    <path fill="#6c757d" d="M17,12L12,7V10H8V14H12V17L17,12Z" />
                </svg>
            </div>
            <div class="uploader-text">
                <h5 class="mb-2">{{ $messages['dictDefaultMessage'] }}</h5>
                @if ($acceptedFiles)
                    <small class="text-muted">Accepted files: {{ $acceptedFiles }}</small><br>
                @endif
                @if ($maxFilesize)
                    <small class="text-muted">Max file size: {{ $maxFilesize }}MB</small><br>
                @endif
                @if ($maxFiles)
                    <small class="text-muted">Max files: {{ $maxFiles }}</small>
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




