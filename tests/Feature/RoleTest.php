<?php

namespace Tests\Feature;

use App\Data\Store\JsonStore;
use App\Support\Roles;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RoleTest extends TestCase
{
    public function test_every_ability_names_only_known_roles_and_admin_has_all(): void
    {
        $roles = array_keys(Roles::all());

        foreach (Roles::abilities() as $key => $ability) {
            $this->assertSame([], array_values(array_diff($ability['roles'], $roles)), "Unbekannte Rolle in abilities.{$key}");
            $this->assertContains('admin', $ability['roles'], "Administration fehlt bei {$key}");
        }
        $this->assertArrayHasKey('role_descriptions', (array) config('intranet'));
        $this->assertSame($roles, array_keys((array) config('intranet.role_descriptions')));
    }

    public function test_menus_used_in_navigation_are_defined_abilities(): void
    {
        $abilities = array_keys(Roles::abilities());
        foreach ((array) config('intranet.modules') as $groupKey => $group) {
            foreach ([$group, ...array_values((array) $group['items'])] as $entry) {
                if (isset($entry['can'])) {
                    $this->assertContains($entry['can'], $abilities, "Unbekanntes Recht in modules.{$groupKey}");
                }
            }
        }
    }

    public function test_gates_follow_the_role_matrix(): void
    {
        $this->signInAs('ict');
        $this->assertTrue(Gate::allows('use-ict'));
        $this->assertTrue(Gate::allows('use-unterricht'));
        $this->assertFalse(Gate::allows('manage-users'));
        $this->assertFalse(Gate::allows('use-verwaltung'));

        $this->signInAs('schulleitung');
        $this->assertTrue(Gate::allows('manage-content'));
        $this->assertTrue(Gate::allows('use-verwaltung'));
        $this->assertFalse(Gate::allows('use-ict'));

        $this->signInAs('lehrperson');
        $this->assertFalse(Gate::allows('manage-content'));
        $this->assertTrue(Gate::allows('order'));
    }

    public function test_ict_sees_ict_menu_but_not_prototype(): void
    {
        $this->signInAs('ict');

        $this->get('/start')->assertInertia(fn (Assert $page) => $page
            ->where('navigation', fn ($groups): bool => collect($groups)->contains('key', 'ict')
                && ! collect($groups)->contains('key', 'prototyp')));
    }

    public function test_role_concept_page_shows_matrix_for_admin(): void
    {
        $this->signInAs('lehrperson');
        $this->get('/rollenkonzept')->assertForbidden();

        $this->signInAs('admin');
        $this->get('/rollenkonzept')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('role-concept')
            ->has('roles', count(Roles::all()))
            ->has('abilities', count(Roles::abilities()))
            ->where('roles', fn ($roles): bool => collect($roles)->firstWhere('key', 'ict')['people'] === 1
                && in_array('ict', array_column(collect($roles)->firstWhere('key', 'ict')['menus'], 'key'), true)));
    }

    public function test_upgrade_moves_old_demo_roles_once(): void
    {
        $store = app(JsonStore::class);
        $beat = collect($store->all('users'))->firstWhere('email', 'beat.beispiel@sekbuelach.example');
        $store->update('users', (string) $beat['id'], ['role' => 'redaktion']);

        Artisan::call('intranet:upgrade');
        $this->assertSame('schulleitung', collect($store->all('users'))->firstWhere('email', 'beat.beispiel@sekbuelach.example')['role']);

        // Später bewusst geänderte Rolle bleibt
        $store->update('users', (string) $beat['id'], ['role' => 'lehrperson']);
        Artisan::call('intranet:upgrade');
        $this->assertSame('lehrperson', collect($store->all('users'))->firstWhere('email', 'beat.beispiel@sekbuelach.example')['role']);
    }
}
