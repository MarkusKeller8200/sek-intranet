<?php

namespace Tests\Feature;

use App\Support\GuiPrototypes;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Menü «GUI-Prototypen»: Bausteine mit Status (Entwicklung → Prüfung → Freigegeben), Versionen,
 * Überarbeitung nach Freigabe und Verbesserungswünschen.
 */
class GuiPrototypesTest extends TestCase
{
    public function test_menu_is_last_and_lists_components(): void
    {
        $this->signInAs('admin');
        $this->get('/start')->assertInertia(fn (Assert $page) => $page->where('navigation', function ($groups): bool {
            $last = collect($groups)->last();
            $hrefs = collect($last['items'])->pluck('href')->all();

            return $last['key'] === 'gui'
                && $hrefs[0] === '/gui-prototypen'
                && in_array('/gui-prototypen/tabelle', $hrefs, true)
                && in_array('/gui-prototypen/dropdown', $hrefs, true)
                && in_array('/gui-prototypen/button', $hrefs, true)
                && in_array('/gui-prototypen/menue', $hrefs, true);
        }));

        $this->get('/gui-prototypen')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('gui-prototypes/index')
            ->where('items.0.open.status', 'entwicklung')
            ->where('items.0.open.version', '1.0.0')
            ->where('items.0.released', null));

        $this->signInAs('lehrperson');
        $this->get('/gui-prototypen')->assertForbidden();
        $this->post('/gui-prototypen/button/status', ['action' => 'pruefen'])->assertForbidden();
    }

    public function test_release_makes_version_binding_and_marks_pages_for_rework(): void
    {
        $admin = $this->signInAs('admin');

        $this->post('/gui-prototypen/button/status', ['action' => 'freigeben'])->assertRedirect();
        $this->assertSame('entwicklung', app(GuiPrototypes::class)->get('button')['versions'][0]['status'], 'Freigabe erst nach Prüfung');

        $this->post('/gui-prototypen/button/status', ['action' => 'pruefen'])->assertRedirect();
        $this->post('/gui-prototypen/button/status', ['action' => 'zurueck', 'comment' => 'Fokusrahmen fehlt'])->assertRedirect();
        $this->post('/gui-prototypen/button/status', ['action' => 'pruefen'])->assertRedirect();
        $this->post('/gui-prototypen/button/status', ['action' => 'freigeben'])->assertRedirect();

        $record = app(GuiPrototypes::class)->get('button');
        $v = $record['versions'][0];
        $this->assertSame('freigegeben', $v['status']);
        $this->assertNotNull($v['created_at']);
        $this->assertNotNull($v['review_at']);
        $this->assertNotNull($v['released_at']);
        $this->assertSame($admin->getAuthIdentifier(), $v['released_by']);
        $this->assertSame(['entwicklung', 'pruefung', 'entwicklung', 'pruefung', 'freigegeben'], array_column($v['log'], 'status'));
        $this->assertSame('Fokusrahmen fehlt', $v['log'][2]['comment']);

        // Alle Seiten mit Buttons sind zur Überarbeitung vorgemerkt
        $files = array_column($record['rework'], 'file');
        $this->assertContains('pages/people/index.tsx', $files);
        $this->assertNotContains('pages/gui-prototypes/show.tsx', $files);
        $this->assertSame(GuiPrototypes::usages('button'), $files);

        $this->post('/gui-prototypen/button/ueberarbeitung', ['file' => 'pages/people/index.tsx', 'done' => true])->assertRedirect();
        $summary = GuiPrototypes::summary(app(GuiPrototypes::class)->get('button'));
        $this->assertSame(count($files) - 1, $summary['rework_open']);
        $this->assertSame('1.0.0', $summary['released']['version']);
        $this->assertNull($summary['open']);

        $this->get('/gui-prototypen/button')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('gui-prototypes/show')
            ->where('summary.released.version', '1.0.0')
            ->where('names.'.$admin->getAuthIdentifier(), $admin->name()));
    }

    public function test_new_version_takes_wishes_and_only_one_version_is_open(): void
    {
        $this->signInAs('admin');

        $this->post('/gui-prototypen/dropdown/versionen', ['bump' => 'minor', 'changes' => 'Suche im Dropdown'])->assertRedirect();
        $this->assertCount(1, app(GuiPrototypes::class)->get('dropdown')['versions'], '1.0.0 ist noch offen');

        $this->post('/gui-prototypen/dropdown/status', ['action' => 'pruefen']);
        $this->post('/gui-prototypen/dropdown/status', ['action' => 'freigeben']);

        $this->post('/gui-prototypen/dropdown/wuensche', ['text' => 'Suchfeld bei langen Listen'])->assertRedirect();
        $wish = app(GuiPrototypes::class)->get('dropdown')['wishes'][0];
        $this->assertNull($wish['version']);

        $this->post('/gui-prototypen/dropdown/versionen', ['bump' => 'minor', 'changes' => 'Suche im Dropdown', 'wishes' => [$wish['id']]])->assertRedirect();
        $record = app(GuiPrototypes::class)->get('dropdown');
        $this->assertSame(['1.0.0', '1.1.0'], array_column($record['versions'], 'version'));
        $this->assertSame('entwicklung', $record['versions'][1]['status']);
        $this->assertSame('1.1.0', $record['wishes'][0]['version']);

        // 1.0.0 bleibt verbindlich, bis 1.1.0 freigegeben ist
        $summary = GuiPrototypes::summary($record);
        $this->assertSame('1.0.0', $summary['released']['version']);
        $this->assertSame('1.1.0', $summary['open']['version']);

        $this->post('/gui-prototypen/dropdown/versionen', ['bump' => 'patch', 'changes' => 'x'])->assertRedirect();
        $this->assertCount(2, app(GuiPrototypes::class)->get('dropdown')['versions']);
    }

    public function test_version_numbers(): void
    {
        $this->assertSame('1.0.1', GuiPrototypes::bump('1.0.0', 'patch'));
        $this->assertSame('1.3.0', GuiPrototypes::bump('1.2.7', 'minor'));
        $this->assertSame('2.0.0', GuiPrototypes::bump('1.2.7', 'major'));
    }
}
