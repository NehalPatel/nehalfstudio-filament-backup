<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Retention (per backup kind and per destination)
    |--------------------------------------------------------------------------
    | After a successful upload, older files matching the same prefix are
    | removed so at most this many copies remain (including the new one).
    */
    'retention_count' => (int) env('FILAMENT_BACKUP_RETENTION', 3),

    /*
    |--------------------------------------------------------------------------
    | PHP max_execution_time (optional)
    |--------------------------------------------------------------------------
    | Applied at the start of each BackupRunner::run() (panel sync, queue job,
    | artisan command, and scheduled runs). null = do not call set_time_limit.
    | 0 = unlimited (@set_time_limit(0)). Ignored on hosts that disable it.
    */
    'max_execution_seconds' => ($v = env('FILAMENT_BACKUP_MAX_EXECUTION')) !== null && $v !== ''
        ? (int) $v
        : null,

    /*
    |--------------------------------------------------------------------------
    | Panel UI: queue backups
    |--------------------------------------------------------------------------
    | When true, Filament backup actions dispatch RunFilamentBackupJob. Requires
    | a running queue worker unless QUEUE_CONNECTION=sync (sync still runs in
    | the HTTP request and can hit web timeouts).
    */
    'use_queue_for_ui' => filter_var(env('FILAMENT_BACKUP_UI_USE_QUEUE', true), FILTER_VALIDATE_BOOLEAN),

    /*
    |--------------------------------------------------------------------------
    | Queue job timeout (seconds)
    |--------------------------------------------------------------------------
    | Laravel worker must use --timeout greater than or equal to this value.
    */
    'queue_timeout_seconds' => (int) env('FILAMENT_BACKUP_QUEUE_TIMEOUT', 7200),

    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    */
    'database' => [
        'connection' => env('FILAMENT_BACKUP_DB_CONNECTION'),
        'gzip' => filter_var(env('FILAMENT_BACKUP_DB_GZIP', true), FILTER_VALIDATE_BOOLEAN),
        'mysqldump_path' => env('FILAMENT_BACKUP_MYSQLDUMP_PATH', 'mysqldump'),
        'mysqldump_options' => [
            '--skip-comments',
            '--single-transaction',
            '--quick',
            '--lock-tables=false',
        ],
        'timeout_seconds' => (int) env('FILAMENT_BACKUP_DB_TIMEOUT', 3600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage archive (ZIP)
    |--------------------------------------------------------------------------
    */
    'storage' => [
        'root' => env('FILAMENT_BACKUP_STORAGE_ROOT'),
        'exclude' => [
            'temp',
            'temp/**',
            'framework/cache/**',
            'framework/sessions/**',
            'framework/views/**',
            'logs/**',
            'backups',
            'backups/**',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Local destination
    |--------------------------------------------------------------------------
    */
    'local' => [
        'enabled' => filter_var(env('FILAMENT_BACKUP_LOCAL_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'path' => env('FILAMENT_BACKUP_LOCAL_PATH'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Google Drive
    |--------------------------------------------------------------------------
    | Recommended for personal @gmail.com: OAuth client JSON (installed / web) plus
    | FILAMENT_BACKUP_GDRIVE_REFRESH_TOKEN from `php artisan filament-backup:google-auth`.
    | Service account JSON is for Workspace / Shared Drive only (not personal My Drive quota).
    */
    'google_drive' => [
        'enabled' => filter_var(env('FILAMENT_BACKUP_GDRIVE_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'credentials_json' => env('FILAMENT_BACKUP_GDRIVE_CREDENTIALS'),
        'oauth_refresh_token' => env('FILAMENT_BACKUP_GDRIVE_REFRESH_TOKEN'),
        'folder_id' => env('FILAMENT_BACKUP_GDRIVE_FOLDER_ID'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Schedule (optional; requires `php artisan schedule:run`)
    |--------------------------------------------------------------------------
    */
    'schedule' => [
        'enabled' => filter_var(env('FILAMENT_BACKUP_SCHEDULE_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'expression' => env('FILAMENT_BACKUP_SCHEDULE_CRON', '0 2 * * *'),
        'type' => env('FILAMENT_BACKUP_SCHEDULE_TYPE', 'both'),
        'without_overlapping' => filter_var(env('FILAMENT_BACKUP_SCHEDULE_WITHOUT_OVERLAPPING', true), FILTER_VALIDATE_BOOLEAN),
        'overlap_minutes' => (int) env('FILAMENT_BACKUP_SCHEDULE_OVERLAP_MINUTES', 120),
    ],

    /*
    |--------------------------------------------------------------------------
    | Filament page access
    |--------------------------------------------------------------------------
    | When false, any authenticated Filament user may open the backup page.
    | When true, the `interactWithFilamentBackup` gate must be registered and pass.
    */
    'require_gate' => filter_var(env('FILAMENT_BACKUP_REQUIRE_GATE', false), FILTER_VALIDATE_BOOLEAN),

];
