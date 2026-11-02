<?php

namespace Modules\Beispiel;

use App\Modules\ModuleProvider;
use Modules\Beispiel\Data\Contracts\EintragRepository;
use Modules\Beispiel\Data\Json\JsonEintragRepository;
use RuntimeException;

/**
 * Beispielmodul: bindet die Repositories an den eigenen Datenspeicher des Moduls.
 */
class ModuleServiceProvider extends ModuleProvider
{
    protected string $module = 'beispiel';

    public function register(): void
    {
        $this->app->singleton(EintragRepository::class, function (): EintragRepository {
            return match ($this->storeType()) {
                'json' => new JsonEintragRepository($this->store()),
                default => throw new RuntimeException('Beispielmodul: Speicher «'.$this->storeType().'» ist noch nicht umgesetzt.'),
            };
        });
    }
}
