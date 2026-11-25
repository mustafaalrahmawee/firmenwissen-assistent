<?php

namespace App\Models;

use App\Enums\DocumentFormat;
use App\Enums\DocumentStatus;
use Database\Factories\DocumentFactory;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An uploaded file of a workspace. The file lies unchanged on the local disk under "path";
 * workspace and uploader are set only through their relations, never by mass assignment.
 * The status starts as pending and is moved only by the processing.
 */
#[Fillable(['name', 'format', 'size', 'sha256', 'path'])]
#[Hidden(['sha256', 'path'])]
class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory, HasUlids;

    /**
     * Upload times in messages are shown in the time zone of the people using the app.
     */
    private const string DISPLAY_TIMEZONE = 'Europe/Berlin';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'format' => DocumentFormat::class,
            'size' => 'integer',
            'status' => DocumentStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * The account that uploaded the document.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The text of the document, page by page.
     *
     * @return HasMany<DocumentPage, $this>
     */
    public function pages(): HasMany
    {
        return $this->hasMany(DocumentPage::class);
    }

    /**
     * The document of the workspace with the same content, if there is one. Its uploader is loaded
     * only from the same workspace, so a message can never name an account of another workspace.
     */
    public static function sameContentIn(Tenant $tenant, string $sha256): ?self
    {
        return $tenant->documents()
            ->with(['user' => fn (Builder $query) => $query->where('tenant_id', $tenant->id)])
            ->where('sha256', $sha256)
            ->first();
    }

    /**
     * The message for an upload of the same content, naming this document.
     */
    public function duplicateMessage(): string
    {
        $uploadedAt = $this->created_at->timezone(self::DISPLAY_TIMEZONE);
        $uploader = $this->user === null ? '' : ' von '.$this->user->name;

        return sprintf(
            'Diese Datei liegt schon im Arbeitsbereich: „%s“, hochgeladen%s am %s um %s Uhr.',
            $this->name,
            $uploader,
            $uploadedAt->format('d.m.Y'),
            $uploadedAt->format('H:i'),
        );
    }
}
