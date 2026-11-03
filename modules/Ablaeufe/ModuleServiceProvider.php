<?php

namespace Modules\Ablaeufe;

use App\Modules\ModuleProvider;
use Modules\Ablaeufe\Data\Contracts\AblaufRepository;
use Modules\Ablaeufe\Data\Contracts\GruppeRepository;
use Modules\Ablaeufe\Data\Json\JsonAblaufRepository;
use Modules\Ablaeufe\Data\Json\JsonGruppeRepository;
use RuntimeException;

/**
 * Abläufe Verwaltung: Repositories im eigenen Datenspeicher des Moduls (<data_path>/modules/ablaeufe).
 */
class ModuleServiceProvider extends ModuleProvider
{
    protected string $module = 'ablaeufe';

    public function register(): void
    {
        $this->app->singleton(AblaufRepository::class, fn (): AblaufRepository => match ($this->storeType()) {
            'json' => new JsonAblaufRepository($this->store()),
            default => throw new RuntimeException('Abläufe: Speicher «'.$this->storeType().'» ist noch nicht umgesetzt.'),
        });
        $this->app->singleton(GruppeRepository::class, fn (): GruppeRepository => match ($this->storeType()) {
            'json' => new JsonGruppeRepository($this->store()),
            default => throw new RuntimeException('Abläufe: Speicher «'.$this->storeType().'» ist noch nicht umgesetzt.'),
        });
    }
}
