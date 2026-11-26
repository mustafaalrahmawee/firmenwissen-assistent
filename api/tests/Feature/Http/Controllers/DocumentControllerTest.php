<?php

use App\Enums\DocumentStatus;
use App\Jobs\ExtractDocumentText;
use App\Models\Document;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToWriteFile;

const DOCUMENTS_URL = '/api/v1/documents';
const WRONG_FORMAT = 'Erlaubt sind nur PDF- und Markdown-Dateien (.pdf, .md, .markdown).';

beforeEach(function () {
    Storage::fake('local');
    Queue::fake();
});

/**
 * The bytes of a small PDF; the text makes two of them differ.
 */
function pdfBytes(string $text = 'Betriebshandbuch VPN'): string
{
    return "%PDF-1.4\n% {$text}\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n";
}

/**
 * Jana Petrov of Nordlicht IT GmbH, logged in.
 */
function janaLoggedIn(): User
{
    $jana = User::factory()
        ->for(Tenant::factory()->state(['name' => 'Nordlicht IT GmbH']))
        ->create(['name' => 'Jana Petrov']);
    Sanctum::actingAs($jana);

    return $jana;
}

describe('POST /api/v1/documents', function () {
    it('stores a PDF unchanged as a document of the workspace of the uploader and returns it', function () {
        $jana = janaLoggedIn();

        $response = $this->postJson(DOCUMENTS_URL, ['file' => UploadedFile::fake()->createWithContent('Betriebshandbuch VPN.pdf', pdfBytes())]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Betriebshandbuch VPN.pdf')
            ->assertJsonPath('data.format', 'pdf')
            ->assertJsonPath('data.size', strlen(pdfBytes()))
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.failure_reason', null);
        expect(array_keys($response->json('data')))->toBe(['id', 'name', 'format', 'size', 'status', 'failure_reason', 'created_at']);

        $document = Document::sole();
        expect($document->id)->toBe($response->json('data.id'))
            ->and($document->tenant_id)->toBe($jana->tenant_id)
            ->and($document->user_id)->toBe($jana->id)
            ->and($document->created_at)->not->toBeNull()
            ->and($document->sha256)->toBe(hash('sha256', pdfBytes()))
            ->and($document->path)->toBe("documents/{$jana->tenant_id}/{$document->id}.pdf");
        expect(Storage::disk('local')->get($document->path))->toBe(pdfBytes());
    });

    it('accepts Markdown and PDF files with their extension in any capitals', function (string $name, string $content, string $format, string $storedExtension) {
        $jana = janaLoggedIn();

        $response = $this->postJson(DOCUMENTS_URL, ['file' => UploadedFile::fake()->createWithContent($name, $content)]);

        $response->assertCreated()->assertJsonPath('data.format', $format);
        $document = Document::sole();
        expect($document->path)->toBe("documents/{$jana->tenant_id}/{$document->id}.{$storedExtension}")
            ->and(Storage::disk('local')->get($document->path))->toBe($content);
    })->with([
        '.md' => ['Laptop einrichten.md', "# Laptop einrichten\n\n- BitLocker aktivieren\n", 'markdown', 'md'],
        '.MD' => ['LAPTOP.MD', "# Laptop\n", 'markdown', 'md'],
        '.markdown' => ['Laptop einrichten.markdown', "# Laptop einrichten\n", 'markdown', 'md'],
        '.PDF' => ['Betriebshandbuch.PDF', pdfBytes(), 'pdf', 'pdf'],
    ]);

    it('lets every person of the workspace upload', function () {
        $jana = janaLoggedIn();
        $tobias = User::factory()->for($jana->tenant)->create(['name' => 'Tobias Kern']);
        Sanctum::actingAs($tobias);

        $response = $this->postJson(DOCUMENTS_URL, ['file' => UploadedFile::fake()->createWithContent('Notizen.md', '# Notizen')]);

        $response->assertCreated();
        expect(Document::sole())->tenant_id->toBe($jana->tenant_id)->user_id->toBe($tobias->id);
    });

    it('returns 401 without a token', function () {
        $response = $this->postJson(DOCUMENTS_URL, ['file' => UploadedFile::fake()->createWithContent('Notizen.md', '# Notizen')]);

        $response->assertUnauthorized();
        $this->assertDatabaseEmpty('documents');
        expect(Storage::disk('local')->allFiles())->toBeEmpty();
    });

    it('puts the document into the workspace of the uploader even when the request names another one', function (string $place) {
        $nordlicht = Tenant::factory()->create(['name' => 'Nordlicht IT GmbH']);
        $mehmet = User::factory()->for(Tenant::factory()->state(['name' => 'Hafenkante Systemhaus GmbH']))->create();
        Sanctum::actingAs($mehmet);
        $file = UploadedFile::fake()->createWithContent('Angebot.md', '# Angebot');

        $response = match ($place) {
            'body' => $this->postJson(DOCUMENTS_URL, ['file' => $file, 'tenant_id' => $nordlicht->id]),
            'query' => $this->postJson(DOCUMENTS_URL.'?tenant_id='.$nordlicht->id, ['file' => $file]),
            'header' => $this->withHeader('X-Tenant-Id', $nordlicht->id)->postJson(DOCUMENTS_URL, ['file' => $file]),
        };

        $response->assertCreated();
        expect(Document::sole()->tenant_id)->toBe($mehmet->tenant_id)
            ->and(Storage::disk('local')->allFiles("documents/{$nordlicht->id}"))->toBeEmpty();
    })->with(['body', 'query', 'header']);

    it('returns 422 for a missing file or a value that is no file', function (array $input, string $message) {
        janaLoggedIn();

        $response = $this->postJson(DOCUMENTS_URL, $input);

        $response->assertUnprocessable()->assertJsonValidationErrors(['file' => $message]);
        $this->assertDatabaseEmpty('documents');
    })->with([
        'missing' => [[], 'Bitte wähle eine Datei aus.'],
        'text' => [['file' => 'Betriebshandbuch.pdf'], 'Die Datei ließ sich nicht hochladen. Bitte versuche es erneut.'],
    ]);

    it('returns 422 for another format and stores nothing', function (string $name) {
        janaLoggedIn();

        $response = $this->postJson(DOCUMENTS_URL, ['file' => UploadedFile::fake()->createWithContent($name, 'Inhalt')]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['file' => WRONG_FORMAT]);
        $this->assertDatabaseEmpty('documents');
        expect(Storage::disk('local')->allFiles())->toBeEmpty();
    })->with(['Angebot.docx', 'Foto.png', 'Archiv.zip', 'skript.php', 'ohne-endung', 'Handbuch.pdf.exe']);

    it('returns 422 for a file ending in .pdf whose content is no PDF', function (string $content) {
        janaLoggedIn();

        $response = $this->postJson(DOCUMENTS_URL, ['file' => UploadedFile::fake()->createWithContent('Betriebshandbuch.pdf', $content)]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['file' => 'Die Datei endet auf .pdf, ist aber kein PDF.']);
        $this->assertDatabaseEmpty('documents');
        expect(Storage::disk('local')->allFiles())->toBeEmpty();
    })->with([
        'zip' => ["PK\x03\x04\x14\x00\x00\x00\x08\x00".str_repeat("\x00", 40)],
        'text' => ["# Kein PDF\n"],
    ]);

    it('returns 422 for a file ending in .md or .markdown that is no UTF-8 text', function (string $name, string $content) {
        janaLoggedIn();

        $response = $this->postJson(DOCUMENTS_URL, ['file' => UploadedFile::fake()->createWithContent($name, $content)]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['file' => 'Die Datei enthält keinen Text in UTF-8.']);
        $this->assertDatabaseEmpty('documents');
    })->with([
        'image renamed' => ['Notizen.md', "\x89PNG\r\n\x1A\n\x00\x00\x00\rIHDR\xFF\xFE"],
        'Latin-1' => ['Notizen.md', "Gr\xF6\xDFe"],
        'null character' => ['Notizen.markdown', "# Notizen\0"],
    ]);

    it('returns 422 for a PDF that ends in .md or .markdown, also one of plain ASCII text', function (string $name, ?string $content = null) {
        janaLoggedIn();

        $response = $this->postJson(DOCUMENTS_URL, ['file' => UploadedFile::fake()->createWithContent($name, $content ?? pdfBytes())]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['file' => 'Die Datei ist ein PDF, endet aber nicht auf .pdf.']);
        $this->assertDatabaseEmpty('documents');
    })->with([
        '.md' => ['Betriebshandbuch.md'],
        '.markdown' => ['Betriebshandbuch.markdown'],
        'binary PDF' => ['Betriebshandbuch.md', "%PDF-1.4\n\xE2\xE3\xCF\xD3\n\xFF\xFE"],
    ]);

    it('returns 422 in German for a file that arrived only in part', function () {
        janaLoggedIn();
        $path = tempnam(sys_get_temp_dir(), 'upload');
        file_put_contents($path, pdfBytes());
        $partial = new UploadedFile($path, 'Betriebshandbuch.pdf', 'application/pdf', UPLOAD_ERR_PARTIAL, true);

        $response = $this->postJson(DOCUMENTS_URL, ['file' => $partial]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['file' => 'Die Datei ließ sich nicht hochladen. Bitte versuche es erneut.']);
        $this->assertDatabaseEmpty('documents');
        expect(Storage::disk('local')->allFiles())->toBeEmpty();
    });

    it('accepts an empty Markdown file', function () {
        janaLoggedIn();

        $response = $this->postJson(DOCUMENTS_URL, ['file' => UploadedFile::fake()->createWithContent('Leer.md', '')]);

        $response->assertCreated()->assertJsonPath('data.size', 0);
    });

    it('returns 422 for an empty file ending in .pdf', function () {
        janaLoggedIn();

        $response = $this->postJson(DOCUMENTS_URL, ['file' => UploadedFile::fake()->createWithContent('Leer.pdf', '')]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['file' => 'Die Datei endet auf .pdf, ist aber kein PDF.']);
        $this->assertDatabaseEmpty('documents');
    });

    it('returns 422 for a file larger than 10 MB', function () {
        janaLoggedIn();

        $response = $this->postJson(DOCUMENTS_URL, ['file' => UploadedFile::fake()->create('Handbuch.pdf', 10 * 1024 + 1, 'application/pdf')]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['file' => 'Die Datei ist zu groß. Erlaubt sind höchstens 10 MB.']);
        $this->assertDatabaseEmpty('documents');
    });

    it('accepts a file of exactly 10 MB', function () {
        janaLoggedIn();
        $content = str_pad(pdfBytes(), 10 * 1024 * 1024, ' ');

        $response = $this->postJson(DOCUMENTS_URL, ['file' => UploadedFile::fake()->createWithContent('Handbuch.pdf', $content)]);

        $response->assertCreated()->assertJsonPath('data.size', 10 * 1024 * 1024);
    });

    it('takes the size limit from the configuration', function () {
        config(['documents.max_size_mb' => 1]);
        janaLoggedIn();

        $response = $this->postJson(DOCUMENTS_URL, ['file' => UploadedFile::fake()->create('Handbuch.pdf', 1025, 'application/pdf')]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['file' => 'Die Datei ist zu groß. Erlaubt sind höchstens 1 MB.']);
    });

    it('keeps the file name with umlauts and special characters', function () {
        janaLoggedIn();
        $name = 'Übergabe & Größe (v2) – Entwurf #1.md';

        $response = $this->postJson(DOCUMENTS_URL, ['file' => UploadedFile::fake()->createWithContent($name, '# Übergabe')]);

        $response->assertCreated()->assertJsonPath('data.name', $name);
        expect(Document::sole()->name)->toBe($name);
    });

    it('drops path parts of the file name and stores the file only under a name of its own', function (string $name, string $storedName) {
        $jana = janaLoggedIn();

        $response = $this->postJson(DOCUMENTS_URL, ['file' => UploadedFile::fake()->createWithContent($name, '# Inhalt')]);

        $response->assertCreated()->assertJsonPath('data.name', $storedName);
        $document = Document::sole();
        expect(Storage::disk('local')->allFiles())->toBe(["documents/{$jana->tenant_id}/{$document->id}.md"]);
    })->with([
        'parent folders' => ['../../.env.md', '.env.md'],
        'backslashes' => ['..\\..\\public\\index.md', 'index.md'],
        'absolute path' => ['/etc/passwd.md', 'passwd.md'],
    ]);

    it('shortens a file name longer than 255 characters to its first 255 and keeps one of 255', function () {
        janaLoggedIn();
        $tooLong = str_repeat('ä', 253).'.md';
        $longest = str_repeat('ä', 252).'.md';

        $this->postJson(DOCUMENTS_URL, ['file' => UploadedFile::fake()->createWithContent($tooLong, '# A')])
            ->assertCreated()
            ->assertJsonPath('data.name', mb_substr($tooLong, 0, 255))
            ->assertJsonPath('data.format', 'markdown');
        $this->postJson(DOCUMENTS_URL, ['file' => UploadedFile::fake()->createWithContent($longest, '# B')])
            ->assertCreated()
            ->assertJsonPath('data.name', $longest);
    });

    it('replaces invalid characters in the file name instead of failing', function () {
        janaLoggedIn();

        $response = $this->postJson(DOCUMENTS_URL, ['file' => UploadedFile::fake()->createWithContent("Gr\xF6\xDFe.md", '# Größe')]);

        $response->assertCreated()->assertJsonPath('data.name', 'Gr??e.md');
    });

    it('keeps two different files with the same name as two documents', function () {
        janaLoggedIn();

        $this->postJson(DOCUMENTS_URL, ['file' => UploadedFile::fake()->createWithContent('Betriebshandbuch VPN.pdf', pdfBytes('Fassung 1'))])->assertCreated();
        $this->postJson(DOCUMENTS_URL, ['file' => UploadedFile::fake()->createWithContent('Betriebshandbuch VPN.pdf', pdfBytes('Fassung 2'))])->assertCreated();

        expect(Document::where('name', 'Betriebshandbuch VPN.pdf')->count())->toBe(2)
            ->and(Storage::disk('local')->allFiles())->toHaveCount(2);
    });

    it('returns 422 naming the existing document when the same content is already in the workspace', function () {
        $this->travelTo(new DateTimeImmutable('2026-11-23 13:05:00 UTC'));
        $jana = janaLoggedIn();
        $this->postJson(DOCUMENTS_URL, ['file' => UploadedFile::fake()->createWithContent('Betriebshandbuch VPN.pdf', pdfBytes())])->assertCreated();
        Sanctum::actingAs(User::factory()->for($jana->tenant)->create(['name' => 'Tobias Kern']));

        $response = $this->postJson(DOCUMENTS_URL, ['file' => UploadedFile::fake()->createWithContent('VPN Handbuch Kopie.pdf', pdfBytes())]);

        $response->assertUnprocessable()->assertJsonValidationErrors([
            'file' => 'Diese Datei liegt schon im Arbeitsbereich: „Betriebshandbuch VPN.pdf“, hochgeladen von Jana Petrov am 23.11.2026 um 14:05 Uhr.',
        ]);
        expect(Document::sole()->name)->toBe('Betriebshandbuch VPN.pdf')
            ->and(Storage::disk('local')->allFiles())->toHaveCount(1);
    });

    it('names no account of another workspace when an existing document points to one', function () {
        $this->travelTo(new DateTimeImmutable('2026-11-23 13:05:00 UTC'));
        $jana = janaLoggedIn();
        $mehmet = User::factory()->for(Tenant::factory()->state(['name' => 'Hafenkante Systemhaus GmbH']))->create(['name' => 'Mehmet Aydin']);
        Document::factory()->create([
            'tenant_id' => $jana->tenant_id,
            'user_id' => $mehmet->id,
            'name' => 'Betriebshandbuch VPN.pdf',
            'sha256' => hash('sha256', pdfBytes()),
        ]);

        $response = $this->postJson(DOCUMENTS_URL, ['file' => UploadedFile::fake()->createWithContent('Handbuch.pdf', pdfBytes())]);

        $response->assertUnprocessable()->assertJsonValidationErrors([
            'file' => 'Diese Datei liegt schon im Arbeitsbereich: „Betriebshandbuch VPN.pdf“, hochgeladen am 23.11.2026 um 14:05 Uhr.',
        ]);
        expect($response->json('errors.file.0'))->not->toContain('Mehmet');
    });

    it('accepts the same content in another workspace without telling either side', function () {
        $jana = janaLoggedIn();
        $this->postJson(DOCUMENTS_URL, ['file' => UploadedFile::fake()->createWithContent('Betriebshandbuch VPN.pdf', pdfBytes())])->assertCreated();
        $mehmet = User::factory()->for(Tenant::factory()->state(['name' => 'Hafenkante Systemhaus GmbH']))->create();
        Sanctum::actingAs($mehmet);

        $response = $this->postJson(DOCUMENTS_URL, ['file' => UploadedFile::fake()->createWithContent('Betriebshandbuch VPN.pdf', pdfBytes())]);

        $response->assertCreated();
        expect(Document::where('tenant_id', $jana->tenant_id)->count())->toBe(1)
            ->and(Document::where('tenant_id', $mehmet->tenant_id)->count())->toBe(1);
    });

    it('returns 422 instead of 500 and removes its file when another upload saves the same content just before', function () {
        $this->travelTo(new DateTimeImmutable('2026-11-23 13:05:00 UTC'));
        $jana = janaLoggedIn();
        $tobias = User::factory()->for($jana->tenant)->create(['name' => 'Tobias Kern']);
        Document::creating(function (Document $document) use ($tobias): void {
            DB::table('documents')->insert([
                'id' => strtolower((string) Str::ulid()),
                'tenant_id' => $document->tenant_id,
                'user_id' => $tobias->id,
                'name' => 'Betriebshandbuch VPN.pdf',
                'format' => 'pdf',
                'size' => $document->size,
                'sha256' => $document->sha256,
                'path' => 'documents/other.pdf',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $response = $this->postJson(DOCUMENTS_URL, ['file' => UploadedFile::fake()->createWithContent('Handbuch.pdf', pdfBytes())]);

        $response->assertUnprocessable()->assertJsonValidationErrors([
            'file' => 'Diese Datei liegt schon im Arbeitsbereich: „Betriebshandbuch VPN.pdf“, hochgeladen von Tobias Kern am 23.11.2026 um 14:05 Uhr.',
        ]);
        expect(Document::sole()->name)->toBe('Betriebshandbuch VPN.pdf')
            ->and(Storage::disk('local')->allFiles())->toBeEmpty();
    });

    it('removes the file again when the document cannot be saved', function () {
        janaLoggedIn();
        Document::creating(fn () => throw new RuntimeException('Datenbank weg'));

        $response = $this->postJson(DOCUMENTS_URL, ['file' => UploadedFile::fake()->createWithContent('Notizen.md', '# Notizen')]);

        $response->assertServerError();
        $this->assertDatabaseEmpty('documents');
        expect(Storage::disk('local')->allFiles())->toBeEmpty();
    });

    it('saves no document and removes a partly written file when the file cannot be written', function () {
        $jana = janaLoggedIn();
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('putFileAs')->once()->andThrow(UnableToWriteFile::atLocation('documents', 'Ablage voll'));
        $disk->shouldReceive('delete')
            ->once()
            ->with(Mockery::on(fn (string $path) => preg_match("#^documents/{$jana->tenant_id}/[0-9a-z]{26}\\.md$#", $path) === 1))
            ->andReturn(true);
        Storage::shouldReceive('disk')->with('local')->andReturn($disk);

        $response = $this->postJson(DOCUMENTS_URL, ['file' => UploadedFile::fake()->createWithContent('Notizen.md', '# Notizen')]);

        $response->assertServerError();
        $this->assertDatabaseEmpty('documents');
    });

    it('logs a warning naming the file when a file without document cannot be removed', function () {
        janaLoggedIn();
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('putFileAs')->once()->andThrow(UnableToWriteFile::atLocation('documents', 'Ablage voll'));
        $disk->shouldReceive('delete')->once()->andThrow(UnableToDeleteFile::atLocation('documents', 'Keine Rechte'));
        Storage::shouldReceive('disk')->with('local')->andReturn($disk);
        Log::spy();

        $this->postJson(DOCUMENTS_URL, ['file' => UploadedFile::fake()->createWithContent('Notizen.md', '# Notizen')])->assertServerError();

        Log::shouldHaveReceived('warning')
            ->with('Datei ohne Dokument blieb in der Ablage liegen.', Mockery::on(fn (array $context) => str_starts_with($context['path'], 'documents/')))
            ->once();
    });

    it('accepts the upload without calling the services for processing and search and queues the processing of the saved document', function () {
        Http::preventStrayRequests();
        janaLoggedIn();

        $response = $this->postJson(DOCUMENTS_URL, ['file' => UploadedFile::fake()->createWithContent('Notizen.md', '# Notizen')]);

        $response->assertCreated();
        $document = Document::sole();
        expect($document->id)->toBe($response->json('data.id'))
            ->and($document->status)->toBe(DocumentStatus::Pending)
            ->and($document->pages()->count())->toBe(0);
        Queue::assertPushed(ExtractDocumentText::class, 1);
        Queue::assertPushed(ExtractDocumentText::class, fn (ExtractDocumentText $job) => $job->document->is($document));
    });

    it('queues nothing for a rejected upload', function () {
        janaLoggedIn();

        $this->postJson(DOCUMENTS_URL, ['file' => UploadedFile::fake()->createWithContent('Angebot.docx', 'Angebot')])->assertUnprocessable();

        Queue::assertNothingPushed();
    });

    it('accepts the upload and reports the error when the processing cannot be queued, the document stays pending', function () {
        Exceptions::fake();
        Queue::shouldReceive('connection')->andThrow(new RuntimeException('Warteschlange nicht erreichbar'));
        janaLoggedIn();

        $response = $this->postJson(DOCUMENTS_URL, ['file' => UploadedFile::fake()->createWithContent('Angebot.md', '# Angebot')]);

        $response->assertCreated();
        expect(Document::sole()->status)->toBe(DocumentStatus::Pending);
        Storage::disk('local')->assertExists(Document::sole()->path);
        Exceptions::assertReported(fn (RuntimeException $exception) => $exception->getMessage() === 'Warteschlange nicht erreichbar');
    });

    it('returns 429 after thirty uploads within a minute from the same account', function () {
        janaLoggedIn();
        foreach (range(1, 30) as $attempt) {
            $this->postJson(DOCUMENTS_URL, ['file' => UploadedFile::fake()->createWithContent("Notiz {$attempt}.md", "# Notiz {$attempt}")])->assertCreated();
        }

        $response = $this->postJson(DOCUMENTS_URL, ['file' => UploadedFile::fake()->createWithContent('Notiz 31.md', '# Notiz 31')]);

        $response->assertTooManyRequests()->assertHeader('Retry-After');
        expect(Document::count())->toBe(30);
    });

    it('counts rejected uploads towards the limit', function () {
        janaLoggedIn();
        foreach (range(1, 30) as $attempt) {
            $this->postJson(DOCUMENTS_URL, [])->assertUnprocessable();
        }

        $this->postJson(DOCUMENTS_URL, ['file' => UploadedFile::fake()->createWithContent('Notizen.md', '# Notizen')])->assertTooManyRequests();
        $this->assertDatabaseEmpty('documents');
    });

    it('does not limit another account of the same workspace', function () {
        $jana = janaLoggedIn();
        foreach (range(1, 30) as $attempt) {
            $this->postJson(DOCUMENTS_URL, []);
        }
        Sanctum::actingAs(User::factory()->for($jana->tenant)->create());

        $response = $this->postJson(DOCUMENTS_URL, ['file' => UploadedFile::fake()->createWithContent('Notizen.md', '# Notizen')]);

        $response->assertCreated();
    });

    it('returns 429 after two hundred uploads within a day from the same account', function () {
        janaLoggedIn();
        foreach (range(1, 7) as $minute) {
            foreach (range(1, $minute < 7 ? 30 : 20) as $attempt) {
                $this->postJson(DOCUMENTS_URL, [])->assertUnprocessable();
            }
            $this->travel(61)->seconds();
        }

        $response = $this->postJson(DOCUMENTS_URL, ['file' => UploadedFile::fake()->createWithContent('Notizen.md', '# Notizen')]);

        $response->assertTooManyRequests();
        expect((int) $response->headers->get('Retry-After'))->toBeGreaterThan(60);
        $this->assertDatabaseEmpty('documents');

        $this->travel(1)->days();
        $this->postJson(DOCUMENTS_URL, ['file' => UploadedFile::fake()->createWithContent('Notizen.md', '# Notizen')])->assertCreated();
    });
});

describe('GET /api/v1/documents/{document}', function () {
    it('returns a document of the workspace with its status and, once failed, its reason', function (DocumentStatus $status, ?string $reason) {
        $jana = janaLoggedIn();
        $document = Document::factory()->for($jana)->for($jana->tenant)->create([
            'name' => 'Betriebshandbuch VPN.pdf',
            'status' => $status,
            'failure_reason' => $reason,
        ]);

        $response = $this->getJson(DOCUMENTS_URL.'/'.$document->id);

        $response->assertOk()
            ->assertJsonPath('data.id', $document->id)
            ->assertJsonPath('data.name', 'Betriebshandbuch VPN.pdf')
            ->assertJsonPath('data.status', $status->value)
            ->assertJsonPath('data.failure_reason', $reason);
        expect(array_keys($response->json('data')))->toBe(['id', 'name', 'format', 'size', 'status', 'failure_reason', 'created_at']);
    })->with([
        'wartet' => [DocumentStatus::Pending, null],
        'in Arbeit' => [DocumentStatus::Processing, null],
        'gescheitert' => [DocumentStatus::Failed, 'Das PDF enthält keinen lesbaren Text. Vielleicht ist es ein Scan.'],
    ]);

    it('returns the status the processing has set since the upload', function () {
        janaLoggedIn();
        $id = $this->postJson(DOCUMENTS_URL, ['file' => UploadedFile::fake()->createWithContent('Notizen.md', '# Notizen')])->json('data.id');

        Document::findOrFail($id)->forceFill(['status' => DocumentStatus::Processing])->save();

        $this->getJson(DOCUMENTS_URL.'/'.$id)->assertOk()->assertJsonPath('data.status', 'processing');
    });

    it('returns 401 without a token', function () {
        $document = Document::factory()->create();

        $this->getJson(DOCUMENTS_URL.'/'.$document->id)->assertUnauthorized();
    });

    it('shows the status to every person of the workspace, not only the uploader', function () {
        $jana = User::factory()->for(Tenant::factory()->state(['name' => 'Nordlicht IT GmbH']))->create(['name' => 'Jana Petrov']);
        $tobias = User::factory()->for($jana->tenant)->create(['name' => 'Tobias Kern']);
        $document = Document::factory()->for($jana)->for($jana->tenant)->create(['status' => DocumentStatus::Processing]);
        Sanctum::actingAs($tobias);

        $this->getJson(DOCUMENTS_URL.'/'.$document->id)
            ->assertOk()
            ->assertJsonPath('data.id', $document->id)
            ->assertJsonPath('data.status', 'processing');
    });

    it('answers a document of another workspace exactly like one that does not exist', function () {
        config(['app.debug' => false]);
        $handbook = Document::factory()->create([
            'name' => 'Betriebshandbuch VPN.pdf',
            'status' => DocumentStatus::Failed,
            'failure_reason' => 'Das PDF ist mit einem Kennwort geschützt.',
        ]);
        $unknownId = (string) Str::ulid();
        Sanctum::actingAs(User::factory()->for(Tenant::factory()->state(['name' => 'Hafenkante Systemhaus GmbH']))->create(['name' => 'Mehmet Aydin']));

        $foreign = $this->getJson(DOCUMENTS_URL.'/'.$handbook->id);
        $unknown = $this->getJson(DOCUMENTS_URL.'/'.$unknownId);

        $foreign->assertNotFound();
        $unknown->assertNotFound();
        expect(str_replace($handbook->id, '{id}', $foreign->getContent()))
            ->toBe(str_replace($unknownId, '{id}', $unknown->getContent()))
            ->not->toContain('Betriebshandbuch')
            ->not->toContain('Kennwort');
    });

    it('answers 404 for a document of another workspace even when the request names that workspace', function (string $place) {
        $jana = User::factory()->for(Tenant::factory()->state(['name' => 'Nordlicht IT GmbH']))->create(['name' => 'Jana Petrov']);
        $handbook = Document::factory()->for($jana)->for($jana->tenant)->create(['name' => 'Betriebshandbuch VPN.pdf']);
        Sanctum::actingAs(User::factory()->for(Tenant::factory()->state(['name' => 'Hafenkante Systemhaus GmbH']))->create(['name' => 'Mehmet Aydin']));
        $url = DOCUMENTS_URL.'/'.$handbook->id;

        $response = match ($place) {
            'body' => $this->json('GET', $url, ['tenant_id' => $jana->tenant_id]),
            'query' => $this->getJson($url.'?tenant_id='.$jana->tenant_id),
            'header' => $this->withHeader('X-Tenant-Id', $jana->tenant_id)->getJson($url),
        };

        $response->assertNotFound();
        expect($response->getContent())->not->toContain('Betriebshandbuch');
    })->with(['body', 'query', 'header']);

    it('answers 404 and no server error for an id that is no document id', function (string $id) {
        janaLoggedIn();

        $this->getJson(DOCUMENTS_URL.'/'.rawurlencode($id))->assertNotFound();
    })->with([
        'zu kurz' => ['abc'],
        'zu lang' => [str_repeat('0', 300)],
        'Sonderzeichen' => ["01j'; DROP TABLE documents; --"],
        'Pfad' => ['../../etc/passwd'],
        'Großbuchstaben' => ['01JZZZZZZZZZZZZZZZZZZZZZZZ'],
    ]);

    it('keeps a document pending while nothing works off the queue, and its processing stays queued', function () {
        janaLoggedIn();
        $id = $this->postJson(DOCUMENTS_URL, ['file' => UploadedFile::fake()->createWithContent('Notizen Laptops.md', '# Neue Laptops')])->json('data.id');

        $this->travel(1)->hour();

        $this->getJson(DOCUMENTS_URL.'/'.$id)
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.failure_reason', null);
        Queue::assertPushed(ExtractDocumentText::class, fn (ExtractDocumentText $job) => $job->document->id === $id);
    });

    it('processes a document to the end after its uploader logged out and shows the new status after the next login', function () {
        $jana = User::factory()->for(Tenant::factory()->state(['name' => 'Nordlicht IT GmbH']))->create(['name' => 'Jana Petrov']);
        $morningToken = $jana->createToken('web')->plainTextToken;
        $id = $this->withToken($morningToken)
            ->postJson(DOCUMENTS_URL, ['file' => UploadedFile::fake()->createWithContent('Notizen Laptops.md', "# Neue Laptops\n\nAlle Geräte bekommen 32 GB RAM.")])
            ->assertCreated()
            ->json('data.id');
        $this->withToken($morningToken)->postJson('/api/v1/logout')->assertNoContent();

        Queue::pushed(ExtractDocumentText::class)->sole()->handle();

        Auth::forgetGuards();
        $this->withToken($morningToken)->getJson(DOCUMENTS_URL.'/'.$id)->assertUnauthorized();
        expect(Document::findOrFail($id)->pages()->count())->toBe(1);

        Auth::forgetGuards();
        $this->withToken($jana->createToken('web')->plainTextToken)
            ->getJson(DOCUMENTS_URL.'/'.$id)
            ->assertOk()
            ->assertJsonPath('data.status', 'processing');
    });
});
