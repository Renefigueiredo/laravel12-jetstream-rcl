<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Spreadsheet Uploads
    |--------------------------------------------------------------------------
    |
    | Limits and storage for the spreadsheets sent to a reconciliation session.
    | Files are kept on a private disk and never served from the public folder.
    |
    */

    'upload' => [
        'max_size_mb' => (int) env('CONCILIATION_UPLOAD_MAX_SIZE_MB', 50),
        'disk' => env('CONCILIATION_UPLOAD_DISK', 'local'),
        'insert_chunk' => (int) env('CONCILIATION_UPLOAD_INSERT_CHUNK', 500),
        'blank_rows_limit' => (int) env('CONCILIATION_UPLOAD_BLANK_ROWS_LIMIT', 10000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Excluded Operation Codes
    |--------------------------------------------------------------------------
    |
    | Limits for the spreadsheet that adds operation codes to the list of
    | codes left out of the reconciliation.
    |
    */

    'excluded_codes' => [
        'max_size_mb' => (int) env('CONCILIATION_EXCLUDED_CODES_MAX_SIZE_MB', 5),
        'max_rows' => (int) env('CONCILIATION_EXCLUDED_CODES_MAX_ROWS', 10000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Import Attempts
    |--------------------------------------------------------------------------
    |
    | How long a period divergence waits for confirmation, and how long
    | finished attempts and their error reports are kept before being pruned.
    |
    */

    'attempts' => [
        'confirmation_ttl_minutes' => (int) env('CONCILIATION_CONFIRMATION_TTL_MINUTES', 30),
        'retention_hours' => (int) env('CONCILIATION_ATTEMPT_RETENTION_HOURS', 24),
    ],

    /*
    |--------------------------------------------------------------------------
    | Stalled Work
    |--------------------------------------------------------------------------
    |
    | After these limits, an import that stopped moving is marked as failed and
    | a session stuck in processing is returned to the open status.
    |
    */

    'stale' => [
        'attempt_minutes' => (int) env('CONCILIATION_STALE_ATTEMPT_MINUTES', 30),
        'processing_minutes' => (int) env('CONCILIATION_STALE_PROCESSING_MINUTES', 120),
    ],

    /*
    |--------------------------------------------------------------------------
    | Reconciliation Engine
    |--------------------------------------------------------------------------
    |
    | The engine is delivered by a later module. While disabled, sessions can
    | be prepared but the reconciliation cannot be executed.
    |
    */

    'engine_enabled' => (bool) env('CONCILIATION_ENGINE_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Display Timezone
    |--------------------------------------------------------------------------
    |
    | Timestamps are stored in UTC. This timezone is used to display them and
    | to decide which month is the current one.
    |
    */

    'display_timezone' => env('CONCILIATION_DISPLAY_TIMEZONE', 'America/Sao_Paulo'),

];
