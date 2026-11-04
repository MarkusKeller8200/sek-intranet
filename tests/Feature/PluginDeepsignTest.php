<?php

namespace Tests\Feature;

use App\Plugins\ManifestOpenApi;
use App\Plugins\PluginManager;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Plugins\Deepsign\Plugin;
use Tests\TestCase;

/**
 * DeepSign (#37): Anmeldung, Demo-Modus, Verbindungstest, API-Tester. Alle Antworten sind erfunden.
 */
class PluginDeepsignTest extends TestCase
{
    private const TOKEN_URL = 'https://deepcloud.swiss/auth/realms/sso/protocol/openid-connect/token';

    /**
     * @param  array<string, mixed>  $settings
     */
    private function enable(array $settings, bool $allowWrite = false): void
    {
        $manager = app(PluginManager::class);
        $manager->saveSettings('deepsign', $settings + ['_allow_write' => $allowWrite]);
        $manager->setEnabled('deepsign', true);
    }

    private function realAccount(): void
    {
        config(['intranet.server_in_switzerland' => true]);
        $this->enable([
            'demo' => false,
            'username' => 'service-sekbuelach', 'password' => 'geheimes-passwort-123',
            'client_id' => 'partner-client-456', 'client_secret' => 'partner-secret-789',
        ]);
    }

    public function test_manifest_with_examples_for_every_parameter(): void
    {
        $manifest = app(PluginManager::class)->manifest('deepsign');
        $this->assertCount(19, $manifest->endpoints());
        $this->assertSame('oauth2_password', $manifest->auth()['type']);
        $this->assertEqualsCanonicalizing(['username', 'password', 'client_id', 'client_secret'], $manifest->secretKeys());

        $document = $manifest->endpoint('document');
        $this->assertSame(Plugin::DEMO_DOCUMENT, $document['params'][0]['example'] ?? null);
        $this->assertSame('anna.muster@sekbuelach.example', $manifest->endpoint('signee-add')['body']['email'] ?? null);

        // API-Tester: jeder Parameter mit Vorgabe oder Beispiel, jeder schreibende Aufruf mit Beispiel-Body
        foreach ($manifest->endpoints() as $endpoint) {
            foreach ($endpoint['params'] ?? [] as $param) {
                $this->assertTrue(isset($param['default']) || isset($param['example']), "{$endpoint['id']}: {$param['name']} ohne Beispiel");
            }
            if (in_array($endpoint['method'], ['POST', 'PUT'], true) && $endpoint['id'] !== 'scan-predefined') {
                $this->assertArrayHasKey('body', $endpoint, "{$endpoint['id']}: Beispiel-Body fehlt");
            }
        }
    }

    public function test_scalar_view_has_body_examples_and_fields(): void
    {
        $spec = ManifestOpenApi::build(app(PluginManager::class)->manifest('deepsign'));
        $body = $spec['paths']['/documents/{documentId}/signees']['post']['requestBody']['content']['application/json'];

        $this->assertSame('anna.muster@sekbuelach.example', $body['example']['email']);
        $this->assertSame(['email'], $body['schema']['required']);
        $this->assertSame(['timestamp', 'advanced', 'qualified'], $spec['paths']['/documents/file']['post']['requestBody']['content']['application/json']['schema']['properties']['signatureMode']['enum']);
        $this->assertSame(Plugin::DEMO_DOCUMENT, $spec['paths']['/documents/{documentId}']['get']['parameters'][0]['example']);
    }

    public function test_demo_mode_without_credentials_sends_nothing(): void
    {
        Http::fake();
        $this->enable(['demo' => true]);
        $this->signInAs('admin');

        $this->postJson('/plugins/deepsign/test')->assertOk()
            ->assertJson(['ok' => true])
            ->assertJsonPath('message', 'Demo-Modus: 3 Dokumente sichtbar. Service-Benutzer: Intranet SekBülach (Demo) (Sekundarschule Bülach (Demo)).');

        $this->postJson('/plugins/deepsign/aktion/documents', ['options' => ['status' => 'signed']])->assertOk()
            ->assertJsonPath('rows.0.status', 'unterschrieben')
            ->assertJsonCount(1, 'rows');

        $this->postJson('/plugins/deepsign/api', ['endpoint' => 'document', 'params' => ['documentId' => Plugin::DEMO_DOCUMENT]])->assertOk()
            ->assertJsonPath('http_status', 200)
            ->assertJsonPath('body', fn (string $body): bool => str_contains($body, 'anna.muster@sekbuelach.example'));
        $this->postJson('/plugins/deepsign/api', ['endpoint' => 'document', 'params' => ['documentId' => 'unbekannt']])
            ->assertJsonPath('http_status', 404);

        Http::assertNothingSent();
    }

    public function test_demo_writes_need_write_permission_and_validate(): void
    {
        Http::fake();
        $this->enable(['demo' => true]);
        $this->signInAs('admin');
        $body = '{"email": "keine-adresse", "signOrder": 0}';

        $this->postJson('/plugins/deepsign/api', ['endpoint' => 'signee-add', 'params' => ['documentId' => Plugin::DEMO_DOCUMENT], 'body' => $body])
            ->assertJsonPath('status', 'error');

        $this->enable(['demo' => true], allowWrite: true);
        $this->postJson('/plugins/deepsign/api', ['endpoint' => 'signee-add', 'params' => ['documentId' => Plugin::DEMO_DOCUMENT], 'body' => $body])
            ->assertJsonPath('http_status', 400);
        $this->postJson('/plugins/deepsign/api', ['endpoint' => 'signee-add', 'params' => ['documentId' => Plugin::DEMO_DOCUMENT], 'body' => '{"email": "eva.fiktiv@sekbuelach.example"}'])
            ->assertJsonPath('http_status', 201);
        $this->postJson('/plugins/deepsign/api', ['endpoint' => 'document-start', 'params' => ['documentId' => Plugin::DEMO_DOCUMENT], 'body' => '{}'])
            ->assertJsonPath('http_status', 204);

        Http::assertNothingSent();
    }

