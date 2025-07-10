<?php

namespace DevWizard\Filex\Http\Requests;

use DevWizard\Filex\Rules\ValidFileUpload;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Enhanced form request for file uploads with comprehensive validation
 * 
 * This form request provides:
 * - Multi-layered file validation
 * - Custom error handling
 * - Security checks
 * - Rate limiting awareness
 */
class FileUploadRequest extends FormRequest
{
    protected $fileField;
    protected $allowedExtensions;
    protected $allowedMimeTypes;
    protected $maxFileSize;
    protected $required;
    protected $multiple;

    /**
     * Configure the request for specific file validation
     */
    public function configure(
        string $fileField = 'file', 
        ?array $allowedExtensions = null, 
        ?array $allowedMimeTypes = null,
        ?int $maxFileSize = null,
        bool $required = true,
        bool $multiple = false
    ): self {
        $this->fileField = $fileField;
        $this->allowedExtensions = $allowedExtensions ?? config('filex.allowed_extensions', []);
        $this->allowedMimeTypes = $allowedMimeTypes ?? config('filex.allowed_mime_types', []);
        $this->maxFileSize = $maxFileSize ?? config('filex.max_file_size', 10);
        $this->required = $required;
        $this->multiple = $multiple;
        
        return $this;
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Add your authorization logic here
        // For example, check if user can upload files
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $fileField = $this->fileField ?? 'file';
        
        $rules = [];
        
        if ($this->multiple) {
            // Multiple file uploads (temp paths)
            $rules[$fileField] = $this->required ? ['required', 'array'] : ['nullable', 'array'];
            $rules[$fileField . '.*'] = [
                'string',
                'starts_with:temp/',
                new ValidFileUpload(
                    $this->allowedExtensions,
                    $this->allowedMimeTypes,
                    $this->maxFileSize * 1024 * 1024 // Convert MB to bytes
                )
            ];
        } else {
            // Single file upload
            if ($this->has($fileField) && !is_array($this->input($fileField))) {
                // Direct file upload
                $rules[$fileField] = array_filter([
                    $this->required ? 'required' : 'nullable',
                    'file',
                    'max:' . ($this->maxFileSize * 1024), // Laravel expects KB
                    $this->allowedExtensions ? 'mimes:' . implode(',', $this->allowedExtensions) : null,
                ]);
            } else {
                // Temp file path
                $rules[$fileField] = $this->required ? ['required', 'string'] : ['nullable', 'string'];
                $rules[$fileField] = array_merge($rules[$fileField], [
                    'starts_with:temp/',
                    new ValidFileUpload(
                        $this->allowedExtensions,
                        $this->allowedMimeTypes,
                        $this->maxFileSize * 1024 * 1024
                    )
                ]);
            }
        }

        return $rules;
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        $fileField = $this->fileField ?? 'file';
        
        return [
            $fileField . '.required' => 'Please select a file to upload.',
            $fileField . '.file' => 'The uploaded item must be a valid file.',
            $fileField . '.max' => 'The file size must not exceed ' . $this->maxFileSize . 'MB.',
            $fileField . '.mimes' => 'The file must be of type: ' . implode(', ', $this->allowedExtensions ?? []),
            $fileField . '.starts_with' => 'Invalid file reference.',
            $fileField . '.*.required' => 'Please select files to upload.',
            $fileField . '.*.string' => 'Invalid file reference.',
            $fileField . '.*.starts_with' => 'Invalid file reference.',
            $fileField . '.array' => 'Invalid file data format.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            $this->fileField ?? 'file' => 'file',
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator)
    {
        if ($this->expectsJson()) {
            throw new HttpResponseException(
                response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                    'field' => $this->fileField ?? 'file'
                ], 422)
            );
        }

        parent::failedValidation($validator);
    }

    /**
     * Static factory methods for common scenarios
     */
    public static function forImages(bool $required = true, bool $multiple = false): self
    {
        return (new self())->configure(
            'images',
            ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'],
            5, // 5MB
            $required,
            $multiple
        );
    }

    public static function forDocuments(bool $required = true, bool $multiple = false): self
    {
        return (new self())->configure(
            'documents',
            ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt'],
            [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'text/plain',
            ],
            10, // 10MB
            $required,
            $multiple
        );
    }

    public static function forAvatars(bool $required = true): self
    {
        return (new self())->configure(
            'avatar',
            ['jpg', 'jpeg', 'png'],
            ['image/jpeg', 'image/jpg', 'image/png'],
            2, // 2MB
            $required,
            false
        );
    }

    public static function forArchives(bool $required = true, bool $multiple = false): self
    {
        return (new self())->configure(
            'archives',
            ['zip', 'rar', '7z', 'tar', 'gz'],
            [
                'application/zip',
                'application/x-rar-compressed',
                'application/x-7z-compressed',
                'application/x-tar',
                'application/gzip',
            ],
            50, // 50MB
            $required,
            $multiple
        );
    }

    /**
     * Get validated file data with additional processing
     */
    public function getValidatedFiles(): array
    {
        $validated = $this->validated();
        $fileField = $this->fileField ?? 'file';
        
        $files = $validated[$fileField] ?? [];
        
        if (!is_array($files)) {
            $files = [$files];
        }

        return array_filter($files); // Remove empty values
    }

    /**
     * Check if request contains valid files
     */
    public function hasValidFiles(): bool
    {
        $files = $this->getValidatedFiles();
        return !empty($files);
    }

    /**
     * Get file count
     */
    public function getFileCount(): int
    {
        return count($this->getValidatedFiles());
    }
}
