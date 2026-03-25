# nehalfstudio/filament-backup

Laravel **Filament v5** admin backups: MySQL/MariaDB (`mysqldump`) or SQLite file copy, ZIP of `storage/app` (configurable), **local** and/or **Google Drive** destinations, **retention** (default last 3 per kind per destination), and an optional **scheduler**. Each run stores files under a **`YYYYMMDD`** subfolder (created automatically) inside your local path and/or under your configured Drive folder.

**Filament panel actions** dispatch a **queued job** by default (`RunFilamentBackupJob`), so long backups avoid the web request’s `max_execution_time`. Run a queue worker with a **timeout ≥** `FILAMENT_BACKUP_QUEUE_TIMEOUT` (see below). Local copies use **`rename()`** when possible (same volume) to skip duplicating huge files; Google Drive is written **before** the local move so the temp file stays available for upload.

`php artisan filament-backup:run` and the **scheduler** still run **in the current PHP process** (CLI often has no execution time limit). Optional **`FILAMENT_BACKUP_MAX_EXECUTION`** calls `set_time_limit()` at the start of every run.

---

## Requirements

- PHP **8.2+**
- Laravel **12** / Filament **v5**
- **MySQL/MariaDB:** `mysqldump` available on `PATH`, or set `FILAMENT_BACKUP_MYSQLDUMP_PATH` (common on Windows/XAMPP).
- **Google Drive (optional):** Google Cloud project with Drive API enabled. **Recommended:** **OAuth 2.0** (OAuth client JSON + refresh token) so backups use **your** personal Google account storage. **Service accounts** are **not** suitable for normal `@gmail.com` “My Drive” (effectively **no quota** there); use a service account only with **Google Workspace** / **Shared drives** where your org provisions storage.

---

## Installation

### 1. Install with Composer

