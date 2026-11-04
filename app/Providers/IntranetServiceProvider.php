<?php

namespace App\Providers;

use App\Auth\IntranetUser;
use App\Auth\RepositoryUserProvider;
use App\Data\Contracts\AnnouncementRepository;
use App\Data\Contracts\DocumentRepository;
use App\Data\Contracts\EventRepository;
use App\Data\Contracts\GlossaryRepository;
use App\Data\Contracts\UserRepository;
use App\Data\Json\JsonAnnouncementRepository;
use App\Data\Json\JsonDocumentRepository;
use App\Data\Json\JsonEventRepository;
use App\Data\Json\JsonGlossaryRepository;
use App\Data\Json\JsonUserRepository;
use App\Data\Store\JsonStore;
use App\Plugins\PluginManager;
use App\Support\Roles;
use App\Support\Signatures;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class IntranetServiceProvider extends ServiceProvider
{
    /**
     * Repository-Schnittstelle => Implementierung je Speicherart.
     *
     * @var array<string, array<class-string, class-string>>
     */
    private const IMPLEMENTATIONS = [
        'json' => [
            UserRepository::class => JsonUserRepository::class,
            AnnouncementRepository::class => JsonAnnouncementRepository::class,
            EventRepository::class => JsonEventRepository::class,
            DocumentRepository::class => JsonDocumentRepository::class,
            GlossaryRepository::class => JsonGlossaryRepository::class,
        ],
        // 'database' => [ UserRepository::class => DatabaseUserRepository::class, ... ],
    ];

    public function register(): void
    {
        $this->app->singleton(JsonStore::class, function (): JsonStore {
            $path = (string) config('intranet.data_path', 'storage/app/data');

            if (! str_starts_with($path, DIRECTORY_SEPARATOR) && preg_match('/^[A-Za-z]:[\\\\\/]/', $path) !== 1) {
                $path = base_path($path);
            }

            return new JsonStore($path, (string) config('app.timezone', 'Europe/Zurich'));
        });

        $this->app->singleton(PluginManager::class, fn ($app): PluginManager => new PluginManager(
            $app->make(JsonStore::class),
            $app->make(UserRepository::class),
            base_path('plugins'),
        ));

        $store = (string) config('intranet.store', 'json');

        if (! array_key_exists($store, self::IMPLEMENTATIONS)) {
            throw new InvalidArgumentException("Unbekannter Intranet-Speicher [{$store}]. Erlaubt: ".implode(', ', array_keys(self::IMPLEMENTATIONS)));
        }

        foreach (self::IMPLEMENTATIONS[$store] as $contract => $implementation) {
            $this->app->singleton($contract, $implementation);
        }
    }

    public function boot(): void
    {
        // Unterschriften ohne Modul (Verwaltung → Unterschriften, Plugin-Aktion «Dokument senden»): PDF im Kern ablegen
        Signatures::listen(Signatures::MANUAL, fn (string $event, array $request, ?string $pdf): ?bool => $this->app->make(Signatures::class)->manualEvent($event, $request, $pdf));

        Auth::provider('intranet', fn ($app): RepositoryUserProvider => new RepositoryUserProvider(
            $app->make(UserRepository::class),
        ));

        // Rechte aus dem Rollenkonzept (config/intranet.php → abilities)
        foreach (array_keys(Roles::abilities()) as $ability) {
            Gate::define($ability, fn (IntranetUser $user): bool => Roles::allows($user->role(), $ability));
        }
    }
}
