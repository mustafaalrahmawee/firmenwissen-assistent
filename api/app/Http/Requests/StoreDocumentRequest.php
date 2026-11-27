<?php

namespace App\Http\Requests;

use Closure;
use finfo;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class StoreDocumentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Every person of a workspace may upload to it.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Only the file is read. A workspace named in the request is ignored, the document
     * always belongs to the workspace of the logged-in user.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'bail',
                'required',
                'file',
                'extensions:pdf',
                'max:'.(config('documents.max_size_mb') * 1024),
                $this->contentIsPdf(...),
            ],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Bitte wähle eine Datei aus.',
            'file.file' => 'Die Datei ließ sich nicht hochladen. Bitte versuche es erneut.',
            'file.uploaded' => 'Die Datei ließ sich nicht hochladen. Bitte versuche es erneut.',
            'file.extensions' => 'Erlaubt sind nur PDF-Dateien (.pdf).',
            'file.max' => 'Die Datei ist zu groß. Erlaubt sind höchstens '.config('documents.max_size_mb').' MB.',
        ];
    }

    /**
     * The type the browser names is not trusted: the file must be recognised as PDF by its bytes.
     */
    private function contentIsPdf(string $attribute, UploadedFile $file, Closure $fail): void
    {
        if ((new finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath()) !== 'application/pdf') {
            $fail('Die Datei endet auf .pdf, ist aber kein PDF.');
        }
    }
}
