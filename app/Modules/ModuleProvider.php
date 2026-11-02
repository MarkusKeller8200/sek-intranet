<?php

namespace App\Modules;

use App\Data\Store\JsonStore;
use Illuminate\Support\ServiceProvider;

/**
 * Grundlage für den ServiceProvider eines Moduls (module.json → «provider»).
 *
 * Ein Modul bindet hier seine Repositories an den eigenen Datenspeicher ({@see store()}) und wählt die
 * Umsetzung nach {@see storeType()} (json bzw. später database). Zugriff auf Daten anderer Module gibt es nicht.
 */
abstract class ModuleProvider extends ServiceProvider
{
    /** Schlüssel des Moduls (wie in module.json). */
    protected string $module = '';

    protected function store(): JsonStore
    {
        return $this->app->make(ModuleRegistry::class)->store($this->module);
    }

    protected function storeType(): string
    {
        return $this->app->make(ModuleRegistry::class)->storeType($this->module);
    }
}
