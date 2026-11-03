<?php

namespace Tests\Feature;

use App\Data\Contracts\UserRepository;
use App\Data\Store\JsonStore;
use App\Support\RolePins;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Zugang mit PIN je Rolle statt Basic Auth (#69). Nur Test-PINs, nie echte.
 */
class RolePinTest extends TestCase
{
    private const ADMIN_PIN = '11112222';

    private const EXTERN_PIN = '33334444';

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('pin-login:127.0.0.1:minute');
        RateLimiter::clear('pin-login:127.0.0.1:hour');
    }

    private function pins(): RolePins
    {
        return app(RolePins::class);
    }

    public function test_admin_sets_pins_only_hash_is_stored(): void
    {
        $this->signInAs('lehrperson');
        $this->get('/einstellungen/zugang')->assertForbidden();

        $this->signInAs('admin');
        $this->get('/einstellungen/zugang')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('settings/access')->where('aktiv', false)->where('rollen.extern.set', false));
        $this->put('/einstellungen/zugang/admin', ['pin' => '1234', 'pin_confirmation' => '1234'])->assertSessionHasErrors('pin');
        $this->put('/einstellungen/zugang/admin', ['pin' => self::ADMIN_PIN, 'pin_confirmation' => '11112223'])->assertSessionHasErrors('pin');
        $this->put('/einstellungen/zugang/admin', ['pin' => self::ADMIN_PIN, 'pin_confirmation' => self::ADMIN_PIN])->assertRedirect();
        $this->put('/einstellungen/zugang/extern', ['pin' => self::ADMIN_PIN, 'pin_confirmation' => self::ADMIN_PIN])->assertSessionHasErrors('pin');
        $this->put('/einstellungen/zugang/gibtsnicht', ['pin' => self::EXTERN_PIN, 'pin_confirmation' => self::EXTERN_PIN])->assertNotFound();

        $raw = (string) file_get_contents(app(JsonStore::class)->filePath(RolePins::COLLECTION));
        $this->assertStringNotContainsString(self::ADMIN_PIN, $raw);
        $this->assertTrue($this->pins()->active());
        $this->assertTrue($this->pins()->check('admin', self::ADMIN_PIN));
        $this->assertFalse($this->pins()->check('admin', '11112223'));
    }

    public function test_basic_auth_ends_once_admin_pin_is_set(): void
    {
        config(['intranet.access.password' => 'geheim']);
        $this->get('/anmelden')->assertUnauthorized();

        $this->pins()->set('admin', self::ADMIN_PIN, null);
        $this->get('/anmelden')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('pinMode', true)->where('role', null)->where('users', [])->where('roles.admin.set', true)->where('roles.verwaltung.set', false));
    }

    public function test_login_with_role_and_pin_then_choose_person(): void
    {
        $this->pins()->set('admin', self::ADMIN_PIN, null);
        $this->pins()->set('extern', self::EXTERN_PIN, null);

        // ohne PIN keine Auswahl möglich
        $anyone = collect(app(UserRepository::class)->active())->firstWhere('role', 'lehrperson');
        $this->post('/anmelden', ['user_id' => $anyone['id']])->assertSessionHasErrors('user_id');
        $this->assertGuest();

        // falscher PIN, Rolle ohne PIN
        $this->post('/anmelden/pin', ['role' => 'extern', 'pin' => '00000000'])->assertSessionHasErrors('pin');
        $this->post('/anmelden/pin', ['role' => 'verwaltung', 'pin' => self::EXTERN_PIN])->assertSessionHasErrors('pin');
        $this->assertGuest();

        // Externe: genau eine Demo-Person → direkt angemeldet
        $this->post('/anmelden/pin', ['role' => 'extern', 'pin' => self::EXTERN_PIN])->assertRedirect('/start');
        $this->assertAuthenticated();
        $this->post('/abmelden');

        // Administration: Auswahl aller Demo-Personen
        $this->post('/anmelden/pin', ['role' => 'admin', 'pin' => self::ADMIN_PIN])->assertRedirect('/anmelden');
        $this->get('/anmelden')->assertInertia(fn (Assert $page) => $page->where('role', 'admin')->where('users', fn ($u): bool => count($u) > 5));
        $this->post('/anmelden', ['user_id' => $anyone['id']])->assertRedirect('/start');
        $this->assertAuthenticated();
    }

    public function test_too_many_wrong_pins_are_blocked(): void
    {
        $this->pins()->set('admin', self::ADMIN_PIN, null);
        for ($i = 0; $i < 5; $i++) {
            $this->post('/anmelden/pin', ['role' => 'admin', 'pin' => '0000000'.$i]);
        }
        $this->post('/anmelden/pin', ['role' => 'admin', 'pin' => self::ADMIN_PIN])
            ->assertSessionHasErrors(['pin' => 'Zu viele Fehlversuche. Bitte in 60 Sekunden nochmals versuchen.']);
        $this->assertGuest();
    }

    public function test_external_role_sees_only_communication_and_knowledge(): void
    {
        $this->signInAs('extern');
        $this->get('/start')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('navigation', fn ($groups): bool => collect($groups)->pluck('key')->sort()->values()->all() === ['kommunikation', 'wissen']));
        $this->get('/glossar')->assertOk();
        $this->get('/dokumente')->assertOk();
        $this->get('/personen')->assertForbidden();
        $this->get('/einstellungen/zugang')->assertForbidden();
        $this->get('/')->assertRedirect('/start');

        // andere Rollen unverändert
        $this->signInAs('lehrperson');
        $this->get('/personen')->assertOk();
    }
}
