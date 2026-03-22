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
    | Google Drive (service account JSON)
    |--------------------------------------------------------------------------
    */
    'google_drive' => [
        'enabled' => filter_var(env('FILAMENT_BACKUP_GDRIVE_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'credentials_json' => env('FILAMENT_BACKUP_GDRIVE_CREDENTIALS'),
        'folder_id' => env('FILAMENT_BACKUP_GDRIVE_FOLDER_ID'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    */
    'queue' => [
        'connection' => env('FILAMENT_BACKUP_QUEUE_CONNECTION'),
        'queue' => env('FILAMENT_BACKUP_QUEUE', 'default'),
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
