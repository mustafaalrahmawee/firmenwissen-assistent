<?php

namespace App\Models;

use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * A workspace. Its ULID never changes and is also its tenant name in the search index.
 */
#[Fillable(['name'])]
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory, HasUlids;

    /**
     * @return HasMany<Document, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * Refuse to change the id of a saved workspace.
     */
    protected static function booted(): void
    {
        static::updating(function (Tenant $tenant): void {
            if ($tenant->isDirty($tenant->getKeyName())) {
                throw new LogicException('Die Kennung eines Arbeitsbereichs lässt sich nicht ändern.');
            }
        });
    }
}
