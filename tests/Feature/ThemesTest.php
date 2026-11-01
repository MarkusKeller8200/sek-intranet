<?php

namespace Tests\Feature;

use App\Support\Themes;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Farbkonzept → Themes: speichern, aktivieren (für alle), löschen; Vorschau in den GUI-Prototypen.
 */
class ThemesTest extends TestCase
{
    public function test_defaults_come_from_app_css(): void
    {
        $defaults = Themes::defaults();

        $this->assertSame('#0272ad', $defaults['light']['primary']);
        $this->assertSame('#38b6f0', $defaults['dark']['primary']);
        $this->assertSame([], array_diff(array_keys($defaults['light']), Themes::tokens()));
        $this->assertCount(count(Themes::tokens()), $defaults['light'], 'jeder Farbwert hat eine Vorgabe in app.css');
    }

    public function test_admin_saves_activates_and_deletes_a_theme(): void
    {
        $this->signInAs('admin');

        $this->get('/farbkonzept')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('color-concept')
            ->where('activeId', 'standard')
            ->where('themes.0.id', 'standard')
            ->has('presets.kontrast'));

        $this->get('/farbkonzept/themes/neu?vorlage=violett')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('theme-editor')
            ->where('theme.name', 'Violett')
            ->where('theme.light.primary', '#6d28d9'));

        // Ungültige Farbe, unbekannte Farbwerte werden ignoriert, gleiche Werte wie Vorgabe nicht gespeichert
        $this->post('/farbkonzept/themes', ['name' => 'Kaputt', 'light' => ['primary' => 'red']])->assertSessionHasErrors('light');
        $this->post('/farbkonzept/themes', [
            'name' => 'Test Violett',
            'light' => ['primary' => '#6D28D9', 'background' => '#ffffff', 'evil' => '#000000'],
            'dark' => ['primary' => '#a78bfa'],
        ])->assertRedirect();

        $theme = collect(app(Themes::class)->all())->firstWhere('name', 'Test Violett');
        $this->assertSame(['primary' => '#6d28d9'], $theme['light']);
        $this->assertSame('', app(Themes::class)->activeCss());

        $this->post("/farbkonzept/themes/{$theme['id']}/aktivieren")->assertRedirect();
        $this->assertSame($theme['id'], app(Themes::class)->activeId());
        $html = $this->get('/farbkonzept')->assertOk()->getContent();
        $this->assertStringContainsString('<style id="intranet-theme">:root{--primary:#6d28d9}.dark{--primary:#a78bfa}</style>', (string) $html);

        $this->delete('/farbkonzept/themes/standard')->assertRedirect();
        $this->delete("/farbkonzept/themes/{$theme['id']}")->assertRedirect('/farbkonzept');
        $this->assertSame('standard', app(Themes::class)->activeId());
    }

    public function test_only_administration_manages_themes_and_prototypes_get_them_for_preview(): void
    {
        $this->signInAs('lehrperson');
        $this->get('/farbkonzept/themes/neu')->assertForbidden();
        $this->post('/farbkonzept/themes', ['name' => 'x'])->assertForbidden();

        $this->signInAs('admin');
        $this->get('/gui-prototypen/button')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('themes.0.id', 'standard')
            ->where('themeDefaults.dark.background', '#131516'));
    }
}
