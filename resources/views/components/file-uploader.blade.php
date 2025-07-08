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

{{-- Include Dropzone.js CSS and JS --}}
@push('css')
    <link rel="stylesheet" href="https://unpkg.com/dropzone@6.0.0-beta.2/dist/dropzone.css" />
@endpush

@push('js')
    <script src="https://unpkg.com/dropzone@6.0.0-beta.2/dist/dropzone-min.js"></script>
@endpush

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

@push('css')
    <style>
        .filex-uploader-wrapper {
            margin-bottom: 1rem;
        }

        .filex-uploader {
            border: 2px dashed #007bff;
            border-radius: 12px;
            background: #f8f9fa;
            padding: 30px 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            min-height: 140px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        /* When files are present, change layout to handle overflow */
        .filex-uploader.dz-started {
            display: block !important;
            text-align: left !important;
            padding: 20px !important;
            overflow-x: auto !important;
            overflow-y: hidden !important;
            white-space: nowrap !important;
            min-height: 200px !important;
        }

        .filex-uploader.dz-started .dz-message {
            display: none !important;
        }

        .filex-uploader:hover {
            border-color: #0056b3;
            background: #e9ecef;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 123, 255, 0.15);
        }

        .filex-uploader.dz-drag-hover {
            border-color: #28a745;
            background: #d4edda;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.15);
        }

        .filex-uploader .dz-message {
            font-size: 16px;
            color: #6c757d;
            margin: 0;
            text-align: center;
        }

        .filex-uploader .uploader-icon {
            margin-bottom: 15px;
        }

        .filex-uploader .uploader-icon svg {
            transition: all 0.3s ease;
        }

        .filex-uploader:hover .uploader-icon svg path {
            fill: #0056b3;
        }

        .spinning-icon {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            from {
                transform: rotate(0deg);
            }

            to {
                transform: rotate(360deg);
            }
        }

        .filex-uploader .uploader-text h5 {
            color: #495057;
            font-weight: 600;
            margin-bottom: 8px;
        }

        @keyframes shimmer {
            0% {
                transform: translateX(-100%);
            }

            100% {
                transform: translateX(100%);
            }
        }

        .upload-status {
            margin-top: 10px;
            text-align: center;
            padding: 8px 12px;
            border-radius: 6px;
            background: rgba(0, 123, 255, 0.05);
            border: 1px solid rgba(0, 123, 255, 0.1);
        }

        .upload-status .text-success {
            color: #28a745 !important;
        }

        .upload-status .text-danger {
            color: #dc3545 !important;
        }

        .retry-section {
            text-align: center !important;
            margin-top: 1rem !important;
            padding: 0.75rem !important;
            background: #fff3cd !important;
            border: 1px solid #ffeaa7 !important;
            border-radius: 8px !important;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif !important;
        }

        .retry-section .btn {
            background: #ffc107 !important;
            border: 1px solid #e0a800 !important;
            color: #212529 !important;
            padding: 0.5rem 1rem !important;
            border-radius: 6px !important;
            font-size: 0.875rem !important;
            font-weight: 500 !important;
            text-decoration: none !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 0.5rem !important;
            cursor: pointer !important;
            transition: all 0.2s ease !important;
            outline: none !important;
            font-family: inherit !important;
        }

        .retry-section .btn:hover {
            background: #e0a800 !important;
            border-color: #d39e00 !important;
            transform: translateY(-1px) !important;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1) !important;
        }

        .retry-section .btn:active {
            transform: translateY(0) !important;
            box-shadow: none !important;
        }

        .retry-section .btn:focus {
            box-shadow: 0 0 0 3px rgba(255, 193, 7, 0.25) !important;
        }



        .hidden-file-inputs {
            display: none;
        }

        .filex-uploader.required {
            border-color: #dc3545;
        }

        .filex-uploader.required .dz-message {
            color: #dc3545;
        }

        .filex-uploader.required .uploader-text h5 {
            color: #dc3545;
        }

        /* File type icon container */
        .file-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
        }


        /* Responsive design */
        @media (max-width: 768px) {
            .filex-uploader {
                min-height: 100px;
                padding: 20px 15px;
            }

            .filex-uploader .dz-message {
                font-size: 14px;
            }

            .filex-uploader .uploader-icon {
                margin-bottom: 10px;
            }

            .filex-uploader .uploader-icon svg {
                width: 32px !important;
                height: 32px !important;
            }
        }

        @media (max-width: 576px) {
            .filex-uploader {
                padding: 15px 10px;
                min-height: 80px;
            }

            .filex-uploader .uploader-text h5 {
                font-size: 0.9rem;
                margin-bottom: 5px;
            }

            .filex-uploader .uploader-text small {
                font-size: 0.75rem;
            }
        }

        /* Dark mode support */
        @media (prefers-color-scheme: dark) {
            .filex-uploader {
                background: #2d3748;
                border-color: #4a5568;
            }

            .filex-uploader:hover {
                background: #374151;
                border-color: #6366f1;
            }

            .filex-uploader .dz-message {
                color: #e2e8f0;
            }

            .filex-uploader .uploader-text h5 {
                color: #f7fafc;
            }

            .upload-status {
                background: rgba(99, 102, 241, 0.1);
                border-color: rgba(99, 102, 241, 0.2);
            }

            .retry-section {
                background: #374151 !important;
                border-color: #4b5563 !important;
            }

            .retry-section .btn {
                background: #f59e0b !important;
                border-color: #d97706 !important;
                color: #111827 !important;
            }

            .retry-section .btn:hover {
                background: #d97706 !important;
                border-color: #b45309 !important;
            }
        }

        /* Print styles */
        @media print {
            .filex-uploader-wrapper {
                display: none;
            }
        }

        /* Dropzone file preview styles - custom to avoid conflicts */
        .filex-uploader .dz-preview {
            position: relative !important;
            display: inline-block !important;
            vertical-align: top !important;
            margin: 8px !important;
            min-height: 140px !important;
            background: #ffffff !important;
            border: 1px solid #e9ecef !important;
            border-radius: 8px !important;
            padding: 16px !important;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1) !important;
            transition: all 0.3s ease !important;
            width: 200px !important;
            max-width: 200px !important;
            overflow: visible !important;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif !important;
            font-size: 14px !important;
            line-height: 1.4 !important;
            color: #495057 !important;
        }

        .filex-uploader .dz-preview:hover {
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15) !important;
            transform: translateY(-2px) !important;
        }

        .filex-uploader .dz-preview.dz-file-preview {
            background: #f8f9fa !important;
        }

        .filex-uploader .dz-preview.dz-image-preview {
            background: #ffffff !important;
        }

        .filex-uploader .dz-preview.dz-success {
            border-color: #28a745 !important;
            background: #f8fff9 !important;
        }

        /* Ensure success state overrides error state with maximum specificity */
        .filex-uploader .dz-preview.dz-success.dz-error,
        .filex-uploader .dz-preview.dz-error.dz-success,
        .filex-uploader .dz-preview.dz-success {
            border-color: #28a745 !important;
            background: #f8fff9 !important;
        }

        /* Force success state to override any error styling */
        .filex-uploader .dz-preview.dz-success,
        .filex-uploader .dz-preview.dz-success:hover,
        .filex-uploader .dz-preview.dz-success:focus {
            border-color: #28a745 !important;
            background: #f8fff9 !important;
        }

        .filex-uploader .dz-preview.dz-error {
            border-color: #dc3545 !important;
            background: #fff5f5 !important;
        }

        .filex-uploader .dz-preview.dz-processing {
            border-color: #007bff !important;
            background: #f0f8ff !important;
        }

        /* Remove Dropzone default success/error marks to prevent conflicts */
        .filex-uploader .dz-preview .dz-success-mark:not(.dz-success-mark),
        .filex-uploader .dz-preview .dz-error-mark:not(.dz-error-mark) {
            display: none !important;
        }

        /* Hide any default Dropzone marks */
        .filex-uploader .dz-preview .dz-success-mark[data-dz-success-mark],
        .filex-uploader .dz-preview .dz-error-mark[data-dz-error-mark] {
            display: none !important;
        }

        /* File image/icon container */
        .filex-uploader .dz-image {
            border-radius: 6px !important;
            overflow: hidden !important;
            width: 100% !important;
            height: 80px !important;
            position: relative !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            background: #f8f9fa !important;
            margin-bottom: 12px !important;
        }

        .filex-uploader .dz-image img {
            /* width: 100% !important; */
            height: 80% !important;
            object-fit: cover !important;
            border-radius: 4px !important;
        }

        .filex-uploader .dz-image i {
            color: #6c757d !important;
            font-size: 2.5rem !important;
        }

        /* File details */
        .filex-uploader .dz-details {
            position: static !important;
            opacity: 1 !important;
            background: transparent !important;
            padding: 0 !important;
            margin: 0 !important;
            width: 100% !important;
            height: auto !important;
            text-align: center !important;
            min-height: 40px !important;
        }

        .filex-uploader .dz-filename {
            margin: 0 !important;
            padding: 0 !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
            white-space: nowrap !important;
            font-weight: 500 !important;
            font-size: 13px !important;
            color: #495057 !important;
            line-height: 1.3 !important;
        }

        .filex-uploader .dz-filename span {
            background: none !important;
            border: none !important;
            color: inherit !important;
            font-weight: inherit !important;
            font-size: inherit !important;
            padding: 0 !important;
            margin: 0 !important;
        }

        .filex-uploader .dz-size {
            margin: 4px 0 0 0 !important;
            padding: 0 !important;
            font-size: 11px !important;
            color: #6c757d !important;
            font-weight: normal !important;
        }

        .filex-uploader .dz-size strong {
            font-weight: 500 !important;
            color: #495057 !important;
        }

        /* Progress bar */
        .filex-uploader .dz-progress {
            position: absolute !important;
            bottom: 0 !important;
            left: 0 !important;
            right: 0 !important;
            height: 3px !important;
            background: rgba(0, 0, 0, 0.1) !important;
            border-radius: 0 0 8px 8px !important;
            overflow: hidden !important;
            margin: 0 !important;
            padding: 0 !important;
            opacity: 1 !important;
        }

        .filex-uploader .dz-upload {
            position: absolute !important;
            top: 0 !important;
            left: 0 !important;
            bottom: 0 !important;
            width: 0% !important;
            background: linear-gradient(90deg, #007bff, #0056b3) !important;
            transition: width 0.3s ease !important;
            border-radius: 0 !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        .filex-uploader .dz-success .dz-upload {
            background: #28a745 !important;
            width: 100% !important;
        }

        .filex-uploader .dz-error .dz-upload {
            background: #dc3545 !important;
        }

        /* Remove button - highly specific to avoid conflicts */
        .filex-uploader .dz-preview .dz-remove {
            position: absolute !important;
            top: -8px !important;
            right: -8px !important;
            width: 24px !important;
            height: 24px !important;
            border-radius: 50% !important;
            background: #dc3545 !important;
            color: #ffffff !important;
            border: 2px solid #ffffff !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            cursor: pointer !important;
            font-size: 12px !important;
            font-weight: bold !important;
            text-decoration: none !important;
            font-family: Arial, sans-serif !important;
            line-height: 1 !important;
            z-index: 10 !important;
            transition: all 0.2s ease !important;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2) !important;
            margin: 0 !important;
            padding: 0 !important;
            outline: none !important;
            opacity: 1 !important;
            visibility: visible !important;
        }

        .filex-uploader .dz-preview .dz-remove svg {
            display: none !important; /* Hide any JavaScript-added SVG */
        }

        .filex-uploader .dz-preview .dz-remove:hover {
            background: #c82333 !important;
            transform: scale(1.1) !important;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.3) !important;
        }

        .filex-uploader .dz-preview .dz-remove:active {
            transform: scale(0.95) !important;
        }

        .filex-uploader .dz-preview .dz-remove:focus {
            box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.25) !important;
        }

        /* Retry button */
        .filex-uploader .dz-preview .dz-retry {
            position: absolute !important;
            bottom: 8px !important;
            right: 8px !important;
            width: 28px !important;
            height: 28px !important;
            border-radius: 50% !important;
            background: #ffc107 !important;
            color: #212529 !important;
            border: 2px solid #ffffff !important;
            display: none !important;
            align-items: center !important;
            justify-content: center !important;
            cursor: pointer !important;
            font-size: 12px !important;
            text-decoration: none !important;
            font-family: Arial, sans-serif !important;
            line-height: 1 !important;
            z-index: 9 !important;
            transition: all 0.2s ease !important;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2) !important;
            margin: 0 !important;
            padding: 0 !important;
            outline: none !important;
            opacity: 1 !important;
            visibility: visible !important;
        }

        .filex-uploader .dz-preview.dz-error .dz-retry {
            display: flex !important;
        }

        .filex-uploader .dz-preview.dz-success .dz-retry {
            display: none !important;
        }

        .filex-uploader .dz-preview .dz-retry:hover {
            background: #e0a800 !important;
            transform: scale(1.05) !important;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2) !important;
        }

        .filex-uploader .dz-preview .dz-retry:before {
            display: none !important;
        }

        .filex-uploader .dz-preview .dz-retry svg {
            width: 14px !important;
            height: 14px !important;
            fill: #212529 !important;
        }

        /* Error message */
        .filex-uploader .dz-error-message {
            position: absolute !important;
            top: 100% !important;
            left: 0 !important;
            right: 0 !important;
            background: #dc3545 !important;
            color: #ffffff !important;
            padding: 4px 8px !important;
            border-radius: 0 0 8px 8px !important;
            font-size: 11px !important;
            font-weight: 500 !important;
            text-align: center !important;
            z-index: 8 !important;
            margin: 0 !important;
            border: none !important;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1) !important;
            opacity: 1 !important;
        }

        .filex-uploader .dz-error-message:after {
            content: "" !important;
            position: absolute !important;
            bottom: 100% !important;
            left: 50% !important;
            margin-left: -5px !important;
            border: 5px solid transparent !important;
            border-bottom-color: #dc3545 !important;
        }

        /* Success and Error marks - simplified and more robust */
        .filex-uploader .dz-success-mark,
        .filex-uploader .dz-error-mark {
            position: absolute !important;
            bottom: -8px !important;
            left: 50% !important;
            transform: translateX(-50%) !important;
            width: 20px !important;
            height: 20px !important;
            border-radius: 50% !important;
            display: none !important;
            align-items: center !important;
            justify-content: center !important;
            z-index: 15 !important;
            margin: 0 !important;
            padding: 0 !important;
            opacity: 1 !important;
            background: transparent !important;
            border: none !important;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2) !important;
        }

        .filex-uploader .dz-success .dz-success-mark,
        .filex-uploader .dz-preview.dz-success .dz-success-mark {
            display: flex !important;
            visibility: visible !important;
        }

        .filex-uploader .dz-error .dz-error-mark,
        .filex-uploader .dz-preview.dz-error .dz-error-mark {
            display: flex !important;
            visibility: visible !important;
        }

        .filex-uploader .dz-success-mark svg,
        .filex-uploader .dz-error-mark svg {
            width: 16px !important;
            height: 16px !important;
        }

        /* Remove button styling */
        .filex-uploader .dz-remove {
            position: absolute !important;
            top: 8px !important;
            right: 8px !important;
            background: rgba(220, 53, 69, 0.8) !important;
            border: none !important;
            border-radius: 50% !important;
            width: 24px !important;
            height: 24px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            cursor: pointer !important;
            z-index: 10 !important;
            transition: all 0.2s ease !important;
        }

        .filex-uploader .dz-remove:hover {
            background: rgba(220, 53, 69, 1) !important;
            transform: scale(1.1) !important;
        }

        .filex-uploader .dz-remove svg {
            width: 12px !important;
            height: 12px !important;
        }

        /* Ensure remove buttons only show SVG icons, no text */
        .filex-uploader .dz-remove {
            text-indent: -9999px !important;
            font-size: 0 !important;
            line-height: 0 !important;
            text-align: center !important;
            overflow: hidden !important;
            white-space: nowrap !important;
            position: relative !important;
        }

        /* Use CSS ::before to add SVG icon */
        .filex-uploader .dz-remove::before {
            content: '' !important;
            position: absolute !important;
            top: 50% !important;
            left: 50% !important;
            transform: translate(-50%, -50%) !important;
            width: 12px !important;
            height: 12px !important;
            background-image: url('data:image/svg+xml;utf8,<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><g stroke-width="0"></g><g stroke-linecap="round" stroke-linejoin="round"></g><g><path d="M9.17065 4C9.58249 2.83481 10.6937 2 11.9999 2C13.3062 2 14.4174 2.83481 14.8292 4" stroke="white" stroke-width="1.5" stroke-linecap="round"></path><path d="M20.5 6H3.49988" stroke="white" stroke-width="1.5" stroke-linecap="round"></path><path d="M18.3735 15.3991C18.1965 18.054 18.108 19.3815 17.243 20.1907C16.378 21 15.0476 21 12.3868 21H11.6134C8.9526 21 7.6222 21 6.75719 20.1907C5.89218 19.3815 5.80368 18.054 5.62669 15.3991L5.16675 8.5M18.8334 8.5L18.6334 11.5" stroke="white" stroke-width="1.5" stroke-linecap="round"></path><path d="M9.5 11L10 16" stroke="white" stroke-width="1.5" stroke-linecap="round"></path><path d="M14.5 11L14 16" stroke="white" stroke-width="1.5" stroke-linecap="round"></path></g></svg>') !important;
            background-size: contain !important;
            background-repeat: no-repeat !important;
            background-position: center !important;
            z-index: 10 !important;
            display: block !important;
        }

        /* Hide any SVG elements that might be added by JavaScript */
        .filex-uploader .dz-remove svg {
            display: none !important;
        }

        /* Hide any text nodes within remove button */
        .filex-uploader .dz-remove::after {
            display: none !important;
        }

        top: 50% !important;
        left: 50% !important;
        transform: translate(-50%, -50%) !important;
        }

        /* Responsive preview styles */
        @media (max-width: 768px) {
            .filex-uploader .dz-preview {
                width: 150px !important;
                max-width: 150px !important;
                margin: 6px !important;
                padding: 12px !important;
                min-height: 120px !important;
            }

            .filex-uploader .dz-image {
                height: 60px !important;
                margin-bottom: 8px !important;
            }

            .filex-uploader .dz-image svg {
                width: 32px !important;
                height: 32px !important;
            }

            .filex-uploader .dz-filename {
                font-size: 12px !important;
            }

            .filex-uploader .dz-size {
                font-size: 10px !important;
            }
        }

        @media (max-width: 576px) {
            .filex-uploader .dz-preview {
                width: 120px !important;
                max-width: 120px !important;
                margin: 4px !important;
                padding: 8px !important;
                min-height: 100px !important;
            }

            .filex-uploader .dz-image {
                height: 50px !important;
                margin-bottom: 6px !important;
            }

            .filex-uploader .dz-image svg {
                width: 24px !important;
                height: 24px !important;
            }

            .filex-uploader .dz-remove {
                width: 20px !important;
                height: 20px !important;
                top: -6px !important;
                right: -6px !important;
                font-size: 10px !important;
            }

            .filex-uploader .dz-retry {
                width: 24px !important;
                height: 24px !important;
                top: -6px !important;
                left: -6px !important;
            }

            .filex-uploader .dz-success-mark,
            .filex-uploader .dz-error-mark {
                width: 20px !important;
                height: 20px !important;
                bottom: -6px !important;
            }
        }

        /* Dark mode support for previews */
        @media (prefers-color-scheme: dark) {
            .filex-uploader .dz-preview {
                background: #374151 !important;
                border-color: #4b5563 !important;
                color: #f3f4f6 !important;
            }

            .filex-uploader .dz-preview.dz-success {
                background: #064e3b !important;
                border-color: #10b981 !important;
            }

            .filex-uploader .dz-preview.dz-error {
                background: #7f1d1d !important;
                border-color: #ef4444 !important;
            }

            .filex-uploader .dz-preview.dz-processing {
                background: #1e3a8a !important;
                border-color: #3b82f6 !important;
            }

            .filex-uploader .dz-image {
                background: #4b5563 !important;
            }

            .filex-uploader .dz-filename {
                color: #f3f4f6 !important;
            }

            .filex-uploader .dz-size {
                color: #9ca3af !important;
            }

            .filex-uploader .dz-size strong {
                color: #e5e7eb !important;
            }
        }

        /* Custom classes for file previews */
        .filex-uploader .filex-preview-existing {
            border-style: dashed !important;
        }

        .filex-uploader .filex-preview-new {
            border-style: solid !important;
        }

        /* Animation for button interactions */
        .filex-uploader .dz-preview .dz-remove {
            transform: scale(0) !important;
            animation: filexButtonFadeIn 0.3s ease forwards !important;
        }

        .filex-uploader .dz-preview:hover .dz-remove {
            transform: scale(1) !important;
        }

        /* Retry button only shows on error state */
        .filex-uploader .dz-preview .dz-retry {
            transform: scale(0) !important;
        }

        .filex-uploader .dz-preview.dz-error .dz-retry {
            transform: scale(1) !important;
            animation: filexButtonFadeIn 0.3s ease forwards !important;
        }

        @keyframes filexButtonFadeIn {
            from {
                transform: scale(0) !important;
                opacity: 0 !important;
            }

            to {
                transform: scale(1) !important;
                opacity: 1 !important;
            }
        }

        /* Ensure buttons are always visible on touch devices */
        @media (hover: none) and (pointer: coarse) {
            .filex-uploader .dz-preview .dz-remove {
                transform: scale(1) !important;
                opacity: 1 !important;
            }

            .filex-uploader .dz-preview.dz-error .dz-retry {
                transform: scale(1) !important;
                opacity: 1 !important;
            }
        }

        /* Prevent text selection on buttons */
        .filex-uploader .dz-preview .dz-remove,
        .filex-uploader .dz-preview .dz-retry {
            user-select: none !important;
            -webkit-user-select: none !important;
            -moz-user-select: none !important;
            -ms-user-select: none !important;
        }
    </style>
