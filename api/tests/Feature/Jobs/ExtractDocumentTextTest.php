<?php

use App\Jobs\ExtractDocumentText;
use App\Models\Document;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

/**
 * A PDF with one page per entry; each page shows its lines one below the other in Helvetica.
 *
 * @param  list<list<string>>  $pages
 */
function pdfWithPages(array $pages): string
{
    $pageCount = count($pages);
    $objects = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids ['.collect(range(0, $pageCount - 1))->map(fn (int $index) => (4 + 2 * $index).' 0 R')->implode(' ').'] /Count '.$pageCount.' >>',
        3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
    ];

    foreach (array_values($pages) as $index => $lines) {
        $text = collect($lines)
            ->map(fn (string $line) => '('.addcslashes(mb_convert_encoding($line, 'Windows-1252', 'UTF-8'), '()\\').') Tj T*')
            ->implode("\n");
        $stream = "BT\n/F1 12 Tf\n14 TL\n72 750 Td\n{$text}\nET";
        $objects[4 + 2 * $index] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents '.(5 + 2 * $index).' 0 R >>';
        $objects[5 + 2 * $index] = '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream";
    }

    $pdf = "%PDF-1.4\n";
    $offsets = [];
    foreach ($objects as $number => $body) {
        $offsets[$number] = strlen($pdf);
        $pdf .= "{$number} 0 obj\n{$body}\nendobj\n";
    }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
    foreach ($offsets as $offset) {
        $pdf .= sprintf("%010d 00000 n \n", $offset);
    }

    return $pdf.'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
}

/**
 * A document of Jana's workspace whose file in the storage is the given PDF.
 */
function documentWithPdf(string $pdf): Document
{
    $document = Document::factory()->create(['name' => 'Betriebshandbuch VPN.pdf', 'size' => strlen($pdf)]);
    Storage::disk('local')->put($document->path, $pdf);

    return $document;
}

/**
 * The stored pages of the document as page number => text.
 *
 * @return array<int, string>
 */
function storedPages(Document $document): array
{
    return $document->pages()->orderBy('page')->pluck('text', 'page')->all();
}

describe('Job ExtractDocumentText', function () {
    it('stores the text of every page with its page number', function () {
        $document = documentWithPdf(pdfWithPages([
            ['Betriebshandbuch VPN', 'Größe der Übertragung: 1500 Byte'],
            ['Zugang für Außendienst'],
            ['Störungen melden an den Support'],
        ]));

        ExtractDocumentText::dispatchSync($document);

        expect(storedPages($document))->toBe([
            1 => "Betriebshandbuch VPN\nGröße der Übertragung: 1500 Byte",
            2 => 'Zugang für Außendienst',
            3 => 'Störungen melden an den Support',
        ]);
    });

    it('joins a word that was split at the end of a line', function () {
        $document = documentWithPdf(pdfWithPages([
            ['Die Verbin-', 'dung wird über das Gateway aufge-', 'baut.'],
        ]));

        ExtractDocumentText::dispatchSync($document);

        expect(storedPages($document))->toBe([1 => 'Die Verbindung wird über das Gateway aufgebaut.']);
    });

    it('stores no text for an empty page and keeps the page numbers of the PDF', function () {
        $document = documentWithPdf(pdfWithPages([
            ['Inhaltsverzeichnis'],
            [],
            ['   ', "\t"],
            ['Kapitel 1: Einwahl'],
        ]));

        ExtractDocumentText::dispatchSync($document);

        expect(storedPages($document))->toBe([1 => 'Inhaltsverzeichnis', 4 => 'Kapitel 1: Einwahl']);
    });

    it('keeps the hyphen of a word split before a capital letter and removes only the line break', function () {
        $document = documentWithPdf(pdfWithPages([
            ['Bitte die E-', 'Mail-Adresse und das VPN-', 'Gateway eintragen.'],
        ]));

        ExtractDocumentText::dispatchSync($document);

        expect(storedPages($document))->toBe([1 => 'Bitte die E-Mail-Adresse und das VPN-Gateway eintragen.']);
    });

    it('keeps the hyphen for a left-out part before a conjunction and puts a space for the line break', function () {
        $document = documentWithPdf(pdfWithPages([
            ['Alle Ein-', 'und Ausgänge, Vor-', 'oder Nachnamen, Montag-', 'bis Freitagsdienste'],
        ]));

        ExtractDocumentText::dispatchSync($document);

        expect(storedPages($document))->toBe([1 => 'Alle Ein- und Ausgänge, Vor- oder Nachnamen, Montag- bis Freitagsdienste']);
    });

    it('keeps headers and footers on every page as they are in the PDF', function () {
        $document = documentWithPdf(pdfWithPages([
            ['Betriebshandbuch VPN · Nordlicht IT GmbH', 'Einleitung', 'Seite 1 von 2'],
            ['Betriebshandbuch VPN · Nordlicht IT GmbH', 'Einwahl', 'Seite 2 von 2'],
        ]));

        ExtractDocumentText::dispatchSync($document);

        expect(storedPages($document))->toBe([
            1 => "Betriebshandbuch VPN · Nordlicht IT GmbH\nEinleitung\nSeite 1 von 2",
            2 => "Betriebshandbuch VPN · Nordlicht IT GmbH\nEinwahl\nSeite 2 von 2",
        ]);
    });

    it('leaves only gaps in the page numbers for several empty pages in a row', function () {
        $document = documentWithPdf(pdfWithPages([['Deckblatt'], [], [], [], ['Kapitel 1']]));

        ExtractDocumentText::dispatchSync($document);

        expect(storedPages($document))->toBe([1 => 'Deckblatt', 5 => 'Kapitel 1']);
    });

    it('keeps each part of a paragraph across a page border on its own page', function () {
        $document = documentWithPdf(pdfWithPages([
            ['Kapitel 2', 'Die Einwahl erfolgt über'],
            ['den Client des Herstellers.'],
        ]));

        ExtractDocumentText::dispatchSync($document);

        expect(storedPages($document))->toBe([
            1 => "Kapitel 2\nDie Einwahl erfolgt über",
            2 => 'den Client des Herstellers.',
        ]);
    });
});
