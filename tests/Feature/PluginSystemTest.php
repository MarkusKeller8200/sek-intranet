<?php

namespace Tests\Feature;

use App\Data\Contracts\UserRepository;
use App\Data\Store\JsonStore;
use App\Plugins\PluginManager;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Fixtures\DemoPlugin;
use Tests\TestCase;

class PluginSystemTest extends TestCase
{
    private const SECRET = 'geheimes-client-secret-123';

    private const TOKEN = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.token';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        DemoPlugin::$events = [];

        $this->app->instance(PluginManager::class, new PluginManager(
            $this->app->make(JsonStore::class),
            $this->app->make(UserRepository::class),
            base_path('tests/Fixtures/plugins'),
        ));
    }

    private function fakeDemoApi(): void
    {
        Http::fake([
            'login.example.test/*' => Http::response(['access_token' => self::TOKEN, 'expires_in' => 3600]),
            'demo.example.test/acme/ping' => Http::response(['ok' => true]),
            'demo.example.test/acme/people*' => Http::response(['value' => [
                ['displayName' => 'Neue Person', 'mail' => 'neu@sekbuelach.example', 'jobTitle' => 'Lehrperson'],
                ['displayName' => 'Daniel Probe', 'mail' => 'daniel.probe@sekbuelach.example', 'jobTitle' => 'Klassenlehrer 2a'],
            ]]),
            'demo.example.test/acme/people/7/tag' => Http::response(['tagged' => true], 201),
        ]);
    }

    private function setUpDemo(bool $allowWrite = false): void
    {
        $manager = app(PluginManager::class);
        $manager->saveSettings('demo', [
            'tenant' => 'acme',
            'client_id' => 'client-1',
            'client_secret' => self::SECRET,
            '_allow_write' => $allowWrite,
        ]);
        $manager->setEnabled('demo', true);
    }

    public function test_plugin_pages_are_admin_only_and_list_the_demo_plugin(): void
    {
        $this->signInAs('redaktion');
        $this->get('/plugins')->assertForbidden();

        $this->signInAs('admin');
        $this->get('/plugins')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('plugins/index')
            ->has('plugins', 1)
            ->where('plugins.0.id', 'demo')
            ->where('plugins.0.enabled', false)
            ->where('plugins.0.configured', false)
            ->where('errors', []));
    }

    public function test_secrets_are_encrypted_never_sent_to_the_browser_and_kept_when_left_empty(): void
    {
        $this->signInAs('admin');

        $this->put('/plugins/demo/einstellungen', [
            'tenant' => 'acme', 'client_id' => 'client-1', 'client_secret' => self::SECRET,
        ])->assertRedirect();

        $file = (string) file_get_contents(app(JsonStore::class)->filePath('plugin_states'));
        $this->assertStringNotContainsString(self::SECRET, $file);

        $this->get('/plugins/demo')->assertInertia(fn (Assert $page) => $page
            ->where('values.client_secret', '')
            ->where('values.tenant', 'acme')
            ->where('secretsSet', ['client_secret']));

        // leer = unverändert
        $this->put('/plugins/demo/einstellungen', ['tenant' => 'acme', 'client_id' => 'client-2', 'client_secret' => '']);
        $ctx = app(PluginManager::class)->context('demo', null);
        $this->assertSame(self::SECRET, $ctx->secret('client_secret'));
        $this->assertSame('client-2', $ctx->setting('client_id'));
        $this->assertTrue(app(PluginManager::class)->isConfigured('demo'));

        $this->assertStringNotContainsString(self::SECRET, (string) $this->get('/plugins/demo')->getContent());
    }

    public function test_connection_test_uses_client_credentials_token_and_redacts_it(): void
    {
        $this->fakeDemoApi();
        $this->setUpDemo();
        $this->signInAs('admin');

        $this->postJson('/plugins/demo/test')
            ->assertOk()
            ->assertJson(['ok' => true, 'status' => 'success']);

        Http::assertSent(fn (Request $r): bool => $r->url() === 'https://login.example.test/acme/token'
            && $r['grant_type'] === 'client_credentials'
            && $r['scope'] === 'api://demo/.default'
            && $r['client_secret'] === self::SECRET);

        Http::assertSent(fn (Request $r): bool => $r->url() === 'https://demo.example.test/acme/ping'
            && $r->hasHeader('Authorization', 'Bearer '.self::TOKEN));

        $runs = (string) file_get_contents(app(JsonStore::class)->filePath('plugin_runs'));
        $this->assertStringNotContainsString(self::TOKEN, $runs);
        $this->assertStringNotContainsString(self::SECRET, $runs);
    }

    public function test_disabled_or_unconfigured_plugins_cannot_run(): void
    {
        $this->signInAs('admin');

        $this->postJson('/plugins/demo/aktion/import-people')
            ->assertStatus(422)
            ->assertJson(['message' => 'Plugin ist ausgeschaltet.']);

        app(PluginManager::class)->setEnabled('demo', true);

        $this->postJson('/plugins/demo/aktion/import-people')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Plugin ist noch nicht eingerichtet (Pflichtfelder in den Einstellungen).');
    }

    public function test_import_shows_a_preview_and_applies_only_with_write_permission(): void
    {
        $this->fakeDemoApi();
        $this->setUpDemo(allowWrite: false);
        $this->signInAs('admin');

        $preview = $this->postJson('/plugins/demo/aktion/import-people')->assertOk()->json();
        $this->assertTrue($preview['preview']);
        $changes = array_column($preview['rows'], '_change', '_key');
        $this->assertSame('neu', $changes['neu@sekbuelach.example']);
        $this->assertSame('ändern', $changes['daniel.probe@sekbuelach.example']);
        $this->assertSame('fehlt dort', $changes['andrea.muster@sekbuelach.example']);

        $this->postJson('/plugins/demo/aktion/import-people', ['apply' => true, 'selected' => ['neu@sekbuelach.example']])
            ->assertStatus(422);
        $this->assertNull(app(UserRepository::class)->findByEmail('neu@sekbuelach.example'));

        $this->setUpDemo(allowWrite: true);
        $this->postJson('/plugins/demo/aktion/import-people', ['apply' => true, 'selected' => ['neu@sekbuelach.example']])
            ->assertOk()
            ->assertJsonPath('message', '1 angelegt, 0 geändert.');

        $created = app(UserRepository::class)->findByEmail('neu@sekbuelach.example');
        $this->assertSame('mitarbeitende', $created['role']);
        $this->assertSame('Lehrperson', $created['function']);
        // nicht ausgewählt → unverändert
        $this->assertSame('Lehrperson Sek A, Klassenlehrer 2a', app(UserRepository::class)->findByEmail('daniel.probe@sekbuelach.example')['function']);
    }

    public function test_write_actions_and_api_writes_need_permission(): void
    {
        $this->fakeDemoApi();
        $this->setUpDemo(allowWrite: false);
        $this->signInAs('admin');

        $this->postJson('/plugins/demo/aktion/tag', ['options' => ['id' => '7']])->assertStatus(422);
        $this->postJson('/plugins/demo/api', ['endpoint' => 'tag', 'params' => ['id' => '7'], 'body' => '{"x":1}'])
            ->assertOk()
            ->assertJson(['status' => 'error']);
        Http::assertNotSent(fn (Request $r): bool => str_contains($r->url(), '/tag'));

        $this->setUpDemo(allowWrite: true);
        $this->postJson('/plugins/demo/aktion/tag', ['options' => ['id' => '7']])->assertOk()->assertJsonPath('message', 'Markiert.');
        Http::assertSent(fn (Request $r): bool => $r->method() === 'POST' && $r->url() === 'https://demo.example.test/acme/people/7/tag' && $r['tag'] === 'intranet');
    }

    public function test_api_tester_and_access_check(): void
    {
        $this->fakeDemoApi();
        $this->setUpDemo();
        $this->signInAs('admin');

        $this->postJson('/plugins/demo/api', ['endpoint' => 'people', 'params' => ['top' => '2']])
            ->assertOk()
            ->assertJsonPath('http_status', 200)
            ->assertJsonPath('status', 'success');
        Http::assertSent(fn (Request $r): bool => $r->url() === 'https://demo.example.test/acme/people?top=2');

        $rows = $this->postJson('/plugins/demo/zugriff')->assertOk()->json('rows');
        $results = array_column($rows, 'result', 'endpoint');
        $this->assertSame('Zugriff', $results['Ping']);
        $this->assertSame('Zugriff', $results['Personen']);
        $this->assertSame('braucht eine ID', $results['Eine Person']);
        $this->assertSame('schreibend – nicht geprüft', $results['Markieren']);
        $this->assertSame('geplant', $results['Später']);
    }

    public function test_widgets_are_cached_and_events_reach_subscribed_plugins(): void
    {
        $this->fakeDemoApi();
        $this->setUpDemo();
        $this->signInAs('admin');

        $manager = app(PluginManager::class);
        $widgets = $manager->widgets(null);
        $this->assertSame('Demo-Personen', $widgets[0]['label']);
        $this->assertSame(2, $widgets[0]['items'][0]['value']);
        $manager->widgets(null);
        Http::assertSentCount(2); // Token + Personen, zweiter Aufruf aus dem Zwischenspeicher

        $this->post('/personen', [
            'name' => 'Ereignis Test', 'email' => 'ereignis@sekbuelach.example', 'role' => 'mitarbeitende', 'school' => 'gesamt', 'active' => true,
        ])->assertRedirect('/personen');

        $this->assertCount(1, DemoPlugin::$events);
        $this->assertSame('person.created', DemoPlugin::$events[0]['event']);
        $this->assertSame('ereignis@sekbuelach.example', DemoPlugin::$events[0]['data']['email']);
    }

    public function test_plugin_errors_never_break_the_intranet_action(): void
    {
        Http::fake(['*' => Http::response('kaputt', 500)]);
        $this->setUpDemo();
        $this->signInAs('admin');

        $this->post('/personen', [
            'name' => 'Trotzdem gespeichert', 'email' => 'trotzdem@sekbuelach.example', 'role' => 'mitarbeitende', 'school' => 'gesamt', 'active' => true,
        ])->assertRedirect('/personen');

        $this->assertNotNull(app(UserRepository::class)->findByEmail('trotzdem@sekbuelach.example'));

        $result = $this->postJson('/plugins/demo/test')->assertOk()->json();
        $this->assertSame('error', $result['status']);
        $this->assertStringContainsString('Anmeldung beim Dienst fehlgeschlagen', $result['message']);
    }
}
