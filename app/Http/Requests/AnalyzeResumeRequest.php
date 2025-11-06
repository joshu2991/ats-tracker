<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;

class AnalyzeResumeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'resume' => [
                'required',
                'file',
                'mimes:pdf,docx',
                'mimetypes:application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'max:5120',
            ],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $file = $this->file('resume');
            if (! $file) {
                return;
            }

            // Verify actual file content (magic bytes) - not just extension
            $this->validateFileContent($file, $validator);
        });
    }

    /**
     * Validate file content by checking magic bytes.
     */
    protected function validateFileContent($file, $validator): void
    {
        $filePath = $file->getRealPath();
        if (! $filePath || ! file_exists($filePath)) {
            return;
        }

        $handle = fopen($filePath, 'rb');
        if (! $handle) {
            $validator->errors()->add('resume', 'Unable to read file for validation.');

            return;
        }

        $header = fread($handle, 8);
        fclose($handle);

        if (! $header) {
            $validator->errors()->add('resume', 'File appears to be empty or corrupted.');

            return;
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $mimeType = $file->getMimeType();

        // Check PDF magic bytes: %PDF
        if (($extension === 'pdf' || $mimeType === 'application/pdf')) {
            if (str_starts_with($header, '%PDF') === false) {
                Log::warning('Invalid PDF file detected', [
                    'ip' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'filename' => $file->getClientOriginalName(),
                    'extension' => $extension,
                    'mime_type' => $mimeType,
                    'header' => bin2hex($header),
                ]);
                $validator->errors()->add('resume', 'The uploaded file is not a valid PDF file.');
            }
        }

        // Check DOCX magic bytes: PK (ZIP format)
        if (($extension === 'docx' || str_contains($mimeType, 'wordprocessingml'))) {
            if (str_starts_with($header, 'PK') === false) {
                Log::warning('Invalid DOCX file detected', [
                    'ip' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'filename' => $file->getClientOriginalName(),
                    'extension' => $extension,
                    'mime_type' => $mimeType,
                    'header' => bin2hex($header),
                ]);
                $validator->errors()->add('resume', 'The uploaded file is not a valid DOCX file.');
            }
        }

        // Additional security: Check for suspicious file types
        $suspiciousSignatures = [
            "\x4d\x5a" => 'PE executable',
            "\x7f\x45\x4c\x46" => 'ELF executable',
            "\xca\xfe\xba\xbe" => 'Java class file',
        ];

        foreach ($suspiciousSignatures as $signature => $type) {
            if (str_starts_with($header, $signature)) {
                Log::alert('Suspicious file upload blocked', [
                    'ip' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'filename' => $file->getClientOriginalName(),
                    'file_type' => $type,
                    'header' => bin2hex($header),
                ]);
                $validator->errors()->add('resume', 'The uploaded file type is not allowed.');
                break;
            }
        }
    }

    /**
     * Get custom error messages for validation.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'resume.required' => 'Please select a resume file to upload.',
            'resume.file' => 'The uploaded file is invalid.',
            'resume.mimes' => 'The resume must be a PDF or DOCX file.',
            'resume.max' => 'The resume file must not be larger than 5MB.',
        ];
    }
}
