<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ModuleNavigationTest extends TestCase
{
    public function test_navigation_is_shared_and_hides_admin_groups_for_staff(): void
    {
        $this->signInAs('lehrperson');

        $this->get('/start')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('navigation.0.key', 'kommunikation')
            ->where('navigation.0.items.0', ['key' => 'start', 'title' => 'Start', 'icon' => 'LayoutGrid', 'href' => '/start', 'planned' => false, 'external' => null, 'open' => null])
            ->where('navigation.0.items.3.href', '/module/pinnwand')
            ->where('navigation.0.items.3.planned', true)
            ->where('navigation', fn ($groups): bool => ! collect($groups)->contains('key', 'prototyp')));

        $this->signInAs('admin');
        $this->get('/start')->assertInertia(fn (Assert $page) => $page
            ->where('navigation', fn ($groups): bool => collect($groups)->contains('key', 'prototyp')));
    }

    public function test_placeholder_page_shows_planned_module(): void
    {
        $this->signInAs('lehrperson');

        $this->get('/module/reservationen')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('modules/show')
            ->where('module.title', 'Reservationen')
            ->where('module.group', 'Räume & Geräte')
            ->has('module.features', 3)
            ->where('plugin', null));

        $this->get('/module/geraete')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('plugin.id', 'jamf')
            ->where('plugin.name', 'Jamf Pro')
            ->where('plugin.enabled', false)
            ->where('plugin.canManage', false));
    }

    public function test_unknown_or_implemented_modules_have_no_placeholder(): void
    {
        $this->signInAs('admin');

        $this->get('/module/gibt-es-nicht')->assertNotFound();
        $this->get('/module/mitteilungen')->assertRedirect('/mitteilungen');
        $this->get('/module/geraeteausgabe')->assertRedirect('/geraeteausgabe');
    }

    public function test_every_implemented_module_link_works_for_admin(): void
    {
        $this->signInAs('admin');
        Http::fake(['*' => Http::response('ok')]);

        foreach (config('intranet.modules') as $group) {
            foreach ($group['items'] as $key => $module) {
                // Untermenü: dessen Einträge prüfen
                foreach (isset($module['items']) ? $module['items'] : [$key => $module] as $childKey => $child) {
                    $href = $child['href'] ?? (isset($child['embed']) ? "/einbindung/{$childKey}" : "/module/{$childKey}");
                    $this->get($href)->assertOk();
                }
            }
        }
    }

    public function test_every_module_icon_exists_in_the_frontend_icon_map(): void
    {
        $map = (string) file_get_contents(resource_path('js/lib/module-icons.ts'));

        foreach (config('intranet.modules') as $groupKey => $group) {
            $this->assertMatchesRegularExpression('/^\s+'.$group['icon'].',$/m', $map, "Symbol {$group['icon']} für Bereich {$groupKey} fehlt in module-icons.ts");
            foreach ($group['items'] as $key => $module) {
                $this->assertMatchesRegularExpression('/^\s+'.$module['icon'].',$/m', $map, "Symbol {$module['icon']} für Modul {$key} fehlt in module-icons.ts");
            }
        }
    }
}
