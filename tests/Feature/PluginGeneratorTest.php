<?php

namespace Tests\Feature;

use App\Data\Contracts\UserRepository;
use App\Data\Store\JsonStore;
use App\Plugins\ManifestOpenApi;
use App\Plugins\PluginGenerator;
use App\Plugins\PluginManager;
use App\Plugins\PluginManifest;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Generator intranet:plugin-from-openapi und API-Ansicht (Scalar). Nur erfundene Beispiel-APIs.
 */
class PluginGeneratorTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        // Plugins in einen Arbeitsordner kopieren, damit Tests das Repository nicht verändern
        $this->dir = storage_path('framework/testing/plugins-'.bin2hex(random_bytes(4)));
        File::copyDirectory(base_path('plugins'), $this->dir);
        $this->app->instance(PluginManager::class, new PluginManager(
            $this->app->make(JsonStore::class),
            $this->app->make(UserRepository::class),
            $this->dir,
        ));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    /**
     * @return array<string, mixed>
     */
    private function spec(): array
    {
        return [
            'openapi' => '3.1.0',
            'info' => ['title' => 'Kursverwaltung (erfunden)', 'version' => '1.0', 'description' => 'Beispiel-API.'],
            'servers' => [['url' => 'https://kurse.example.org']],
            'components' => ['securitySchemes' => ['token' => ['type' => 'http', 'scheme' => 'bearer']]],
            'paths' => [
                '/api/v1/kurse' => [
                    'get' => ['summary' => 'Kurse', 'parameters' => [['name' => 'page', 'in' => 'query', 'schema' => ['type' => 'integer', 'default' => 1]]]],
                    'post' => ['summary' => 'Kurs anlegen'],
                ],
                '/api/v1/kurse/{kursId}' => [
                    'parameters' => [['name' => 'kursId', 'in' => 'path', 'required' => true]],
                    'get' => ['operationId' => 'getKurs'],
                ],
            ],
        ];
    }

    /**
     * Befehl ausführen; liefert [Exit-Code, Ausgabe].
     *
     * @param  array<string, mixed>  $args
     * @return array{0: int, 1: string}
     */
    private function generate(array $args): array
    {
        $code = Artisan::call('intranet:plugin-from-openapi', $args);

        return [$code, Artisan::output()];
    }

    private function writeSpec(array $spec): string
    {
        $file = $this->dir.'/spec.json';
        file_put_contents($file, json_encode($spec));

        return $file;
    }

    public function test_describe_maps_openapi_to_plugin_endpoints(): void
    {
        $d = PluginGenerator::describe($this->spec());

        $this->assertSame('Kursverwaltung (erfunden)', $d['title']);
        $this->assertSame('https://kurse.example.org', $d['base_url']);
        $this->assertSame(['type' => 'bearer', 'token_setting' => 'api_token'], $d['auth']);
        $this->assertSame('password', $d['auth_settings'][0]['type']);
        $this->assertSame(['kurse', 'post-kurse', 'kurse-one'], array_column($d['endpoints'], 'id'));
        $this->assertSame(['partial'], array_values(array_unique(array_column($d['endpoints'], 'status'))));
        $this->assertTrue($d['endpoints'][1]['danger']);
        $this->assertSame([['name' => 'page', 'default' => '1']], $d['endpoints'][0]['params']);
        $this->assertSame([['name' => 'kursId', 'required' => true]], $d['endpoints'][2]['params']);
        $this->assertSame('getKurs', $d['endpoints'][2]['label']);

        $this->expectException(InvalidArgumentException::class);
        PluginGenerator::describe(['paths' => []]);
    }

    public function test_new_plugin_is_created_and_valid(): void
    {
        $file = $this->writeSpec($this->spec());

        $this->assertSame(0, $this->generate(['quelle' => $file, '--neu' => 'Kurse', '--dry-run' => true])[0]);
        $this->assertDirectoryDoesNotExist($this->dir.'/Kurse');

        $this->assertSame(0, $this->generate(['quelle' => $file, '--neu' => 'Kurse'])[0]);
        foreach (['plugin.json', 'Plugin.php', 'README.md'] as $name) {
            $this->assertFileExists($this->dir.'/Kurse/'.$name);
        }
        $manifest = PluginManifest::fromFile($this->dir.'/Kurse/plugin.json');
        $this->assertSame('kurse', $manifest->id());
        $this->assertSame(['api_token'], $manifest->secretKeys());
        $this->assertCount(3, $manifest->endpoints());
        $this->assertStringContainsString('namespace Plugins\Kurse;', (string) file_get_contents($this->dir.'/Kurse/Plugin.php'));
        $this->assertStringNotContainsString('example.org', (string) json_encode($manifest->settings()[1]));

        // Vorhandener Ordner und ungültiger Name werden abgelehnt
        $this->assertSame(1, $this->generate(['quelle' => $file, '--neu' => 'Kurse'])[0]);
        $this->assertSame(1, $this->generate(['quelle' => $file, '--neu' => 'kurse-2'])[0]);
    }

    public function test_existing_plugin_is_extended_without_overwriting(): void
    {
        $spec = $this->spec();
        // Jamf kennt diesen Endpunkt bereits (Platzhalter-Name egal)
        $spec['paths']['/api/v2/mobile-devices/{deviceId}/detail'] = ['get' => ['summary' => 'Schon da']];
        $file = $this->writeSpec($spec);
        $jamf = $this->dir.'/Jamf/plugin.json';
        $before = (string) file_get_contents($jamf);

        [$code, $out] = $this->generate(['quelle' => $file, '--plugin' => 'jamf', '--dry-run' => true]);
        $this->assertSame(0, $code);
        $this->assertStringContainsString('vorhanden: GET /api/v2/mobile-devices/{}/detail', $out);
        $this->assertSame($before, file_get_contents($jamf));

        $this->assertSame(0, $this->generate(['quelle' => $file, '--plugin' => 'jamf'])[0]);
        $manifest = PluginManifest::fromFile($jamf);
        $ids = array_column($manifest->endpoints(), 'id');
        $this->assertContains('post-kurse', $ids);
        $this->assertSame('Ein Mobilgerät', $manifest->endpoint('mobile-device')['label'] ?? null);
        $this->assertSame('full', $manifest->endpoint('version')['status'] ?? null);

        // Zweiter Lauf: nichts mehr zu ergänzen
        [$code, $out] = $this->generate(['quelle' => $file, '--plugin' => 'jamf']);
        $this->assertSame(0, $code);
        $this->assertStringContainsString('kennt bereits alle Endpunkte', $out);
    }

    public function test_recording_is_used_as_source_with_its_plugin(): void
    {
        $this->signInAs('admin');
        $id = $this->postJson('/api-recorder', [
            'title' => 'Jamf (Aufzeichnung)',
            'base_url' => 'https://jamf.example.org',
            'plugin_id' => 'jamf',
            'operations' => [[
                'key' => 'GET https://jamf.example.org/api/v1/buildings', 'method' => 'GET', 'path' => '/api/v1/buildings',
                'summary' => 'Gebäude', 'tag' => 'v1', 'status' => 200,
            ]],
        ])->assertOk()->json('doc.id');

        $this->assertSame(0, $this->generate(['quelle' => $id])[0]);
        $this->assertSame('Gebäude', PluginManifest::fromFile($this->dir.'/Jamf/plugin.json')->endpoint('buildings')['label'] ?? null);

        $this->assertSame(1, $this->generate(['quelle' => 'gibt-es-nicht', '--plugin' => 'jamf'])[0]);
    }

    public function test_generator_does_not_write_in_production(): void
    {
        $this->app['env'] = 'production';
        $this->assertSame(1, $this->generate(['quelle' => $this->writeSpec($this->spec()), '--neu' => 'Kurse'])[0]);
        $this->assertDirectoryDoesNotExist($this->dir.'/Kurse');
    }

    public function test_manifest_validation_rejects_duplicate_ids_methods_and_status(): void
    {
        $base = ['id' => 'test', 'api' => 1, 'service' => ['endpoints' => [['id' => 'a', 'method' => 'GET', 'path' => '/a']]]];
        PluginManifest::fromArray($base, 'Test');

        foreach ([
            ['id' => 'a', 'method' => 'GET', 'path' => '/b'],
            ['id' => 'b', 'method' => 'HACK', 'path' => '/b'],
            ['id' => 'c', 'method' => 'GET', 'path' => '/c', 'status' => 'fertig'],
        ] as $bad) {
            $data = $base;
            $data['service']['endpoints'][] = $bad;
            try {
                PluginManifest::fromArray($data, 'Test');
                $this->fail('Ungültiges Manifest angenommen: '.json_encode($bad));
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_api_views_render_with_scalar_sources(): void
    {
        $this->signInAs('lehrperson');
        $this->get('/api-doku/referenz')->assertForbidden();

        $this->signInAs('admin');
        $this->get('/api-doku/referenz')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('api-view')
            ->where('url', '/api/openapi.json'));

        $this->get('/plugins/jamf/api-ansicht')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('api-view')
            ->where('spec.openapi', '3.1.0')
            ->where('spec.paths./api/v1/jamf-pro-version.get.operationId', 'version')
            ->where('spec.components.securitySchemes.plugin.type', 'oauth2'));
        $this->get('/plugins/gibt-es-nicht/api-ansicht')->assertNotFound();

        $id = $this->postJson('/api-recorder', ['title' => 'Test', 'operations' => []])->json('doc.id');
        $this->get("/api-recorder/{$id}/ansicht")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('api-view')
            ->where('doc.id', $id));
    }

    public function test_manifest_openapi_contains_no_setting_values(): void
    {
        $spec = ManifestOpenApi::build(app(PluginManager::class)->manifest('entra'));

        $this->assertSame('https://graph.microsoft.com/v1.0', $spec['servers'][0]['url']);
        $this->assertStringNotContainsString('client_secret', (string) json_encode($spec['paths']));
    }

    public function test_scalar_runs_without_telemetry_and_foreign_services(): void
    {
        $component = (string) file_get_contents(resource_path('js/components/api-reference.tsx'));

        $this->assertStringContainsString('telemetry: false', $component);
        $this->assertStringContainsString('withDefaultFonts: false', $component);
        $this->assertStringContainsString('agent: { disabled: true }', $component);
        $this->assertStringNotContainsString('proxyUrl', $component);
    }

    public function test_endpoints_can_be_assigned_to_a_second_host(): void
    {
        $spec = ['openapi' => '3.1.0', 'info' => ['title' => 'Zweite Adresse (erfunden)'], 'paths' => [
            '/rest/kurse/{id}' => ['get' => ['summary' => 'Kurs']],
            '/rest/clientpreferences/{id}' => ['get' => ['summary' => 'Schon da']],
        ]];
        $file = $this->writeSpec($spec);

        $this->assertSame(1, $this->generate(['quelle' => $file, '--plugin' => 'lmvz', '--host' => 'gibt-es-nicht'])[0]);

        [$code, $out] = $this->generate(['quelle' => $file, '--plugin' => 'lmvz', '--host' => 'digital']);
        $this->assertSame(0, $code);
        $this->assertStringContainsString('vorhanden: digital GET /rest/clientpreferences/{}', $out);
        $endpoint = PluginManifest::fromFile($this->dir.'/Lmvz/plugin.json')->endpoint('kurse-one');
        $this->assertSame('digital', $endpoint['host'] ?? null);
    }

    /**
     * Plugin-Beschreibung einer API aus EduKSX-Connectors (GET /plugin.json, sek-intranet#229). Erfundene Beispielwerte.
     *
     * @return array<string, mixed>
     */
    private function connector(): array
    {
        $data = json_decode((string) file_get_contents(base_path('tests/Fixtures/eduksx-connector-plugin.json')), true);
        $this->assertIsArray($data);

        /** @var array<string, mixed> $data */
        return $data;
    }

    public function test_connector_description_keeps_labels_examples_and_danger(): void
    {
        $this->assertTrue(PluginGenerator::isConnectorManifest($this->connector()));
        $this->assertFalse(PluginGenerator::isConnectorManifest($this->spec()));

        $d = PluginGenerator::describe($this->connector());
        $this->assertSame('CMI-API', $d['title']);
        $this->assertSame(['type' => 'header', 'header' => 'X-API-Key', 'setting' => 'api_key'], $d['auth']);
        $this->assertSame(['api_key'], array_column($d['auth_settings'], 'key'));
        $e = array_column($d['endpoints'], null, 'id');

        $this->assertSame('Kontakte suchen (fremdkey, name, email oder TentaQl)', $e['kontakte']['label']);
        $this->assertSame('escola-650', $e['kontakte']['params'][0]['example']);
        $this->assertArrayNotHasKey('danger', $e['kontakte']);

        // Schreibend: danger, Beispiel-Body, Felder; Probelauf als Parameter mit Vorgabe «true»
        $sync = $e['post-kontakte-sync'];
        $this->assertTrue($sync['danger']);
        $this->assertSame('escola-650', $sync['body']['kontakte'][0]['fremdkey']);
        $this->assertSame(['name' => 'probelauf', 'default' => 'true'], array_intersect_key(end($sync['params']), ['name' => 1, 'default' => 1]));
        $this->assertArrayNotHasKey('probelauf', $sync);

        // Datei-Upload: Teil «dateien», Formularfelder im Hinweis
        $this->assertSame(['field' => 'dateien'], $e['post-zeugnisse']['file']);
        $this->assertStringContainsString('Formularfelder: dateien, zuordnung', $e['post-zeugnisse']['note']);
        $this->assertArrayNotHasKey('form', $e['post-zeugnisse']);
    }

    public function test_connector_plugin_is_created_with_version_and_notice(): void
    {
        $file = $this->writeSpec($this->connector());
        [$code, $out] = $this->generate(['quelle' => $file, '--neu' => 'EduksxCmi']);
        $this->assertSame(0, $code, $out);
        $this->assertStringContainsString('aus EduKSX-Connectors', $out);

        $manifest = PluginManifest::fromFile($this->dir.'/EduksxCmi/plugin.json');
        $this->assertSame('eduksx-cmi', $manifest->id());
        $this->assertSame('0.9.0', $manifest->toArray()['version']);
        $this->assertSame('school', $manifest->toArray()['category']);
        $this->assertNotEmpty($manifest->toArray()['data_notice']);
        $this->assertSame(['api_key'], $manifest->secretKeys());
        $this->assertCount(5, $manifest->endpoints());
        $this->assertStringContainsString('X-Demo-Session', (string) $manifest->toArray()['service']['auth_note']);
        $this->assertStringContainsString('--plugin=eduksx-cmi', (string) file_get_contents($this->dir.'/EduksxCmi/README.md'));
        // keine Adresse und kein Schlüssel als Wert im Manifest
        $this->assertStringNotContainsString('127.0.0.1', (string) json_encode($manifest->toArray()['service']));
    }

    public function test_connector_update_adds_only_new_endpoints_and_reports_version(): void
    {
        $data = $this->connector();
        $this->generate(['quelle' => $this->writeSpec($data), '--neu' => 'EduksxCmi']);
        // Klasse des neuen Plugins laden (Arbeitsordner liegt ausserhalb des Autoloaders)
        require_once $this->dir.'/EduksxCmi/Plugin.php';

        $data['version'] = '0.10.0';
        $data['service']['endpoints'][] = ['id' => 'neu', 'method' => 'GET', 'path' => '/neu', 'label' => 'Neu', 'status' => 'partial'];
        $data['service']['endpoints'][0]['label'] = 'Geändert';
        [$code, $out] = $this->generate(['quelle' => $this->writeSpec($data), '--plugin' => 'eduksx-cmi']);

        $this->assertSame(0, $code, $out);
        $this->assertStringContainsString('Version 0.10.0', $out);
        $manifest = PluginManifest::fromFile($this->dir.'/EduksxCmi/plugin.json');
        $this->assertCount(6, $manifest->endpoints());
        $this->assertNotSame('Geändert', $manifest->endpoints()[0]['label']);
    }

    public function test_openapi_with_x_eduksx_is_honoured(): void
    {
        $spec = $this->spec();
        $spec['paths']['/auth/login'] = ['post' => ['summary' => 'Anmelden', 'x-eduksx' => ['effect' => 'read', 'danger' => false]]];
        $spec['paths']['/api/v1/kurse']['post']['parameters'] = [['name' => 'probelauf', 'in' => 'query', 'schema' => ['type' => 'boolean', 'default' => false]]];
        $spec['paths']['/api/v1/kurse']['post']['x-eduksx'] = ['status' => 'full', 'use' => 'Kurs anlegen'];
        $e = array_column(PluginGenerator::describe($spec)['endpoints'], null, 'id');

        $this->assertArrayNotHasKey('danger', $e['post-auth-login']);
        $this->assertSame('read', $e['post-auth-login']['effect']);
        $this->assertSame('full', $e['post-kurse']['status']);
        $this->assertSame('Kurs anlegen', $e['post-kurse']['use']);
        $this->assertSame('true', $e['post-kurse']['params'][0]['default']);
        // ohne x-eduksx bleibt alles wie bisher
        $this->assertSame('partial', $e['kurse']['status']);
    }
}
