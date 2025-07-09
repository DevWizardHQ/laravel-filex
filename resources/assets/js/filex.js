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

(function(window, document) {
    'use strict';

    /**
     * Initialize Filex uploader instances
     */
    function initializeFilex() {
        // Check if Dropzone is available
        if (typeof Dropzone === 'undefined') {
            console.error('Dropzone.js library not loaded');
            return;
        }

        // Find all filex uploader elements
        const filexElements = document.querySelectorAll('.filex-uploader[data-component-id]');
        
        filexElements.forEach(function(filexElement) {
            const componentId = filexElement.dataset.componentId;
            const hiddenInputsContainer = document.getElementById(componentId + '-hidden-inputs');
            const statusElement = document.getElementById(componentId + '-status');

            if (!filexElement) {
                console.error('Filex element not found:', componentId);
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

            // Get configuration from data attributes
            const config = {
                maxFilesize: parseFloat(filexElement.dataset.maxFilesize) || 10,
                maxFiles: filexElement.dataset.maxFiles ? parseInt(filexElement.dataset.maxFiles) : null,
                acceptedFiles: filexElement.dataset.acceptedFiles || null,
                multiple: filexElement.dataset.multiple === 'true',
                name: filexElement.dataset.name || 'files',
                disk: filexElement.dataset.disk || 'public',
                targetDirectory: filexElement.dataset.targetDirectory || 'uploads'
            };

            // Initialize existing files from hidden inputs
            const existingInputs = hiddenInputsContainer ? hiddenInputsContainer.querySelectorAll('.existing-file-input') : [];
            const existingFiles = Array.from(existingInputs).map(input => input.value);
            
            if (existingFiles && existingFiles.length > 0) {
                uploadedFiles = [...existingFiles];
            }

            // Create hidden input name
            const hiddenInputName = config.multiple ? config.name + '[]' : config.name;

            // Dropzone configuration
            const dropzoneConfig = {
                url: window.filexRoutes ? window.filexRoutes.upload : '/filex/upload/temp',
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': getCSRFToken()
                },
                paramName: 'file',
                maxFilesize: config.maxFilesize,
                maxFiles: config.maxFiles,
                acceptedFiles: config.acceptedFiles,
                addRemoveLinks: true,
                autoProcessQueue: true,
                parallelUploads: 2,
                uploadMultiple: false,
                chunking: true,
                chunkSize: 1048576, // 1MB chunks
                parallelChunkUploads: false,
                retryChunks: true,
                retryChunksLimit: 3,
                forceChunking: function(file) {
                    return file.size > 50 * 1024 * 1024; // 50MB threshold
                },
                retries: 3,
                timeout: 30000,
                thumbnailWidth: 120,
                thumbnailHeight: 120,
                thumbnailMethod: 'contain',
                dictRemoveFile: '',

                // Override file type validation
                accept: function(file, done) {
                    done();
                },

                init: function() {
                    const dz = this;

                    // Client-side validation function
                    function validateFile(file) {
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
                                    const pattern = acceptedType.replace('*', '');
                                    if (file.type && file.type.startsWith(pattern)) {
                                        isValidType = true;
                                        break;
                                    }
                                } else if (acceptedType.startsWith('.')) {
                                    const fileExtension = '.' + file.name.split('.').pop().toLowerCase();
                                    if (fileExtension === acceptedType.toLowerCase()) {
                                        isValidType = true;
                                        break;
                                    }
                                } else if (file.type === acceptedType) {
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

                                // Show error mark
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

                        // Add file icon if needed
                        if (mockFile.previewElement) {
                            const extension = fileName.split('.').pop().toLowerCase();
                            const iconSvg = getFileIconSvg(extension);
                            const imageElement = mockFile.previewElement.querySelector('.dz-image');
                            if (imageElement && (!mockFile.type || !mockFile.type.startsWith('image/'))) {
                                imageElement.innerHTML = iconSvg;
                            }
                        }

                        // Add remove functionality for existing files
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
                        // Add custom styling classes for new files
                        if (file.previewElement && !file.isExisting) {
                            file.previewElement.classList.add('filex-preview-new');

                            // Add success and error marks
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
                        }

                        // Perform client-side validation
                        if (!file.isExisting && !validateFile(file)) {
                            return;
                        }

                        if (file.status !== Dropzone.SUCCESS && !file.isExisting) {
                            activeUploads++;
                            updateFormState();
                            updateStatus();
                        }

                        // Add file icon for non-image files
                        if (!file.type || !file.type.startsWith('image/')) {
                            const extension = file.name.split('.').pop().toLowerCase();
                            const iconSvg = getFileIconSvg(extension);
                            const imageElement = file.previewElement ? file.previewElement
                                .querySelector('.dz-image') : null;
                            if (imageElement) {
                                imageElement.innerHTML = iconSvg;
                            }
                        }

                        // Add retry button
                        if (file.previewElement) {
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

                    // Success event
                    dz.on('success', function(file, response) {
                        if (response && response.success && response.tempPath) {
                            file.tempPath = response.tempPath;
                            file.originalName = response.originalName;
                            file.serverSize = response.size;

                            // Mark file as successfully uploaded
                            file.status = Dropzone.SUCCESS;
                            if (file.previewElement) {
                                // Cache DOM elements
                                const preview = file.previewElement;
                                const progressEl = preview.querySelector('.dz-progress');
                                const errorEl = preview.querySelector('.dz-error-message');
                                const retryBtn = preview.querySelector('.dz-retry');
                                const successMark = preview.querySelector('.dz-success-mark');
                                const errorMark = preview.querySelector('.dz-error-mark');

                                // Clear error states and add success state
                                preview.classList.remove('dz-processing', 'dz-error');
                                preview.classList.add('dz-success');

                                // Force success state to persist
                                setTimeout(() => {
                                    if (preview && file.status === Dropzone.SUCCESS) {
                                        preview.classList.remove('dz-processing', 'dz-error');
                                        preview.classList.add('dz-success');
                                    }
                                }, 100);

                                // Hide progress bar
                                if (progressEl) {
                                    progressEl.style.display = 'none';
                                }

                                // Remove error messages
                                if (errorEl) {
                                    errorEl.style.display = 'none';
                                    errorEl.textContent = '';
                                }

                                // Hide retry button
                                if (retryBtn) {
                                    retryBtn.style.display = 'none';
                                }

                                // Show success mark and hide error mark
                                if (successMark) {
                                    successMark.style.display = 'flex';
                                    successMark.style.visibility = 'visible';
                                    successMark.style.opacity = '1';
                                }

                                if (errorMark) {
                                    errorMark.style.display = 'none';
                                    errorMark.style.visibility = 'hidden';
                                    errorMark.style.opacity = '0';
                                }
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
                        } else {
                            // Handle invalid response
                            if (file.previewElement) {
                                file.previewElement.classList.add('dz-error');
                                const errorMsg = (response && response.message) ? response.message : 'Upload failed';
                                const errorElement = file.previewElement.querySelector('.dz-error-message');
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
                            // Cache DOM elements
                            const preview = file.previewElement;
                            const retryBtn = preview.querySelector('.dz-retry');
                            const errorMark = preview.querySelector('.dz-error-mark');
                            const successMark = preview.querySelector('.dz-success-mark');

                            // Clear success state and add error state
                            preview.classList.remove('dz-success', 'dz-processing');
                            preview.classList.add('dz-error');

                            // Show retry button
                            if (retryBtn) {
                                retryBtn.style.display = 'flex';
                            }

                            // Show error mark and hide success mark
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

                        updateFormState();
                        updateStatus();
                    });

                    // Complete event
                    dz.on('complete', function(file) {
                        // Ensure proper visual state based on file status
                        if (file.previewElement) {
                            if (file.status === Dropzone.SUCCESS) {
                                file.previewElement.classList.remove('dz-processing', 'dz-error');
                                file.previewElement.classList.add('dz-success');
                            } else if (file.status === Dropzone.ERROR) {
                                file.previewElement.classList.remove('dz-processing', 'dz-success');
                                file.previewElement.classList.add('dz-error');
                            }
                        }
                    });

                    // Removed file event
                    dz.on('removedfile', function(file) {
                        if (file.tempPath) {
                            // Remove from uploaded files array
                            const index = uploadedFiles.indexOf(file.tempPath);
                            if (index > -1) {
                                uploadedFiles.splice(index, 1);
                            }

                            // Send delete request to server (only for uploaded files, not existing ones)
                            if (!file.isExisting && window.filexRoutes && window.filexRoutes.delete) {
                                const filename = file.tempPath.split('/').pop();
                                const deleteUrl = window.filexRoutes.delete.replace('__FILENAME__', encodeURIComponent(filename));

                                fetch(deleteUrl, {
                                    method: 'DELETE',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': getCSRFToken()
                                    }
                                }).catch(error => {
                                    console.error('Failed to delete temp file:', error);
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
                    });

                    // Max files exceeded event
                    dz.on('maxfilesexceeded', function(file) {
                        dz.removeFile(file);
                        alert('Maximum number of files exceeded');
                    });
                }
            };

            // Initialize Dropzone
            const myFilex = new Dropzone(filexElement, dropzoneConfig);

            // Add event delegation for remove and retry buttons
            filexElement.addEventListener('click', function(e) {
                if (e.target.matches('.dz-remove') || e.target.closest('.dz-remove')) {
                    e.preventDefault();
                    e.stopPropagation();
                    const removeButton = e.target.matches('.dz-remove') ? e.target : e.target.closest('.dz-remove');
                    const preview = removeButton.closest('.dz-preview');
                    if (preview && preview._file) {
                        myFilex.removeFile(preview._file);
                    }
                }

                if (e.target.matches('.dz-retry') || e.target.closest('.dz-retry')) {
                    e.preventDefault();
                    e.stopPropagation();
                    const retryButton = e.target.matches('.dz-retry') ? e.target : e.target.closest('.dz-retry');
                    const preview = retryButton.closest('.dz-preview');
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
                if (!hiddenInputsContainer) return;

                // Clear existing hidden inputs (except existing files)
                const existingInputs = hiddenInputsContainer.querySelectorAll('.existing-file-input');
                hiddenInputsContainer.innerHTML = '';

                // Re-add existing file inputs
                existingInputs.forEach(input => hiddenInputsContainer.appendChild(input));

                // Add new file inputs
                uploadedFiles.forEach(function(filePath) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = hiddenInputName;
                    input.value = filePath;
                    input.className = 'uploaded-file-input';
                    hiddenInputsContainer.appendChild(input);
                });
            }

            function updateFormState() {
                if (activeUploads > 0) {
                    // Disable submit buttons
                    submitButtons.forEach(btn => {
                        btn.disabled = true;
                        if (!btn.dataset.originalText) {
                            btn.dataset.originalText = btn.textContent;
                        }
                        btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" class="spinning-icon"><path fill="currentColor" d="M12,4V2A10,10 0 0,0 2,12H4A8,8 0 0,1 12,4Z"/></svg> Uploading...';
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
            }

            function updateStatus() {
                if (!statusElement) return;

                // Cache status elements
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
                        const errorMessage = errorText.querySelector('.error-message');
                        if (errorMessage) {
                            errorMessage.textContent = failedFiles.length + ' file(s) failed to upload';
                        }
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
                    const isRequired = filexElement.classList.contains('required');
                    
                    if (isRequired && uploadedFiles.length === 0) {
                        e.preventDefault();
                        alert('Please upload at least one file.');
                        return false;
                    }

                    if (activeUploads > 0) {
                        e.preventDefault();
                        alert('Please wait for all files to finish uploading.');
                        return false;
                    }

                    if (failedFiles.length > 0 && !confirm('Some files failed to upload. Do you want to continue without them?')) {
                        e.preventDefault();
                        return false;
                    }
                });
            }

            // Expose helper functions for external use
            window[componentId + '_helpers'] = {
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
    }

    /**
     * Get file icon SVG based on file extension
     */
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
            'zip': '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48"><path fill="#6c757d" d="M14,17H12V15H14M14,13H12V11H14M12,9H14V7H12M12,19H14V17H12M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/><text x="12" y="16" text-anchor="middle" fill="white" font-size="6" font-weight="bold">ZIP</text></svg>',
            'jpg': '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48"><path fill="#6f42c1" d="M8.5,13.5L11,16.5L14.5,12L19,18H5M21,19V5C21,3.89 20.1,3 19,3H5A2,2 0 0,0 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19Z"/></svg>',
            'jpeg': '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48"><path fill="#6f42c1" d="M8.5,13.5L11,16.5L14.5,12L19,18H5M21,19V5C21,3.89 20.1,3 19,3H5A2,2 0 0,0 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19Z"/></svg>',
            'png': '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48"><path fill="#6f42c1" d="M8.5,13.5L11,16.5L14.5,12L19,18H5M21,19V5C21,3.89 20.1,3 19,3H5A2,2 0 0,0 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19Z"/></svg>',
            'mp3': '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48"><path fill="#20c997" d="M14,3.23V5.29C16.89,6.15 19,8.83 19,12C19,15.17 16.89,17.84 14,18.7V20.77C18,19.86 21,16.28 21,12C21,7.72 18,4.14 14,3.23M16.5,12C16.5,10.23 15.5,8.71 14,7.97V16C15.5,15.29 16.5,13.76 16.5,12M3,9V15H7L12,20V4L7,9H3Z"/></svg>',
            'mp4': '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48"><path fill="#fd7e14" d="M17,10.5V7A1,1 0 0,0 16,6H4A1,1 0 0,0 3,7V17A1,1 0 0,0 4,18H16A1,1 0 0,0 17,17V13.5L21,17.5V6.5L17,10.5Z"/></svg>'
        };
        return iconMap[extension.toLowerCase()] || '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48"><path fill="#6c757d" d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/></svg>';
    }

    /**
     * Get CSRF token from meta tag or fallback
     */
    function getCSRFToken() {
        const metaTag = document.querySelector('meta[name="csrf-token"]');
        return metaTag ? metaTag.getAttribute('content') : '';
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeFilex);
    } else {
        initializeFilex();
    }

    // Expose to global scope for external access
    window.Filex = {
        initialize: initializeFilex,
        getFileIconSvg: getFileIconSvg
    };

})(window, document);
