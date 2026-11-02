<?php

namespace Modules\Zeiterfassung;

use App\Modules\ModuleProvider;
use Modules\Zeiterfassung\Data\Contracts\EintragRepository;
use Modules\Zeiterfassung\Data\Contracts\MonatRepository;
use Modules\Zeiterfassung\Data\Json\JsonEintragRepository;
use Modules\Zeiterfassung\Data\Json\JsonMonatRepository;
use RuntimeException;

/**
 * Zeiterfassung: Repositories im eigenen Datenspeicher des Moduls (<data_path>/modules/zeiterfassung).
 */
class ModuleServiceProvider extends ModuleProvider
{
    protected string $module = 'zeiterfassung';

    public function register(): void
    {
        $this->app->singleton(EintragRepository::class, fn (): EintragRepository => match ($this->storeType()) {
            'json' => new JsonEintragRepository($this->store()),
            default => throw new RuntimeException('Zeiterfassung: Speicher «'.$this->storeType().'» ist noch nicht umgesetzt.'),
        });
        $this->app->singleton(MonatRepository::class, fn (): MonatRepository => match ($this->storeType()) {
            'json' => new JsonMonatRepository($this->store()),
            default => throw new RuntimeException('Zeiterfassung: Speicher «'.$this->storeType().'» ist noch nicht umgesetzt.'),
        });
    }
}
