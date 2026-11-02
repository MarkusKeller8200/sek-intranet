<?php

namespace App\Providers;

use App\Data\Store\JsonStore;
use App\Modules\ModuleRegistry;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Lädt die eingeschalteten Module (docs/KONZEPT-MODULE.md): Rechte und Menü in die Konfiguration, eigener
 * ServiceProvider, Routen (nur angemeldet). Ausgeschaltete Module haben weder Menü noch Routen.
 */
class ModulesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ModuleRegistry::class, function ($app): ModuleRegistry {
            // Mehrere Ordner mit Komma getrennt (Tests: modules,tests/Fixtures/modules)
            $paths = array_values(array_filter(array_map('trim', explode(',', (string) config('intranet.modules_path', 'modules')))));
            $paths = array_map(fn (string $p): string => str_starts_with($p, DIRECTORY_SEPARATOR) ? $p : base_path($p), $paths);

            return new ModuleRegistry($app->make(JsonStore::class), $paths, $app->make(JsonStore::class)->path());
        });

        $registry = $this->app->make(ModuleRegistry::class);
        $registry->registerAutoloader();
        $registry->integrateConfig();

        foreach ($registry->active() as $manifest) {
            if ($manifest->provider !== null && class_exists($manifest->provider)) {
                $this->app->register($manifest->provider);
            }
        }
    }

    public function boot(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }
        foreach ($this->app->make(ModuleRegistry::class)->active() as $manifest) {
            $routes = $manifest->directory.DIRECTORY_SEPARATOR.'routes.php';
            if (is_file($routes)) {
                Route::middleware(['web', 'auth'])->group($routes);
            }
        }
    }
}
