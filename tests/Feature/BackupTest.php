<?php

namespace Tests\Feature;

use App\Data\Store\JsonStore;
use App\Support\Backups;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;
use ZipArchive;

/**
 * Sicherung herunterladen und wiederherstellen (#174). Nur erfundene Testwerte.
 */
class BackupTest extends TestCase
{
    private const SECRET = 'erfundenes-test-token-123';

    private const PASSWORD = 'ein-langes-testpasswort';

    protected function setUp(): void
    {
        parent::setUp();
        // Der Zähler der Begrenzung gilt je Person über alle begrenzten Routen – hier nicht Gegenstand des Tests
        $this->withoutMiddleware(ThrottleRequests::class);
        Storage::fake('local');
        Storage::fake('public');
    }

    private function store(): JsonStore
    {
        return app(JsonStore::class);
    }

    private function seedSecret(): void
    {
        $this->store()->replace('plugin_states', [[
            'id' => 'testplugin', 'enabled' => false, 'allow_write' => false, 'settings' => ['base_url' => 'https://test.example'],
            'secrets' => ['token' => Crypt::encryptString(self::SECRET)],
        ]]);
    }

    /**
     * @param  TestResponse<BinaryFileResponse>  $response
     */
    private function savedFile(TestResponse $response, string $name): string
    {
        $base = $response->baseResponse;
        $this->assertInstanceOf(BinaryFileResponse::class, $base);
        $target = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('sicherung-', true).'-'.$name;
        copy($base->getFile()->getPathname(), $target);

        return $target;
    }

