<?php

namespace Tests\Feature;

use App\Plugins\ManifestOpenApi;
use App\Plugins\PluginManager;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Inertia\Testing\AssertableInertia as Assert;
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
        $this->assertCount(54, $manifest->endpoints(), '53 Endpunkte aus dem Swagger von DeepSign plus users/me beim Anmeldedienst');
        $this->assertSame([], array_values(array_filter($manifest->endpoints(), fn (array $e): bool => ($e['status'] ?? '') !== 'full')));
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
            $onlyFile = isset($endpoint['file']) && ! isset($endpoint['file']['data']);
            if (in_array($endpoint['method'], ['POST', 'PUT'], true) && ! $onlyFile) {
                $this->assertArrayHasKey('body', $endpoint, "{$endpoint['id']}: Beispiel-Body fehlt");
            }
        }
    }

    public function test_scalar_view_has_body_examples_and_fields(): void
    {
        $spec = ManifestOpenApi::build(app(PluginManager::class)->manifest('deepsign'));
        $body = $spec['paths']['/documents/{documentId}/signees']['post']['requestBody']['content']['application/json'];

        $this->assertSame('anna.muster@sekbuelach.example', $body['example']['email']);
        $this->assertSame(['signeeType', 'fileSignatureFields'], $body['schema']['required']);
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
        $body = '{"signeeType": "sign", "email": "keine-adresse", "signOrder": 0, "fileSignatureFields": {}}';

        $this->postJson('/plugins/deepsign/api', ['endpoint' => 'signee-add', 'params' => ['documentId' => Plugin::DEMO_DOCUMENT], 'body' => $body])
            ->assertJsonPath('status', 'error');

        $this->enable(['demo' => true], allowWrite: true);
        $this->postJson('/plugins/deepsign/api', ['endpoint' => 'signee-add', 'params' => ['documentId' => Plugin::DEMO_DOCUMENT], 'body' => $body])
            ->assertJsonPath('http_status', 400);
        $this->postJson('/plugins/deepsign/api', ['endpoint' => 'signee-add', 'params' => ['documentId' => Plugin::DEMO_DOCUMENT], 'body' => '{"signeeType": "sign", "email": "eva.fiktiv@sekbuelach.example", "fileSignatureFields": {}}'])
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

    public function test_endtest_allows_real_calls_outside_switzerland(): void
    {
        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'token-abc-123456', 'expires_in' => 300]),
            'api.sign.deepbox.swiss/api/v1/overview*' => Http::response(['documents' => [], 'size' => 2]),
            'deepcloud.swiss/api/v1/users/me' => Http::response(['display_name' => 'Intranet Testschule']),
        ]);
        config(['intranet.server_in_switzerland' => false]);
        $this->enable([
            'demo' => false, 'endtest' => true,
            'username' => 'service-sekbuelach', 'password' => 'geheimes-passwort-123',
            'client_id' => 'partner-client-456', 'client_secret' => 'partner-secret-789',
        ]);
        $this->signInAs('admin');

        $this->postJson('/plugins/deepsign/test')->assertJson(['ok' => true])
            ->assertJsonPath('message', fn (string $m): bool => str_starts_with($m, 'Verbunden mit DeepSign: 2 Dokumente sichtbar.'));
        Http::assertSent(fn (Request $r): bool => str_starts_with($r->url(), 'https://api.sign.deepbox.swiss/api/v1/overview'));
    }

    public function test_api_tester_uploads_file_as_multipart(): void
    {
        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'token-abc-123456', 'expires_in' => 300]),
            'api.sign.deepbox.swiss/api/v1/documents/file' => Http::response(['documentId' => 'doc-echt-1', 'documentStatus' => 'draft'], 201),
        ]);
        config(['intranet.server_in_switzerland' => false]);
        $this->enable([
            'demo' => false, 'endtest' => true,
            'username' => 'service-sekbuelach', 'password' => 'geheimes-passwort-123',
            'client_id' => 'partner-client-456', 'client_secret' => 'partner-secret-789',
        ], allowWrite: true);
        $this->signInAs('admin');
        $payload = (string) json_encode(['endpoint' => 'document-upload', 'params' => [], 'body' => '{"initiatorAliasName": "Sekundarschule Bülach"}']);
        $pdf = UploadedFile::fake()->createWithContent('Endtest.pdf', "%PDF-1.4\n% Endtest (erfunden)\n%%EOF");

        $this->post('/plugins/deepsign/api', ['payload' => $payload, 'file_upload' => $pdf], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('http_status', 201)
            ->assertJsonPath('body', fn (string $b): bool => str_contains($b, 'doc-echt-1'));

        Http::assertSent(fn (Request $r): bool => $r->url() === 'https://api.sign.deepbox.swiss/api/v1/documents/file'
            && $r->hasFile('file', null, 'Endtest.pdf')
            && $r->hasFile('data'));

        // Endpunkte ohne Datei nehmen keine an
        $other = (string) json_encode(['endpoint' => 'overview', 'params' => []]);
        $this->post('/plugins/deepsign/api', ['payload' => $other, 'file_upload' => $pdf], ['Accept' => 'application/json'])->assertStatus(422);
    }

    public function test_demo_answers_every_endpoint(): void
    {
        Http::fake();
        $this->enable(['demo' => true], allowWrite: true);
        $this->signInAs('admin');
        $manifest = app(PluginManager::class)->manifest('deepsign');

        foreach ($manifest->endpoints() as $endpoint) {
            $params = [];
            foreach ($endpoint['params'] ?? [] as $param) {
                $params[$param['name']] = (string) ($param['example'] ?? $param['default'] ?? '');
            }
            $body = isset($endpoint['body']) ? (string) json_encode($endpoint['body']) : null;
            $answer = $this->postJson('/plugins/deepsign/api', ['endpoint' => $endpoint['id'], 'params' => $params, 'body' => $body])->json();
            $this->assertNotSame(501, $answer['http_status'] ?? null, "{$endpoint['id']} im Demo-Modus nicht simuliert");
            $this->assertLessThan(500, (int) ($answer['http_status'] ?? 500), "{$endpoint['id']}: ".($answer['message'] ?? ''));
        }
        Http::assertNothingSent();
    }

    public function test_real_send_uses_file_signature_fields_and_bottom_left_position(): void
    {
        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'token-abc-123456', 'expires_in' => 300]),
            'api.sign.deepbox.swiss/api/v1/documents/file' => Http::response(['documentId' => 'doc-1', 'documentStatus' => 'draft', 'files' => [['fileId' => 'file-1', 'fileName' => 'a.pdf', 'fileUrl' => 'https://x.example/f']]], 201),
            'api.sign.deepbox.swiss/api/v1/documents/doc-1/signees' => Http::response(['signeeId' => 'signee-1'], 201),
        ]);
        $this->realAccount();
        app(PluginManager::class)->saveSettings('deepsign', ['_allow_write' => true]);
        $ctx = app(PluginManager::class)->context('deepsign', null);

        $sent = (new Plugin)->signatureSend($ctx, [
            'title' => 'Monatsrapport (erfunden)', 'file_name' => 'a.pdf', 'comment' => null,
            'signees' => [['email' => 'anna.muster@sekbuelach.example', 'order' => 0, 'position' => ['pageNumber' => 1, 'x' => 42, 'y' => 95, 'width' => 240, 'height' => 60]]],
            'observers' => [],
        ], "%PDF-1.4\n%%EOF", null);

        $this->assertSame(['provider_id' => 'doc-1', 'signee_ids' => ['signee-1']], $sent);
        Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), '/documents/doc-1/signees')
            && $r['signeeType'] === 'sign'
            && $r['fileSignatureFields'] === ['file-1' => ['autographPositions' => [['pageNumber' => 1, 'x' => 42, 'y' => 95, 'width' => 240, 'height' => 60]]]]);
    }

    public function test_seal_and_identification_actions_in_demo(): void
    {
        Http::fake();
        $this->enable(['demo' => true]);
        $this->signInAs('admin');

        $this->postJson('/plugins/deepsign/aktion/seals', ['options' => []])->assertOk()->assertJsonPath('rows.0.key', 'siegel-schulverwaltung');
        $this->postJson('/plugins/deepsign/aktion/authority', ['options' => ['signatureMode' => 'qualified']])->assertOk()
            ->assertJsonPath('rows.0.service', 'MobileID')
            ->assertJsonPath('rows.1.available', 'nein');
        Http::assertNothingSent();
    }

    public function test_swagger_link_on_plugin_page(): void
    {
        $this->signInAs('admin');
        $this->get('/plugins/deepsign')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('plugin.service.swagger', 'https://api.sign.deepbox.swiss/swagger-ui/index.html?configUrl=/api-docs/swagger-config'));
    }
}
