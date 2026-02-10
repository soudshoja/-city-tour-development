<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class DocumentProcessingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by webhook signature middleware
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $allowedMimeTypes = config('webhook.file_validation.allowed_mime_types', []);
        $maxFileSize = config('webhook.file_validation.max_file_size', 10485760); // 10MB

        return [
            'document_id' => 'required|uuid',
            'supplier_id' => 'required|string|max:255',
            'company_id' => 'required|integer|exists:companies,id',
            'document_type' => 'required|in:AIR,PDF,image,email',
            'file_path' => ['required', 'string', function ($attribute, $value, $fail) {
                if (!$this->isValidFilePath($value)) {
                    $fail('The file path contains invalid characters or directory traversal attempts.');
                }
            }],
            'file_url' => 'nullable|url',
            'file' => [
                'nullable',
                'file',
                'max:' . ($maxFileSize / 1024), // Laravel expects KB
                'mimes:' . implode(',', $this->extractExtensionsFromMimeTypes($allowedMimeTypes)),
            ],
            'metadata' => 'nullable|array',
            'callback_url' => 'nullable|url',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'document_id.required' => 'Document ID is required for tracking.',
            'document_id.uuid' => 'Document ID must be a valid UUID.',
            'supplier_id.required' => 'Supplier ID is required.',
            'company_id.exists' => 'The specified company does not exist.',
            'document_type.in' => 'Document type must be one of: AIR, PDF, image, email.',
            'file_path.required' => 'File path is required.',
            'file.max' => 'File size must not exceed ' . (config('webhook.file_validation.max_file_size') / 1048576) . 'MB.',
            'file.mimes' => 'File type is not supported.',
        ];
    }

    /**
     * Validate file path to prevent directory traversal
     */
    private function isValidFilePath(string $path): bool
    {
        // Check for directory traversal attempts
        if (str_contains($path, '..') || str_contains($path, './')) {
            return false;
        }

        // Check for absolute paths trying to escape base directory
        if (str_starts_with($path, '/') && !str_starts_with($path, '/home/') && !str_starts_with($path, 's3://')) {
            return false;
        }

        // Check for null bytes
        if (str_contains($path, "\0")) {
            return false;
        }

        // Allow S3 paths, relative paths, or safe absolute paths
        return true;
    }

    /**
     * Extract file extensions from MIME types for Laravel validation
     */
    private function extractExtensionsFromMimeTypes(array $mimeTypes): array
    {
        return config('webhook.file_validation.allowed_extensions', [
            'pdf', 'jpg', 'jpeg', 'png', 'gif',
            'doc', 'docx', 'xls', 'xlsx', 'eml',
        ]);
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422)
        );
    }
}
