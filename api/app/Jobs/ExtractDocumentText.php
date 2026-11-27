<?php

namespace App\Jobs;

use App\Enums\DocumentStatus;
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
     * Store every page that has text with its page number in the PDF. pdftotext reads with -raw,
     * which keeps the lines as they are; without it, pdftotext would drop every hyphen at a line
     * end itself ("E-Mail" would become "EMail"). It ends every page with a form feed, so the
     * piece after the last one is no page. At a hyphen at the end of a
     * line, a word split before a small letter ("Verbin-" and "dung") is joined again; before a
     * capital letter the hyphen belongs to the word ("E-Mail") and only the line break goes; before
     * a conjunction it stands for a left-out part ("Ein- und Ausgänge") and the break becomes a
     * space. Headers and footers stay as they are. Spaces and control characters alone are no
     * text: a page needs a letter or a digit, otherwise it is left out. A PDF without any page
     * of text fails at once with its reason and ends the job, so the queue does not try it again.
     */
    public function handle(): void
    {
        $output = Process::run(['pdftotext', '-raw', '-enc', 'UTF-8', Storage::disk('local')->path($this->document->path), '-'])
            ->throw()
            ->output();

        $pages = collect(explode("\f", $output))
            ->slice(0, -1)
            ->map(fn (string $text, int $index) => [
                'page' => $index + 1,
                'text' => trim(preg_replace(
                    [
                        '/(?<=\p{L})-[ \t]*\R[ \t]*(?=(?:und|oder|bis|sowie|bzw)\b)/u',
                        '/(?<=\p{L})-[ \t]*\R[ \t]*(?=\p{Ll})/u',
                        '/(?<=\p{L})-[ \t]*\R[ \t]*(?=\p{Lu})/u',
                    ],
                    ['- ', '', '-'],
                    $text,
                )),
            ])
            ->filter(fn (array $page) => preg_match('/[\p{L}\p{N}]/u', $page['text']) === 1)
            ->values();

        if ($pages->isEmpty()) {
            $this->document->status = DocumentStatus::Failed;
            $this->document->failure_reason = 'Das PDF enthält keinen lesbaren Text. Vermutlich ist es ein Scan.';
            $this->document->save();

            return;
        }

        $this->document->pages()->createMany($pages->all());
    }
}
