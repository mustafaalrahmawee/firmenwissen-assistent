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
    | The upload dialog (app/app/components/DocumentUploadDialog.vue) names
    | and checks the same 10 MB before sending; change all three together.
    |
    */

    'max_size_mb' => 10,

];
