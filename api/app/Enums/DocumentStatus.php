<?php

namespace App\Enums;

/**
 * Where a document stands in its processing.
 */
enum DocumentStatus: string
{
    case Pending = 'pending';
    case Failed = 'failed';
}
