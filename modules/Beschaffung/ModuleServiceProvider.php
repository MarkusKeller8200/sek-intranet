<?php

namespace Modules\Beschaffung;

use App\Modules\ModuleProvider;
use Modules\Beschaffung\Data\Contracts\ArtikelRepository;
use Modules\Beschaffung\Data\Json\JsonArtikelRepository;
use RuntimeException;

/**
 * Beschaffung: Kataloge im eigenen Datenspeicher des Moduls (<data_path>/modules/beschaffung).
 */
class ModuleServiceProvider extends ModuleProvider
{
    protected string $module = 'beschaffung';

    public function register(): void
    {
        $this->app->singleton(ArtikelRepository::class, fn (): ArtikelRepository => match ($this->storeType()) {
            'json' => new JsonArtikelRepository($this->store()),
            default => throw new RuntimeException('Beschaffung: Speicher «'.$this->storeType().'» ist noch nicht umgesetzt.'),
        });
    }
}
