<?php

namespace Tests\Feature;

use App\Data\Contracts\UserRepository;
use Illuminate\Auth\SessionGuard;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PersonTest extends TestCase
{
    public function test_staff_see_active_people_admin_sees_all(): void
    {
        $this->signInAs('mitarbeitende');
        $this->get('/personen')->assertInertia(fn (Assert $page) => $page->component('people/index')->has('people', 7));

        $this->signInAs('admin');
        $this->get('/personen')->assertInertia(fn (Assert $page) => $page->has('people', 8));
    }

    public function test_only_admin_manages_people(): void
    {
        $this->signInAs('redaktion');

        $this->get('/personen/neu')->assertForbidden();
        $this->get('/datenstrukturen')->assertForbidden();
    }

    public function test_admin_creates_person_with_normalized_unique_email(): void
    {
        $this->signInAs('admin');

        $this->post('/personen', [
            'name' => 'Ina Neu', 'email' => ' Ina.Neu@SekBuelach.example ', 'role' => 'mitarbeitende',
            'school' => 'hinterbirch', 'active' => true,
        ])->assertRedirect('/personen');

        $this->assertNotNull(app(UserRepository::class)->findByEmail('ina.neu@sekbuelach.example'));

        $this->post('/personen', [
            'name' => 'Doppelt', 'email' => 'ina.neu@sekbuelach.example', 'role' => 'mitarbeitende', 'school' => 'gesamt',
        ])->assertSessionHasErrors(['email' => 'Diese E-Mail-Adresse ist bereits erfasst.']);
    }

    public function test_admin_cannot_remove_own_admin_role_or_delete_self(): void
    {
        $admin = $this->signInAs('admin');

        $this->put("/personen/{$admin->id()}", [
            'name' => $admin->name(), 'email' => $admin->email(), 'role' => 'mitarbeitende', 'school' => 'verwaltung', 'active' => true,
        ])->assertSessionHasErrors('role');

        $this->delete("/personen/{$admin->id()}")->assertStatus(422);
        $this->assertNotNull(app(UserRepository::class)->find($admin->id()));
    }

    public function test_deactivated_user_loses_session(): void
    {
        $staff = $this->signInAs('mitarbeitende');
        app(UserRepository::class)->update($staff->id(), ['active' => false]);

        // Neuer Request ohne actingAs: Session-ID wird über den Provider aufgelöst
        $this->app['auth']->forgetGuards();
        $this->withSession(['login_web_'.sha1(SessionGuard::class) => $staff->id()])
            ->get('/start')->assertRedirect('/anmelden');
    }
}
