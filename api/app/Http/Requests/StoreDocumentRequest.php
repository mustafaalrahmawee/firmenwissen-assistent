<?php

namespace App\Http\Requests;

use App\Enums\DocumentFormat;
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
                'extensions:pdf,md,markdown',
                'max:'.(config('documents.max_size_mb') * 1024),
                $this->contentMatchesExtension(...),
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
            'file.extensions' => 'Erlaubt sind nur PDF- und Markdown-Dateien (.pdf, .md, .markdown).',
            'file.max' => 'Die Datei ist zu groß. Erlaubt sind höchstens '.config('documents.max_size_mb').' MB.',
        ];
    }

    /**
     * The type the browser names is not trusted: a PDF must be recognised as PDF by its bytes.
     * Markdown has no mark of its own: it must be UTF-8 text without null characters and must
     * not be recognised as PDF, since a PDF can consist of ASCII text only.
     */
    private function contentMatchesExtension(string $attribute, UploadedFile $file, Closure $fail): void
    {
        $path = $file->getRealPath();
        $isPdf = (new finfo(FILEINFO_MIME_TYPE))->file($path) === 'application/pdf';

        if (DocumentFormat::fromExtension($file->getClientOriginalExtension()) === DocumentFormat::Pdf) {
            if (! $isPdf) {
                $fail('Die Datei endet auf .pdf, ist aber kein PDF.');
            }

            return;
        }

        if ($isPdf) {
            $fail('Die Datei ist ein PDF, endet aber nicht auf .pdf.');

            return;
        }

        $content = file_get_contents($path);

        if (! mb_check_encoding($content, 'UTF-8') || str_contains($content, "\0")) {
            $fail('Die Datei enthält keinen Text in UTF-8.');
        }
    }
}
