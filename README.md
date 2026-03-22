# nehalfstudio/filament-backup

Laravel **Filament v5** admin backups: MySQL/MariaDB (`mysqldump`) or SQLite file copy, ZIP of `storage/app` (configurable), **local** and/or **Google Drive** destinations, **retention** (default last 3 per kind per destination), **synchronous** runs from the panel and CLI, and an optional **scheduler**.

Backups from the Filament UI and from `php artisan filament-backup:run` execute **in the current PHP process** (no queue job). Plan for **PHP `max_execution_time`** and web server timeouts on large sites.

---

## Requirements

- PHP **8.2+**
- Laravel **12** / Filament **v5**
- **MySQL/MariaDB:** `mysqldump` available on `PATH`, or set `FILAMENT_BACKUP_MYSQLDUMP_PATH` (common on Windows/XAMPP).
- **Google Drive (optional):** Google Cloud project with Drive API enabled and a **service account** JSON key—not a Gmail password.

---

## Installation

### 1. Install with Composer

From [Packagist](https://packagist.org/packages/nehalfstudio/filament-backup):

```bash
composer require nehalfstudio/filament-backup
```

If you only have a `dev-main` release and Composer complains about stability, you can require it explicitly, for example:

```bash
composer require nehalfstudio/filament-backup:dev-main
```

### 2. Publish configuration (recommended)

```bash
php artisan vendor:publish --tag=filament-backup-config
```

This creates `config/filament-backup.php`. You can instead rely on package defaults and **only** use `.env` variables (see below).

### 3. Register the Filament plugin

In your panel provider (e.g. `app/Providers/Filament/AdminPanelProvider.php`):

```php
use NehalfStudio\FilamentBackup\Filament\FilamentBackupPlugin;

return $panel
    // ...
    ->plugin(FilamentBackupPlugin::make());
```

### 4. Database notifications (recommended)

Success and failure messages can be stored as Filament database notifications. Your `User` model should use `Notifiable`, and you need the notifications table:

```bash
php artisan notifications:table
php artisan migrate
```

If notifications are missing, the package still shows **toast** notifications in the panel; database delivery is skipped with a log warning.

### 5. Environment variables

Copy the variables you need into `.env` (full list in [Configuration](#configuration-environment-variables)). At minimum, set a **local backup path** if you do not want the default `storage/app/backups`, and configure **Google Drive** if you use it.

### 6. Clear config cache

After changing `.env` or config:

```bash
php artisan config:clear
```

---

## Optional: translations

```bash
php artisan vendor:publish --tag=filament-backup-translations
```

Edit files under `lang/vendor/filament-backup`.

---

## Plugin options

```php
use NehalfStudio\FilamentBackup\Filament\FilamentBackupPlugin;

->plugin(
    FilamentBackupPlugin::make()
        ->authorize(fn (): bool => auth()->user()?->email === 'admin@example.com')
        ->usingPollingInterval('30s') // optional Livewire poll to refresh the page
        // ->usingPage(App\Filament\Pages\CustomBackups::class) // must extend ManageBackups
)
```

If `authorize()` is set, it runs for authenticated users and **takes precedence** over `FILAMENT_BACKUP_REQUIRE_GATE` / `interactWithFilamentBackup`.

---

## Configuration (environment variables)

| Variable | Default / behavior |
|----------|-------------------|
| `FILAMENT_BACKUP_RETENTION` | Max copies per prefix (`db-`, `storage-`) **per destination** (default `3`). |
| `FILAMENT_BACKUP_DB_CONNECTION` | Empty = Laravel `database.default`. |
| `FILAMENT_BACKUP_DB_GZIP` | `true` / `false` — gzip SQL/SQLite dumps. |
| `FILAMENT_BACKUP_MYSQLDUMP_PATH` | Path to `mysqldump` if not on `PATH` (e.g. `C:/xampp/mysql/bin/mysqldump.exe`). |
| `FILAMENT_BACKUP_DB_TIMEOUT` | Seconds for the dump subprocess (default `3600`). |
| `FILAMENT_BACKUP_STORAGE_ROOT` | Root folder for the storage ZIP; empty = `storage_path('app')`. |
| `FILAMENT_BACKUP_LOCAL_ENABLED` | `true` / `false` — write backups to the local path. |
| `FILAMENT_BACKUP_LOCAL_PATH` | Directory for local files; empty = `storage/app/backups`. Use forward slashes or quoted paths on Windows (e.g. `"F:/My Backups"`). |
| `FILAMENT_BACKUP_GDRIVE_ENABLED` | `true` / `false`. |
| `FILAMENT_BACKUP_GDRIVE_CREDENTIALS` | Absolute path to the **service account** JSON key file. |
| `FILAMENT_BACKUP_GDRIVE_FOLDER_ID` | Target Drive folder ID (from the folder URL). |
| `FILAMENT_BACKUP_SCHEDULE_ENABLED` | `true` registers a scheduled backup (default `false`). |
| `FILAMENT_BACKUP_SCHEDULE_CRON` | Cron expression (default `0 2 * * *`). |
| `FILAMENT_BACKUP_SCHEDULE_TYPE` | `database`, `storage`, or `both`. |
| `FILAMENT_BACKUP_SCHEDULE_WITHOUT_OVERLAPPING` | `true` / `false`. |
| `FILAMENT_BACKUP_SCHEDULE_OVERLAP_MINUTES` | Mutex TTL for `withoutOverlapping` (default `120`). |
| `FILAMENT_BACKUP_REQUIRE_GATE` | When `true`, require a registered `interactWithFilamentBackup` gate. |

Excluded from the storage ZIP by default (see `config/filament-backup.php`): `temp`, framework cache/sessions/views, `logs`, and `backups` under `storage/app`.

---

## Google Drive credentials (service account)

This package uses the **Google Drive API** with a **service account**. Your personal Gmail **password is not used** and should not be placed in `.env` for this feature.

### Step 1 — Google Cloud project

1. Open [Google Cloud Console](https://console.cloud.google.com/).
2. Create a project (or pick an existing one).

### Step 2 — Enable Google Drive API

1. **APIs & Services → Library**.
2. Search for **Google Drive API** → **Enable**.

### Step 3 — Create a service account

1. **APIs & Services → Credentials**.
2. **Create credentials → Service account**.
3. Name it (e.g. `filament-backup`), finish the wizard.
4. Open the service account → **Keys → Add key → Create new key → JSON**.
5. Download the JSON file and store it **outside the web root** (e.g. `storage/app/keys/gdrive-service-account.json`).

Set in `.env`:

```env
FILAMENT_BACKUP_GDRIVE_CREDENTIALS=/absolute/path/to/your-key.json
```

(On Windows you can use `F:/keys/project.json` style paths.)

### Step 4 — Drive folder and sharing

1. In [Google Drive](https://drive.google.com/), create a folder for backups (or use an existing one).
2. Open the folder; the URL looks like:  
   `https://drive.google.com/drive/folders/THIS_IS_THE_FOLDER_ID`
3. **Share** the folder with the service account’s **email** (found in the JSON as `client_email`, e.g. `something@project-id.iam.gserviceaccount.com`) with **Editor** access.

Set:

```env
FILAMENT_BACKUP_GDRIVE_FOLDER_ID=THIS_IS_THE_FOLDER_ID
FILAMENT_BACKUP_GDRIVE_ENABLED=true
```

### Step 5 — Verify

```bash
php artisan config:clear
php artisan filament-backup:run storage
```

Check the folder in Drive for a new `storage-*.zip` (or run `database` / `both` as needed).

---

## Filament panel

After installation, open **System → Backups** (or your navigation group). Actions run **immediately**; wait for the request to finish. Large backups may need higher `max_execution_time` (PHP) and proxy timeouts (nginx/Apache).

---

## Artisan

```bash
php artisan filament-backup:run           # default: both
php artisan filament-backup:run database
php artisan filament-backup:run storage
php artisan filament-backup:run both
```

All modes run **synchronously** in the current process.

---

## Scheduler

Set `FILAMENT_BACKUP_SCHEDULE_ENABLED=true` and ensure the OS runs Laravel’s scheduler every minute, for example:

```cron
* * * * * cd /path-to-your-app && php artisan schedule:run >> /dev/null 2>&1
```

Scheduled backups also run **synchronously** inside `schedule:run`; failures are logged. For very heavy jobs, consider wrapping `php artisan filament-backup:run` in your own shell script and cron instead.

---

## Authorization

By default, any authenticated Filament user can open the backups page.

**Option A — Plugin closure**

```php
FilamentBackupPlugin::make()->authorize(fn (): bool => auth()->user()?->is_admin);
```

**Option B — Gate**

Set `FILAMENT_BACKUP_REQUIRE_GATE=true` and register:

```php
Gate::define('interactWithFilamentBackup', fn (User $user) => $user->is_admin);
```

---

## MySQL password note

The dumper passes the database password to `mysqldump` via the `MYSQL_PWD` environment variable (common for automation). Some MySQL builds deprecate this; if dumps fail, upgrade the client or adapt the dumper for a `--defaults-extra-file` approach.

---

## Archive format

Archives use **ZIP** (`ZipArchive`) only. RAR is not supported.

---

## License

MIT.