    public function test_only_administration_has_access(): void
    {
        $this->signInAs('lehrperson');
        $this->get('/einstellungen/sicherung')->assertForbidden();
        $this->postJson('/einstellungen/sicherung/herunterladen')->assertForbidden();

        $this->signInAs('admin');
        $this->get('/einstellungen/sicherung')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('settings/backup')->where('vorschau', null)->where('minPasswort', Backups::MIN_PASSWORD));
    }

    public function test_download_without_password_is_zip_without_credentials(): void
    {
        $this->seedSecret();
        Storage::disk('local')->put('dokumente/merkblatt.txt', 'Testinhalt');
        $this->signInAs('admin');

        $response = $this->postJson('/einstellungen/sicherung/herunterladen', ['passwort' => '', 'passwort_confirmation' => '']);
        $response->assertOk();
        $file = $this->savedFile($response, 'a.zip');

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($file) === true);
        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
        $this->assertIsArray($manifest);
        $this->assertSame('ohne', $manifest['zugangsdaten']);
        $this->assertGreaterThan(0, $manifest['sammlungen']['users']);
        $this->assertSame('Testinhalt', $zip->getFromName('files/private/dokumente/merkblatt.txt'));
        $states = json_decode((string) $zip->getFromName('data/plugin_states.json'), true);
        $this->assertSame([], $states[0]['secrets']);
        $zip->close();
        $this->assertStringNotContainsString(self::SECRET, (string) file_get_contents($file));
        @unlink($file);
    }

    public function test_password_rules(): void
    {
        $this->signInAs('admin');
        $this->postJson('/einstellungen/sicherung/herunterladen', ['passwort' => 'kurz', 'passwort_confirmation' => 'kurz'])
            ->assertStatus(422)->assertJsonValidationErrors('passwort');
        $this->postJson('/einstellungen/sicherung/herunterladen', ['passwort' => self::PASSWORD, 'passwort_confirmation' => 'anders-anders-anders'])
            ->assertStatus(422)->assertJsonValidationErrors('passwort');
    }

    public function test_encrypted_backup_restores_data_files_and_credentials(): void
    {
        $this->seedSecret();
        Storage::disk('local')->put('dokumente/merkblatt.txt', 'Version 1');
        $this->signInAs('admin');
        $usersBefore = $this->store()->count('users');

        $response = $this->postJson('/einstellungen/sicherung/herunterladen', ['passwort' => self::PASSWORD, 'passwort_confirmation' => self::PASSWORD]);
        $response->assertOk();
        $file = $this->savedFile($response, 'b.intranet-backup');
        $raw = (string) file_get_contents($file);
        $this->assertStringStartsWith('SEKBAK01', $raw);
        $this->assertStringNotContainsString(self::SECRET, $raw);
        $this->assertStringNotContainsString('manifest.json', $raw);

        // Daten danach ändern
        $this->store()->replace('plugin_states', []);
        $this->store()->replace('announcements', []);
        Storage::disk('local')->put('dokumente/merkblatt.txt', 'Version 2');
        Storage::disk('local')->put('dokumente/neu.txt', 'kommt weg');

        // Ohne bzw. mit falschem Passwort nicht lesbar
        $upload = fn (): UploadedFile => new UploadedFile($file, 'sicherung.intranet-backup', null, null, true);
        $this->post('/einstellungen/sicherung/vorbereiten', ['datei' => $upload()])->assertSessionHasErrors('passwort');
        $this->post('/einstellungen/sicherung/vorbereiten', ['datei' => $upload(), 'passwort' => 'falsches-passwort-xyz'])->assertSessionHasErrors('passwort');

        $this->post('/einstellungen/sicherung/vorbereiten', ['datei' => $upload(), 'passwort' => self::PASSWORD])->assertSessionHasNoErrors();
        $this->get('/einstellungen/sicherung')->assertInertia(fn (Assert $page) => $page
            ->where('vorschau.zugangsdaten', 'klartext')
            ->where('vorschau.sammlungen.users', $usersBefore)
            ->where('aktuell.announcements', 0));

        $this->post('/einstellungen/sicherung/wiederherstellen', ['bestaetigung' => 'ja'])->assertSessionHasErrors('bestaetigung');
        $this->post('/einstellungen/sicherung/wiederherstellen', ['bestaetigung' => 'WIEDERHERSTELLEN'])->assertRedirect('/anmelden');
        $this->assertGuest();

        $this->assertGreaterThan(0, $this->store()->count('announcements'));
        $state = $this->store()->find('plugin_states', 'testplugin');
        $this->assertNotNull($state);
        $this->assertSame(self::SECRET, Crypt::decryptString((string) $state['secrets']['token']));
        $this->assertSame('Version 1', Storage::disk('local')->get('dokumente/merkblatt.txt'));
        $this->assertFalse(Storage::disk('local')->exists('dokumente/neu.txt'));

        // Vorheriger Stand liegt auf dem Server
        $list = app(Backups::class)->serverBackups();
        $this->assertCount(1, $list);
        $this->assertSame('vor-wiederherstellung', $list[0]['art']);
        @unlink($file);
    }

    public function test_server_backup_can_be_restored(): void
    {
        $this->signInAs('admin');
        $this->post('/einstellungen/sicherung/server')->assertRedirect();
        $name = app(Backups::class)->serverBackups()[0]['name'];
        $this->get("/einstellungen/sicherung/server/{$name}")->assertOk();
        $this->get('/einstellungen/sicherung/server/..%2F..%2F.env')->assertNotFound();

        $this->store()->replace('announcements', []);
        $this->post("/einstellungen/sicherung/server/{$name}/vorbereiten")->assertSessionHasNoErrors();
        $this->post('/einstellungen/sicherung/wiederherstellen', ['bestaetigung' => 'WIEDERHERSTELLEN'])->assertRedirect('/anmelden');
        $this->assertGreaterThan(0, $this->store()->count('announcements'));
    }

    public function test_tampered_or_foreign_files_are_rejected(): void
    {
        $this->signInAs('admin');
        $response = $this->postJson('/einstellungen/sicherung/herunterladen', ['passwort' => '', 'passwort_confirmation' => '']);
        $file = $this->savedFile($response, 'c.zip');

        // Veränderter Inhalt: Prüfsumme stimmt nicht
        $zip = new ZipArchive;
        $zip->open($file);
        $zip->addFromString('data/users.json', '[]');
        $zip->close();
        $this->post('/einstellungen/sicherung/vorbereiten', ['datei' => new UploadedFile($file, 'c.zip', null, null, true)])
            ->assertSessionHasErrors('datei');

        // Fremder Pfad im Archiv
        $evil = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('evil-', true).'.zip';
        $zip = new ZipArchive;
        $zip->open($evil, ZipArchive::CREATE);
        $zip->addFromString('files/private/../../../../tmp/x.txt', 'x');
        $zip->addFromString('manifest.json', (string) json_encode(['format' => 1, 'version' => '0.0.1', 'zugangsdaten' => 'ohne',
            'pruefsummen' => ['files/private/../../../../tmp/x.txt' => hash('sha256', 'x')]]));
        $zip->close();
        $this->post('/einstellungen/sicherung/vorbereiten', ['datei' => new UploadedFile($evil, 'evil.zip', null, null, true)])
            ->assertSessionHasErrors('datei');

        // Neuere Version
        $newer = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('neu-', true).'.zip';
        $zip = new ZipArchive;
        $zip->open($newer, ZipArchive::CREATE);
        $zip->addFromString('data/users.json', '[{"id":"x"}]');
        $zip->addFromString('manifest.json', (string) json_encode(['format' => 1, 'version' => '99.0.0', 'zugangsdaten' => 'ohne',
            'pruefsummen' => ['data/users.json' => hash('sha256', '[{"id":"x"}]')]]));
        $zip->close();
        $this->post('/einstellungen/sicherung/vorbereiten', ['datei' => new UploadedFile($newer, 'neu.zip', null, null, true)])
            ->assertSessionHasErrors('datei');

        // Keine Sicherung
        $text = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('text-', true).'.zip';
        file_put_contents($text, 'kein zip');
        $this->post('/einstellungen/sicherung/vorbereiten', ['datei' => new UploadedFile($text, 'text.zip', null, null, true)])
            ->assertSessionHasErrors('datei');

        $this->post('/einstellungen/sicherung/wiederherstellen', ['bestaetigung' => 'WIEDERHERSTELLEN'])->assertSessionHasErrors('bestaetigung');
        foreach ([$file, $evil, $newer, $text] as $path) {
            @unlink($path);
        }
    }
}
