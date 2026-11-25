<?php

namespace App\Jobs;

use App\Enums\DocumentFormat;
use App\Models\Document;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\WithoutRelations;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Normalizer;
use RuntimeException;

/**
 * Reads the text of an uploaded document from the disk and keeps it page by page:
 * each page of a PDF becomes one row, a Markdown file is page 1.
 */
class ExtractDocumentText implements ShouldQueue
{
    use Queueable;

    /**
     * The queue keeps only the id of the document; workspace and path are read
     * from MySQL when the job runs, since there is no login in the background.
     */
    public function __construct(#[WithoutRelations] public Document $document) {}

    public function handle(): void
    {
        $pages = collect($this->readPages())
            ->map($this->clean(...))
            ->map(fn (string $text, int $index): array => ['page' => $index + 1, 'text' => $text]);

        $this->document->pages()->createMany($pages);
    }

    /**
     * The raw text of each page, in the order of the document. pdftotext ends every page
     * of a PDF with a form feed, also a page without text, so the pieces between them
     * are its pages. A Markdown file is one page.
     *
     * @return list<string>
     */
    private function readPages(): array
    {
        $disk = Storage::disk('local');

        if ($this->document->format === DocumentFormat::Pdf) {
            $output = Process::run(['pdftotext', '-enc', 'UTF-8', $disk->path($this->document->path), '-'])
                ->throw()
                ->output();

            $pages = explode("\f", $output);
            array_pop($pages);

            return $pages;
        } else {
            return [(string) $disk->get($this->document->path)];
        }
    }

    /**
     * Unifies line endings and Unicode form, drops BOM, null characters and spaces at
     * line ends, and shortens runs of more than two blank lines to two. Markdown markup stays.
     */
    private function clean(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = str_replace(["\u{FEFF}", "\0"], '', mb_scrub($text, 'UTF-8'));
        $text = Normalizer::normalize($text, Normalizer::FORM_C);

        if ($text === false) {
            throw new RuntimeException('Der Text ließ sich nicht in Unicode NFC bringen.');
        }

        $text = preg_replace('/[ \t]+$/m', '', $text);

        return preg_replace("/\n{4,}/", "\n\n\n", $text);
    }
}
