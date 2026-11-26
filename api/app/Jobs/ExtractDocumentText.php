<?php

namespace App\Jobs;

use App\Enums\DocumentFormat;
use App\Enums\DocumentStatus;
use App\Exceptions\UnreadableDocument;
use App\Models\Document;
use App\Models\DocumentPage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\WithoutRelations;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\UnableToReadFile;
use Normalizer;
use RuntimeException;
use Throwable;

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
     * Any error that is not about the file itself may pass, so a run is tried three times.
     */
    public int $tries = 3;

    /**
     * Seconds to wait before the second and the third attempt, so a passing error has time to end.
     *
     * @var list<int>
     */
    public array $backoff = [10, 60];

    /**
     * The reason after the last attempt; the error itself is kept in the failed jobs of the queue.
     */
    private const string TECHNICAL_FAILURE = 'Die Verarbeitung ist nach drei Versuchen an einem technischen Fehler gescheitert.';

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
     * Pages are written in bundles: few queries for a long PDF, and each query stays
     * far below the size limit MySQL has for one statement.
     */
    private const int PAGES_PER_INSERT = 100;

    /**
     * The most plain text a PDF may give. A run holds its text about three times over,
     * so this keeps it inside the 128 MB of the worker; the upload limit of 10 MB counts
     * the file, and a PDF can unpack into far more text than that.
     */
    public const int MAX_TEXT_BYTES = 20_000_000;

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

            $isBlank = $texts->every(fn (string $text): bool => preg_match('/[^\s\p{Z}\p{Cf}\p{Cc}]/u', $text) === 0);

            if ($isBlank) {
                throw $this->document->format === DocumentFormat::Pdf ? UnreadableDocument::noTextInPdf() : UnreadableDocument::emptyMarkdown();
            }
        } catch (UnreadableDocument $exception) {
            $this->failDocument($exception->getMessage());

            return;
        }

        $pages = $texts->map(fn (string $text, int $index): array => [
            'document_id' => $this->document->id,
            'page' => $index + 1,
            'text' => $text,
        ]);

        DB::transaction(function () use ($pages): void {
            $this->document->pages()->delete();
            $pages->chunk(self::PAGES_PER_INSERT)->each(fn (Collection $bundle) => DocumentPage::insert($bundle->values()->all()));
        });
    }

    /**
     * Called by the queue after the last attempt, on a new instance of the job.
     */
    public function failed(?Throwable $exception): void
    {
        $this->failDocument(self::TECHNICAL_FAILURE);
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
     * password, is damaged or gives more text than MAX_TEXT_BYTES, is unreadable; any
     * other error of pdftotext or of the disk is thrown to be retried.
     *
     * pdftotext is killed as soon as its text passes the limit, so a PDF that unpacks
     * into gigabytes costs a few seconds and no more memory than the limit. The output
     * is counted from the start; a process that has already ended is not killed, its
     * text is still too much.
     *
     * A PDF the worker may not read is no damaged PDF: it is thrown as a disk error to be
     * retried, before pdftotext would report it as a file it cannot open.
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
            $path = $disk->path($this->document->path);

            if (! is_readable($path)) {
                throw UnableToReadFile::fromLocation($this->document->path, 'Der Worker darf die Datei nicht lesen.');
            }

            $textBytes = 0;
            $isKilled = false;
            $process = null;

            $process = Process::start(['pdftotext', '-enc', 'UTF-8', $path, '-'], function (string $type, string $output) use (&$process, &$textBytes, &$isKilled): void {
                if ($type === 'out') {
                    $textBytes += strlen($output);

                    if ($textBytes > self::MAX_TEXT_BYTES && $process !== null && ! $isKilled) {
                        $isKilled = true;
                        rescue(fn () => $process->signal(SIGKILL), report: false);
                    }
                }
            });

            $result = $process->wait();

            if ($textBytes > self::MAX_TEXT_BYTES) {
                throw UnreadableDocument::tooMuchText();
            }

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
