<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Uploaded Documents
    |--------------------------------------------------------------------------
    |
    | The largest file a person may upload, in megabytes. PHP must accept
    | slightly more (docker/api/uploads.ini), so that a file just above this
    | limit gets the message of the application instead of a failed upload.
    | The page for documents checks the same value before sending
    | (MAX_SIZE_MB in app/app/pages/documents.vue); change all three together.
    |
    */

    'max_size_mb' => 10,

];
