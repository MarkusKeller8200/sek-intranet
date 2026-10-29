<?php

namespace Tests\Feature;

use App\Data\Contracts\UserRepository;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuthTest extends TestCase
{
    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/start')->assertRedirect('/anmelden');
    }

    public function test_login_page_lists_only_active_demo_users(): void
    {
        $this->get('/anmelden')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/login')
                ->where('demoLogin', true)
                ->has('users', 7));
    }

    public function test_demo_user_can_sign_in_and_out(): void
    {
        $user = app(UserRepository::class)->findByEmail('daniel.probe@sekbuelach.example');

        $this->post('/anmelden', ['user_id' => $user['id']])->assertRedirect('/start');
        $this->assertAuthenticated();

        $this->get('/start')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('auth.user.name', 'Daniel Probe')
                ->where('auth.can.manageContent', false)
                ->has('options.schools'));

        $this->post('/abmelden')->assertRedirect('/anmelden');
        $this->assertGuest();
    }

    public function test_inactive_user_cannot_sign_in(): void
    {
        $user = app(UserRepository::class)->findByEmail('hans.exempel@sekbuelach.example');

        $this->post('/anmelden', ['user_id' => $user['id']])->assertSessionHasErrors('user_id');
        $this->assertGuest();
    }

    public function test_demo_login_can_be_disabled(): void
    {
        config(['intranet.demo_login' => false]);
        $user = app(UserRepository::class)->findByEmail('daniel.probe@sekbuelach.example');

        $this->get('/anmelden')->assertInertia(fn (Assert $page) => $page->where('demoLogin', false)->has('users', 0));
        $this->post('/anmelden', ['user_id' => $user['id']])->assertForbidden();
    }
}
