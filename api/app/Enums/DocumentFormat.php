<?php

namespace App\Enums;

/**
 * The formats a document can have, stored in the column "format".
 */
enum DocumentFormat: string
{
    case Pdf = 'pdf';
    case Markdown = 'markdown';

    /**
     * The format for an accepted file extension, in any capitals.
     */
    public static function fromExtension(string $extension): self
    {
        return match (strtolower($extension)) {
            'pdf' => self::Pdf,
            'md', 'markdown' => self::Markdown,
        };
    }

    /**
     * The extension of the file on the disk.
     */
    public function extension(): string
    {
        return match ($this) {
            self::Pdf => 'pdf',
            self::Markdown => 'md',
        };
    }
}
