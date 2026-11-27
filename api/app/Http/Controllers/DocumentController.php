<?php

namespace App\Http\Controllers;

use App\Http\CurrentTenant;
use App\Http\Requests\StoreDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class DocumentController extends Controller
{
    /**
     * Store the file unchanged and record it as a document of the current workspace.
     * If anything fails, the file is removed again. The same content twice in one workspace
     * is stopped by the unique index. A name longer than 255 characters is shortened.
     */
    public function store(StoreDocumentRequest $request, CurrentTenant $currentTenant): DocumentResource
    {
        $tenant = $currentTenant->get();
        $user = $request->user();
        $file = $request->file('file');
        $disk = Storage::disk('local');

        $document = new Document([
            'name' => mb_substr(mb_scrub($file->getClientOriginalName(), 'UTF-8'), 0, 255),
            'size' => $file->getSize(),
            'sha256' => hash_file('sha256', $file->getRealPath()),
        ]);
        $document->id = $document->newUniqueId();
        $document->tenant()->associate($tenant);
        $document->user()->associate($user);

        $directory = 'documents/'.$tenant->id;
        $fileName = $document->id.'.pdf';
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

        return DocumentResource::make($document);
    }
}