@endpush

@push('js')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Check if Dropzone is available
            if (typeof Dropzone === 'undefined') {
                @if ($debug)
                    console.error('Dropzone.js library not loaded');
                @endif
                return;
            }

            const filexElement = document.getElementById('{{ $componentId }}');
            const hiddenInputsContainer = document.getElementById('{{ $componentId }}-hidden-inputs');
            const statusElement = document.getElementById('{{ $componentId }}-status');

            if (!filexElement) {
                @if ($debug)
                    console.error('Filex element not found: {{ $componentId }}');
                @endif
                return;
            }

            // Find parent form
            const parentForm = filexElement.closest('form');
            const submitButtons = parentForm ? parentForm.querySelectorAll(
                'button[type="submit"], input[type="submit"]') : [];

            let activeUploads = 0;
            let uploadedFiles = [];
            let failedFiles = [];
            let retryCount = {};

            // Initialize existing files
            const existingFiles = @json($existingFiles);
            if (existingFiles && existingFiles.length > 0) {
                uploadedFiles = [...existingFiles];
            }

            // Configuration from props with fallbacks
            const config = {
                maxFilesize: {{ $maxFilesize }},
                maxFiles: {{ $maxFiles ?? 'null' }},
                acceptedFiles: {!! $acceptedFiles ? json_encode($acceptedFiles) : 'null' !!},
                chunkSize: {{ $chunkSize }},
                retries: {{ $retries }},
                timeout: {{ $timeout }},
                parallelUploads: {{ $parallelUploads }},
                multiple: {{ $multiple ? 'true' : 'false' }},
                required: {{ $required ? 'true' : 'false' }},
                showProgress: {{ $showProgress ? 'true' : 'false' }},
                showFileIcons: {{ $showFileIcons ? 'true' : 'false' }},
                enableRetry: {{ $enableRetry ? 'true' : 'false' }},
                serverValidation: {{ $serverValidation ? 'true' : 'false' }},
                clientValidation: {{ $clientValidation ? 'true' : 'false' }},
                thumbnailWidth: {{ $thumbnailWidth }},
                thumbnailHeight: {{ $thumbnailHeight }},
                debug: {{ $debug ? 'true' : 'false' }}
            };
            @if ($debug)
                console.log('Filex config:', config);
                console.log('Upload URL:', '{{ route('filex.upload.temp') }}');
                console.log('CSRF Token:', '{{ csrf_token() }}');
                console.log('acceptedFiles raw:', {!! $acceptedFiles ? json_encode($acceptedFiles) : 'null' !!});
            @endif

            // Dropzone configuration
            const dropzoneConfig = {
                url: '{{ route('filex.upload.temp') }}',
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': (function() {
                        const metaTag = document.querySelector('meta[name="csrf-token"]');
                        return metaTag ? metaTag.getAttribute('content') : '{{ csrf_token() }}';
                    })()
                },
                paramName: 'file',
                maxFilesize: config.maxFilesize,
                maxFiles: config.maxFiles,
                acceptedFiles: config.acceptedFiles,
                acceptMimeTypes: config.acceptedFiles,
                addRemoveLinks: {{ $addRemoveLinks ? 'true' : 'false' }},
                autoProcessQueue: {{ $autoProcessQueue ? 'true' : 'false' }},
                parallelUploads: config.parallelUploads,
                uploadMultiple: {{ $uploadMultiple ? 'true' : 'false' }},
                chunking: true,
                chunkSize: config.chunkSize,
                parallelChunkUploads: false, // Sequential chunks for better memory management
                retryChunks: true,
                retryChunksLimit: 3,
                forceChunking: function(file) {
                    const threshold = {{ config('filex.performance.chunk_threshold', 50 * 1024 * 1024) }};
                    return file.size > threshold;
                },
                retries: config.retries,
                timeout: config.timeout,
                thumbnailWidth: config.thumbnailWidth,
                thumbnailHeight: config.thumbnailHeight,
                thumbnailMethod: '{{ $thumbnailMethod }}',
                dictDefaultMessage: '{{ $messages['dictDefaultMessage'] }}',
                dictFileTooBig: '{{ $messages['dictFileTooBig'] }}',
                dictInvalidFileType: '{{ $messages['dictInvalidFileType'] }}',
                dictResponseError: '{{ $messages['dictResponseError'] }}',
                dictMaxFilesExceeded: '{{ $messages['dictMaxFilesExceeded'] }}',
                dictRemoveFile: '', // Empty string to prevent text from showing

                // Override file type validation to prevent default Dropzone messages
                accept: function(file, done) {
                    // Always accept - we'll handle validation in our custom logic
                    done();
                }
                @if (!$previewTemplate)
                    , previewTemplate: '<div style="display:none"></div>'
                @endif
                @if ($resizeWidth || $resizeHeight)
                    , resizeWidth: {{ $resizeWidth ?? 'null' }}, resizeHeight: {{ $resizeHeight ?? 'null' }},
                        resizeQuality: {{ $resizeQuality }}, resizeMimeType:
                        {{ $resizeMimeType ? "'" . $resizeMimeType . "'" : 'null' }}
                @endif

                ,
                init: function() {
                    const dz = this;

                    // Client-side validation function
                    function validateFile(file) {
                        if (!config.clientValidation) return true;

                        const errors = [];

                        // Check file size
                        if (file.size > config.maxFilesize * 1024 * 1024) {
                            errors.push('File is too large');
                        }

                        // Check file type
                        if (config.acceptedFiles) {
                            const acceptedTypes = config.acceptedFiles.split(',').map(type => type.trim());
                            let isValidType = false;

                            for (const acceptedType of acceptedTypes) {
                                if (acceptedType.includes('*')) {
                                    // Handle MIME type patterns like 'image/*'
                                    const pattern = acceptedType.replace('*', '');
                                    if (file.type && file.type.startsWith(pattern)) {
                                        isValidType = true;
                                        break;
                                    }
                                } else if (acceptedType.startsWith('.')) {
                                    // Handle file extensions like '.jpg'
                                    const fileExtension = '.' + file.name.split('.').pop().toLowerCase();
                                    if (fileExtension === acceptedType.toLowerCase()) {
                                        isValidType = true;
                                        break;
                                    }
                                } else if (file.type === acceptedType) {
                                    // Handle exact MIME types like 'image/jpeg'
                                    isValidType = true;
                                    break;
                                }
                            }

                            if (!isValidType) {
                                errors.push('File type not allowed');
                            }
                        }

                        if (errors.length > 0) {
                            if (file.previewElement) {
                                file.previewElement.classList.add('dz-error');
                                const errorElement = file.previewElement.querySelector('.dz-error-message');
                                if (errorElement) {
                                    errorElement.textContent = errors.join(', ');
                                }

                                // Show error mark for client-side validation errors
                                const errorMark = file.previewElement.querySelector('.dz-error-mark');
                                const successMark = file.previewElement.querySelector('.dz-success-mark');

                                if (errorMark) {
                                    errorMark.style.display = 'flex';
                                    errorMark.style.visibility = 'visible';
                                    errorMark.style.opacity = '1';
                                }

                                if (successMark) {
                                    successMark.style.display = 'none';
                                    successMark.style.visibility = 'hidden';
                                    successMark.style.opacity = '0';
                                }
                            }
                            return false;
                        }

                        return true;
                    }

                    // Add existing files as mock files
                    existingFiles.forEach(function(filePath, index) {
                        const fileName = filePath.split('/').pop();
                        const mockFile = {
                            name: fileName,
                            size: 0,
                            accepted: true,
                            processing: false,
                            upload: {
                                progress: 100
                            },
                            status: Dropzone.SUCCESS,
                            tempPath: filePath,
                            isExisting: true
                        };

                        dz.emit('addedfile', mockFile);
                        dz.emit('complete', mockFile);

                        // Add file icon if enabled
                        if (config.showFileIcons && mockFile.previewElement) {
                            const extension = fileName.split('.').pop().toLowerCase();
                            const iconSvg = getFileIconSvg(extension);
                            const imageElement = mockFile.previewElement.querySelector('.dz-image');
                            if (imageElement) {
                                imageElement.innerHTML = iconSvg;
                            }
                        }                        // Add remove functionality for existing files
                        const removeButton = mockFile.previewElement ? mockFile.previewElement
                            .querySelector('.dz-remove') : null;
                        if (removeButton) {
                            removeButton.addEventListener('click', function(e) {
                                e.preventDefault();
                                e.stopPropagation();
                                dz.removeFile(mockFile);
                            });
                        }

                        // Add custom styling classes
                        if (mockFile.previewElement) {
                            mockFile.previewElement.classList.add('filex-preview-existing');
                        }
                    });

                    // File added event
                    dz.on('addedfile', function(file) {
                        @if ($debug)
                            console.log('File added:', file.name);
                        @endif

                        // Add custom styling classes for new files first
                        if (file.previewElement && !file.isExisting) {
                            file.previewElement.classList.add('filex-preview-new');

                            // Remove button will automatically get SVG icon via CSS

                            // Add success and error marks early so they're available for validation
                            const successMark = document.createElement('div');
                            successMark.className = 'dz-success-mark';
                            successMark.innerHTML =
                                '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16"><circle cx="12" cy="12" r="10" fill="#28a745"/><path fill="white" d="M9 12l2 2 4-4" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
                            successMark.style.display = 'none';
                            successMark.style.visibility = 'hidden';
                            file.previewElement.appendChild(successMark);

                            const errorMark = document.createElement('div');
                            errorMark.className = 'dz-error-mark';
                            errorMark.innerHTML =
                                '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16"><circle cx="12" cy="12" r="10" fill="#dc3545"/><path fill="white" d="M12 8v4M12 16h.01" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
                            errorMark.style.display = 'none';
                            errorMark.style.visibility = 'hidden';
                            file.previewElement.appendChild(errorMark);

                            @if ($debug)
                                console.log('Success and error marks added to preview element for:',
                                    file.name);
                            @endif
                        }

                        // Perform client-side validation
                        if (!file.isExisting && !validateFile(file)) {
                            return;
                        }

                        @if ($onAddedFile)
                            const addedFileCallback = {!! $onAddedFile !!};
                            if (typeof addedFileCallback === 'function') {
                                addedFileCallback(file, dz);
                            }
                        @endif

                        if (file.status !== Dropzone.SUCCESS && !file.isExisting) {
                            activeUploads++;
                            updateFormState();
                            updateStatus();
                        }

                        // Add file icon for non-image files
                        if (config.showFileIcons && (!file.type || !file.type.startsWith(
                                'image/'))) {
                            const extension = file.name.split('.').pop().toLowerCase();
                            const iconSvg = getFileIconSvg(extension);
                            const imageElement = file.previewElement ? file.previewElement
                                .querySelector('.dz-image') : null;
                            if (imageElement) {
                                imageElement.innerHTML = iconSvg;
                                if (config.debug) {
                                    console.log('Added icon for file:', file.name, 'extension:',
                                        extension, 'type:', file.type);
                                }
                            }
                        }

                        // Add retry button if enabled
                        if (config.enableRetry && file.previewElement) {
                            const retryButton = document.createElement('button');
                            retryButton.type = 'button';
                            retryButton.className = 'dz-retry';
                            retryButton.style.display = 'none';
                            retryButton.title = 'Retry upload';
                            retryButton.innerHTML =
                                '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14"><path fill="currentColor" d="M17.65,6.35C16.2,4.9 14.21,4 12,4A8,8 0 0,0 4,12A8,8 0 0,0 12,20C15.73,20 18.84,17.45 19.73,14H17.65C16.83,16.33 14.61,18 12,18A6,6 0 0,1 6,12A6,6 0 0,1 12,6C13.66,6 15.14,6.69 16.22,7.78L13,11H20V4L17.65,6.35Z"/></svg>';
                            retryButton.addEventListener('click', function(e) {
                                e.preventDefault();
                                e.stopPropagation();
                                retryUpload(file);
                            });
                            file.previewElement.appendChild(retryButton);
                        }
                    });

                    // Upload progress event
                    @if ($showProgress)
                        dz.on('uploadprogress', function(file, progress, bytesSent) {
                            if (config.debug) {
                                console.log('Upload progress:', file.name, progress + '%');
                            }

                            // Update custom progress indicator if needed
                            const progressElement = file.previewElement ? file.previewElement
                                .querySelector('.upload-progress') : null;
                            if (progressElement) {
                                progressElement.style.width = progress + '%';
                            }
                        });
                    @endif

                    // Success event
                    dz.on('success', function(file, response) {
                        @if ($debug)
                            console.log('Upload success:', file.name, response);
                        @endif

                        if (response && response.success && response.tempPath) {
                            file.tempPath = response.tempPath;
                            file.originalName = response.originalName;
                            file.serverSize = response.size;

                            // Mark file as successfully uploaded
                            file.status = Dropzone.SUCCESS;
                            if (file.previewElement) {
                                @if ($debug)
                                    console.log('Updating preview element for success:', file.name);
                                @endif

                                // Cache DOM elements for better performance
                                const preview = file.previewElement;
                                const progressEl = preview.querySelector('.dz-progress');
                                const errorEl = preview.querySelector('.dz-error-message');
                                const retryBtn = preview.querySelector('.dz-retry');
                                const successMark = preview.querySelector('.dz-success-mark');
                                const errorMark = preview.querySelector('.dz-error-mark');

                                // Clear all error and processing states immediately
                                preview.classList.remove('dz-processing', 'dz-error');
                                preview.classList.add('dz-success');

                                // Force clear any remaining error styling by removing inline styles that might override CSS
                                preview.style.borderColor = '';
                                preview.style.backgroundColor = '';

                                // Use setTimeout to ensure success state persists after any Dropzone interference
                                setTimeout(() => {
                                    if (preview && file.status === Dropzone.SUCCESS) {
                                        preview.classList.remove('dz-processing',
                                            'dz-error');
                                        preview.classList.add('dz-success');

                                        @if ($debug)
                                            console.log('Final success state applied:',
                                                preview.className);
                                        @endif
                                    }
                                }, 100);

                                // Hide progress bar
                                if (progressEl) {
                                    progressEl.style.display = 'none';
                                }

                                // Remove any error messages
                                if (errorEl) {
                                    errorEl.style.display = 'none';
                                    errorEl.textContent = '';
                                }

                                // Hide retry button for successful uploads
                                if (retryBtn) {
                                    retryBtn.style.display = 'none';
                                }

                                // Show success mark and hide error mark
                                if (successMark) {
                                    successMark.style.display = 'flex';
                                    successMark.style.visibility = 'visible';
                                    successMark.style.opacity = '1';
                                    @if ($debug)
                                        console.log('Success mark shown for:', file.name,
                                            'Element:', successMark);
                                    @endif
                                }

                                if (errorMark) {
                                    errorMark.style.display = 'none';
                                    errorMark.style.visibility = 'hidden';
                                    errorMark.style.opacity = '0';
                                    @if ($debug)
                                        console.log('Error mark hidden for:', file.name);
                                    @endif
                                }

                                @if ($debug)
                                    console.log('Success state applied to preview element:', preview
                                        .className);
                                @endif
                            }

                            uploadedFiles.push(response.tempPath);
                            updateHiddenInputs();

                            // Remove from failed files if it was there
                            const failedIndex = failedFiles.findIndex(f => f.name === file.name);
                            if (failedIndex > -1) {
                                failedFiles.splice(failedIndex, 1);
                            }

                            // Reset retry count
                            delete retryCount[file.name];

                            @if ($onSuccess)
                                const successCallback = {!! $onSuccess !!};
                                if (typeof successCallback === 'function') {
                                    successCallback(file, response, dz);
                                }
                            @endif

                            @if ($onUpload)
                                const uploadCallback = {!! $onUpload !!};
                                if (typeof uploadCallback === 'function') {
                                    uploadCallback(file, response, dz);
                                }
                            @endif
                        } else {
                            // Handle invalid response
                            if (file.previewElement) {
                                file.previewElement.classList.add('dz-error');
                                const errorMsg = (response && response.message) ? response.message :
                                    'Upload failed';
                                const errorElement = file.previewElement.querySelector(
                                    '.dz-error-message');
                                if (errorElement) {
                                    errorElement.textContent = errorMsg;
                                }
                            }
                        }

                        activeUploads--;
                        updateFormState();
                        updateStatus();
                    });

                    // Error event
                    dz.on('error', function(file, errorMessage, xhr) {
                        @if ($debug)
                            console.error('Upload error:', file.name, errorMessage);
                        @endif

                        activeUploads--;

                        // Add to failed files list
                        if (!failedFiles.find(f => f.name === file.name)) {
                            failedFiles.push({
                                name: file.name,
                                error: errorMessage,
                                file: file
                            });
                        }

                        // Apply error state to preview element
                        if (file.previewElement) {
                            @if ($debug)
                                console.log('Updating preview element for error:', file.name);
                            @endif

                            // Cache DOM elements for better performance
                            const preview = file.previewElement;
                            const retryBtn = preview.querySelector('.dz-retry');
                            const errorMark = preview.querySelector('.dz-error-mark');
                            const successMark = preview.querySelector('.dz-success-mark');

                            // Clear success state and add error state
                            preview.classList.remove('dz-success', 'dz-processing');
                            preview.classList.add('dz-error');

                            // Show retry button if enabled
                            if (config.enableRetry) {
                                if (retryBtn) {
                                    retryBtn.style.display = 'flex';
                                }

                                // Show error mark and hide success mark
                                if (errorMark) {
                                    errorMark.style.display = 'flex';
                                    errorMark.style.visibility = 'visible';
                                    errorMark.style.opacity = '1';
                                    @if ($debug)
                                        console.log('Error mark shown for:', file.name, 'Element:',
                                            errorMark);
                                    @endif
                                }

                                if (successMark) {
                                    successMark.style.display = 'none';
                                    successMark.style.visibility = 'hidden';
                                    successMark.style.opacity = '0';
                                    @if ($debug)
                                        console.log('Success mark hidden for:', file.name);
                                    @endif
                                }
                            }

                            @if ($debug)
                                console.log('Error state applied to preview element:', preview
                                    .className);
                            @endif
                        }

                        updateFormState();
                        updateStatus();

                        @if ($onError)
                            const errorCallback = {!! $onError !!};
                            if (typeof errorCallback === 'function') {
                                errorCallback(file, errorMessage, xhr, dz);
                            }
                        @endif
                    });

                    // Complete event
                    dz.on('complete', function(file) {
                        @if ($debug)
                            console.log('File complete:', file.name, 'Status:', file.status);
                        @endif

                        // Ensure proper visual state based on file status
                        if (file.previewElement) {
                            if (file.status === Dropzone.SUCCESS) {
                                // Force success state
                                file.previewElement.classList.remove('dz-processing', 'dz-error');
                                file.previewElement.classList.add('dz-success');

                                @if ($debug)
                                    console.log('Complete event - applied success state:', file
                                        .previewElement.className);
                                @endif
                            } else if (file.status === Dropzone.ERROR) {
                                // Force error state
                                file.previewElement.classList.remove('dz-processing', 'dz-success');
                                file.previewElement.classList.add('dz-error');

                                @if ($debug)
                                    console.log('Complete event - applied error state:', file
                                        .previewElement.className);
                                @endif
                            }
                        }

                        @if ($onComplete)
                            const completeCallback = {!! $onComplete !!};
                            if (typeof completeCallback === 'function') {
                                completeCallback(file, dz);
                            }
                        @endif
                    });

                    // Removed file event
                    dz.on('removedfile', function(file) {
                        @if ($debug)
                            console.log('File removed:', file.name);
                        @endif

                        if (file.tempPath) {
                            // Remove from uploaded files array
                            const index = uploadedFiles.indexOf(file.tempPath);
                            if (index > -1) {
                                uploadedFiles.splice(index, 1);
                            }

                            // Send delete request to server (only for uploaded files, not existing ones)
                            if (!file.isExisting) {
                                const filename = file.tempPath.split('/').pop();
                                const deleteUrl =
                                    '{{ route('filex.temp.delete', ['filename' => '__FILENAME__']) }}'
                                    .replace('__FILENAME__', encodeURIComponent(filename));

                                fetch(deleteUrl, {
                                    method: 'DELETE',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': (function() {
                                            const metaTag = document.querySelector(
                                                'meta[name="csrf-token"]');
                                            return metaTag ? metaTag.getAttribute(
                                                    'content') :
                                                '{{ csrf_token() }}';
                                        })()
                                    }
                                }).catch(error => {
                                    if (config.debug) {
                                        console.error('Failed to delete temp file:', error);
                                    }
                                });
                            }
                        }

                        // Remove from failed files if it was there
                        const failedIndex = failedFiles.findIndex(f => f.name === file.name);
                        if (failedIndex > -1) {
                            failedFiles.splice(failedIndex, 1);
                        }

                        updateHiddenInputs();
                        updateStatus();

                        @if ($onRemovedFile)
                            const removedFileCallback = {!! $onRemovedFile !!};
                            if (typeof removedFileCallback === 'function') {
                                removedFileCallback(file, dz);
                            }
                        @endif
                    });

                    // Max files exceeded event
                    dz.on('maxfilesexceeded', function(file) {
                        dz.removeFile(file);
                        alert('{{ $messages['dictMaxFilesExceeded'] }}');
                    });
                }
            };

            // Initialize Dropzone
            const myFilex = new Dropzone(filexElement, dropzoneConfig);

            @if ($debug)
                console.log('Filex initialized successfully:', myFilex);
            @endif

            // Add event delegation for remove and retry buttons
            filexElement.addEventListener('click', function(e) {
                if (e.target.matches('.dz-remove') || e.target.closest('.dz-remove')) {
                    e.preventDefault();
                    e.stopPropagation();
                    const removeButton = e.target.matches('.dz-remove') ? e.target : e.target.closest(
                        '.dz-remove');
                    const preview = removeButton.closest('.dz-preview');
                    if (preview && preview._file) {
                        myFilex.removeFile(preview._file);
                    }
                }

                if (e.target.matches('.dz-retry') || e.target.closest('.dz-retry')) {
                    e.preventDefault();
                    e.stopPropagation();
                    const retryButton = e.target.matches('.dz-retry') ? e.target : e.target.closest(
                        '.dz-retry');
                    const preview = retryButton.closest('.dz-preview');
                    if (preview && preview._file) {
                        retryUpload(preview._file);
                    }
                }
            });

            // Store reference for external access
            window['{{ $componentId }}'] = myFilex;

            // Helper functions
            function getFileIconSvg(extension) {
                const iconMap = {
                    'pdf': '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48"><path fill="#dc3545" d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/><text x="12" y="16" text-anchor="middle" fill="white" font-size="6" font-weight="bold">PDF</text></svg>',
                    'doc': '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48"><path fill="#2b579a" d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/><text x="12" y="16" text-anchor="middle" fill="white" font-size="5" font-weight="bold">DOC</text></svg>',
                    'docx': '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48"><path fill="#2b579a" d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/><text x="12" y="16" text-anchor="middle" fill="white" font-size="5" font-weight="bold">DOC</text></svg>',
                    'xls': '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48"><path fill="#107c41" d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/><text x="12" y="16" text-anchor="middle" fill="white" font-size="6" font-weight="bold">XLS</text></svg>',
                    'xlsx': '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48"><path fill="#107c41" d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/><text x="12" y="16" text-anchor="middle" fill="white" font-size="6" font-weight="bold">XLS</text></svg>',
                    'ppt': '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48"><path fill="#d24726" d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/><text x="12" y="16" text-anchor="middle" fill="white" font-size="6" font-weight="bold">PPT</text></svg>',
                    'pptx': '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48"><path fill="#d24726" d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/><text x="12" y="16" text-anchor="middle" fill="white" font-size="6" font-weight="bold">PPT</text></svg>',
                    'txt': '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48"><path fill="#6c757d" d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/><text x="12" y="16" text-anchor="middle" fill="white" font-size="6" font-weight="bold">TXT</text></svg>',
                    'rtf': '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48"><path fill="#6c757d" d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/><text x="12" y="16" text-anchor="middle" fill="white" font-size="6" font-weight="bold">RTF</text></svg>',
                    'zip': '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48"><path fill="#6c757d" d="M14,17H12V15H14M14,13H12V11H14M12,9H14V7H12M12,19H14V17H12M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/><text x="12" y="16" text-anchor="middle" fill="white" font-size="6" font-weight="bold">ZIP</text></svg>',
                    'rar': '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48"><path fill="#6c757d" d="M14,17H12V15H14M14,13H12V11H14M12,9H14V7H12M12,19H14V17H12M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/><text x="12" y="16" text-anchor="middle" fill="white" font-size="6" font-weight="bold">RAR</text></svg>',
                    '7z': '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48"><path fill="#6c757d" d="M14,17H12V15H14M14,13H12V11H14M12,9H14V7H12M12,19H14V17H12M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/><text x="12" y="16" text-anchor="middle" fill="white" font-size="6" font-weight="bold">7Z</text></svg>',
                    'app': '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48"><path fill="#007bff" d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/><text x="12" y="16" text-anchor="middle" fill="white" font-size="5" font-weight="bold">APP</text></svg>',
                    'exe': '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48"><path fill="#dc3545" d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/><text x="12" y="16" text-anchor="middle" fill="white" font-size="6" font-weight="bold">EXE</text></svg>',
                    'dmg': '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48"><path fill="#6c757d" d="M14,17H12V15H14M14,13H12V11H14M12,9H14V7H12M12,19H14V17H12M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/><text x="12" y="16" text-anchor="middle" fill="white" font-size="5" font-weight="bold">DMG</text></svg>',
                    'pkg': '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48"><path fill="#6c757d" d="M14,17H12V15H14M14,13H12V11H14M12,9H14V7H12M12,19H14V17H12M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/><text x="12" y="16" text-anchor="middle" fill="white" font-size="6" font-weight="bold">PKG</text></svg>',
                    'jpg': '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48"><path fill="#6f42c1" d="M8.5,13.5L11,16.5L14.5,12L19,18H5M21,19V5C21,3.89 20.1,3 19,3H5A2,2 0 0,0 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19Z"/></svg>',
                    'jpeg': '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48"><path fill="#6f42c1" d="M8.5,13.5L11,16.5L14.5,12L19,18H5M21,19V5C21,3.89 20.1,3 19,3H5A2,2 0 0,0 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19Z"/></svg>',
                    'png': '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48"><path fill="#6f42c1" d="M8.5,13.5L11,16.5L14.5,12L19,18H5M21,19V5C21,3.89 20.1,3 19,3H5A2,2 0 0,0 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19Z"/></svg>',
                    'gif': '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48"><path fill="#6f42c1" d="M8.5,13.5L11,16.5L14.5,12L19,18H5M21,19V5C21,3.89 20.1,3 19,3H5A2,2 0 0,0 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19Z"/></svg>',
                    'bmp': '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48"><path fill="#6f42c1" d="M8.5,13.5L11,16.5L14.5,12L19,18H5M21,19V5C21,3.89 20.1,3 19,3H5A2,2 0 0,0 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19Z"/></svg>',
                    'webp': '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48"><path fill="#6f42c1" d="M8.5,13.5L11,16.5L14.5,12L19,18H5M21,19V5C21,3.89 20.1,3 19,3H5A2,2 0 0,0 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19Z"/></svg>',
                    'svg': '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48"><path fill="#6f42c1" d="M8.5,13.5L11,16.5L14.5,12L19,18H5M21,19V5C21,3.89 20.1,3 19,3H5A2,2 0 0,0 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19Z"/></svg>',
                    'mp3': '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48"><path fill="#20c997" d="M14,3.23V5.29C16.89,6.15 19,8.83 19,12C19,15.17 16.89,17.84 14,18.7V20.77C18,19.86 21,16.28 21,12C21,7.72 18,4.14 14,3.23M16.5,12C16.5,10.23 15.5,8.71 14,7.97V16C15.5,15.29 16.5,13.76 16.5,12M3,9V15H7L12,20V4L7,9H3Z"/></svg>',
                    'wav': '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48"><path fill="#20c997" d="M14,3.23V5.29C16.89,6.15 19,8.83 19,12C19,15.17 16.89,17.84 14,18.7V20.77C18,19.86 21,16.28 21,12C21,7.72 18,4.14 14,3.23M16.5,12C16.5,10.23 15.5,8.71 14,7.97V16C15.5,15.29 16.5,13.76 16.5,12M3,9V15H7L12,20V4L7,9H3Z"/></svg>',
                    'ogg': '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48"><path fill="#20c997" d="M14,3.23V5.29C16.89,6.15 19,8.83 19,12C19,15.17 16.89,17.84 14,18.7V20.77C18,19.86 21,16.28 21,12C21,7.72 18,4.14 14,3.23M16.5,12C16.5,10.23 15.5,8.71 14,7.97V16C15.5,15.29 16.5,13.76 16.5,12M3,9V15H7L12,20V4L7,9H3Z"/></svg>',
                    'mp4': '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48"><path fill="#fd7e14" d="M17,10.5V7A1,1 0 0,0 16,6H4A1,1 0 0,0 3,7V17A1,1 0 0,0 4,18H16A1,1 0 0,0 17,17V13.5L21,17.5V6.5L17,10.5Z"/></svg>',
                    'avi': '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48"><path fill="#fd7e14" d="M17,10.5V7A1,1 0 0,0 16,6H4A1,1 0 0,0 3,7V17A1,1 0 0,0 4,18H16A1,1 0 0,0 17,17V13.5L21,17.5V6.5L17,10.5Z"/></svg>',
                    'mov': '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48"><path fill="#fd7e14" d="M17,10.5V7A1,1 0 0,0 16,6H4A1,1 0 0,0 3,7V17A1,1 0 0,0 4,18H16A1,1 0 0,0 17,17V13.5L21,17.5V6.5L17,10.5Z"/></svg>',
                    'wmv': '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48"><path fill="#fd7e14" d="M17,10.5V7A1,1 0 0,0 16,6H4A1,1 0 0,0 3,7V17A1,1 0 0,0 4,18H16A1,1 0 0,0 17,17V13.5L21,17.5V6.5L17,10.5Z"/></svg>'
                };
                return iconMap[extension.toLowerCase()] ||
                    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48"><path fill="#6c757d" d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/></svg>';
            }

            function retryUpload(file) {
                const currentRetries = retryCount[file.name] || 0;
                if (currentRetries >= config.retries) {
                    alert('Maximum retry attempts reached for ' + file.name);
                    return;
                }

                retryCount[file.name] = currentRetries + 1;

                // Hide retry button
                const retryButton = file.previewElement ? file.previewElement.querySelector('.dz-retry') : null;
                if (retryButton) {
                    retryButton.style.display = 'none';
                }

                // Reset file status
                file.status = Dropzone.QUEUED;
                if (file.previewElement) {
                    file.previewElement.classList.remove('dz-error');
                }

                // Remove from failed files
                const failedIndex = failedFiles.findIndex(f => f.name === file.name);
                if (failedIndex > -1) {
                    failedFiles.splice(failedIndex, 1);
                }

                activeUploads++;
                updateFormState();
                updateStatus();

                // Re-upload the file
                myFilex.processFile(file);
            }

            function updateHiddenInputs() {
                // Clear existing hidden inputs (except existing files)
                const existingInputs = hiddenInputsContainer.querySelectorAll('.existing-file-input');
                hiddenInputsContainer.innerHTML = '';

                // Re-add existing file inputs
                existingInputs.forEach(input => hiddenInputsContainer.appendChild(input));

                // Add new file inputs
                uploadedFiles.forEach(function(filePath) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = '{{ $hiddenInputName }}';
                    input.value = filePath;
                    input.className = 'uploaded-file-input';
                    hiddenInputsContainer.appendChild(input);
                });

                @if ($debug)
                    console.log('Updated hidden inputs:', uploadedFiles);
                @endif
            }

            function updateFormState() {
                @if ($disableFormOnUpload)
                    if (activeUploads > 0) {
                        // Disable submit buttons
                        submitButtons.forEach(btn => {
                            btn.disabled = true;
                            if (!btn.dataset.originalText) {
                                btn.dataset.originalText = btn.textContent;
                            }
                            btn.innerHTML =
                                '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" class="spinning-icon"><path fill="currentColor" d="M12,4V2A10,10 0 0,0 2,12H4A8,8 0 0,1 12,4Z"/></svg> Uploading...';
                        });
                    } else {
                        // Enable submit buttons
                        submitButtons.forEach(btn => {
                            btn.disabled = false;
                            if (btn.dataset.originalText) {
                                btn.textContent = btn.dataset.originalText;
                                delete btn.dataset.originalText;
                            }
                        });
                    }
                @endif
            }

            function updateStatus() {
                // Cache status elements for better performance
                const uploadingText = statusElement.querySelector('.uploading-text');
                const completedText = statusElement.querySelector('.completed-text');
                const errorText = statusElement.querySelector('.error-text');

                if (activeUploads > 0) {
                    statusElement.style.display = 'block';
                    if (uploadingText) uploadingText.style.display = 'inline';
                    if (completedText) completedText.style.display = 'none';
                    if (errorText) errorText.style.display = 'none';
                } else if (failedFiles.length > 0) {
                    statusElement.style.display = 'block';
                    if (uploadingText) uploadingText.style.display = 'none';
                    if (completedText) completedText.style.display = 'none';
                    if (errorText) {
                        errorText.style.display = 'inline';
                        errorText.textContent = failedFiles.length + ' file(s) failed to upload';
                    }
                } else if (uploadedFiles.length > 0) {
                    statusElement.style.display = 'block';
                    if (uploadingText) uploadingText.style.display = 'none';
                    if (completedText) completedText.style.display = 'inline';
                    if (errorText) errorText.style.display = 'none';
                } else {
                    statusElement.style.display = 'none';
                }
            }

            // Form validation helper
            if (parentForm) {
                parentForm.addEventListener('submit', function(e) {
                    @if ($required)
                        if (uploadedFiles.length === 0) {
                            e.preventDefault();
                            alert('Please upload at least one file.');
                            return false;
                        }
                    @endif

                    if (activeUploads > 0) {
                        e.preventDefault();
                        alert('Please wait for all files to finish uploading.');
                        return false;
                    }

                    if (failedFiles.length > 0 && !confirm(
                            'Some files failed to upload. Do you want to continue without them?')) {
                        e.preventDefault();
                        return false;
                    }
                });
            }

            // Expose helper functions for external use
            window['{{ $componentId }}_helpers'] = {
                getUploadedFiles: () => uploadedFiles,
                getFailedFiles: () => failedFiles,
                retryFailedUploads: () => {
                    failedFiles.forEach(failed => {
                        retryUpload(failed.file);
                    });
                },
                clearAll: () => {
                    myFilex.removeAllFiles();
                    uploadedFiles = [];
                    failedFiles = [];
                    updateHiddenInputs();
                    updateStatus();
                }
            };
        });
    </script>
@endpush
