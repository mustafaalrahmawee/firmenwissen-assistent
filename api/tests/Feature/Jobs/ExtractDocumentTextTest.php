<?php

use App\Enums\DocumentFormat;
use App\Enums\DocumentStatus;
use App\Http\CurrentTenant;
use App\Jobs\ExtractDocumentText;
use App\Models\Document;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

/**
 * A document of the given format whose file lies on the disk with the given content.
 */
function documentWithFile(DocumentFormat $format, string $content): Document
{
    $document = Document::factory()->create(['format' => $format, 'sha256' => hash('sha256', $content)]);
    $document->update(['path' => preg_replace('/\.\w+$/', '.'.$format->extension(), $document->path)]);
    Storage::disk('local')->put($document->path, $content);

    return $document;
}

describe('ExtractDocumentText', function () {
    it('keeps the text of a PDF page by page, with a row for a page without text', function () {
        $document = documentWithFile(DocumentFormat::Pdf, file_get_contents(fixture('documents/betriebshandbuch-vpn.pdf')));

        ExtractDocumentText::dispatchSync($document);

        expect($document->pages()->orderBy('page')->pluck('text', 'page')->all())->toBe([
            1 => "Betriebshandbuch VPN\nÜberblick: Der Zugang läuft über WireGuard.\n\n",
            2 => '',
            3 => "Störungen\nBei Ausfall des Gateways die Bereitschaft anrufen.\n\n",
        ]);
    });

    it('moves the document from pending to processing when it starts', function () {
        $document = documentWithFile(DocumentFormat::Markdown, "# Neue Laptops\n");
        expect($document->fresh()->status)->toBe(DocumentStatus::Pending);

        ExtractDocumentText::dispatchSync($document);

        expect($document->fresh()->status)->toBe(DocumentStatus::Processing);
    });

    it('keeps the text in MySQL and leaves the file unchanged on the disk', function () {
        $content = file_get_contents(fixture('documents/betriebshandbuch-vpn.pdf'));
        $document = documentWithFile(DocumentFormat::Pdf, $content);

        ExtractDocumentText::dispatchSync($document);

        $stored = Document::findOrFail($document->id);
        $file = Storage::disk('local')->get($stored->path);

        expect($stored->pages()->orderBy('page')->pluck('page')->all())->toBe([1, 2, 3])
            ->and($file)->toBe($content)
            ->and(hash('sha256', $file))->toBe($stored->sha256);
    });

    it('keeps a Markdown file as page 1, cleaned, with its headings', function () {
        $document = documentWithFile(
            DocumentFormat::Markdown,
            "\u{FEFF}# Neue Laptops  \r\n\r\n## Einrichtung\r\nBIOS-Kennwort\0 setzen.\t\r\n\r\n\r\n\r\n\r\n\r\nDocking-Station pru\u{0308}fen.\r\n",
        );

        ExtractDocumentText::dispatchSync($document);

        expect($document->pages()->pluck('text', 'page')->all())->toBe([
            1 => "# Neue Laptops\n\n## Einrichtung\nBIOS-Kennwort setzen.\n\n\nDocking-Station prüfen.\n",
        ]);
    });

    it('keeps only the id of the document in the queue and reads the document from MySQL when it runs', function () {
        $document = documentWithFile(DocumentFormat::Markdown, "# Neue Laptops\n");
        $document->load('tenant', 'user');

        $payload = serialize(new ExtractDocumentText($document));

        expect($payload)->toContain($document->id)
            ->not->toContain($document->tenant_id)
            ->not->toContain($document->name);

        $document->update(['name' => 'Laptop-Notizen.md']);
        $job = unserialize($payload);

        expect($job->document->name)->toBe('Laptop-Notizen.md')
            ->and($job->document->relationLoaded('tenant'))->toBeFalse()
            ->and($job->document->relationLoaded('user'))->toBeFalse();
    });

    it('runs without a workspace of a request and keeps the pages at the document of their own workspace', function () {
        $nordlicht = documentWithFile(DocumentFormat::Markdown, "# Neue Laptops\n");
        $hafenkante = documentWithFile(DocumentFormat::Markdown, "# Angebot Hafenkante\n");
        expect($nordlicht->tenant_id)->not->toBe($hafenkante->tenant_id)
            ->and(fn () => app(CurrentTenant::class)->get())->toThrow(LogicException::class);

        ExtractDocumentText::dispatchSync($nordlicht);
        ExtractDocumentText::dispatchSync($hafenkante);

        expect($nordlicht->pages()->pluck('text')->all())->toBe(["# Neue Laptops\n"])
            ->and($hafenkante->pages()->pluck('text')->all())->toBe(["# Angebot Hafenkante\n"]);
    });
});
