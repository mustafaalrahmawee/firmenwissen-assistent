<?php

namespace App\Exceptions;

use Exception;

/**
 * The file of a document can never be read as it is, so reading it again is no use.
 * The message is the reason in German that the person sees at the document.
 */
class UnreadableDocument extends Exception
{
    public static function protectedPdf(): self
    {
        return new self('Das PDF ist mit einem Kennwort geschützt. Bitte eine Fassung ohne Kennwort hochladen.');
    }

    public static function damagedPdf(): self
    {
        return new self('Das PDF ist beschädigt und lässt sich nicht öffnen.');
    }

    /**
     * Text in images is not recognised, so a scan has no readable text.
     */
    public static function noTextInPdf(): self
    {
        return new self('Das PDF enthält keinen lesbaren Text. Vielleicht ist es ein Scan; Text aus Bildern wird nicht erkannt.');
    }

    public static function emptyMarkdown(): self
    {
        return new self('Die Markdown-Datei ist leer.');
    }
}
