<?php

namespace Tests\Feature;

use App\Data\Store\JsonStore;
use App\Modules\ModuleRegistry;
use Tests\TestCase;

/**
 * Regression: Auf dem Server läuft «php artisan optimize» – die zwischengespeicherte Konfiguration enthält die
 * Rechte und Menüeinträge der Module bereits. Das darf die Module nicht ungültig machen (sonst Menü ohne Routen = 404),
 * und ausgeschaltete Module dürfen nicht im Menü bleiben.
 */
class ModulesCachedConfigTest extends TestCase
{
    public function test_modules_stay_valid_when_config_already_contains_their_entries(): void
    {
        $registry = app(ModuleRegistry::class);
        // Konfiguration wie aus dem Cache: Modulrechte und -menü sind schon drin
        $this->assertArrayHasKey('use-zeiterfassung', (array) config('intranet.abilities'));

        $fresh = new ModuleRegistry(app(JsonStore::class), $registry->paths(), app(JsonStore::class)->path());
        $this->assertSame([], $fresh->errors());
        $this->assertNotNull($fresh->find('zeiterfassung'));
        $fresh->integrateConfig();
        $this->assertArrayHasKey('use-zeiterfassung', (array) config('intranet.abilities'));

        // ausgeschaltet → Rechte und Menü verschwinden auch aus der übernommenen Konfiguration
        $fresh->setEnabled('zeiterfassung', false, null);
        try {
            $fresh->integrateConfig();
            $this->assertArrayNotHasKey('use-zeiterfassung', (array) config('intranet.abilities'));
            $this->assertStringNotContainsString('/zeiterfassung', (string) json_encode(config('intranet.modules')));
        } finally {
            $fresh->setEnabled('zeiterfassung', true, null);
        }
    }

    public function test_route_exists_for_logged_in_staff(): void
    {
        config(['inertia.testing.ensure_pages_exist' => false]);
        $this->signInAs('lehrperson');
        $this->get('/zeiterfassung')->assertOk();
    }
}
