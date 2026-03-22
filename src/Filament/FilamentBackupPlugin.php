<?php

namespace NehalfStudio\FilamentBackup\Filament;

use Closure;
use Filament\Contracts\Plugin;
use Filament\Panel;
use InvalidArgumentException;
use NehalfStudio\FilamentBackup\Filament\Pages\ManageBackups;

class FilamentBackupPlugin implements Plugin
{
    public const ID = 'nehalfstudio-filament-backup';

    protected ?Closure $authorizeUsing = null;

    /**
     * @var class-string<ManageBackups>
     */
    protected string $pageClass = ManageBackups::class;

    protected ?string $pollingInterval = null;

    public static function make(): static
    {
        return new static;
    }

    public function getId(): string
    {
        return self::ID;
    }

    /**
     * Restrict access to the backups page.
     * When set, this runs after the user is authenticated and overrides gate-based checks.
     */
    public function authorize(?Closure $callback): static
    {
        $this->authorizeUsing = $callback;

        return $this;
    }

    public function getAuthorizeUsing(): ?Closure
    {
        return $this->authorizeUsing;
    }

    /**
     * @param  class-string<ManageBackups>  $class
     */
    public function usingPage(string $class): static
    {
        if (! is_subclass_of($class, ManageBackups::class)) {
            throw new InvalidArgumentException(
                'The backup page must extend ['.ManageBackups::class.'].'
            );
        }

        $this->pageClass = $class;

        return $this;
    }

    /**
     * @return class-string<ManageBackups>
     */
    public function getPageClass(): string
    {
        return $this->pageClass;
    }

    /**
     * Optional Livewire poll interval for the backups page (e.g. "10s", "1m") to refresh the UI.
     */
    public function usingPollingInterval(?string $interval): static
    {
        $this->pollingInterval = $interval;

        return $this;
    }

    public function getPollingInterval(): ?string
    {
        return $this->pollingInterval;
    }

    public function register(Panel $panel): void
    {
        $panel->pages([
            $this->pageClass,
        ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
