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
    | While disabled, sessions can be prepared but the reconciliation cannot
    | be executed.
    |
    */

    'engine_enabled' => (bool) env('CONCILIATION_ENGINE_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Matching Rules
    |--------------------------------------------------------------------------
    |
    | Score thresholds and reading windows of the engine. The tolerance and the
    | surcharge cap are changed by administrators and live in the database.
    |
    */

    'engine' => [
        'automatic_threshold' => (int) env('CONCILIATION_ENGINE_AUTOMATIC_THRESHOLD', 90),
        'suggestion_threshold' => (int) env('CONCILIATION_ENGINE_SUGGESTION_THRESHOLD', 60),
        'supplier_threshold' => (int) env('CONCILIATION_ENGINE_SUPPLIER_THRESHOLD', 90),
        'lookback_months' => (int) env('CONCILIATION_ENGINE_LOOKBACK_MONTHS', 3),
        'suggestions_per_authorization' => (int) env('CONCILIATION_ENGINE_SUGGESTIONS_PER_AUTHORIZATION', 5),
        'card_species_marker' => env('CONCILIATION_ENGINE_CARD_SPECIES_MARKER', 'FATURA CARTAO'),
        'card_method_marker' => env('CONCILIATION_ENGINE_CARD_METHOD_MARKER', 'CARTAO'),
        'lock_wait_seconds' => (int) env('CONCILIATION_ENGINE_LOCK_WAIT_SECONDS', 600),
    ],

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
