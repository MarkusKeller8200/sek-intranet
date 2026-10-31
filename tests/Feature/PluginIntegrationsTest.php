<?php

namespace Tests\Feature;

use App\Data\Contracts\UserRepository;
use App\Plugins\PluginManager;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Die mitgelieferten Plugins gegen simulierte APIs (Antwortformat laut Herstellerdoku).
 */
class PluginIntegrationsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function enable(string $id, array $settings, bool $allowWrite = false): void
    {
        $manager = app(PluginManager::class);
        $manager->saveSettings($id, $settings + ['_allow_write' => $allowWrite]);
        $manager->setEnabled($id, true);
    }

    public function test_all_bundled_plugins_load_without_errors(): void
    {
        $manager = app(PluginManager::class);

        $this->assertSame(['entra', 'escola', 'ida', 'inventory360', 'jamf', 'lmvz'], array_keys($manager->all()));
        $this->assertSame([], $manager->loadErrors());
    }

    public function test_entra_connects_and_imports_people_with_paging(): void
    {
        Http::fake([
            'login.microsoftonline.com/tenant-1/oauth2/v2.0/token' => Http::response(['token_type' => 'Bearer', 'expires_in' => 3599, 'access_token' => 'graph-token-abcdef']),
            'graph.microsoft.com/v1.0/organization*' => Http::response(['value' => [['id' => 'o1', 'displayName' => 'Sekundarschule Bülach (Test)']]]),
            'graph.microsoft.com/v1.0/users?*' => Http::response([
                'value' => [['displayName' => 'Neu Lehrperson', 'mail' => 'neu.lp@sekbuelach.example', 'jobTitle' => 'Lehrperson', 'businessPhones' => ['044 000 00 99'], 'accountEnabled' => true]],
                '@odata.nextLink' => 'https://graph.microsoft.com/v1.0/users/next-page',
            ]),
            'graph.microsoft.com/v1.0/users/next-page' => Http::response(['value' => [
                ['displayName' => 'Deaktiviert', 'mail' => 'alt@sekbuelach.example', 'accountEnabled' => false],
                ['displayName' => 'Daniel Probe', 'mail' => 'daniel.probe@sekbuelach.example', 'jobTitle' => 'Lehrperson Sek A, Klassenlehrer 2a', 'accountEnabled' => true],
            ]]),
        ]);

        $this->enable('entra', ['tenant_id' => 'tenant-1', 'client_id' => 'app-1', 'client_secret' => 'graph-secret-xyz', 'default_school' => 'hinterbirch'], allowWrite: true);
        $this->signInAs('admin');

        $this->postJson('/plugins/entra/test')->assertOk()->assertJson(['ok' => true, 'message' => 'Verbunden mit «Sekundarschule Bülach (Test)».']);

        Http::assertSent(fn (Request $r): bool => str_starts_with($r->url(), 'https://login.microsoftonline.com/tenant-1/')
            && $r['scope'] === 'https://graph.microsoft.com/.default' && $r['grant_type'] === 'client_credentials');

        $preview = $this->postJson('/plugins/entra/aktion/import-people', ['options' => ['only_enabled' => true]])->assertOk()->json();
        $changes = array_column($preview['rows'], '_change', '_key');
        $this->assertSame('neu', $changes['neu.lp@sekbuelach.example']);
        $this->assertSame('gleich', $changes['daniel.probe@sekbuelach.example']);
        $this->assertArrayNotHasKey('alt@sekbuelach.example', $changes, 'deaktivierte Konten werden ausgelassen');

        $this->postJson('/plugins/entra/aktion/import-people', ['options' => ['only_enabled' => true], 'apply' => true, 'selected' => ['neu.lp@sekbuelach.example']])
            ->assertOk()->assertJsonPath('message', '1 Personen angelegt, 0 geändert.');

        $person = app(UserRepository::class)->findByEmail('neu.lp@sekbuelach.example');
        $this->assertSame('hinterbirch', $person['school']);
        $this->assertSame('044 000 00 99', $person['phone']);
    }

    public function test_entra_adds_member_to_group_with_odata_reference(): void
    {
        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'graph-token-abcdef', 'expires_in' => 3599]),
            'graph.microsoft.com/v1.0/users/daniel.probe%40sekbuelach.example*' => Http::response(['id' => 'user-42']),
            'graph.microsoft.com/v1.0/groups/group-7/members/$ref' => Http::response(null, 204),
        ]);

        $this->enable('entra', ['tenant_id' => 't', 'client_id' => 'c', 'client_secret' => 'graph-secret-xyz'], allowWrite: true);
        $this->signInAs('admin');

        $this->postJson('/plugins/entra/aktion/add-to-group', ['options' => ['email' => 'daniel.probe@sekbuelach.example', 'group_id' => 'group-7']])
            ->assertOk()->assertJsonPath('message', 'daniel.probe@sekbuelach.example zur Gruppe hinzugefügt.');

        Http::assertSent(fn (Request $r): bool => $r->method() === 'POST'
            && $r->url() === 'https://graph.microsoft.com/v1.0/groups/group-7/members/$ref'
            && $r['@odata.id'] === 'https://graph.microsoft.com/v1.0/directoryObjects/user-42');
    }

    public function test_jamf_lists_devices_of_a_person_and_sends_mdm_command(): void
    {
        $device = [
            'mobileDeviceId' => '15',
            'managementId' => 'mgmt-15',
            'general' => ['displayName' => 'iPad 2a-07', 'osVersion' => '18.1', 'managed' => true, 'lastInventoryUpdateDate' => '2026-10-28T08:00:00Z'],
            'hardware' => ['serialNumber' => 'DMQX1234'],
            'userAndLocation' => ['username' => 'daniel.probe', 'realName' => 'Daniel Probe', 'emailAddress' => 'daniel.probe@sekbuelach.example'],
        ];

        Http::fake([
            'jamf.example.test/api/v1/oauth/token' => Http::response(['access_token' => 'jamf-token-123456', 'expires_in' => 1199]),
            'jamf.example.test/api/v1/jamf-pro-version' => Http::response(['version' => '11.11.0']),
            'jamf.example.test/api/v2/mobile-devices/detail*' => Http::response(['totalCount' => 1, 'results' => [$device]]),
            'jamf.example.test/api/v1/computers-inventory*' => Http::response(['totalCount' => 0, 'results' => []]),
            'jamf.example.test/api/v2/mdm/commands' => Http::response([['id' => 'cmd-1', 'href' => '/v2/mdm/commands/cmd-1']], 201),
        ]);

        $this->enable('jamf', ['base_url' => 'https://jamf.example.test', 'client_id' => 'c', 'client_secret' => 'jamf-secret-xyz', 'token_path' => '/api/v1/oauth/token'], allowWrite: true);
        $this->signInAs('admin');

        $this->postJson('/plugins/jamf/test')->assertOk()->assertJsonPath('message', 'Verbunden mit Jamf Pro 11.11.0.');

        $result = $this->postJson('/plugins/jamf/aktion/devices-of-person', ['options' => ['email' => 'daniel.probe@sekbuelach.example']])->assertOk()->json();
        $this->assertSame('iPad 2a-07', $result['rows'][0]['name']);
        $this->assertSame('DMQX1234', $result['rows'][0]['serial']);

        Http::assertSent(fn (Request $r): bool => str_contains($r->url(), '/api/v2/mobile-devices/detail?section=GENERAL&section=HARDWARE&section=USER_AND_LOCATION')
            && str_contains(urldecode($r->url()), 'filter=emailAddress=="daniel.probe@sekbuelach.example"'));

        $this->postJson('/plugins/jamf/aktion/refresh-inventory', ['options' => ['serial' => 'DMQX1234']])->assertOk();
        Http::assertSent(fn (Request $r): bool => $r->url() === 'https://jamf.example.test/api/v2/mdm/commands'
            && $r['clientData'][0]['managementId'] === 'mgmt-15'
            && $r['commandData']['commandType'] === 'DEVICE_INFORMATION');

        $widget = app(PluginManager::class)->widgets(null);
        $this->assertSame([], $widget, 'Kachel nur für Administration');
    }

    public function test_jamf_reports_devices_when_a_person_is_deactivated(): void
    {
        Http::fake([
            'jamf.example.test/api/v1/oauth/token' => Http::response(['access_token' => 'jamf-token-123456', 'expires_in' => 1199]),
            'jamf.example.test/api/v2/mobile-devices/detail*' => Http::response(['totalCount' => 1, 'results' => [[
                'mobileDeviceId' => '15', 'general' => ['displayName' => 'iPad 2a-07'], 'hardware' => ['serialNumber' => 'DMQX1234'],
            ]]]),
        ]);

        $this->enable('jamf', ['base_url' => 'https://jamf.example.test', 'client_id' => 'c', 'client_secret' => 'jamf-secret-xyz', 'token_path' => '/api/v1/oauth/token']);
        $admin = $this->signInAs('admin');
        $person = app(UserRepository::class)->findByEmail('daniel.probe@sekbuelach.example');

        $this->put("/personen/{$person['id']}", [
            'name' => $person['name'], 'email' => $person['email'], 'role' => 'mitarbeitende', 'school' => $person['school'], 'active' => false,
        ])->assertRedirect('/personen');

        $run = app(PluginManager::class)->runs('jamf', 1)[0];
        $this->assertSame('Ereignis person.updated', $run['label']);
        $this->assertStringContainsString('hat aber 1 iPad(s) in Jamf: iPad 2a-07', implode("\n", $run['log']));
        $this->assertSame($admin->name(), $run['user_name']);
    }

    public function test_inventory360_uses_configurable_token_header_and_detects_columns(): void
    {
        Http::fake([
            'inventory.example.test/api/2.0/assets/hardware*' => Http::response(['data' => [
                ['inventorynumber' => 'INV-0001', 'name' => 'Beamer Zimmer 104', 'manufacturer' => 'Epson', 'nested' => ['x' => 1]],
            ]]),
        ]);

        $this->enable('inventory360', ['base_url' => 'https://inventory.example.test', 'api_token' => 'inv-token-123456', 'auth_header' => 'X-Api-Token', 'auth_prefix' => 'none']);
        $this->signInAs('admin');

        $this->postJson('/plugins/inventory360/test')->assertOk()->assertJson(['ok' => true]);
        Http::assertSent(fn (Request $r): bool => $r->hasHeader('X-Api-Token', 'inv-token-123456') && ! $r->hasHeader('Authorization'));

        $result = $this->postJson('/plugins/inventory360/aktion/search', ['options' => ['search' => 'Beamer']])->assertOk()->json();
        $this->assertSame(['inventorynumber', 'name', 'manufacturer'], array_column($result['columns'], 'key'));
        $this->assertSame('Beamer Zimmer 104', $result['rows'][0]['name']);
    }

    public function test_inventory360_follows_api_reference(): void
    {
        Http::fake([
            'inventory.example.test/api/2.0/assets/hardware/101001' => Http::response(['id' => 12, 'inventorynumber' => 101001, 'name' => 'Beamer 104', 'room' => ['x' => 1]]),
            'inventory.example.test/api/2.0/assets/hardware/999' => Http::response([], 404),
            'inventory.example.test/api/2.0/assets/hardware*' => Http::response([
                '_query' => ['total' => 70, 'pages' => 70, 'page' => 1],
                'results' => [['id' => 12, 'inventorynumber' => 101001, 'name' => 'Beamer 104']],
            ]),
        ]);

        // Vorgabe laut Referenz: Token unverändert in «Authorization»
        $this->enable('inventory360', ['base_url' => 'https://inventory.example.test', 'api_token' => 'inv-token-123456']);
        $this->signInAs('admin');

        $this->postJson('/plugins/inventory360/test')->assertOk()->assertJson(['ok' => true, 'message' => 'Verbunden, Hardware-Inventar lesbar (70 Geräte).']);
        Http::assertSent(fn (Request $r): bool => $r->hasHeader('Authorization', 'inv-token-123456'));

        $result = $this->postJson('/plugins/inventory360/aktion/search', ['options' => ['search' => 'Beamer', 'per_page' => 1]])->assertOk()->json();
        $this->assertSame('1 von 70 Einträgen (erste Seite).', $result['message']);

        $device = $this->postJson('/plugins/inventory360/aktion/device', ['options' => ['inventorynumber' => '101001']])->assertOk()->json();
        $this->assertSame(['id', 'inventorynumber', 'name'], array_column($device['rows'], 'field'));

        $this->postJson('/plugins/inventory360/aktion/device', ['options' => ['inventorynumber' => '999']])->assertOk()->assertJsonFragment(['message' => 'Kein Gerät mit Inventarnummer 999 gefunden.']);
        $this->postJson('/plugins/inventory360/aktion/device', ['options' => ['inventorynumber' => '../x']])->assertOk()->assertJsonFragment(['message' => 'Bitte eine gültige Inventarnummer angeben.']);
    }

    public function test_ida_checks_health_without_token(): void
    {
        Http::fake(['ida.example.test/up' => Http::response('<html>ok</html>')]);

        $this->enable('ida', ['base_url' => 'https://ida.example.test']);
        $this->signInAs('admin');

        $this->postJson('/plugins/ida/test')->assertOk()->assertJson(['ok' => true]);
        Http::assertSent(fn (Request $r): bool => $r->url() === 'https://ida.example.test/up' && ! $r->hasHeader('Authorization'));
    }

    public function test_base_urls_must_use_https(): void
    {
        $this->signInAs('admin');

        $this->put('/plugins/jamf/einstellungen', ['base_url' => 'http://jamf.example.test', 'client_id' => 'c', 'client_secret' => 's'])
            ->assertSessionHasErrors(['settings' => '«Jamf-Pro-Adresse» muss mit https:// beginnen.']);
    }
}
