<?php

namespace Tests\Feature;

use App\Data\Contracts\UserRepository;
use App\Data\Store\JsonStore;
use Illuminate\Support\Facades\Artisan;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ColorConceptTest extends TestCase
{
    private const PALETTE = ['brand', 'slate', 'sky', 'cyan', 'teal', 'emerald', 'amber', 'orange', 'rose', 'violet', 'indigo'];

    public function test_every_option_has_a_unique_valid_color(): void
    {
        foreach (['schools', 'roles', 'audiences', 'announcement_categories', 'event_categories', 'document_categories'] as $list) {
            $keys = array_keys((array) config("intranet.{$list}"));
            $colors = (array) config("intranet.colors.{$list}");

            $this->assertSame([], array_values(array_diff($keys, array_keys($colors))), "Farbe fehlt in colors.{$list}");
            $this->assertSame([], array_values(array_diff($colors, self::PALETTE)), "Unbekannte Farbe in colors.{$list}");
            $this->assertSame(count($colors), count(array_unique($colors)), "Farbe doppelt in colors.{$list}");
        }
    }

    public function test_colors_are_shared_with_the_frontend(): void
    {
        $this->signInAs('mitarbeitende');

        $this->get('/start')->assertInertia(fn (Assert $page) => $page
            ->where('options.colors.schools.hinterbirch', 'teal')
            ->where('options.schools.mettmenriet', 'Schulhaus Mettmenriet'));
    }

    public function test_upgrade_renames_old_school_keys_and_is_idempotent(): void
    {
        $store = app(JsonStore::class);
        $user = app(UserRepository::class)->findByEmail('daniel.probe@sekbuelach.example');
        $store->update('users', $user['id'], ['school' => 'schulhaus-a']);

        Artisan::call('intranet:upgrade');
        $this->assertStringContainsString('1 Werte umgestellt', Artisan::output());
        $this->assertSame('hinterbirch', $store->find('users', $user['id'])['school']);

        Artisan::call('intranet:upgrade');
        $this->assertStringContainsString('keine Umstellung', Artisan::output());
    }

    public function test_color_concept_page_is_admin_only(): void
    {
        $this->signInAs('mitarbeitende');
        $this->get('/farbkonzept')->assertForbidden();

        $this->signInAs('admin');
        $this->get('/farbkonzept')->assertOk()->assertInertia(fn (Assert $page) => $page->component('color-concept'));
    }
}
