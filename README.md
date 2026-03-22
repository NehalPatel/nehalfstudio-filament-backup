# nehalfstudio/filament-backup

Laravel **Filament v5** admin backups: MySQL/MariaDB (`mysqldump`) or SQLite file copy, ZIP of `storage/app` (configurable), **local** and/or **Google Drive** destinations, **retention** (default last 3 per kind per destination), **queue** jobs, **Artisan** command, and optional **scheduler**.

## Installation

### Path repository (monorepo / local dev)

In your app `composer.json`:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "packages/nehalfstudio/filament-backup",
            "options": { "symlink": true }
        }
    ],
    "require": {
        "nehalfstudio/filament-backup": "*"
    }
}
```

Then:

```bash
composer update nehalfstudio/filament-backup
php artisan vendor:publish --tag=filament-backup-config
```

### Filament panel

Register the plugin on your panel provider:

```php
use NehalfStudio\FilamentBackup\Filament\FilamentBackupPlugin;

return $panel
    // ...
    ->plugin(FilamentBackupPlugin::make());
```

### Fluent plugin options (similar to [juniyasyos/filament-backup](https://packagist.org/packages/juniyasyos/filament-backup))

Chain configuration on the plugin instance:

```php
use NehalfStudio\FilamentBackup\Filament\FilamentBackupPlugin;

->plugin(
    FilamentBackupPlugin::make()
        ->authorize(fn (): bool => auth()->user()?->email === 'admin@example.com')
        ->usingQueue('backups', connection: 'redis') // optional; null uses config
        ->timeout(3600)      // job timeout seconds for Filament-dispatched jobs
        // ->noTimeout()     // or use a very high timeout (7 days) for huge dumps
        ->usingPollingInterval('10s') // optional Livewire poll to refresh the page
        // ->usingPage(App\Filament\Pages\CustomBackups::class) // must extend ManageBackups
)
```

If `authorize()` is set, it runs for authenticated users and **takes precedence** over `FILAMENT_BACKUP_REQUIRE_GATE` / `interactWithFilamentBackup`.

### Translations

Publish and edit strings (after publishing, the app loads from `lang/vendor/filament-backup`):

```bash
php artisan vendor:publish --tag=filament-backup-translations
```

### Queue

Backups from the UI and the default Artisan mode are **queued**. Run a worker:

```bash
php artisan queue:work
```

### Database notifications

Completion/failure messages use Filament database notifications. Ensure your `User` model uses `Notifiable` and you have run:

```bash
php artisan notifications:table
php artisan migrate
```

(Or use Filament’s own notification migrations if you already installed them.)

## Configuration

See `config/filament-backup.php`. Important environment variables:

| Variable | Purpose |
|----------|---------|
| `FILAMENT_BACKUP_RETENTION` | Max copies per prefix (`db-`, `storage-`) per destination (default `3`) |
| `FILAMENT_BACKUP_LOCAL_ENABLED` | Enable writing under local path (default `true`) |
| `FILAMENT_BACKUP_LOCAL_PATH` | Directory for local copies (default `storage/app/backups`) |
| `FILAMENT_BACKUP_GDRIVE_ENABLED` | Enable Google Drive uploads |
| `FILAMENT_BACKUP_GDRIVE_CREDENTIALS` | Absolute path to **service account** JSON |
| `FILAMENT_BACKUP_GDRIVE_FOLDER_ID` | Target Drive folder ID |
| `FILAMENT_BACKUP_MYSQLDUMP_PATH` | e.g. `C:\xampp\mysql\bin\mysqldump.exe` on Windows if not on `PATH` |
| `FILAMENT_BACKUP_SCHEDULE_ENABLED` | Register package schedule (default `false`) |
| `FILAMENT_BACKUP_SCHEDULE_CRON` | Cron expression (default `0 2 * * *`) |
| `FILAMENT_BACKUP_SCHEDULE_TYPE` | `database`, `storage`, or `both` |

### Google Drive (service account)

1. Create a service account in Google Cloud, enable **Drive API**, download JSON key.
2. Create a Drive folder; share it with the service account email (Editor).
3. Set `FILAMENT_BACKUP_GDRIVE_CREDENTIALS` and `FILAMENT_BACKUP_GDRIVE_FOLDER_ID` (from the folder URL).

### Authorization

By default any authenticated Filament user can open **System → Backups**.

You can restrict access with **`FilamentBackupPlugin::make()->authorize(fn (): bool => ...)`** (see above); that closure runs after login and overrides the env/gate flow.

Alternatively, set `FILAMENT_BACKUP_REQUIRE_GATE=true` and register the gate (access is denied if the gate is missing):

```php
Gate::define('interactWithFilamentBackup', fn (User $user) => $user->is_admin);
```

## Artisan

```bash
# Queue a job (default)
php artisan filament-backup:run both

# Run immediately in the current process (no queue)
php artisan filament-backup:run both --sync
```

## Scheduler

Enable `FILAMENT_BACKUP_SCHEDULE_ENABLED=true` and ensure the OS cron hits `php artisan schedule:run` every minute.

## MySQL password note

The dumper uses the `MYSQL_PWD` environment variable for the `mysqldump` process (common for automation). Some MySQL builds deprecate this; if `mysqldump` fails, consider upgrading the client or switching to a defaults-file approach in a fork.

## RAR

Archives use **ZIP** (`ZipArchive`) only. RAR creation is not supported without external proprietary tools.

## Comparison with `juniyasyos/filament-backup`

| | [juniyasyos/filament-backup](https://packagist.org/packages/juniyasyos/filament-backup) | this package |
|---|----------------------------------------------------------------------------------------|--------------|
| Filament | v4 | **v5** |
| Backup engine | [spatie/laravel-backup](https://github.com/spatie/laravel-backup) | Built-in `mysqldump` / SQLite + storage ZIP |
| Google Drive / retention | Via Spatie disks & config | First-class in package config |
| Plugin API | `::make()`, `authorize`, `usingQueue`, `timeout`, polling | **Same ideas** (`make()`, `authorize`, `usingQueue`, `timeout` / `noTimeout`, `usingPollingInterval`, `usingPage`) |

## License

MIT.
