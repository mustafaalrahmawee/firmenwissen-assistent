<?php

namespace App\Jobs;

use App\Enums\DocumentFormat;
use App\Enums\DocumentStatus;
use App\Exceptions\UnreadableDocument;
use App\Models\Document;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\WithoutRelations;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
     * A run is stopped after this many seconds, below the retry_after of the queue (90 s),
     * so the queue never hands out the job again while a run is still going.
     */
    public int $timeout = 80;

    /**
     * The lock of a run that died expires between the timeout and the retry_after of
     * the queue, so the next attempt of the same job finds it gone.
     */
    public const int LOCK_SECONDS = 85;

    /**
     * The exit code of pdftotext for "error opening a PDF file": the file is damaged
     * or needs a password. Every other error says nothing about the file.
     */
    private const int PDFTOTEXT_OPEN_ERROR = 1;

    /**
     * The queue keeps only the id of the document; workspace and path are read
     * from MySQL when the job runs, since there is no login in the background.
     */
    public function __construct(#[WithoutRelations] public Document $document) {}

    /**
     * Only one run per document at a time. A second run that finds the lock is dropped,
     * not released, so it uses up no attempt; the running one reads the same file.
     *
     * @return list<object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->document->id))->dontRelease()->expireAfter(self::LOCK_SECONDS)];
    }

    /**
     * Each run replaces all pages of the document in one transaction, so a repeated
     * run leaves every page once and an interrupted one leaves the old pages whole.
     * A document that cannot be opened or has no readable text fails at once, without
     * a further attempt, since reading the same file again gives the same result.
     */
    public function handle(): void
    {
        $this->document->status = DocumentStatus::Processing;
        $this->document->failure_reason = null;
        $this->document->save();

        try {
            $texts = collect($this->readPages())->map($this->clean(...));

            $isBlank = $texts->every(fn (string $text): bool => preg_match('/[^\s\p{Z}\p{Cf}]/u', $text) === 0);

            if ($isBlank) {
                throw $this->document->format === DocumentFormat::Pdf ? UnreadableDocument::noTextInPdf() : UnreadableDocument::emptyMarkdown();
            }
        } catch (UnreadableDocument $exception) {
            $this->failDocument($exception->getMessage());

            return;
        }

        $pages = $texts->map(fn (string $text, int $index): array => ['page' => $index + 1, 'text' => $text]);

        DB::transaction(function () use ($pages): void {
            $this->document->pages()->delete();
            $this->document->pages()->createMany($pages);
        });
    }

    /**
     * Marks the document as failed with a reason in German and removes the pages of an
     * earlier run, so a failed document never has text. The file stays on the disk.
     */
    private function failDocument(string $reason): void
    {
        DB::transaction(function () use ($reason): void {
            $this->document->pages()->delete();
            $this->document->status = DocumentStatus::Failed;
            $this->document->failure_reason = $reason;
            $this->document->save();
        });

        Log::warning('Dokument ist gescheitert.', ['document_id' => $this->document->id, 'reason' => $reason]);
    }

    /**
     * The raw text of each page, in the order of the document. pdftotext ends every page
     * of a PDF with a form feed, also a page without text, so the pieces between them
     * are its pages. A Markdown file is one page. A missing file, or a PDF that needs a
     * password or is damaged, is unreadable; any other error of pdftotext or of the disk
     * is thrown to be retried.
     *
     * @return list<string>
     *
     * @throws UnreadableDocument
     */
    private function readPages(): array
    {
        $disk = Storage::disk('local');

        if (! $disk->exists($this->document->path)) {
            throw UnreadableDocument::missingFile();
        }

        if ($this->document->format === DocumentFormat::Pdf) {
            $result = Process::run(['pdftotext', '-enc', 'UTF-8', $disk->path($this->document->path), '-']);

            if ($result->exitCode() === self::PDFTOTEXT_OPEN_ERROR) {
                $needsPassword = str_contains($result->errorOutput(), 'Incorrect password');

                throw $needsPassword ? UnreadableDocument::protectedPdf() : UnreadableDocument::damagedPdf();
            }

            $output = $result->throw()->output();

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
