<?php

use App\Jobs\ExtractDocumentText;
use App\Models\Document;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToWriteFile;

const DOCUMENTS_URL = '/api/v1/documents';
const WRONG_FORMAT = 'Erlaubt sind nur PDF-Dateien (.pdf).';

beforeEach(function () {
    Storage::fake('local');
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
    beforeEach(function () {
        Queue::fake();
    });

    it('stores a PDF unchanged as a document of the workspace of the uploader and returns it', function () {
        $jana = janaLoggedIn();

        $response = $this->postJson(DOCUMENTS_URL, ['file' => UploadedFile::fake()->createWithContent('Betriebshandbuch VPN.pdf', pdfBytes())]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Betriebshandbuch VPN.pdf')
            ->assertJsonPath('data.size', strlen(pdfBytes()));
        expect(array_keys($response->json('data')))->toBe(['id', 'name', 'size', 'created_at']);

        $document = Document::sole();
        expect($document->id)->toBe($response->json('data.id'))
            ->and($document->tenant_id)->toBe($jana->tenant_id)
            ->and($document->user_id)->toBe($jana->id)
            ->and($document->created_at)->not->toBeNull()
            ->and($document->sha256)->toBe(hash('sha256', pdfBytes()))
            ->and($document->path)->toBe("documents/{$jana->tenant_id}/{$document->id}.pdf");
        expect(Storage::disk('local')->get($document->path))->toBe(pdfBytes());
    });

    it('accepts a PDF with its extension in any capitals and stores it as .pdf', function (string $name) {
        $jana = janaLoggedIn();

        $response = $this->postJson(DOCUMENTS_URL, ['file' => UploadedFile::fake()->createWithContent($name, pdfBytes())]);

        $response->assertCreated();
        $document = Document::sole();
        expect($document->path)->toBe("documents/{$jana->tenant_id}/{$document->id}.pdf")
            ->and(Storage::disk('local')->get($document->path))->toBe(pdfBytes());
    })->with(['Betriebshandbuch.PDF', 'Betriebshandbuch.Pdf']);

    it('lets every person of the workspace upload', function () {
        $jana = janaLoggedIn();
        $tobias = User::factory()->for($jana->tenant)->create(['name' => 'Tobias Kern']);
        Sanctum::actingAs($tobias);

        $response = $this->postJson(DOCUMENTS_URL, ['file' => UploadedFile::fake()->createWithContent('Notizen.pdf', pdfBytes('Notizen'))]);

        $response->assertCreated();
        expect(Document::sole())->tenant_id->toBe($jana->tenant_id)->user_id->toBe($tobias->id);
    });

    it('returns 401 without a token', function () {
        $response = $this->postJson(DOCUMENTS_URL, ['file' => UploadedFile::fake()->createWithContent('Notizen.pdf', pdfBytes('Notizen'))]);

        $response->assertUnauthorized();
        $this->assertDatabaseEmpty('documents');
        expect(Storage::disk('local')->allFiles())->toBeEmpty();
    });

    it('puts the document into the workspace of the uploader even when the request names another one', function (string $place) {
        $nordlicht = Tenant::factory()->create(['name' => 'Nordlicht IT GmbH']);
        $mehmet = User::factory()->for(Tenant::factory()->state(['name' => 'Hafenkante Systemhaus GmbH']))->create();
        Sanctum::actingAs($mehmet);
        $file = UploadedFile::fake()->createWithContent('Angebot.pdf', pdfBytes('Angebot'));

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
    })->with(['Angebot.docx', 'Notizen.md', 'Notizen.markdown', 'Notizen.txt', 'Foto.png', 'Archiv.zip', 'skript.php', 'ohne-endung', 'Handbuch.pdf.exe']);

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

    it('returns 422 for a PDF whose name does not end in .pdf', function (string $name) {
        janaLoggedIn();

        $response = $this->postJson(DOCUMENTS_URL, ['file' => UploadedFile::fake()->createWithContent($name, pdfBytes())]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['file' => WRONG_FORMAT]);
        $this->assertDatabaseEmpty('documents');
        expect(Storage::disk('local')->allFiles())->toBeEmpty();
    })->with(['Betriebshandbuch.md', 'Betriebshandbuch.markdown', 'Betriebshandbuch']);

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
        $name = 'Übergabe & Größe (v2) – Entwurf #1.pdf';

        $response = $this->postJson(DOCUMENTS_URL, ['file' => UploadedFile::fake()->createWithContent($name, pdfBytes())]);

        $response->assertCreated()->assertJsonPath('data.name', $name);
        expect(Document::sole()->name)->toBe($name);
    });

    it('drops path parts of the file name and stores the file only under a name of its own', function (string $name, string $storedName) {
        $jana = janaLoggedIn();

        $response = $this->postJson(DOCUMENTS_URL, ['file' => UploadedFile::fake()->createWithContent($name, pdfBytes())]);

        $response->assertCreated()->assertJsonPath('data.name', $storedName);
        $document = Document::sole();
        expect(Storage::disk('local')->allFiles())->toBe(["documents/{$jana->tenant_id}/{$document->id}.pdf"]);
    })->with([
        'parent folders' => ['../../.env.pdf', '.env.pdf'],
        'backslashes' => ['..\\..\\public\\index.pdf', 'index.pdf'],
        'absolute path' => ['/etc/passwd.pdf', 'passwd.pdf'],
    ]);

    it('shortens a file name longer than 255 characters to its first 255 and keeps one of 255', function () {
        janaLoggedIn();
        $tooLong = str_repeat('ä', 252).'.pdf';
        $longest = str_repeat('ä', 251).'.pdf';

        $this->postJson(DOCUMENTS_URL, ['file' => UploadedFile::fake()->createWithContent($tooLong, pdfBytes('A'))])
            ->assertCreated()
            ->assertJsonPath('data.name', mb_substr($tooLong, 0, 255));
        $this->postJson(DOCUMENTS_URL, ['file' => UploadedFile::fake()->createWithContent($longest, pdfBytes('B'))])
            ->assertCreated()
            ->assertJsonPath('data.name', $longest);
    });

    it('replaces invalid characters in the file name instead of failing', function () {
        janaLoggedIn();

        $response = $this->postJson(DOCUMENTS_URL, ['file' => UploadedFile::fake()->createWithContent("Gr\xF6\xDFe.pdf", pdfBytes())]);

        $response->assertCreated()->assertJsonPath('data.name', 'Gr??e.pdf');
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
        DB::beforeStartingTransaction(function () use ($jana, $tobias): void {
            DB::table('documents')->insertOrIgnore([
                'id' => strtolower((string) Str::ulid()),
                'tenant_id' => $jana->tenant_id,
                'user_id' => $tobias->id,
                'name' => 'Betriebshandbuch VPN.pdf',
                'size' => strlen(pdfBytes()),
                'sha256' => hash('sha256', pdfBytes()),
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

        $response = $this->postJson(DOCUMENTS_URL, ['file' => UploadedFile::fake()->createWithContent('Notizen.pdf', pdfBytes('Notizen'))]);

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
            ->with(Mockery::on(fn (string $path) => preg_match("#^documents/{$jana->tenant_id}/[0-9a-z]{26}\\.pdf$#", $path) === 1))
            ->andReturn(true);
        Storage::shouldReceive('disk')->with('local')->andReturn($disk);

        $response = $this->postJson(DOCUMENTS_URL, ['file' => UploadedFile::fake()->createWithContent('Notizen.pdf', pdfBytes('Notizen'))]);

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

        $this->postJson(DOCUMENTS_URL, ['file' => UploadedFile::fake()->createWithContent('Notizen.pdf', pdfBytes('Notizen'))])->assertServerError();

        Log::shouldHaveReceived('warning')
            ->with('Datei ohne Dokument blieb in der Ablage liegen.', Mockery::on(fn (array $context) => str_starts_with($context['path'], 'documents/')))
            ->once();
    });

    it('starts reading the text of the uploaded document without calling the services for embeddings and search', function () {
        Http::preventStrayRequests();
        janaLoggedIn();

        $response = $this->postJson(DOCUMENTS_URL, ['file' => UploadedFile::fake()->createWithContent('Notizen.pdf', pdfBytes('Notizen'))]);

        $response->assertCreated();
        Queue::assertPushedTimes(ExtractDocumentText::class, 1);
        Queue::assertPushed(ExtractDocumentText::class, fn (ExtractDocumentText $job) => $job->document->is(Document::sole()));
    });

    it('starts no job for an upload that is rejected', function (string $content) {
        janaLoggedIn();
        $this->postJson(DOCUMENTS_URL, ['file' => UploadedFile::fake()->createWithContent('Betriebshandbuch VPN.pdf', pdfBytes())])->assertCreated();
        Queue::fake();

        $response = $this->postJson(DOCUMENTS_URL, ['file' => UploadedFile::fake()->createWithContent('Kopie.pdf', $content)]);

        $response->assertUnprocessable();
        Queue::assertNothingPushed();
    })->with([
        'same content' => [pdfBytes()],
        'no PDF' => ["# Kein PDF\n"],
    ]);

    it('returns 429 after thirty uploads within a minute from the same account', function () {
        janaLoggedIn();
        foreach (range(1, 30) as $attempt) {
            $this->postJson(DOCUMENTS_URL, ['file' => UploadedFile::fake()->createWithContent("Notiz {$attempt}.pdf", pdfBytes("Notiz {$attempt}"))])->assertCreated();
        }

        $response = $this->postJson(DOCUMENTS_URL, ['file' => UploadedFile::fake()->createWithContent('Notiz 31.pdf', pdfBytes('Notiz 31'))]);

        $response->assertTooManyRequests()->assertHeader('Retry-After');
        expect(Document::count())->toBe(30);
    });

    it('counts rejected uploads towards the limit', function () {
        janaLoggedIn();
        foreach (range(1, 30) as $attempt) {
            $this->postJson(DOCUMENTS_URL, [])->assertUnprocessable();
        }

        $this->postJson(DOCUMENTS_URL, ['file' => UploadedFile::fake()->createWithContent('Notizen.pdf', pdfBytes('Notizen'))])->assertTooManyRequests();
        $this->assertDatabaseEmpty('documents');
    });

    it('does not limit another account of the same workspace', function () {
        $jana = janaLoggedIn();
        foreach (range(1, 30) as $attempt) {
            $this->postJson(DOCUMENTS_URL, []);
        }
        Sanctum::actingAs(User::factory()->for($jana->tenant)->create());

        $response = $this->postJson(DOCUMENTS_URL, ['file' => UploadedFile::fake()->createWithContent('Notizen.pdf', pdfBytes('Notizen'))]);

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

        $response = $this->postJson(DOCUMENTS_URL, ['file' => UploadedFile::fake()->createWithContent('Notizen.pdf', pdfBytes('Notizen'))]);

        $response->assertTooManyRequests();
        expect((int) $response->headers->get('Retry-After'))->toBeGreaterThan(60);
        $this->assertDatabaseEmpty('documents');

        $this->travel(1)->days();
        $this->postJson(DOCUMENTS_URL, ['file' => UploadedFile::fake()->createWithContent('Notizen.pdf', pdfBytes('Notizen'))])->assertCreated();
    });
});

describe('POST /api/v1/documents with the queue in the database', function () {
    beforeEach(function () {
        config(['queue.default' => 'database']);
    });

    it('answers the upload before the text is read and leaves the job waiting in the queue', function () {
        janaLoggedIn();

        $response = $this->postJson(DOCUMENTS_URL, ['file' => UploadedFile::fake()->createWithContent('Notizen.pdf', pdfBytes('Notizen'))]);

        $response->assertCreated();
        $job = DB::table('jobs')->sole();
        expect(json_decode($job->payload, true)['displayName'])->toBe(ExtractDocumentText::class)
            ->and($job->payload)->toContain($response->json('data.id'))
            ->and($job->attempts)->toBe(0);
    });

    it('saves no document and removes its file when the job cannot be queued', function () {
        Schema::drop('jobs');
        janaLoggedIn();

        $response = $this->postJson(DOCUMENTS_URL, ['file' => UploadedFile::fake()->createWithContent('Notizen.pdf', pdfBytes('Notizen'))]);

        $response->assertServerError();
        $this->assertDatabaseEmpty('documents');
        expect(Storage::disk('local')->allFiles())->toBeEmpty();
    });
});
