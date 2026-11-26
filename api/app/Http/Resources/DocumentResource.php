<?php

namespace App\Http\Resources;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Document
 */
class DocumentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * The place on the disk and the SHA-256 stay on the server.
     *
     * The reason is set only for a failed document.
     *
     * @return array{id: string, name: string, format: string, size: int, status: string, failure_reason: ?string, created_at: mixed}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'format' => $this->format->value,
            'size' => $this->size,
            'status' => $this->status->value,
            'failure_reason' => $this->failure_reason,
            'created_at' => $this->created_at,
        ];
    }
}
