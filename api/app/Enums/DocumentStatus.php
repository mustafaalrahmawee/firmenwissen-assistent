<?php

namespace App\Enums;

/**
 * How far the processing of a document is, stored in the column "status".
 */
enum DocumentStatus: string
{
    /** Uploaded, the processing has not started yet. */
    case Pending = 'pending';

    /** The processing has started and is not finished. */
    case Processing = 'processing';

    /** The processing gave up; the reason stands at the document. */
    case Failed = 'failed';
}
