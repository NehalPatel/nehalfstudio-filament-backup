<?php

namespace NehalfStudio\FilamentBackup\Commands;

use Google\Client as GoogleClient;
use Google\Service\Drive;
use Illuminate\Console\Command;
use NehalfStudio\FilamentBackup\Services\GoogleDriveDestination;
use Throwable;

class GoogleDriveAuthCommand extends Command
{
    protected $signature = 'filament-backup:google-auth
                            {--credentials= : Path to OAuth client JSON (default: FILAMENT_BACKUP_GDRIVE_CREDENTIALS)}
                            {--redirect-uri=http://127.0.0.1:8765/ : Authorized redirect URI (must match Google Cloud Console)}';

    protected $description = 'Recommended for personal Gmail: create FILAMENT_BACKUP_GDRIVE_REFRESH_TOKEN using OAuth client JSON (uses your Drive quota, not a service account).';

    public function handle(): int
    {
        $path = (string) ($this->option('credentials') ?: config('filament-backup.google_drive.credentials_json', ''));
        if (trim($path) === '' || ! is_readable($path)) {
            $this->error('Set --credentials=path or FILAMENT_BACKUP_GDRIVE_CREDENTIALS to your OAuth client JSON file.');

            return self::INVALID;
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            $this->error('Could not read credentials file.');

            return self::FAILURE;
        }

        $data = json_decode($raw, true);
        if (! is_array($data)) {
            $this->error('Credentials file is not valid JSON.');

            return self::FAILURE;
        }

        if (($data['type'] ?? null) === 'service_account') {
            $this->error('This file is a service account key. Use filament-backup:google-auth only with OAuth client (Desktop or Web) JSON from Google Cloud → APIs & Services → Credentials.');

            return self::INVALID;
        }

        try {
            [$clientId, $clientSecret] = GoogleDriveDestination::oauthClientFromJson($data);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::INVALID;
        }

        $redirectUri = (string) $this->option('redirect-uri');

        $this->warn('In Google Cloud Console, open your OAuth 2.0 Client ID and add this Authorized redirect URI:');
        $this->line('  '.$redirectUri);
        $this->newLine();

        $client = new GoogleClient;
        $client->setApplicationName('Filament Backup');
        $client->setClientId($clientId);
        $client->setClientSecret($clientSecret);
        $client->setRedirectUri($redirectUri);
        $client->setScopes([Drive::DRIVE]);
        $client->setAccessType('offline');
        $client->setPrompt('consent');

        $authUrl = $client->createAuthUrl();
        $this->line('Open this URL in your browser, sign in, and approve access:');
        $this->newLine();
        $this->line($authUrl);
        $this->newLine();

        $input = (string) $this->ask('Paste the full redirect URL (with code=…) or the authorization code only');
        $code = $this->parseAuthorizationCode($input);
        if ($code === '') {
            $this->error('No authorization code found.');

            return self::INVALID;
        }

        try {
            $token = $client->fetchAccessTokenWithAuthCode($code);
        } catch (Throwable $e) {
            $this->error('Token exchange failed: '.$e->getMessage());

            return self::FAILURE;
        }

        if (isset($token['error'])) {
            $msg = is_string($token['error_description'] ?? null)
                ? (string) $token['error_description']
                : (string) $token['error'];
            $this->error('Google returned an error: '.$msg);

            return self::FAILURE;
        }

        $refresh = $token['refresh_token'] ?? null;
        if (! is_string($refresh) || $refresh === '') {
            $this->error('No refresh token in response. Revoke the app at https://myaccount.google.com/permissions and run this command again so Google issues a new refresh token.');
            $this->line('Ensure you use the same Google account that owns the backup folder.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Add these to your .env (then run php artisan config:clear):');
        $this->newLine();
        $this->line('FILAMENT_BACKUP_GDRIVE_CREDENTIALS='.str_replace('\\', '/', $path));
        $this->line('FILAMENT_BACKUP_GDRIVE_REFRESH_TOKEN='.$refresh);
        $this->newLine();
        $this->comment('Use a folder in your own Drive; set FILAMENT_BACKUP_GDRIVE_FOLDER_ID from the folder URL. No need to share the folder with a service account.');

        return self::SUCCESS;
    }

    protected function parseAuthorizationCode(string $input): string
    {
        $input = trim($input);
        if ($input === '') {
            return '';
        }

        if (preg_match('/[?&]code=([^&]+)/', $input, $m) === 1) {
            return rawurldecode($m[1]);
        }

        return $input;
    }
}