From [Packagist](https://packagist.org/packages/nehalfstudio/filament-backup):

```bash
composer require nehalfstudio/filament-backup
```

Stable line is now available (`v1.0.0+`). For the latest unreleased changes only, you can still use:

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

### 4. Publish Filament assets (required for a styled Backups page)

The Backups UI ships as a **pre-built stylesheet** in the package (`dist/manage-backups-page.css`). It is copied into `public/` with the same command you use for Filament’s own assets. **You do not need to change your app’s `theme.css` or Vite config** for this package.

After `composer install` / `composer update`, run:

```bash
php artisan filament:assets
```

That publishes (among other files) `public/css/nehalfstudio-filament-backup/manage-backups-page.css`. The package injects a `<link>` for it only on the **Backups** page.

Re-run `php artisan filament:assets` whenever you upgrade Filament or this package.

### 5. Database notifications (recommended)

Success and failure messages can be stored as Filament database notifications. Your `User` model should use `Notifiable`, and you need the notifications table:

```bash
php artisan notifications:table
php artisan migrate
```

If notifications are missing, the package still shows **toast** notifications in the panel; database delivery is skipped with a log warning.

### 6. Environment variables

Copy the variables you need into `.env` (full list in [Configuration](#configuration-environment-variables)). At minimum, set a **local backup path** if you do not want the default `storage/app/backups`. For **Google Drive** with a personal Gmail account, use **OAuth** (see [Google Drive setup](#google-drive-setup-recommended-oauth)); do not rely on a service account for personal “My Drive.”

### 7. Clear config cache

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
| `FILAMENT_BACKUP_MAX_EXECUTION` | Empty = do not change PHP time limit. `0` = `set_time_limit(0)`. Any positive integer = seconds (e.g. `3600`). May be ignored on some hosts. |
| `FILAMENT_BACKUP_UI_USE_QUEUE` | `true` / `false` — panel backup buttons dispatch a queue job (default `true`). Set `false` to run synchronously in the request (needs high `max_execution_time` / proxy limits for large sites). |
| `FILAMENT_BACKUP_QUEUE_TIMEOUT` | Job timeout in seconds (default `7200`). Your worker must use at least this value, e.g. `php artisan queue:work --timeout=7200`. |
| `FILAMENT_BACKUP_DB_CONNECTION` | Empty = Laravel `database.default`. |
| `FILAMENT_BACKUP_DB_GZIP` | `true` / `false` — gzip SQL/SQLite dumps. |
| `FILAMENT_BACKUP_MYSQLDUMP_PATH` | Path to `mysqldump` if not on `PATH` (e.g. `C:/xampp/mysql/bin/mysqldump.exe`). |
| `FILAMENT_BACKUP_DB_TIMEOUT` | Seconds for the dump subprocess (default `3600`). |
| `FILAMENT_BACKUP_STORAGE_ROOT` | Root folder for the storage ZIP; empty = `storage_path('app')`. |
| `FILAMENT_BACKUP_LOCAL_ENABLED` | `true` / `false` — write backups to the local path. |
| `FILAMENT_BACKUP_LOCAL_PATH` | Directory for local files; empty = `storage/app/backups`. Use forward slashes or quoted paths on Windows (e.g. `"F:/My Backups"`). |
| `FILAMENT_BACKUP_GDRIVE_ENABLED` | `true` / `false`. |
| `FILAMENT_BACKUP_GDRIVE_CREDENTIALS` | **Recommended (personal Gmail):** absolute path to **OAuth client** JSON from Google Cloud (`installed` / `web`). **Alternative (Workspace / Shared Drive only):** service account JSON. |
| `FILAMENT_BACKUP_GDRIVE_REFRESH_TOKEN` | **Required** with OAuth client JSON (`php artisan filament-backup:google-auth`). Leave empty when using a service account. |
| `FILAMENT_BACKUP_GDRIVE_FOLDER_ID` | Folder ID from the Drive URL (a folder **you** own for OAuth; shared with the service account for service-account mode). |
| `FILAMENT_BACKUP_SCHEDULE_ENABLED` | `true` registers a scheduled backup (default `false`). |
| `FILAMENT_BACKUP_SCHEDULE_CRON` | Cron expression (default `0 2 * * *`). |
| `FILAMENT_BACKUP_SCHEDULE_TYPE` | `database`, `storage`, or `both`. |
| `FILAMENT_BACKUP_SCHEDULE_WITHOUT_OVERLAPPING` | `true` / `false`. |
| `FILAMENT_BACKUP_SCHEDULE_OVERLAP_MINUTES` | Mutex TTL for `withoutOverlapping` (default `120`). |
| `FILAMENT_BACKUP_REQUIRE_GATE` | When `true`, require a registered `interactWithFilamentBackup` gate. |

Excluded from the storage ZIP by default (see `config/filament-backup.php`): `temp`, framework cache/sessions/views, `logs`, and `backups` under `storage/app`.

---

## Google Drive setup (recommended: OAuth)

For **personal `@gmail.com`** (or any consumer Google account), use **OAuth 2.0** only. **Service accounts do not get your personal Drive quota**; uploads to “My Drive” as a service account will fail or misbehave. With OAuth, backups run as **you** and use **your** storage.

### OAuth quick steps

1. In [Google Cloud Console](https://console.cloud.google.com/) → **APIs & Services → Credentials**, create **OAuth client ID** (type **Desktop app** or **Web application**).
2. If you use **Web application**, add an authorized redirect URI (must match the command exactly), for example: `http://127.0.0.1:8765/`
3. Download the client JSON (e.g. `client_secret_….json`) and store it outside the web root.
4. Run once on your machine:

```bash
php artisan config:clear
php artisan filament-backup:google-auth --credentials=/absolute/path/to/client_secret….json
```

5. Paste the authorization code from the browser when prompted. Add the printed lines to `.env`:

```env
FILAMENT_BACKUP_GDRIVE_CREDENTIALS=/absolute/path/to/client_secret….json
FILAMENT_BACKUP_GDRIVE_REFRESH_TOKEN=…
FILAMENT_BACKUP_GDRIVE_FOLDER_ID=folder_id_from_your_drive_url
FILAMENT_BACKUP_GDRIVE_ENABLED=true
```

Use a folder **you** create in **your** Drive. You do **not** share it with a service account when using OAuth.

---

## Google Drive — service account (optional: Workspace / Shared Drive only)

> **Not for personal Gmail “My Drive.”** Google does not give consumer personal storage quota to service accounts. Use **[OAuth](#google-drive-setup-recommended-oauth)** for `@gmail.com` backups.

Use a **service account** only when your organization uses **Google Workspace** and **Shared drives** (or another setup where the service account can write without relying on personal Gmail quota). Your Gmail **password** is never stored in `.env`.

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

After installation, open **System → Backups** (or your navigation group). With **`FILAMENT_BACKUP_UI_USE_QUEUE=true`** (default), choosing a backup shows a **“Backup queued”** toast; a queue worker runs `BackupRunner` and delivers **Filament database notifications** to the user who started the run (session toasts only appear when **`FILAMENT_BACKUP_UI_USE_QUEUE=false`** and the backup runs in the browser).

**Queue worker**

```bash
php artisan queue:work --timeout=7200
```

Use a `--timeout` **≥** `FILAMENT_BACKUP_QUEUE_TIMEOUT`. If `QUEUE_CONNECTION=sync`, the job still runs **inside the HTTP request** and can hit the same **120s (or lower)** `max_execution_time` as before—use `database`, `redis`, etc., and a real worker for production.

**If you must run backups synchronously from the browser**, set `FILAMENT_BACKUP_UI_USE_QUEUE=false`, raise PHP **`max_execution_time`** (e.g. in `php.ini` or your vhost), and increase **nginx** `fastcgi_read_timeout` / **Apache** `TimeOut` as needed. Optionally set **`FILAMENT_BACKUP_MAX_EXECUTION=0`** so the runner calls `set_time_limit(0)` (where allowed).

---

## Artisan

```bash
php artisan filament-backup:run           # default: both
php artisan filament-backup:run database
php artisan filament-backup:run storage
php artisan filament-backup:run both

php artisan filament-backup:google-auth   # recommended: OAuth refresh token for personal Drive quota
```

All modes run **synchronously** in the current process (no queue). Optional **`FILAMENT_BACKUP_MAX_EXECUTION`** still applies at the start of `BackupRunner::run()`.

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

## Maintainers: rebuild the Backups page CSS

If you change `resources/views/pages/manage-backups.blade.php`, regenerate the committed bundle:

```bash
cd vendor/nehalfstudio/filament-backup   # or your path repo clone
npm install
npm run build:css
php artisan filament:assets              # from the host Laravel app
```

Source: `resources/build/manage-backups.tw.css` (Tailwind v4 CLI).

---

## License

MIT.
