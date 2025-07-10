/**
 * Laravel Filex - File Upload Component JavaScript
 *
 * A comprehensive JavaScript module for the Laravel Filex file upload component
 * built on Dropzone.js with advanced features like chunked uploads, retry logic,
 * and comprehensive error handling.
 *
 * @package DevWizard\Filex
 * @version 1.0.0
 */

(function (window, document) {
    "use strict";

    // Filex JavaScript functionality

    /**
     * Enhanced error notification system
     */
    function showErrorMessage(message, componentId = null, timeout = 5000) {
        showNotification(message, 'error', componentId, timeout);
    }

    /**
     * Show success message
     */
    function showSuccessMessage(message, componentId = null, timeout = 3000) {
        showNotification(message, 'success', componentId, timeout);
    }

    /**
     * Show warning message
     */
    function showWarningMessage(message, componentId = null, timeout = 4000) {
        showNotification(message, 'warning', componentId, timeout);
    }

    /**
     * Show info message
     */
    function showInfoMessage(message, componentId = null, timeout = 3000) {
        showNotification(message, 'info', componentId, timeout);
    }

    /**
     * Generic notification system
     */
    function showNotification(message, type = 'info', componentId = null, timeout = 3000) {
        // Create or get notification container
        let container = document.getElementById('filex-notifications-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'filex-notifications-container';
            container.className = 'filex-notifications-container';
            document.body.appendChild(container);
        }

        // Create notification element
        const notification = document.createElement('div');
        notification.className = `filex-notification filex-notification--${type}`;

        // Get appropriate icon for the notification type
        const icon = getNotificationIcon(type);

        // Create notification content
        notification.innerHTML = `
            <div class="filex-notification__content">
                <div class="filex-notification__icon">${icon}</div>
                <div class="filex-notification__message">${message}</div>
                <button class="filex-notification__close" type="button" aria-label="Close notification">×</button>
            </div>
            ${timeout > 0 ? '<div class="filex-notification__progress"></div>' : ''}
        `;

        // Add close functionality
        const closeBtn = notification.querySelector('.filex-notification__close');
        closeBtn.addEventListener('click', () => removeNotification(notification));

        // Add to container
        container.appendChild(notification);

        // Auto-remove with progress bar animation
        if (timeout > 0) {
            const progressBar = notification.querySelector('.filex-notification__progress');
            if (progressBar) {
                // Animate progress bar
                progressBar.style.width = '100%';
                progressBar.style.transition = `width ${timeout}ms linear`;
                
                // Start animation after a brief delay
                setTimeout(() => {
                    progressBar.style.width = '0%';
                }, 10);
            }

            setTimeout(() => {
                if (notification.parentNode) {
                    removeNotification(notification);
                }
            }, timeout);
        }

        // Update component-specific error if componentId provided
        if (componentId && type === 'error') {
            updateComponentStatus(componentId, message);
        }

        return notification;
    }

    /**
     * Remove notification with animation
     */
    function removeNotification(notification) {
        if (!notification || !notification.parentNode) return;
        
        notification.classList.add('filex-notification--sliding-out');
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 300);
    }

    /**
     * Get icon SVG for notification type
     */
    function getNotificationIcon(type) {
        const icons = {
            error: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                <circle cx="12" cy="12" r="10"/>
                <path fill="white" d="M12 8v4M12 16h.01" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>`,
            success: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                <circle cx="12" cy="12" r="10"/>
                <path fill="white" d="M9 12l2 2 4-4" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>`,
            warning: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2L1 21h22L12 2zm0 3.5L19.5 19h-15L12 5.5zM11 10v4h2v-4h-2zm0 6v2h2v-2h-2z"/>
            </svg>`,
            info: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                <circle cx="12" cy="12" r="10"/>
                <path fill="white" d="M12 8v8M12 16h.01" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>`
        };
        
        return icons[type] || icons.info;
    }

    /**
     * Update component-specific status
     */
    function updateComponentStatus(componentId, message) {
        const statusElement = document.getElementById(componentId + '-status');
        if (statusElement) {
            const errorText = statusElement.querySelector('.error-text');
            const errorMessage = statusElement.querySelector('.error-message');
            if (errorText && errorMessage) {
                errorText.style.display = 'inline';
                errorMessage.textContent = message;
                statusElement.style.display = 'block';
            }
        }
    }

    /**
     * Enhanced AJAX request handler with comprehensive error handling
     */
    function makeAjaxRequest(url, options = {}) {
        const defaultOptions = {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCSRFToken(),
                'X-Requested-With': 'XMLHttpRequest'
            },
            timeout: 30000
        };

        const finalOptions = { ...defaultOptions, ...options };
        
        // Add timeout handling
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), finalOptions.timeout);
        finalOptions.signal = controller.signal;

        return fetch(url, finalOptions)
            .then(response => {
                clearTimeout(timeoutId);
                
                // Handle different response types
                const contentType = response.headers.get('content-type');
                
                if (!response.ok) {
                    // Try to parse error message from response
                    if (contentType && contentType.includes('application/json')) {
                        return response.json().then(data => {
                            throw new Error(data.message || data.error || `Server error: ${response.status}`);
                        });
                    } else {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                }

                // Parse successful response
                if (contentType && contentType.includes('application/json')) {
                    return response.json();
                } else {
                    return response.text();
                }
            })
            .catch(error => {
                clearTimeout(timeoutId);
                
                if (error.name === 'AbortError') {
                    throw new Error('Request timed out. Please try again.');
                } else if (error.message) {
                    throw error;
                } else {
                    throw new Error('Network error. Please check your connection and try again.');
                }
            });
    }

    /**
     * Initialize Filex uploader instances
     */
    function initializeFilex() {
        // Prevent double initialization
        if (window.filexInitialized) {
            return;
        }
        window.filexInitialized = true;

        // Check if Dropzone is available
        if (typeof Dropzone === "undefined") {
            console.error("Dropzone.js library not loaded");
            return;
        }

        // Disable Dropzone auto-discovery to prevent conflicts
        Dropzone.autoDiscover = false;

        // Find all filex uploader elements
        const filexElements = document.querySelectorAll(
            ".filex-uploader[data-component-id]"
        );

        if (filexElements.length === 0) {
            return;
        }

        filexElements.forEach(function (filexElement) {
            const componentId = filexElement.dataset.componentId;

            // Skip if already initialized
            if (
                filexElement.getAttribute("data-filex-initialized") ===
                    "true" ||
                filexElement.dropzone
            ) {
                return;
            }

            const hiddenInputsContainer = document.getElementById(
                componentId + "-hidden-inputs"
            );
            const statusElement = document.getElementById(
                componentId + "-status"
            );

            if (!filexElement) {
                console.error("Filex element not found:", componentId);
                return;
            }

            // Find parent form
            const parentForm = filexElement.closest("form");
            const submitButtons = parentForm
                ? parentForm.querySelectorAll(
                      'button[type="submit"], input[type="submit"]'
                  )
                : [];

            let activeUploads = 0;
            let uploadedFiles = [];
            let failedFiles = [];
            let retryCount = {};

            // Get configuration from data attributes
            const rawConfig = filexElement.dataset.config;
            let config = {};

            if (rawConfig) {
                try {
                    config = JSON.parse(rawConfig);
                } catch (e) {
                    console.warn(
                        "Failed to parse Filex config, using defaults:",
                        e
                    );
                }
            }

            // Fallback to individual data attributes for backward compatibility
            config = {
                componentId:
                    config.componentId || filexElement.dataset.componentId,
                name: config.name || filexElement.dataset.name || "files",
                multiple:
                    config.multiple !== undefined
                        ? config.multiple
                        : filexElement.dataset.multiple === "true",
                required:
                    config.required !== undefined
                        ? config.required
                        : filexElement.hasAttribute("data-required"),
                disabled:
                    config.disabled !== undefined
                        ? config.disabled
                        : filexElement.hasAttribute("data-disabled"),
                readonly:
                    config.readonly !== undefined
                        ? config.readonly
                        : filexElement.hasAttribute("data-readonly"),
                maxFiles:
                    config.maxFiles ||
                    (filexElement.dataset.maxFiles
                        ? parseInt(filexElement.dataset.maxFiles)
                        : null),
                maxSize:
                    config.maxSize ||
                    parseFloat(filexElement.dataset.maxFilesize) ||
                    10,
                minSize: config.minSize || null,
                accept:
                    config.accept || filexElement.dataset.acceptedFiles || null,
                autoProcess:
                    config.autoProcess !== undefined
                        ? config.autoProcess
                        : true,
                parallelUploads: config.parallelUploads || 2,
                chunkSize: config.chunkSize || 1048576,
                retries: config.retries || 3,
                timeout: config.timeout || 30000,
                validation: config.validation || {
                    rules: JSON.parse(
                        filexElement.dataset.frontendRules || "[]"
                    ),
                    enabled: filexElement.dataset.validationEnabled === "true",
                },
                messages: config.messages || {},
                debug: config.debug || false,
                thumbnailWidth: config.thumbnailWidth || 120,
                thumbnailHeight: config.thumbnailHeight || 120,
                thumbnailMethod: config.thumbnailMethod || "contain",
                events: config.events || {},
            };

            // Initialize existing files from hidden inputs and config
            const existingInputs = hiddenInputsContainer
                ? hiddenInputsContainer.querySelectorAll(".existing-file-input")
                : [];
            const existingFilesFromInputs = Array.from(existingInputs).map(
                (input) => input.value
            );
            const existingFilesFromConfig = config.existingFiles || [];
            const existingFiles = [
                ...existingFilesFromInputs,
                ...existingFilesFromConfig,
            ].filter(Boolean);

            // Initialize uploadedFiles with existing files (don't duplicate)
            if (existingFiles && existingFiles.length > 0) {
                uploadedFiles = [...new Set(existingFiles)]; // Use Set to remove duplicates
            }

            // Create hidden input name
            const hiddenInputName = config.multiple
                ? config.name + "[]"
                : config.name;

            // Dropzone configuration
            const dropzoneConfig = {
                url: window.filexRoutes
                    ? window.filexRoutes.upload
                    : "/filex/upload/temp",
                method: "POST",
                headers: {
                    "X-CSRF-TOKEN": getCSRFToken(),
                },
                paramName: "file",
                maxFilesize: config.maxSize,
                maxFiles: config.maxFiles,
                acceptedFiles: config.accept,
                addRemoveLinks: true,
                autoProcessQueue: config.autoProcess,
                parallelUploads: config.parallelUploads,
                uploadMultiple: false,
                chunking: true,
                chunkSize: config.chunkSize,
                parallelChunkUploads: false,
                retryChunks: true,
                retryChunksLimit: config.retries,
                forceChunking: function (file) {
                    return file.size > 50 * 1024 * 1024; // 50MB threshold
                },
                retries: config.retries,
                timeout: config.timeout,
                thumbnailWidth: config.thumbnailWidth,
                thumbnailHeight: config.thumbnailHeight,
                thumbnailMethod: config.thumbnailMethod,
                dictRemoveFile: config.messages.dictRemoveFile || "",
                dictDefaultMessage:
                    config.messages.dictDefaultMessage ||
                    "Drop files here or click to upload",
                dictFileTooBig:
                    config.messages.dictFileTooBig ||
                    "File is too big (:filesize MB). Max filesize: :maxfilesize MB.",
                dictInvalidFileType:
                    config.messages.dictInvalidFileType ||
                    "You cannot upload files of this type.",
                dictResponseError:
                    config.messages.dictResponseError ||
                    "Server error occurred during upload. Please try again.",
                dictMaxFilesExceeded:
                    config.messages.dictMaxFilesExceeded ||
                    "You cannot upload any more files.",

                // Override file type validation
                accept: function (file, done) {
                    done();
                },

                init: function () {
                    const dz = this;

                    // Enhanced client-side validation function
                    async function validateFile(file) {
                        const errors = [];

                        // Skip validation if disabled
                        if (!config.validation.enabled) {
                            return errors;
                        }

                        // Parse and apply validation rules
                        if (
                            config.validation.rules &&
                            config.validation.rules.length > 0
                        ) {
                            for (const rule of config.validation.rules) {
                                const ruleError = await applyValidationRule(
                                    file,
                                    rule
                                );
                                if (ruleError) {
                                    errors.push(ruleError);
                                }
                            }
                        }

                        // Legacy validation for backward compatibility
                        if (file.size > config.maxSize * 1024 * 1024) {
                            errors.push(
                                `File is too large. Maximum size: ${config.maxSize}MB`
                            );
                        }

                        if (
                            config.minSize &&
                            file.size < config.minSize * 1024 * 1024
                        ) {
                            errors.push(
                                `File is too small. Minimum size: ${config.minSize}MB`
                            );
                        }

                        if (config.accept) {
                            const acceptedTypes = config.accept
                                .split(",")
                                .map((type) => type.trim());
                            let isValidType = false;

                            for (const acceptedType of acceptedTypes) {
                                if (acceptedType.includes("*")) {
                                    const pattern = acceptedType.replace(
                                        "*",
                                        ""
                                    );
                                    if (
                                        file.type &&
                                        file.type.startsWith(pattern)
                                    ) {
                                        isValidType = true;
                                        break;
                                    }
                                } else if (acceptedType.startsWith(".")) {
                                    const fileExtension =
                                        "." +
                                        file.name
                                            .split(".")
                                            .pop()
                                            .toLowerCase();
                                    if (
                                        fileExtension ===
                                        acceptedType.toLowerCase()
                                    ) {
                                        isValidType = true;
                                        break;
                                    }
                                } else if (file.type === acceptedType) {
                                    isValidType = true;
                                    break;
                                }
                            }

                            if (!isValidType) {
                                errors.push("File type not allowed");
                            }
                        }

                        // Return only the errors array - no UI manipulation here
                        return errors;
                    }

                    // Apply individual validation rule
                    async function applyValidationRule(file, rule) {
                        if (typeof rule !== "string") return null;

                        const [ruleName, ruleValue] = rule.includes(":")
                            ? rule.split(":", 2)
                            : [rule, null];

                        switch (ruleName) {
                            case "required":
                                // Required validation handled at form level
                                return null;

                            case "image":
                                if (!file.type.startsWith("image/")) {
                                    return "File must be an image";
                                }
                                return null;

                            case "mimes":
                                if (ruleValue) {
                                    const allowedMimes = ruleValue
                                        .split(",")
                                        .map((ext) => ext.trim());
                                    const fileExt = getFileExtension(file.name);
                                    if (!allowedMimes.includes(fileExt)) {
                                        return `File type not allowed. Allowed types: ${allowedMimes.join(
                                            ", "
                                        )}`;
                                    }
                                }
                                return null;

                            case "max":
                                if (ruleValue) {
                                    const maxSizeKB = parseInt(ruleValue);
                                    if (file.size > maxSizeKB * 1024) {
                                        return `File too large. Maximum size: ${formatFileSize(
                                            maxSizeKB * 1024
                                        )}`;
                                    }
                                }
                                return null;

                            case "min":
                                if (ruleValue) {
                                    const minSizeKB = parseInt(ruleValue);
                                    if (file.size < minSizeKB * 1024) {
                                        return `File too small. Minimum size: ${formatFileSize(
                                            minSizeKB * 1024
                                        )}`;
                                    }
                                }
                                return null;

                            case "size":
                                if (ruleValue) {
                                    const exactSizeKB = parseInt(ruleValue);
                                    if (file.size !== exactSizeKB * 1024) {
                                        return `File must be exactly ${formatFileSize(
                                            exactSizeKB * 1024
                                        )}`;
                                    }
                                }
                                return null;

                            case "dimensions":
                                // Dimensions validation requires image loading
                                if (
                                    file.type.startsWith("image/") &&
                                    ruleValue
                                ) {
                                    return await validateImageDimensions(
                                        file,
                                        ruleValue
                                    );
                                }
                                return null;

                            case "file":
                                // Generic file validation (always passes for file objects)
                                return null;

                            case "mimetypes":
                                if (ruleValue) {
                                    const allowedMimeTypes = ruleValue
                                        .split(",")
                                        .map((type) => type.trim());
                                    if (!allowedMimeTypes.includes(file.type)) {
                                        return `File MIME type not allowed. Allowed types: ${allowedMimeTypes.join(
                                            ", "
                                        )}`;
                                    }
                                }
                                return null;

                            case "between":
                                if (ruleValue) {
                                    const [minSize, maxSize] = ruleValue
                                        .split(",")
                                        .map((size) => parseInt(size.trim()));
                                    const fileSizeKB = Math.round(
                                        file.size / 1024
                                    );
                                    if (
                                        fileSizeKB < minSize ||
                                        fileSizeKB > maxSize
                                    ) {
                                        return `File size must be between ${formatFileSize(
                                            minSize * 1024
                                        )} and ${formatFileSize(
                                            maxSize * 1024
                                        )}`;
                                    }
                                }
                                return null;

                            default:
                                if (config.debug) {
                                    console.warn(
                                        "Unknown validation rule:",
                                        ruleName
                                    );
                                }
                                return null;
                        }
                    }

                    // Validate image dimensions (async)
                    function validateImageDimensions(file, dimensionRule) {
                        return new Promise((resolve) => {
                            const img = new Image();
                            const url = URL.createObjectURL(file);

                            img.onload = function () {
                                URL.revokeObjectURL(url);
                                const dimensions =
                                    parseDimensionRule(dimensionRule);
                                const errors = [];

                                if (
                                    dimensions.min_width &&
                                    img.width < dimensions.min_width
                                ) {
                                    errors.push(
                                        `Image width too small. Minimum: ${dimensions.min_width}px`
                                    );
                                }
                                if (
                                    dimensions.max_width &&
                                    img.width > dimensions.max_width
                                ) {
                                    errors.push(
                                        `Image width too large. Maximum: ${dimensions.max_width}px`
                                    );
                                }
                                if (
                                    dimensions.min_height &&
                                    img.height < dimensions.min_height
                                ) {
                                    errors.push(
                                        `Image height too small. Minimum: ${dimensions.min_height}px`
                                    );
                                }
                                if (
                                    dimensions.max_height &&
                                    img.height > dimensions.max_height
                                ) {
                                    errors.push(
                                        `Image height too large. Maximum: ${dimensions.max_height}px`
                                    );
                                }
                                if (
                                    dimensions.width &&
                                    img.width !== dimensions.width
                                ) {
                                    errors.push(
                                        `Image width must be exactly ${dimensions.width}px`
                                    );
                                }
                                if (
                                    dimensions.height &&
                                    img.height !== dimensions.height
                                ) {
                                    errors.push(
                                        `Image height must be exactly ${dimensions.height}px`
                                    );
                                }
                                if (dimensions.ratio) {
                                    const actualRatio = img.width / img.height;
                                    if (
                                        Math.abs(
                                            actualRatio - dimensions.ratio
                                        ) > 0.01
                                    ) {
                                        errors.push(
                                            `Image aspect ratio must be ${dimensions.ratio}`
                                        );
                                    }
                                }

                                resolve(errors.join("; ") || null);
                            };

                            img.onerror = function () {
                                URL.revokeObjectURL(url);
                                resolve("Invalid image file");
                            };

                            img.src = url;
                        });
                    }

                    // Parse dimension rule string
                    function parseDimensionRule(dimensionRule) {
                        const dimensions = {};
                        const parts = dimensionRule.split(",");

                        for (const part of parts) {
                            const [key, value] = part.split("=");
                            if (key && value) {
                                dimensions[key.trim()] = parseFloat(
                                    value.trim()
                                );
                            }
                        }

                        return dimensions;
                    }

                    // Helper functions
                    function getFileExtension(filename) {
                        return filename.toLowerCase().split(".").pop();
                    }

                    function formatFileSize(bytes) {
                        if (bytes >= 1048576) {
                            return (
                                Math.round((bytes / 1048576) * 100) / 100 +
                                " MB"
                            );
                        } else if (bytes >= 1024) {
                            return (
                                Math.round((bytes / 1024) * 100) / 100 + " KB"
                            );
                        }
                        return bytes + " bytes";
                    }

                    function getEstimatedFileSize(extension) {
                        // Provide reasonable estimates based on file types
                        const sizeEstimates = {
                            // Images
                            jpg: 500000,
                            jpeg: 500000,
                            png: 800000,
                            gif: 300000,
                            bmp: 2000000,
                            webp: 400000,
                            svg: 50000,
                            // Documents
                            pdf: 1000000,
                            doc: 500000,
                            docx: 500000,
                            txt: 10000,
                            rtf: 100000,
                            // Videos (smaller estimates for temp files)
                            mp4: 5000000,
                            avi: 8000000,
                            mov: 6000000,
                            wmv: 4000000,
                            flv: 3000000,
                            webm: 4000000,
                            // Audio
                            mp3: 3000000,
                            wav: 10000000,
                            ogg: 2500000,
                            aac: 2000000,
                            flac: 8000000,
                            // Archives
                            zip: 2000000,
                            rar: 2000000,
                            "7z": 1500000,
                            tar: 2500000,
                            // Default
                            default: 100000,
                        };

                        return (
                            sizeEstimates[extension] || sizeEstimates["default"]
                        );
                    } // Add existing files as mock files after Dropzone is ready
                    dz.on("init", function () {
                        if (!existingFiles || existingFiles.length === 0) {
                            return;
                        }

                        existingFiles.forEach(function (filePath, index) {
                            const fileName = filePath.split("/").pop();
                            const extension = fileName
                                .split(".")
                                .pop()
                                .toLowerCase();

                            // Determine file type based on extension
                            let fileType = "";
                            const imageExtensions = [
                                "jpg",
                                "jpeg",
                                "png",
                                "gif",
                                "bmp",
                                "webp",
                                "svg",
                            ];
                            const documentExtensions = [
                                "pdf",
                                "doc",
                                "docx",
                                "txt",
                                "rtf",
                            ];
                            const videoExtensions = [
                                "mp4",
                                "avi",
                                "mov",
                                "wmv",
                                "flv",
                                "webm",
                            ];
                            const audioExtensions = [
                                "mp3",
                                "wav",
                                "ogg",
                                "aac",
                                "flac",
                            ];

                            if (imageExtensions.includes(extension)) {
                                fileType =
                                    "image/" +
                                    (extension === "jpg" ? "jpeg" : extension);
                            } else if (documentExtensions.includes(extension)) {
                                fileType = "application/" + extension;
                            } else if (videoExtensions.includes(extension)) {
                                fileType = "video/" + extension;
                            } else if (audioExtensions.includes(extension)) {
                                fileType = "audio/" + extension;
                            } else {
                                fileType = "application/octet-stream";
                            }

                            const mockFile = {
                                name: fileName,
                                size: getEstimatedFileSize(extension), // Set estimated size immediately
                                type: fileType,
                                accepted: true,
                                processing: false,
                                upload: {
                                    progress: 100,
                                },
                                status: Dropzone.SUCCESS,
                                tempPath: filePath,
                                isExisting: true,
                            };

                            // Try to get file size from server if routes are available
                            if (
                                window.filexRoutes &&
                                window.filexRoutes.fileInfo
                            ) {
                                const fileInfoUrl =
                                    window.filexRoutes.fileInfo.replace(
                                        "__FILEPATH__",
                                        encodeURIComponent(filePath)
                                    );
                                
                                makeAjaxRequest(fileInfoUrl, { method: 'GET' })
                                    .then((data) => {
                                        if (data.success && data.size) {
                                            mockFile.size = data.size;
                                            // Update the size display in the preview
                                            if (mockFile.previewElement) {
                                                const sizeElement =
                                                    mockFile.previewElement.querySelector(
                                                        ".dz-size span"
                                                    );
                                                if (sizeElement) {
                                                    sizeElement.textContent =
                                                        formatFileSize(
                                                            data.size
                                                        );
                                                }
                                            }
                                        }
                                    })
                                    .catch((error) => {
                                        console.warn('Failed to fetch file info for existing file:', error.message);
                                        // Fallback to estimated size based on file type
                                        mockFile.size =
                                            getEstimatedFileSize(extension);
                                        if (mockFile.previewElement) {
                                            const sizeElement =
                                                mockFile.previewElement.querySelector(
                                                    ".dz-size span"
                                                );
                                            if (sizeElement) {
                                                sizeElement.textContent =
                                                    formatFileSize(
                                                        mockFile.size
                                                    );
                                            }
                                        }
                                    });
                            } else {
                                // Fallback to estimated size based on file type
                                mockFile.size = getEstimatedFileSize(extension);
                            }

                            dz.emit("addedfile", mockFile);
                            dz.emit("complete", mockFile);

                            // Wait for preview element to be created, then process it
                            setTimeout(function () {
                                if (mockFile.previewElement) {
                                    // For existing files, always show file icon (no image loading)
                                    const imageElement =
                                        mockFile.previewElement.querySelector(
                                            ".dz-image"
                                        );
                                    if (imageElement) {
                                        const iconSvg =
                                            getFileIconSvg(extension);
                                        imageElement.innerHTML = iconSvg;
                                    }

                                    // Hide progress bar for existing files
                                    const progressElement =
                                        mockFile.previewElement.querySelector(
                                            ".dz-progress"
                                        );
                                    if (progressElement) {
                                        progressElement.style.display = "none";
                                    }

                                    // Remove any error styling
                                    mockFile.previewElement.classList.remove(
                                        "dz-error",
                                        "dz-processing"
                                    );
                                    mockFile.previewElement.classList.add(
                                        "dz-success",
                                        "filex-preview-existing"
                                    );

                                    // Hide error elements
                                    const errorElement =
                                        mockFile.previewElement.querySelector(
                                            ".dz-error-message"
                                        );
                                    if (errorElement) {
                                        errorElement.style.display = "none";
                                    }

                                    // Show success mark
                                    const successMark =
                                        mockFile.previewElement.querySelector(
                                            ".dz-success-mark"
                                        );
                                    if (successMark) {
                                        successMark.style.display = "flex";
                                        successMark.style.visibility =
                                            "visible";
                                        successMark.style.opacity = "1";
                                    }

                                    // Hide error mark
                                    const errorMark =
                                        mockFile.previewElement.querySelector(
                                            ".dz-error-mark"
                                        );
                                    if (errorMark) {
                                        errorMark.style.display = "none";
                                        errorMark.style.visibility = "hidden";
                                        errorMark.style.opacity = "0";
                                    }
                                } else {
                                }
                            }, 300);

                            // Add remove functionality for existing files
                            const removeButton = mockFile.previewElement
                                ? mockFile.previewElement.querySelector(
                                      ".dz-remove"
                                  )
                                : null;
                            if (removeButton) {
                                removeButton.addEventListener(
                                    "click",
                                    function (e) {
                                        e.preventDefault();
                                        e.stopPropagation();
                                        dz.removeFile(mockFile);
                                    }
                                );
                            }
                        });
                    });

                    // File added event
                    dz.on("addedfile", async function (file) {
                        // Add custom styling classes for new files
                        if (file.previewElement && !file.isExisting) {
                            file.previewElement.classList.add(
                                "filex-preview-new"
                            );

                            // Add success and error marks
                            const successMark = document.createElement("div");
                            successMark.className = "dz-success-mark";
                            successMark.innerHTML =
                                '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16"><circle cx="12" cy="12" r="10" fill="#28a745"/><path fill="white" d="M9 12l2 2 4-4" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
                            successMark.style.display = "none";
                            successMark.style.visibility = "hidden";
                            file.previewElement.appendChild(successMark);

                            const errorMark = document.createElement("div");
                            errorMark.className = "dz-error-mark";
                            errorMark.innerHTML =
                                '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16"><circle cx="12" cy="12" r="10" fill="#dc3545"/><path fill="white" d="M12 8v4M12 16h.01" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
                            errorMark.style.display = "none";
                            errorMark.style.visibility = "hidden";
                            file.previewElement.appendChild(errorMark);
                        }

                        // Perform client-side validation (async for image dimensions)
                        if (!file.isExisting) {
                            const validationErrors = await validateFile(file);
                            if (
                                validationErrors &&
                                validationErrors.length > 0
                            ) {
                                // Show validation error notification
                                if (config.showErrorNotifications !== false) {
                                    showErrorMessage(`Validation failed for ${file.name}: ${validationErrors.join(', ')}`, componentId, config.errorTimeout);
                                }

                                // Apply error state
                                if (file.previewElement) {
                                    file.previewElement.classList.add(
                                        "dz-error"
                                    );
                                    const errorElement =
                                        file.previewElement.querySelector(
                                            ".dz-error-message"
                                        );
                                    if (errorElement) {
                                        errorElement.textContent =
                                            validationErrors.join(", ");
                                        errorElement.style.display = 'block';
                                    }

                                    // Show error mark
                                    const errorMark =
                                        file.previewElement.querySelector(
                                            ".dz-error-mark"
                                        );
                                    const successMark =
                                        file.previewElement.querySelector(
                                            ".dz-success-mark"
                                        );

                                    if (errorMark) {
                                        errorMark.style.display = "flex";
                                        errorMark.style.visibility = "visible";
                                        errorMark.style.opacity = "1";
                                    }

                                    if (successMark) {
                                        successMark.style.display = "none";
                                        successMark.style.visibility = "hidden";
                                        successMark.style.opacity = "0";
                                    }
                                }

                                // Add to failed files list for validation errors
                                if (!failedFiles.find((f) => f.name === file.name)) {
                                    failedFiles.push({
                                        name: file.name,
                                        error: validationErrors.join(', '),
                                        file: file,
                                        type: 'validation'
                                    });
                                }

                                // Don't process invalid files
                                dz.removeFile(file);
                                updateStatus();
                                return;
                            } else {
                                // Validation passed - ensure no error states are showing
                                if (file.previewElement) {
                                    // Remove any error classes
                                    file.previewElement.classList.remove("dz-error");
                                    
                                    // Hide error message
                                    const errorElement = file.previewElement.querySelector(".dz-error-message");
                                    if (errorElement) {
                                        errorElement.style.display = 'none';
                                        errorElement.textContent = '';
                                    }
                                    
                                    // Hide error mark
                                    const errorMark = file.previewElement.querySelector(".dz-error-mark");
                                    if (errorMark) {
                                        errorMark.style.display = "none";
                                        errorMark.style.visibility = "hidden";
                                        errorMark.style.opacity = "0";
                                    }
                                }
                            }
                        }

                        if (
                            file.status !== Dropzone.SUCCESS &&
                            !file.isExisting
                        ) {
                            activeUploads++;
                            updateFormState();
                            updateStatus();
                        }

                        // Add file icon for non-image files
                        if (!file.type || !file.type.startsWith("image/")) {
                            const extension = file.name
                                .split(".")
                                .pop()
                                .toLowerCase();
                            const iconSvg = getFileIconSvg(extension);
                            const imageElement = file.previewElement
                                ? file.previewElement.querySelector(".dz-image")
                                : null;
                            if (imageElement) {
                                imageElement.innerHTML = iconSvg;
                            }
                        }

                        // Add retry button
                        if (file.previewElement) {
                            const retryButton =
                                document.createElement("button");
                            retryButton.type = "button";
                            retryButton.className = "dz-retry";
                            retryButton.style.display = "none";
                            retryButton.title = "Retry upload";
                            retryButton.innerHTML =
                                '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14"><path fill="currentColor" d="M17.65,6.35C16.2,4.9 14.21,4 12,4A8,8 0 0,0 4,12A8,8 0 0,0 12,20C15.73,20 18.84,17.45 19.73,14H17.65C16.83,16.33 14.61,18 12,18A6,6 0 0,1 6,12A6,6 0 0,1 12,6C13.66,6 15.14,6.69 16.22,7.78L13,11H20V4L17.65,6.35Z"/></svg>';
                            retryButton.addEventListener("click", function (e) {
                                e.preventDefault();
                                e.stopPropagation();
                                retryUpload(file);
                            });
                            file.previewElement.appendChild(retryButton);
                        }
                    });

                    // Success event
                    dz.on("success", function (file, response) {
                        if (response && response.success && response.tempPath) {
                            file.tempPath = response.tempPath;
                            file.originalName = response.originalName;
                            file.serverSize = response.size;

                            // Show success notification if enabled
                            if (config.showSuccessMessages) {
                                showSuccessMessage(`${file.name} uploaded successfully`, componentId, config.successTimeout);
                            }

                            // Mark file as successfully uploaded
                            file.status = Dropzone.SUCCESS;
                            if (file.previewElement) {
                                // Cache DOM elements
                                const preview = file.previewElement;
                                const progressEl =
                                    preview.querySelector(".dz-progress");
                                const errorEl =
                                    preview.querySelector(".dz-error-message");
                                const retryBtn =
                                    preview.querySelector(".dz-retry");
                                const successMark =
                                    preview.querySelector(".dz-success-mark");
                                const errorMark =
                                    preview.querySelector(".dz-error-mark");

                                // Clear error states and add success state
                                preview.classList.remove(
                                    "dz-processing",
                                    "dz-error"
                                );
                                preview.classList.add("dz-success");

                                // Force success state to persist
                                setTimeout(() => {
                                    if (
                                        preview &&
                                        file.status === Dropzone.SUCCESS
                                    ) {
                                        preview.classList.remove(
                                            "dz-processing",
                                            "dz-error"
                                        );
                                        preview.classList.add("dz-success");
                                    }
                                }, 100);

                                // Hide progress bar
                                if (progressEl) {
                                    progressEl.style.display = "none";
                                }

                                // Remove error messages
                                if (errorEl) {
                                    errorEl.style.display = "none";
                                    errorEl.textContent = "";
                                }

                                // Hide retry button
                                if (retryBtn) {
                                    retryBtn.style.display = "none";
                                }

                                // Show success mark and hide error mark
                                if (successMark) {
                                    successMark.style.display = "flex";
                                    successMark.style.visibility = "visible";
                                    successMark.style.opacity = "1";
                                }

                                if (errorMark) {
                                    errorMark.style.display = "none";
                                    errorMark.style.visibility = "hidden";
                                    errorMark.style.opacity = "0";
                                }
                            }

                            // Add to uploaded files only if not already present (prevent duplicates)
                            if (!uploadedFiles.includes(response.tempPath)) {
                                uploadedFiles.push(response.tempPath);
                            }
                            updateHiddenInputs();

                            // Remove from failed files if it was there
                            const failedIndex = failedFiles.findIndex(
                                (f) => f.name === file.name
                            );
                            if (failedIndex > -1) {
                                failedFiles.splice(failedIndex, 1);
                            }

                            // Reset retry count
                            delete retryCount[file.name];
                        } else {
                            // Handle invalid response
                            if (file.previewElement) {
                                file.previewElement.classList.add("dz-error");
                                const errorMsg =
                                    response && response.message
                                        ? response.message
                                        : "Upload failed";
                                const errorElement =
                                    file.previewElement.querySelector(
                                        ".dz-error-message"
                                    );
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
                    dz.on("error", function (file, errorMessage, xhr) {
                        activeUploads--;

                        // Parse error message from different sources
                        let finalErrorMessage = errorMessage;
                        
                        if (typeof errorMessage === 'object') {
                            if (errorMessage.message) {
                                finalErrorMessage = errorMessage.message;
                            } else if (errorMessage.error) {
                                finalErrorMessage = errorMessage.error;
                            } else {
                                finalErrorMessage = 'Upload failed';
                            }
                        } else if (typeof errorMessage === 'string') {
                            // Try to parse JSON error messages
                            try {
                                const parsed = JSON.parse(errorMessage);
                                finalErrorMessage = parsed.message || parsed.error || errorMessage;
                            } catch (e) {
                                finalErrorMessage = errorMessage;
                            }
                        }

                        // Handle Dropzone.js default error message with statusCode placeholder
                        if (typeof finalErrorMessage === 'string' && finalErrorMessage.includes(':statusCode')) {
                            if (xhr && xhr.status) {
                                finalErrorMessage = finalErrorMessage.replace(':statusCode', xhr.status);
                            } else {
                                // If no status code available, provide a generic message
                                finalErrorMessage = 'Server error occurred during upload';
                            }
                        }

                        // Handle different HTTP status codes
                        if (xhr && xhr.status) {
                            switch (xhr.status) {
                                case 413:
                                    finalErrorMessage = 'File is too large for upload';
                                    break;
                                case 422:
                                    finalErrorMessage = finalErrorMessage.includes('Server responded with') ? 'File validation failed' : finalErrorMessage;
                                    break;
                                case 429:
                                    finalErrorMessage = 'Too many upload attempts. Please wait and try again.';
                                    break;
                                case 500:
                                    finalErrorMessage = 'Server error during upload. Please try again.';
                                    break;
                                case 0:
                                    finalErrorMessage = 'Network error. Please check your connection.';
                                    break;
                                default:
                                    // For other status codes, if still using generic template, improve it
                                    if (finalErrorMessage.includes('Server responded with') && finalErrorMessage.includes('code')) {
                                        finalErrorMessage = `Server error (${xhr.status}): Please try again`;
                                    }
                                    break;
                            }
                        } else if (finalErrorMessage.includes(':statusCode')) {
                            // No xhr object available, replace with generic message
                            finalErrorMessage = 'Server error occurred during upload';
                        }

                        // Show error notification only if enabled
                        if (config.showErrorNotifications !== false) {
                            showErrorMessage(`Upload failed for ${file.name}: ${finalErrorMessage}`, componentId, config.errorTimeout);
                        }

                        // Add to failed files list
                        if (!failedFiles.find((f) => f.name === file.name)) {
                            failedFiles.push({
                                name: file.name,
                                error: finalErrorMessage,
                                file: file,
                            });
                        }

                        // Apply error state to preview element
                        if (file.previewElement) {
                            // Cache DOM elements
                            const preview = file.previewElement;
                            const retryBtn = preview.querySelector(".dz-retry");
                            const errorMark =
                                preview.querySelector(".dz-error-mark");
                            const successMark =
                                preview.querySelector(".dz-success-mark");
                            const errorElement = preview.querySelector(".dz-error-message");

                            // Clear success state and add error state
                            preview.classList.remove(
                                "dz-success",
                                "dz-processing"
                            );
                            preview.classList.add("dz-error");

                            // Show detailed error in preview
                            if (errorElement) {
                                errorElement.textContent = finalErrorMessage;
                                errorElement.style.display = 'block';
                            }

                            // Show retry button
                            if (retryBtn) {
                                retryBtn.style.display = "flex";
                            }

                            // Show error mark and hide success mark
                            if (errorMark) {
                                errorMark.style.display = "flex";
                                errorMark.style.visibility = "visible";
                                errorMark.style.opacity = "1";
                            }

                            if (successMark) {
                                successMark.style.display = "none";
                                successMark.style.visibility = "hidden";
                                successMark.style.opacity = "0";
                            }
                        }

                        updateFormState();
                        updateStatus();
                    });

                    // Complete event
                    dz.on("complete", function (file) {
                        // Ensure proper visual state based on file status
                        if (file.previewElement) {
                            if (file.status === Dropzone.SUCCESS) {
                                file.previewElement.classList.remove(
                                    "dz-processing",
                                    "dz-error"
                                );
                                file.previewElement.classList.add("dz-success");
                            } else if (file.status === Dropzone.ERROR) {
                                file.previewElement.classList.remove(
                                    "dz-processing",
                                    "dz-success"
                                );
                                file.previewElement.classList.add("dz-error");
                            }
                        }
                    });

                    // Removed file event
                    dz.on("removedfile", function (file) {
                        if (file.tempPath) {
                            // Remove from uploaded files array
                            const index = uploadedFiles.indexOf(file.tempPath);
                            if (index > -1) {
                                uploadedFiles.splice(index, 1);
                            }

                            // Send delete request to server (only for uploaded files, not existing ones)
                            if (
                                !file.isExisting &&
                                window.filexRoutes &&
                                window.filexRoutes.delete
                            ) {
                                const filename = file.tempPath.split("/").pop();
                                const deleteUrl =
                                    window.filexRoutes.delete.replace(
                                        "__FILENAME__",
                                        encodeURIComponent(filename)
                                    );

                                makeAjaxRequest(deleteUrl, {
                                    method: "DELETE"
                                })
                                .then(response => {
                                    if (response.success) {
                                        if (config.debug || config.showSuccessMessages) {
                                            showSuccessMessage(`File ${file.name} deleted successfully`, componentId, config.successTimeout);
                                        }
                                    } else {
                                        if (config.showErrorNotifications !== false) {
                                            showErrorMessage(response.message || 'Failed to delete file from server', componentId, config.errorTimeout);
                                        }
                                    }
                                })
                                .catch(error => {
                                    console.error("Failed to delete temp file:", error);
                                    if (config.showErrorNotifications !== false) {
                                        showErrorMessage(`Failed to delete ${file.name}: ${error.message}`, componentId, config.errorTimeout);
                                    }
                                });
                            }
                        }

                        // Remove from failed files if it was there
                        const failedIndex = failedFiles.findIndex(
                            (f) => f.name === file.name
                        );
                        if (failedIndex > -1) {
                            failedFiles.splice(failedIndex, 1);
                        }

                        updateHiddenInputs();
                        updateStatus();
                    });

                    // Max files exceeded event
                    dz.on("maxfilesexceeded", function (file) {
                        const maxFiles = config.maxFiles || 'unlimited';
                        if (config.showErrorNotifications !== false) {
                            showErrorMessage(`Maximum number of files exceeded. Limit: ${maxFiles}`, componentId, config.errorTimeout);
                        }
                        dz.removeFile(file);
                    });
                },
            };

            // Check if Dropzone is already initialized on this element
            if (filexElement.dropzone) {
                return;
            }

            // Mark element as being initialized to prevent double initialization
            filexElement.setAttribute("data-filex-initialized", "true");

            // Initialize Dropzone
            const myFilex = new Dropzone(filexElement, dropzoneConfig);

            // Process existing files immediately (fallback if init event doesn't fire)
            if (existingFiles && existingFiles.length > 0) {
                setTimeout(function () {
                    processExistingFiles(myFilex, existingFiles);
                }, 100);
            }

            // Function to process existing files
            function processExistingFiles(dz, fileList) {
                fileList.forEach(function (filePath, index) {
                    const fileName = filePath.split("/").pop();
                    const extension = fileName.split(".").pop().toLowerCase();

                    // Determine file type based on extension
                    let fileType = "";
                    const imageExtensions = [
                        "jpg",
                        "jpeg",
                        "png",
                        "gif",
                        "bmp",
                        "webp",
                        "svg",
                    ];

                    if (imageExtensions.includes(extension)) {
                        fileType =
                            "image/" +
                            (extension === "jpg" ? "jpeg" : extension);
                    } else {
                        fileType = "application/octet-stream";
                    }

                    const mockFile = {
                        name: fileName,
                        size: 500000, // Default size
                        type: fileType,
                        accepted: true,
                        processing: false,
                        upload: { progress: 100 },
                        status: Dropzone.SUCCESS,
                        tempPath: filePath,
                        isExisting: true,
                    };

                    // Add file to Dropzone
                    dz.emit("addedfile", mockFile);
                    dz.emit("complete", mockFile);

                    // Process preview immediately
                    setTimeout(function () {
                        if (mockFile.previewElement) {
                            // For existing files, always show just a file icon instead of loading the image
                            const imageElement =
                                mockFile.previewElement.querySelector(
                                    ".dz-image"
                                );
                            if (imageElement) {
                                // Always show file icon for existing files
                                const extension = fileName
                                    .split(".")
                                    .pop()
                                    .toLowerCase();
                                const iconSvg = getFileIconSvg(extension);
                                imageElement.innerHTML = iconSvg;
                            }

                            // Style the preview as successful
                            mockFile.previewElement.classList.remove(
                                "dz-error",
                                "dz-processing"
                            );
                            mockFile.previewElement.classList.add(
                                "dz-success",
                                "filex-preview-existing"
                            );

                            // Hide progress bar
                            const progressElement =
                                mockFile.previewElement.querySelector(
                                    ".dz-progress"
                                );
                            if (progressElement) {
                                progressElement.style.display = "none";
                            }
                        }
                    }, 200);
                });
            }

            // Add event delegation for remove and retry buttons
            filexElement.addEventListener("click", function (e) {
                if (
                    e.target.matches(".dz-remove") ||
                    e.target.closest(".dz-remove")
                ) {
                    e.preventDefault();
                    e.stopPropagation();
                    const removeButton = e.target.matches(".dz-remove")
                        ? e.target
                        : e.target.closest(".dz-remove");
                    const preview = removeButton.closest(".dz-preview");
                    if (preview && preview._file) {
                        myFilex.removeFile(preview._file);
                    }
                }

                if (
                    e.target.matches(".dz-retry") ||
                    e.target.closest(".dz-retry")
                ) {
                    e.preventDefault();
                    e.stopPropagation();
                    const retryButton = e.target.matches(".dz-retry")
                        ? e.target
                        : e.target.closest(".dz-retry");
                    const preview = retryButton.closest(".dz-preview");
                    if (preview && preview._file) {
                        retryUpload(preview._file);
                    }
                }
            });

            // Store reference for external access
            window[componentId] = myFilex;

            // Helper functions
            function retryUpload(file) {
                const currentRetries = retryCount[file.name] || 0;
                if (currentRetries >= 3) {
                    if (config.showErrorNotifications !== false) {
                        showErrorMessage(`Maximum retry attempts reached for ${file.name}`, componentId, config.errorTimeout);
                    }
                    return;
                }

                retryCount[file.name] = currentRetries + 1;

                // Hide retry button
                const retryButton = file.previewElement
                    ? file.previewElement.querySelector(".dz-retry")
                    : null;
                if (retryButton) {
                    retryButton.style.display = "none";
                }

                // Reset file status
                file.status = Dropzone.QUEUED;
                if (file.previewElement) {
                    file.previewElement.classList.remove("dz-error");
                }

                // Remove from failed files
                const failedIndex = failedFiles.findIndex(
                    (f) => f.name === file.name
                );
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
                if (!hiddenInputsContainer) return;

                // Get existing file paths from existing inputs
                const existingInputs = hiddenInputsContainer.querySelectorAll(
                    ".existing-file-input"
                );
                const existingFilePaths = Array.from(existingInputs).map(
                    (input) => input.value
                );

                // Clear all uploaded file inputs (keep existing file inputs)
                const uploadedInputs = hiddenInputsContainer.querySelectorAll(
                    ".uploaded-file-input"
                );
                uploadedInputs.forEach((input) => input.remove());

                // Add inputs only for newly uploaded files (not existing ones)
                const uniqueUploadedFiles = [...new Set(uploadedFiles)]; // Remove duplicates
                uniqueUploadedFiles.forEach(function (filePath) {
                    // Skip if this file is already in existing inputs
                    if (existingFilePaths.includes(filePath)) {
                        return;
                    }

                    const input = document.createElement("input");
                    input.type = "hidden";
                    input.name = hiddenInputName;
                    input.value = filePath;
                    input.className = "uploaded-file-input";
                    hiddenInputsContainer.appendChild(input);
                });
            }

            function updateFormState() {
                if (activeUploads > 0) {
                    // Disable submit buttons
                    submitButtons.forEach((btn) => {
                        btn.disabled = true;
                        if (!btn.dataset.originalText) {
                            btn.dataset.originalText = btn.textContent;
                        }
                        btn.innerHTML =
                            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" class="spinning-icon"><path fill="currentColor" d="M12,4V2A10,10 0 0,0 2,12H4A8,8 0 0,1 12,4Z"/></svg> Uploading...';
                    });
                } else {
                    // Enable submit buttons
                    submitButtons.forEach((btn) => {
                        btn.disabled = false;
                        if (btn.dataset.originalText) {
                            btn.textContent = btn.dataset.originalText;
                            delete btn.dataset.originalText;
                        }
                    });
                }
            }

            function updateStatus() {
                if (!statusElement) return;

                // Cache status elements
                const uploadingText =
                    statusElement.querySelector(".uploading-text");
                const completedText =
                    statusElement.querySelector(".completed-text");
                const errorText = statusElement.querySelector(".error-text");

                if (activeUploads > 0) {
                    statusElement.style.display = "block";
                    if (uploadingText) uploadingText.style.display = "inline";
                    if (completedText) completedText.style.display = "none";
                    if (errorText) errorText.style.display = "none";
                } else if (failedFiles.length > 0) {
                    statusElement.style.display = "block";
                    if (uploadingText) uploadingText.style.display = "none";
                    if (completedText) completedText.style.display = "none";
                    if (errorText) {
                        errorText.style.display = "inline";
                        const errorMessage =
                            errorText.querySelector(".error-message");
                        if (errorMessage) {
                            errorMessage.textContent =
                                failedFiles.length +
                                " file(s) failed to upload";
                        }
                    }
                } else if (uploadedFiles.length > 0) {
                    statusElement.style.display = "block";
                    if (uploadingText) uploadingText.style.display = "none";
                    if (completedText) completedText.style.display = "inline";
                    if (errorText) errorText.style.display = "none";
                } else {
                    statusElement.style.display = "none";
                }
            }

            // Form validation helper
            if (parentForm) {
                parentForm.addEventListener("submit", function (e) {
                    const isRequired =
                        filexElement.classList.contains("required");

                    if (isRequired && uploadedFiles.length === 0) {
                        e.preventDefault();
                        if (config.showErrorNotifications !== false) {
                            showErrorMessage('Please upload at least one file before submitting', componentId, config.errorTimeout);
                        }
                        return false;
                    }

                    if (activeUploads > 0) {
                        e.preventDefault();
                        if (config.showErrorNotifications !== false) {
                            showErrorMessage('Please wait for all files to finish uploading before submitting', componentId, config.errorTimeout);
                        }
                        return false;
                    }

                    if (failedFiles.length > 0) {
                        e.preventDefault();
                        const failedFileNames = failedFiles.map(f => f.name).join(', ');
                        const confirmMessage = `Some files failed to upload (${failedFileNames}). Do you want to continue without them?`;
                        
                        if (confirm(confirmMessage)) {
                            // Allow form submission
                            return true;
                        } else {
                            if (config.showErrorNotifications !== false) {
                                showErrorMessage('Please fix upload errors or remove failed files before submitting', componentId, config.errorTimeout);
                            }
                            return false;
                        }
                    }
                });
            }

            // Expose helper functions for external use
            window[componentId + "_helpers"] = {
                getUploadedFiles: () => uploadedFiles,
                getFailedFiles: () => failedFiles,
                retryFailedUploads: () => {
                    failedFiles.forEach((failed) => {
                        retryUpload(failed.file);
                    });
                },
                clearAll: () => {
                    myFilex.removeAllFiles();
                    uploadedFiles = [];
                    failedFiles = [];
                    updateHiddenInputs();
                    updateStatus();
                },
            };
        });
    }

    /**
     * Get file icon SVG based on file extension
     */
    function getFileIconSvg(extension) {
        const iconMap = {
            pdf: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48"><path fill="#dc3545" d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/><text x="12" y="16" text-anchor="middle" fill="white" font-size="6" font-weight="bold">PDF</text></svg>',
            doc: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48"><path fill="#2b579a" d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/><text x="12" y="16" text-anchor="middle" fill="white" font-size="5" font-weight="bold">DOC</text></svg>',
            docx: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48"><path fill="#2b579a" d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/><text x="12" y="16" text-anchor="middle" fill="white" font-size="5" font-weight="bold">DOC</text></svg>',
            xls: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48"><path fill="#107c41" d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/><text x="12" y="16" text-anchor="middle" fill="white" font-size="6" font-weight="bold">XLS</text></svg>',
            xlsx: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48"><path fill="#107c41" d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/><text x="12" y="16" text-anchor="middle" fill="white" font-size="6" font-weight="bold">XLS</text></svg>',
            ppt: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48"><path fill="#d24726" d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/><text x="12" y="16" text-anchor="middle" fill="white" font-size="6" font-weight="bold">PPT</text></svg>',
            pptx: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48"><path fill="#d24726" d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/><text x="12" y="16" text-anchor="middle" fill="white" font-size="6" font-weight="bold">PPT</text></svg>',
            txt: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48"><path fill="#6c757d" d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/><text x="12" y="16" text-anchor="middle" fill="white" font-size="6" font-weight="bold">TXT</text></svg>',
            zip: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48"><path fill="#6c757d" d="M14,17H12V15H14M14,13H12V11H14M12,9H14V7H12M12,19H14V17H12M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/><text x="12" y="16" text-anchor="middle" fill="white" font-size="6" font-weight="bold">ZIP</text></svg>',
            jpg: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48"><path fill="#6f42c1" d="M8.5,13.5L11,16.5L14.5,12L19,18H5M21,19V5C21,3.89 20.1,3 19,3H5A2,2 0 0,0 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19Z"/></svg>',
            jpeg: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48"><path fill="#6f42c1" d="M8.5,13.5L11,16.5L14.5,12L19,18H5M21,19V5C21,3.89 20.1,3 19,3H5A2,2 0 0,0 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19Z"/></svg>',
            png: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48"><path fill="#6f42c1" d="M8.5,13.5L11,16.5L14.5,12L19,18H5M21,19V5C21,3.89 20.1,3 19,3H5A2,2 0 0,0 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19Z"/></svg>',
            mp3: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48"><path fill="#20c997" d="M14,3.23V5.29C16.89,6.15 19,8.83 19,12C19,15.17 16.89,17.84 14,18.7V20.77C18,19.86 21,16.28 21,12C21,7.72 18,4.14 14,3.23M16.5,12C16.5,10.23 15.5,8.71 14,7.97V16C15.5,15.29 16.5,13.76 16.5,12M3,9V15H7L12,20V4L7,9H3Z"/></svg>',
            mp4: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48"><path fill="#fd7e14" d="M17,10.5V7A1,1 0 0,0 16,6H4A1,1 0 0,0 3,7V17A1,1 0 0,0 4,18H16A1,1 0 0,0 17,17V13.5L21,17.5V6.5L17,10.5Z"/></svg>',
        };
        return (
            iconMap[extension.toLowerCase()] ||
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48"><path fill="#6c757d" d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/></svg>'
        );
    }

    /**
     * Get CSRF token from meta tag or fallback
     */
    function getCSRFToken() {
        const metaTag = document.querySelector('meta[name="csrf-token"]');
        return metaTag ? metaTag.getAttribute("content") : "";
    }

    // Initialize when DOM is ready
    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initializeFilex);
    } else {
        initializeFilex();
    }

    // Expose to global scope for external access
    window.Filex = {
        // Core functionality
        initialize: initializeFilex,
        getFileIconSvg: getFileIconSvg,
        makeAjaxRequest: makeAjaxRequest,
        
        // Notification system
        showError: showErrorMessage,
        showSuccess: showSuccessMessage,
        showWarning: showWarningMessage,
        showInfo: showInfoMessage,
        showNotification: showNotification,
        removeNotification: removeNotification,
        
        // Legacy aliases for backward compatibility
        showErrorMessage: showErrorMessage,
        showSuccessMessage: showSuccessMessage
    };
})(window, document);
