<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DeviceHandoutTest extends TestCase
{
    public function test_device_handout_shows_generator_for_administration(): void
    {
        $this->signInAs('admin');

        $this->get('/geraeteausgabe')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('ict/device-handout')
            ->has('planned.features', 4));
    }

    public function test_device_handout_is_not_for_staff(): void
    {
        $this->signInAs('mitarbeitende');

        $this->get('/geraeteausgabe')->assertForbidden();
    }

    public function test_generator_keeps_the_original_powershell_script_and_logo(): void
    {
        $assets = (string) file_get_contents(resource_path('js/lib/access-codes/assets.ts'));

        $this->assertStringContainsString('Update-MgUser -UserId $upn -PasswordProfile $passwordProfile -PasswordPolicies "None"', $assets);
        $this->assertStringContainsString('viewBox="0 0 2048 410"', $assets);
    }
}
