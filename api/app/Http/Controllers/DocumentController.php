<?php

namespace App\Http\Controllers;

use App\Enums\DocumentFormat;
use App\Http\CurrentTenant;
use App\Http\Requests\StoreDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Jobs\ExtractDocumentText;
use App\Models\Document;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class DocumentController extends Controller
{
    /**
     * Store the file unchanged and record it as a document of the current workspace.
     * If anything fails, the file is removed again. The same content twice in one workspace
     * is stopped by the unique index. A name longer than 255 characters is shortened.
     * Once the document is saved, its processing is queued; if queueing fails, it is
     * reported and the upload still succeeds, the document stays pending.
     */
    public function store(StoreDocumentRequest $request, CurrentTenant $currentTenant): DocumentResource
    {
        $tenant = $currentTenant->get();
        $user = $request->user();
        $file = $request->file('file');
        $disk = Storage::disk('local');

        $document = new Document([
            'name' => mb_substr(mb_scrub($file->getClientOriginalName(), 'UTF-8'), 0, 255),
            'format' => DocumentFormat::fromExtension($file->getClientOriginalExtension()),
            'size' => $file->getSize(),
            'sha256' => hash_file('sha256', $file->getRealPath()),
        ]);
        $document->id = $document->newUniqueId();
        $document->tenant()->associate($tenant);
        $document->user()->associate($user);

        $directory = 'documents/'.$tenant->id;
        $fileName = $document->id.'.'.$document->format->extension();
        $document->path = $directory.'/'.$fileName;

        try {
            $disk->putFileAs($directory, $file, $fileName);
            $document->save();
        } catch (Throwable $exception) {
            rescue(
                fn () => $disk->delete($document->path),
                fn () => Log::warning('Datei ohne Dokument blieb in der Ablage liegen.', ['path' => $document->path]),
                report: false,
            );

            if ($exception instanceof UniqueConstraintViolationException) {
                $existing = Document::sameContentIn($tenant, $document->sha256);

                throw ValidationException::withMessages([
                    'file' => $existing?->duplicateMessage() ?? 'Diese Datei liegt schon im Arbeitsbereich.',
                ]);
            }

            throw $exception;
        }

        rescue(function () use ($document): void {
            ExtractDocumentText::dispatch($document);
        });

        return DocumentResource::make($document);
    }

    /**
     * Return one document of the current workspace with its processing status.
     * The document is looked up only among the documents of the workspace. An id that is no
     * ULID is not looked up at all, because MySQL would match an id with trailing spaces;
     * it gets the same 404 as an unknown id.
     */
    public function show(CurrentTenant $currentTenant, string $document): DocumentResource
    {
        if (! Str::isUlid($document)) {
            throw (new ModelNotFoundException)->setModel(Document::class, $document);
        }

        return DocumentResource::make($currentTenant->get()->documents()->findOrFail($document));
    }
}
