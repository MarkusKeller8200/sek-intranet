<?php

namespace Tests\Feature;

use App\Data\Store\JsonStore;
use App\Plugins\PluginManager;
use App\Support\Notifications;
use App\Support\Signatures;
use App\Support\SimplePdf;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

/**
 * Kern-Schnittstelle «Unterschriften» (#38–#42) mit DeepSign im Demo-Modus: senden, Reihenfolge, Ereignisse,
 * Abholen, Zurückziehen, Löschen, Webhook mit Signatur, regelmässige Abfrage, Seite und Rechte.
 */
class SignaturesTest extends TestCase
{
    /** @var list<array{event: string, status: string, pdf: bool}> */
    private array $events = [];

    private bool $accept = true;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
        Signatures::listen('test', function (string $event, array $request, ?string $pdf): ?bool {
            $this->events[] = ['event' => $event, 'status' => (string) $request['status'], 'pdf' => $pdf !== null && str_starts_with($pdf, '%PDF')];

            return $event === 'signatur.abgeschlossen' ? $this->accept : true;
        });
    }

    protected function tearDown(): void
    {
        Signatures::forgetListeners();
        parent::tearDown();
    }

    private function enableDeepsign(bool $write = true): void
    {
        $manager = app(PluginManager::class);
        $manager->saveSettings('deepsign', ['demo' => true, '_allow_write' => $write]);
        $manager->setEnabled('deepsign', true);
    }

    private function pdf(): string
    {
        $pdf = new SimplePdf('Test');
        $pdf->text(40, 60, 'Testdokument (erfunden) – Grüsse', 12, true);

        return $pdf->output();
    }

    /**
     * @return array<string, mixed>
     */
    private function create(): array
    {
        return app(Signatures::class)->create([
            'module' => 'test',
            'reference' => 'person:demo:2026-10',
            'title' => 'Monatsrapport Oktober 2026 – Muster Anna',
            'file_name' => 'Monatsrapport-2026-10.pdf',
            'signees' => [
                ['name' => 'Anna Muster', 'email' => 'anna.muster@sekbuelach.example', 'order' => 0, 'position' => ['pageNumber' => 1, 'x' => 42, 'y' => 686, 'width' => 240, 'height' => 60]],
                ['name' => 'Beat Beispiel', 'email' => 'beat.beispiel@sekbuelach.example', 'order' => 1],
            ],
            'observers' => ['personal@sekbuelach.example'],
        ], $this->pdf(), null);
    }

    public function test_simple_pdf_is_a_valid_pdf(): void
    {
        $out = $this->pdf();
        $this->assertStringStartsWith('%PDF-1.4', $out);
        $this->assertStringEndsWith("%%EOF\n", $out);
        $this->assertStringContainsString('/WinAnsiEncoding', $out);
        $this->assertStringContainsString("Gr\xFCsse", $out, 'Umlaute als Windows-1252');
    }

    public function test_send_in_order_sign_and_hand_over_pdf(): void
    {
        $this->enableDeepsign();
        $signatures = app(Signatures::class);

        $request = $this->create();
        $this->assertSame('versendet', $request['status']);
        $this->assertStringStartsWith('demo-', (string) $request['provider_id']);
        $this->assertSame(['offen', 'wartet'], array_column($request['signees'], 'status'));
        $this->assertArrayNotHasKey('callback_secret', $request);

        $request = $signatures->simulate((string) $request['id'], null);
        $this->assertSame('teilweise', $request['status']);
        $this->assertSame(['unterschrieben', 'offen'], array_column($request['signees'], 'status'));

        $request = $signatures->simulate((string) $request['id'], null);
        $this->assertSame('unterschrieben', $request['status']);
        $this->assertNotNull($request['fetched_at']);
        $this->assertSame(['signatur.unterschrieben', 'signatur.unterschrieben', 'signatur.abgeschlossen'], array_column($this->events, 'event'));
        $this->assertTrue($this->events[2]['pdf']);
        Http::assertNothingSent();
    }

    public function test_withdraw_delete_and_rules(): void
    {
        $this->enableDeepsign();
        $signatures = app(Signatures::class);
        $request = $this->create();

        $this->expectExceptionOnDelete($signatures, (string) $request['id']);
        $signatures->withdraw((string) $request['id'], null);
        $this->assertSame('zurueckgezogen', $signatures->find((string) $request['id'])['status'] ?? null);
        $this->assertSame('signatur.abgelehnt', end($this->events)['event'] ?? null);

        $signatures->delete((string) $request['id'], null);
        $this->assertNull($signatures->find((string) $request['id']));
    }

    private function expectExceptionOnDelete(Signatures $signatures, string $id): void
    {
        try {
            $signatures->delete($id, null);
            $this->fail('Laufende Aufträge dürfen nicht gelöscht werden.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('zurückziehen', $e->getMessage());
        }
    }

    public function test_needs_provider_and_write_permission(): void
    {
        try {
            $this->create();
            $this->fail('Ohne Anbieter kein Auftrag.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Kein Anbieter', $e->getMessage());
        }

        $this->enableDeepsign(write: false);
        try {
            $this->create();
            $this->fail('Ohne Schreibfreigabe kein Senden.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('fehlgeschlagen', $e->getMessage());
        }
        $this->assertSame('fehler', app(Signatures::class)->all()[0]['status']);
    }

    public function test_webhook_checks_signature_and_known_id(): void
    {
        config(['intranet.signatures.webhook' => true]);
        $this->enableDeepsign();
        $request = $this->create();
        $stored = app(JsonStore::class)->find(Signatures::COLLECTION, (string) $request['id']);
        $secret = Crypt::decryptString((string) $stored['callback_secret']);
        $body = (string) json_encode(['documentId' => $request['provider_id'], 'eventType' => 'signee-completed']);

        $this->call('POST', '/api/signaturen/deepsign/callback', [], [], [], ['HTTP_X_DEEPSIGN_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, 'falsch'), 'CONTENT_TYPE' => 'application/json'], $body)
            ->assertStatus(401);
        $this->call('POST', '/api/signaturen/deepsign/callback', [], [], [], ['HTTP_X_DEEPSIGN_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, $secret), 'CONTENT_TYPE' => 'application/json'], $body)
            ->assertStatus(204);
        $this->assertNotNull(app(Signatures::class)->find((string) $request['id'])['last_checked_at'] ?? null);

        $unknown = (string) json_encode(['documentId' => 'demo-gibt-es-nicht']);
        $this->call('POST', '/api/signaturen/deepsign/callback', [], [], [], ['HTTP_X_DEEPSIGN_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $unknown, $secret)], $unknown)
            ->assertStatus(404);
    }

    public function test_scheduled_refresh_warns_when_signed_pdf_is_not_stored(): void
    {
        $this->enableDeepsign();
        $this->accept = false;
        $signatures = app(Signatures::class);
        $request = $this->create();
        $signatures->simulate((string) $request['id'], null);
        $signatures->simulate((string) $request['id'], null);
        $this->assertNull($signatures->find((string) $request['id'])['fetched_at'] ?? null);

        $this->travel(25)->hours();
        $this->artisan('intranet:signatures-refresh')->assertExitCode(0);

        $admin = $this->signInAs('admin');
        $this->assertNotEmpty(array_filter(app(Notifications::class)->forUser((string) $admin->getAuthIdentifier()), fn (array $n): bool => $n['kind'] === 'signatur.nicht_abgeholt'));

        // Danach klappt das Ablegen beim nächsten Lauf
        $this->accept = true;
        $this->artisan('intranet:signatures-refresh')->assertExitCode(0);
        $this->assertNotNull($signatures->find((string) $request['id'])['fetched_at'] ?? null);
    }

    public function test_page_actions_and_rights(): void
    {
        $this->enableDeepsign();
        $request = $this->create();

        $this->signInAs('lehrperson');
        $this->get('/unterschriften')->assertForbidden();

        $this->signInAs('verwaltung');
        $this->get('/unterschriften')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('signatures/index')
            ->has('auftraege', 1)
            ->where('anbieter.demo', true)
            ->where('darfOeffnen', false)
            ->missing('auftraege.0.callback_secret'));
        $this->post("/unterschriften/{$request['id']}/erinnern", ['person' => 0])->assertRedirect();
        $this->postJson("/unterschriften/{$request['id']}/link")->assertForbidden();
        $this->post("/unterschriften/{$request['id']}/demo")->assertRedirect();
        $this->assertSame('teilweise', app(Signatures::class)->find((string) $request['id'])['status'] ?? null);

        $this->signInAs('admin');
        $this->postJson("/unterschriften/{$request['id']}/link")->assertOk()->assertJsonPath('url', fn (string $url): bool => str_starts_with($url, 'https://'));
        $this->post("/unterschriften/{$request['id']}/zurueckziehen")->assertRedirect();
        $this->delete("/unterschriften/{$request['id']}")->assertRedirect();
        $this->assertSame([], app(Signatures::class)->all());
    }

    public function test_send_document_from_page_and_download_signed_pdf(): void
    {
        Storage::fake('local');
        $this->enableDeepsign();
        $this->signInAs('verwaltung');
        $pdf = UploadedFile::fake()->createWithContent('Vereinbarung.pdf', $this->pdf());

        // Pflichtangaben
        $this->post('/unterschriften', ['unterzeichnende' => []])->assertSessionHasErrors(['datei', 'unterzeichnende']);
        $this->post('/unterschriften', ['datei' => $pdf, 'unterzeichnende' => [['name' => 'Anna', 'email' => 'keine-adresse']]])
            ->assertSessionHasErrors('unterzeichnende.0.email');

        $this->post('/unterschriften', [
            'datei' => $pdf,
            'titel' => 'Vereinbarung Weiterbildung (erfunden)',
            'unterzeichnende' => [
                ['name' => 'Anna Muster', 'email' => 'anna.muster@sekbuelach.example'],
                ['name' => 'Beat Beispiel', 'email' => 'beat.beispiel@sekbuelach.example'],
            ],
            'gleichzeitig' => '0',
            'beobachtende' => 'personal@sekbuelach.example',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $request = app(Signatures::class)->all()[0];
        $this->assertSame(Signatures::MANUAL, $request['module']);
        $this->assertSame('Vereinbarung Weiterbildung (erfunden)', $request['title']);
        $this->assertSame([0, 1], array_column($request['signees'], 'order'));
        $this->assertSame(['personal@sekbuelach.example'], $request['observers']);
        $this->get("/unterschriften/{$request['id']}/pdf")->assertNotFound();

        $this->post("/unterschriften/{$request['id']}/demo")->assertRedirect();
        $this->post("/unterschriften/{$request['id']}/demo")->assertRedirect();
        $this->get('/unterschriften')->assertInertia(fn (Assert $page) => $page->where('mitPdf', [$request['id']]));
        $download = $this->get("/unterschriften/{$request['id']}/pdf")->assertOk()->streamedContent();
        $this->assertStringStartsWith('%PDF', $download);

        $this->signInAs('lehrperson');
        $this->get("/unterschriften/{$request['id']}/pdf")->assertForbidden();
    }

    public function test_send_action_in_plugin_with_file_dialog(): void
    {
        $this->enableDeepsign();
        $this->signInAs('admin');
        $payload = (string) json_encode(['options' => [
            'title' => '', 'order' => 'gleichzeitig',
            'signees' => "Anna Muster; anna.muster@sekbuelach.example\nbeat.beispiel@sekbuelach.example",
        ]]);
        $file = UploadedFile::fake()->createWithContent('Protokoll.pdf', $this->pdf());

        $this->post('/plugins/deepsign/aktion/send', ['payload' => $payload, 'file_file' => $file], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('rows.1.name', 'beat.beispiel@sekbuelach.example');

        $request = app(Signatures::class)->all()[0];
        $this->assertSame('Protokoll', $request['title']);
        $this->assertSame([0, 0], array_column($request['signees'], 'order'));
        $this->assertSame(
            [['name' => 'Anna Muster', 'email' => 'anna.muster@sekbuelach.example'], ['name' => 'b@x.ch', 'email' => 'b@x.ch']],
            Signatures::parseSignees("Anna Muster; anna.muster@sekbuelach.example\n\nb@x.ch"),
        );
    }
}
