<?php

use App\Enums\DocumentFormat;
use App\Enums\DocumentStatus;
use App\Http\CurrentTenant;
use App\Jobs\ExtractDocumentText;
use App\Models\Document;
use App\Models\DocumentPage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Process\Exceptions\ProcessFailedException;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use League\Flysystem\UnableToReadFile;

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

    it('keeps every page once when it runs a second time', function () {
        $document = documentWithFile(DocumentFormat::Pdf, file_get_contents(fixture('documents/betriebshandbuch-vpn.pdf')));

        ExtractDocumentText::dispatchSync($document);
        ExtractDocumentText::dispatchSync($document);

        expect($document->pages()->orderBy('page')->pluck('page')->all())->toBe([1, 2, 3]);
    });

    it('replaces pages of an earlier run, also those the new text no longer has', function () {
        $document = documentWithFile(DocumentFormat::Markdown, "# Neue Laptops\n");
        $document->pages()->createMany([
            ['page' => 1, 'text' => 'alter Text'],
            ['page' => 2, 'text' => 'alte zweite Seite'],
        ]);

        ExtractDocumentText::dispatchSync($document);

        expect($document->pages()->pluck('text', 'page')->all())->toBe([1 => "# Neue Laptops\n"]);
    });

    it('leaves the pages of an earlier run whole when saving the new ones fails', function () {
        $document = documentWithFile(DocumentFormat::Markdown, "# Neue Laptops\n");
        $document->pages()->createMany([
            ['page' => 1, 'text' => 'alter Text'],
            ['page' => 2, 'text' => 'alte zweite Seite'],
        ]);
        DocumentPage::creating(fn () => throw new RuntimeException('Datenbank weg'));

        expect(fn () => (new ExtractDocumentText($document))->handle())->toThrow(RuntimeException::class, 'Datenbank weg');

        expect($document->pages()->pluck('text', 'page')->all())->toBe([1 => 'alter Text', 2 => 'alte zweite Seite']);
    });

    it('drops a run for a document another run is working on and changes nothing', function () {
        $document = documentWithFile(DocumentFormat::Markdown, "# Neue Laptops\n");
        $job = new ExtractDocumentText($document);
        $lock = Cache::lock((new WithoutOverlapping($document->id))->getLockKey($job), 60);
        expect($lock->get())->toBeTrue();

        ExtractDocumentText::dispatchSync($document);

        expect($document->pages()->count())->toBe(0)
            ->and($document->fresh()->status)->toBe(DocumentStatus::Pending);

        $lock->release();
        ExtractDocumentText::dispatchSync($document);

        expect($document->pages()->count())->toBe(1);
    });

    it('lets the lock of a dead run expire after the timeout and before the queue hands out the job again', function () {
        $document = Document::factory()->create();
        $job = new ExtractDocumentText($document);
        $lock = $job->middleware()[0];

        expect($lock->expiresAfter)->toBe(ExtractDocumentText::LOCK_SECONDS)
            ->and($job->timeout)->toBeLessThan($lock->expiresAfter)
            ->and($lock->expiresAfter)->toBeLessThan(config('queue.connections.database.retry_after'));
    });

    it('lets runs for other documents go on while one document is locked', function () {
        $locked = documentWithFile(DocumentFormat::Markdown, "# Neue Laptops\n");
        $other = documentWithFile(DocumentFormat::Markdown, "# Angebot\n");
        $job = new ExtractDocumentText($locked);
        Cache::lock((new WithoutOverlapping($locked->id))->getLockKey($job), 60)->get();

        ExtractDocumentText::dispatchSync($other);

        expect($other->pages()->count())->toBe(1);
    });

    it('fails a PDF without readable text at once with a reason in German, removes earlier pages and keeps the file', function () {
        Log::spy();
        $content = file_get_contents(fixture('documents/wartungsvertrag-scan.pdf'));
        $document = documentWithFile(DocumentFormat::Pdf, $content);
        $document->pages()->create(['page' => 1, 'text' => 'alter Text']);
        $job = (new ExtractDocumentText($document))->withFakeQueueInteractions();

        $job->handle();

        $job->assertNotFailed()->assertNotReleased();
        $stored = $document->fresh();
        $reason = 'Das PDF enthält keinen lesbaren Text. Vielleicht ist es ein Scan; Text aus Bildern wird nicht erkannt.';
        expect($stored->status)->toBe(DocumentStatus::Failed)
            ->and($stored->failure_reason)->toBe($reason)
            ->and($stored->pages()->count())->toBe(0)
            ->and(Storage::disk('local')->get($stored->path))->toBe($content);
        Log::shouldHaveReceived('warning')
            ->with('Dokument ist gescheitert.', ['document_id' => $document->id, 'reason' => $reason])
            ->once();
    });

    it('fails a Markdown file with nothing but white space as empty', function (string $content) {
        $document = documentWithFile(DocumentFormat::Markdown, $content);

        ExtractDocumentText::dispatchSync($document);

        $stored = $document->fresh();
        expect($stored->status)->toBe(DocumentStatus::Failed)
            ->and($stored->failure_reason)->toBe('Die Markdown-Datei ist leer.')
            ->and($stored->pages()->count())->toBe(0);
        Storage::disk('local')->assertExists($stored->path);
    })->with([
        'leer' => [''],
        'Leerzeilen, Leerzeichen und Tabulatoren' => ["\r\n\r\n  \t\n"],
        'geschütztes Leerzeichen, unsichtbares Zeichen und BOM' => ["\u{FEFF}\u{00A0}\u{200B}\n"],
    ]);

    it('counts a document with one readable character as readable', function () {
        $document = documentWithFile(DocumentFormat::Markdown, "\n\n3\n");

        ExtractDocumentText::dispatchSync($document);

        expect($document->fresh()->status)->toBe(DocumentStatus::Processing)
            ->and($document->pages()->pluck('text')->all())->toBe(["\n\n3\n"]);
    });

    it('clears the reason of an earlier failure when it runs again', function () {
        $document = documentWithFile(DocumentFormat::Markdown, "# Neue Laptops\n");
        $document->forceFill(['status' => DocumentStatus::Failed, 'failure_reason' => 'Die Markdown-Datei ist leer.'])->save();

        ExtractDocumentText::dispatchSync($document);

        $stored = $document->fresh();
        expect($stored->status)->toBe(DocumentStatus::Processing)
            ->and($stored->failure_reason)->toBeNull()
            ->and($stored->pages()->count())->toBe(1);
    });

    it('fails a PDF that cannot be opened at once with its reason in German and keeps the file', function (string $fixture, string $reason) {
        Log::spy();
        $content = file_get_contents(fixture('documents/'.$fixture));
        $document = documentWithFile(DocumentFormat::Pdf, $content);
        $document->pages()->create(['page' => 1, 'text' => 'alter Text']);
        $job = (new ExtractDocumentText($document))->withFakeQueueInteractions();

        $job->handle();

        $job->assertNotFailed()->assertNotReleased();
        $stored = $document->fresh();
        expect($stored->status)->toBe(DocumentStatus::Failed)
            ->and($stored->failure_reason)->toBe($reason)
            ->and($stored->pages()->count())->toBe(0)
            ->and(Storage::disk('local')->get($stored->path))->toBe($content);
        Log::shouldHaveReceived('warning')
            ->with('Dokument ist gescheitert.', ['document_id' => $document->id, 'reason' => $reason])
            ->once();
    })->with([
        'mit Kennwort geschützt' => ['angebot-kennwort.pdf', 'Das PDF ist mit einem Kennwort geschützt. Bitte eine Fassung ohne Kennwort hochladen.'],
        'beschädigt' => ['vertrag-beschaedigt.pdf', 'Das PDF ist beschädigt und lässt sich nicht öffnen.'],
    ]);

    it('reads a PDF that opens without a password and only forbids copying', function () {
        $document = documentWithFile(DocumentFormat::Pdf, file_get_contents(fixture('documents/preisliste-kopierschutz.pdf')));

        ExtractDocumentText::dispatchSync($document);

        expect($document->fresh()->status)->toBe(DocumentStatus::Processing)
            ->and($document->pages()->pluck('text', 'page')->all())->toBe([1 => "Preisliste 2027\nWartung pro Stunde\n\n"]);
    });

    it('throws any other error of pdftotext to be retried and does not fail the document', function () {
        Process::fake(['*' => Process::result(errorOutput: 'Out of memory', exitCode: 99)]);
        $document = documentWithFile(DocumentFormat::Pdf, file_get_contents(fixture('documents/betriebshandbuch-vpn.pdf')));
        $document->pages()->create(['page' => 1, 'text' => 'alter Text']);

        expect(fn () => (new ExtractDocumentText($document))->handle())->toThrow(ProcessFailedException::class);

        $stored = $document->fresh();
        expect($stored->status)->toBe(DocumentStatus::Processing)
            ->and($stored->failure_reason)->toBeNull()
            ->and($stored->pages()->pluck('text')->all())->toBe(['alter Text']);
    });

    it('fails a document whose file is missing at once with its own reason, not as empty or damaged', function (DocumentFormat $format) {
        Log::spy();
        $document = documentWithFile($format, '');
        Storage::disk('local')->delete($document->path);
        $job = (new ExtractDocumentText($document))->withFakeQueueInteractions();

        $job->handle();

        $job->assertNotFailed()->assertNotReleased();
        $stored = $document->fresh();
        expect($stored->status)->toBe(DocumentStatus::Failed)
            ->and($stored->failure_reason)->toBe('Die Datei des Dokuments fehlt in der Ablage.')
            ->and($stored->pages()->count())->toBe(0);
        Log::shouldHaveReceived('warning')
            ->with('Dokument ist gescheitert.', ['document_id' => $document->id, 'reason' => 'Die Datei des Dokuments fehlt in der Ablage.'])
            ->once();
    })->with([
        'PDF' => [DocumentFormat::Pdf],
        'Markdown' => [DocumentFormat::Markdown],
    ]);

    it('throws another error while reading the disk to be retried and does not fail the document', function () {
        $document = documentWithFile(DocumentFormat::Markdown, "# Neue Laptops\n");
        chmod(Storage::disk('local')->path($document->path), 0);

        expect(fn () => (new ExtractDocumentText($document))->handle())->toThrow(UnableToReadFile::class);

        $stored = $document->fresh();
        expect($stored->status)->toBe(DocumentStatus::Processing)
            ->and($stored->failure_reason)->toBeNull();
    });

    it('tries three times, waiting 10 and then 60 seconds', function () {
        $job = new ExtractDocumentText(Document::factory()->create());

        expect($job->tries)->toBe(3)
            ->and($job->backoff)->toBe([10, 60]);
    });

    it('retries a passing error and fails the document with a general reason only after the third attempt', function () {
        config(['queue.default' => 'database']);
        Log::spy();
        Process::fake(['*' => Process::result(errorOutput: 'Out of memory', exitCode: 99)]);
        $document = documentWithFile(DocumentFormat::Pdf, file_get_contents(fixture('documents/betriebshandbuch-vpn.pdf')));
        $document->pages()->create(['page' => 1, 'text' => 'alter Text']);
        ExtractDocumentText::dispatch($document);

        workOneJob();
        expect($document->fresh()->status)->toBe(DocumentStatus::Processing);

        $this->travel(10)->seconds();
        workOneJob();
        expect($document->fresh()->status)->toBe(DocumentStatus::Processing);

        $this->travel(60)->seconds();
        workOneJob();

        $reason = 'Die Verarbeitung ist nach drei Versuchen an einem technischen Fehler gescheitert.';
        $stored = $document->fresh();
        expect($stored->status)->toBe(DocumentStatus::Failed)
            ->and($stored->failure_reason)->toBe($reason)
            ->and($stored->pages()->count())->toBe(0)
            ->and(DB::table('jobs')->count())->toBe(0)
            ->and(DB::table('failed_jobs')->count())->toBe(1);
        Log::shouldHaveReceived('warning')
            ->with('Dokument ist gescheitert.', ['document_id' => $document->id, 'reason' => $reason])
            ->once();
    });

    it('goes on with the other documents while one waits for its next attempt and another has failed', function () {
        config(['queue.default' => 'database']);
        $waiting = documentWithFile(DocumentFormat::Markdown, "# Angebot\n");
        chmod(Storage::disk('local')->path($waiting->path), 0);
        $scan = documentWithFile(DocumentFormat::Pdf, file_get_contents(fixture('documents/wartungsvertrag-scan.pdf')));
        $notes = documentWithFile(DocumentFormat::Markdown, "# Neue Laptops\n");
        collect([$waiting, $scan, $notes])->each(fn (Document $document) => ExtractDocumentText::dispatch($document));

        Artisan::call('queue:work', ['--stop-when-empty' => true, '--sleep' => 0]);

        expect($waiting->fresh()->status)->toBe(DocumentStatus::Processing)
            ->and($scan->fresh()->status)->toBe(DocumentStatus::Failed)
            ->and($notes->fresh()->status)->toBe(DocumentStatus::Processing)
            ->and($notes->pages()->pluck('text')->all())->toBe(["# Neue Laptops\n"])
            ->and(DB::table('jobs')->count())->toBe(1);
    });

    it('works off twenty uploads one after another and gives each document its own pages', function () {
        config(['queue.default' => 'database']);
        Sanctum::actingAs(User::factory()->create(['name' => 'Jana Petrov']));
        $numbers = range(1, 20);

        foreach ($numbers as $number) {
            $this->postJson('/api/v1/documents', ['file' => UploadedFile::fake()->createWithContent("Notiz {$number}.md", "# Notiz {$number}\n")])
                ->assertCreated();
        }
        expect(DB::table('jobs')->count())->toBe(20);

        Artisan::call('queue:work', ['--stop-when-empty' => true, '--sleep' => 0]);

        $documents = Document::with('pages')->get();
        expect($documents)->toHaveCount(20)
            ->and($documents->every(fn (Document $document): bool => $document->status === DocumentStatus::Processing))->toBeTrue()
            ->and($documents->mapWithKeys(fn (Document $document): array => [$document->name => $document->pages->pluck('text')->all()])->sortKeys()->all())
            ->toBe(collect($numbers)->mapWithKeys(fn (int $number): array => ["Notiz {$number}.md" => ["# Notiz {$number}\n"]])->sortKeys()->all())
            ->and(DB::table('jobs')->count())->toBe(0)
            ->and(DB::table('failed_jobs')->count())->toBe(0);
    });
});

/**
 * Lets the queue worker run the next job that is due, as the service "worker" does.
 */
function workOneJob(): void
{
    Artisan::call('queue:work', ['--once' => true, '--sleep' => 0]);
}
