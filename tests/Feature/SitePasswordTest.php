<?php

namespace Tests\Feature;

use Tests\TestCase;

class SitePasswordTest extends TestCase
{
    public function test_no_password_configured_means_open_site(): void
    {
        $this->get('/anmelden')->assertOk();
    }

    public function test_password_protects_every_page_but_health_check(): void
    {
        config(['intranet.access.password' => 'geheim']);

        $this->get('/anmelden')
            ->assertUnauthorized()
            ->assertHeader('WWW-Authenticate', 'Basic realm="SekBuelach Intranet", charset="UTF-8"');

        $this->withBasicAuth('egal', 'falsch')->get('/anmelden')->assertUnauthorized();
        $this->withBasicAuth('egal', 'geheim')->get('/anmelden')->assertOk();
        $this->get('/up')->assertOk();
    }

    public function test_optional_user_name_is_checked(): void
    {
        config(['intranet.access.password' => 'geheim', 'intranet.access.user' => 'sekbuelach']);

        $this->withBasicAuth('anders', 'geheim')->get('/anmelden')->assertUnauthorized();
        $this->withBasicAuth('sekbuelach', 'geheim')->get('/anmelden')->assertOk();
    }
}
