<?php

namespace App\Jobs;

use App\Models\Document;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

/**
 * Reads the text of an uploaded PDF in the background, page by page.
 */
class ExtractDocumentText implements ShouldQueue
{
    use Queueable;

    public function __construct(public Document $document) {}

    /**
     * Store every page that has text with its page number in the PDF. pdftotext ends every page
     * with a form feed, so the piece after the last one is no page. A word split at the end of
     * a line ("Verbin-" and "dung") is joined again. Spaces and control characters alone are
     * no text: a page needs a letter or a digit, otherwise it is left out.
     */
    public function handle(): void
    {
        $output = Process::run(['pdftotext', '-enc', 'UTF-8', Storage::disk('local')->path($this->document->path), '-'])
            ->throw()
            ->output();

        $pages = collect(explode("\f", $output))
            ->slice(0, -1)
            ->map(fn (string $text, int $index) => [
                'page' => $index + 1,
                'text' => trim(preg_replace('/(?<=\p{L})-[ \t]*\R[ \t]*(?=\p{Ll})/u', '', $text)),
            ])
            ->filter(fn (array $page) => preg_match('/[\p{L}\p{N}]/u', $page['text']) === 1)
            ->values();

        $this->document->pages()->createMany($pages->all());
    }
}