    public function test_outside_switzerland_demo_is_forced(): void
    {
        Http::fake();
        config(['intranet.server_in_switzerland' => false]);
        $this->enable([
            'demo' => false,
            'username' => 'service-sekbuelach', 'password' => 'geheimes-passwort-123',
            'client_id' => 'partner-client-456', 'client_secret' => 'partner-secret-789',
        ]);
        $this->signInAs('admin');

        $this->postJson('/plugins/deepsign/test')->assertJsonPath('message', fn (string $m): bool => str_starts_with($m, 'Demo-Modus: 3 Dokumente sichtbar.'));
        Http::assertNothingSent();
    }

    public function test_real_account_gets_token_and_calls_api(): void
    {
        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'token-abc-123456', 'expires_in' => 300]),
            'api.sign.deepbox.swiss/api/v1/overview*' => Http::response(['documents' => [], 'pagination' => ['offset' => 0, 'limit' => 1], 'size' => 7]),
            'deepcloud.swiss/api/v1/users/me' => Http::response(['display_name' => 'Intranet Testschule', 'companies' => [['group_id' => 'g-1', 'display_name' => 'Testschule (erfunden)']]]),
        ]);
        $this->realAccount();
        $this->signInAs('admin');

        $this->postJson('/plugins/deepsign/test')->assertOk()
            ->assertJson(['ok' => true])
            ->assertJsonPath('message', 'Verbunden mit DeepSign: 7 Dokumente sichtbar. Service-Benutzer: Intranet Testschule (Testschule (erfunden)).');
        Http::assertSent(fn (Request $r): bool => $r->url() === 'https://deepcloud.swiss/api/v1/users/me'
            && $r->header('Authorization') === ['Bearer token-abc-123456']);

        Http::assertSent(fn (Request $r): bool => $r->url() === self::TOKEN_URL
            && $r['grant_type'] === 'password' && $r['username'] === 'service-sekbuelach'
            && $r['client_id'] === 'partner-client-456' && $r['client_secret'] === 'partner-secret-789');
        Http::assertSent(fn (Request $r): bool => str_starts_with($r->url(), 'https://api.sign.deepbox.swiss/api/v1/overview')
            && $r->header('Authorization') === ['Bearer token-abc-123456']);
        Http::assertNotSent(fn (Request $r): bool => str_starts_with($r->url(), 'https://api.sign.deepbox.swiss/') && $r->hasHeader('client_secret'));
    }

    public function test_real_account_reports_401_and_429(): void
    {
        Sleep::fake();
        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'token-abc-123456', 'expires_in' => 300]),
            'api.sign.deepbox.swiss/api/v1/overview*' => Http::sequence()
                ->push(['message' => 'Unauthorized'], 401)
                ->push(['message' => 'Too many requests'], 429)
                ->push(['message' => 'Too many requests'], 429)
                ->push(['message' => 'Too many requests'], 429),
        ]);
        $this->realAccount();
        $this->signInAs('admin');

        $this->postJson('/plugins/deepsign/test')->assertJson(['ok' => false])
            ->assertJsonPath('message', fn (string $m): bool => str_contains($m, 'HTTP 401'));
        $this->postJson('/plugins/deepsign/test')->assertJson(['ok' => false])
            ->assertJsonPath('message', fn (string $m): bool => str_contains($m, 'HTTP 429'));
    }

    public function test_failed_login_is_reported_without_secrets(): void
    {
        Http::fake([self::TOKEN_URL => Http::response(['error' => 'invalid_grant', 'error_description' => 'Invalid user credentials'], 401)]);
        $this->realAccount();
        $this->signInAs('admin');

        $message = (string) $this->postJson('/plugins/deepsign/test')->json('message');
        $this->assertStringContainsString('Anmeldung beim Dienst fehlgeschlagen', $message);
        $this->assertStringNotContainsString('geheimes-passwort-123', $message);
    }

    public function test_service_user_link_for_production_and_test(): void
    {
        $this->assertSame(
            'https://admin.deepbox.swiss/third-party-connection/?response_type=service_account&client_id=partner-client-456&response_mode=copy_paste',
            Plugin::serviceUserUrl(Plugin::PRODUCTION, 'partner-client-456'),
        );
        $this->assertStringStartsWith('https://admin.int.deepbox.swiss/third-party-connection/?', Plugin::serviceUserUrl(Plugin::TEST, 'x'));

        Http::fake();
        $this->signInAs('admin');

        // Ohne client_id: Hinweis statt Link
        $this->enable(['demo' => true]);
        $this->postJson('/plugins/deepsign/aktion/service-user', ['options' => []])->assertOk()
            ->assertJsonPath('status', 'warning')
            ->assertJsonPath('links', []);

        // Nur Partner-Client eingetragen (noch kein Service-Benutzer): Aktion läuft, Link zur Testumgebung
        config(['intranet.server_in_switzerland' => true]);
        $this->enable(['demo' => false, 'environment' => Plugin::TEST, 'client_id' => 'partner-client-456', 'client_secret' => 'partner-secret-789']);
        $this->postJson('/plugins/deepsign/aktion/service-user', ['options' => []])->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('links.0.url', Plugin::serviceUserUrl(Plugin::TEST, 'partner-client-456'));
        Http::assertNothingSent();
    }
}
