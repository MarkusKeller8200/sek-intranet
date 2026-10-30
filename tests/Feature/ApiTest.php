<?php

namespace Tests\Feature;

use App\Data\Contracts\UserRepository;
use App\Data\Store\JsonStore;
use App\Support\ApiTokens;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ApiTest extends TestCase
{
    /**
     * @param  list<string>  $abilities
     */
    private function token(string $role, array $abilities = ['read', 'write']): string
    {
        $owner = collect(app(UserRepository::class)->active())->firstWhere('role', $role);

        return app(ApiTokens::class)->create("Test {$role}", $abilities, (string) $owner['id'], null)['token'];
    }

    public function test_openapi_spec_is_public_and_describes_people(): void
    {
        $this->getJson('/api/openapi.json')
            ->assertOk()
            ->assertJsonPath('openapi', '3.1.0')
            ->assertJsonPath('components.schemas.Person.properties.email.format', 'email')
            ->assertJsonPath('components.schemas.Person.properties.emergency_name.description', 'Notfallkontakt (Person) – nur Administration')
            ->assertJsonStructure(['paths' => ['/people', '/people/{id}', '/me', '/events']]);
    }

    public function test_requests_need_a_valid_token(): void
    {
        $this->getJson('/api/v1/people')->assertUnauthorized();
        $this->getJson('/api/v1/people', ['Authorization' => 'Bearer sbi_falsch'])->assertUnauthorized();

        $token = $this->token('admin');
        $this->getJson('/api/v1/me', ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJsonPath('data.admin', true)
            ->assertJsonPath('data.user.email', 'andrea.muster@sekbuelach.example');

        // Token wird nur als Hash gespeichert
        $this->assertStringNotContainsString($token, (string) file_get_contents(app(JsonStore::class)->filePath('api_tokens')));
    }

    public function test_staff_tokens_read_without_admin_fields_and_cannot_write(): void
    {
        $token = $this->token('lehrperson');
        $headers = ['Authorization' => "Bearer {$token}"];

        $response = $this->getJson('/api/v1/people?school=hinterbirch&limit=2', $headers)->assertOk()
            ->assertJsonPath('meta.limit', 2);
        $this->assertArrayNotHasKey('emergency_name', $response->json('data.0'));
        $this->assertArrayNotHasKey('role', $response->json('data.0'));
        $this->assertSame(['hinterbirch'], array_values(array_unique(array_column($response->json('data'), 'school'))));

        $this->postJson('/api/v1/people', ['last_name' => 'X', 'email' => 'x@sekbuelach.example'], $headers)->assertForbidden();
        $this->getJson('/api/v1/events', $headers)->assertOk()->assertJsonStructure(['data', 'meta' => ['total']]);
        $this->getJson('/api/v1/documents', $headers)->assertOk()->assertJsonMissingPath('data.0.file_path');
    }

    public function test_read_only_tokens_cannot_use_write_methods(): void
    {
        $token = $this->token('admin', ['read']);

        $this->postJson('/api/v1/people', ['last_name' => 'X'], ['Authorization' => "Bearer {$token}"])
            ->assertForbidden()
            ->assertJsonPath('message', 'Dieses Token hat kein Recht «write».');
    }

    public function test_admin_tokens_create_update_and_delete_people_with_audit(): void
    {
        $headers = ['Authorization' => 'Bearer '.$this->token('admin')];

        $this->postJson('/api/v1/people', ['first_name' => 'Nora', 'last_name' => 'Beispiel', 'email' => 'bad'], $headers)
            ->assertStatus(422)->assertJsonStructure(['errors' => ['email']]);

        $id = $this->postJson('/api/v1/people', ['name' => 'Nora Api', 'email' => 'Nora.Api@sekbuelach.example', 'school' => 'mettmenriet'], $headers)
            ->assertCreated()
            ->assertJsonPath('data.first_name', 'Nora')
            ->assertJsonPath('data.email', 'nora.api@sekbuelach.example')
            ->assertJsonPath('data.role', 'mitarbeitende')
            ->json('data.id');

        $this->patchJson("/api/v1/people/{$id}", ['function' => 'Klassenlehrperson 2b', 'last_name' => 'Api-Neu'], $headers)
            ->assertOk()
            ->assertJsonPath('data.name', 'Nora Api-Neu')
            ->assertJsonPath('data.school', 'mettmenriet');

        $this->patchJson("/api/v1/people/{$id}", ['school' => 'mond'], $headers)->assertStatus(422);

        $log = collect(app(JsonStore::class)->all('audit_log'))->where('record_id', $id);
        $this->assertSame(['created', 'updated'], $log->pluck('action')->values()->all());
        $this->assertSame('API', $log->last()['source']);

        $this->deleteJson("/api/v1/people/{$id}", [], $headers)->assertNoContent();
        $this->getJson("/api/v1/people/{$id}", $headers)->assertNotFound();
    }

    public function test_admin_manages_tokens_on_the_api_page(): void
    {
        $this->signInAs('lehrperson');
        $this->get('/api-doku')->assertForbidden();

        $this->signInAs('admin');
        $this->get('/api-doku')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('api-docs')
            ->where('spec.info.title', 'SekBülach Intranet API')
            ->has('tokens', 0));

        $created = $this->postJson('/api-doku/tokens', ['name' => 'Jamf-Abgleich', 'abilities' => ['read'], 'days' => 30])->assertCreated()->json();
        $this->assertStringStartsWith('sbi_', $created['token']);
        $this->assertArrayNotHasKey('token_hash', $created['record']);

        $this->getJson('/api/v1/me', ['Authorization' => "Bearer {$created['token']}"])->assertOk();

        $this->get('/api-doku')->assertInertia(fn (Assert $page) => $page
            ->has('tokens', 1)
            ->where('tokens.0.name', 'Jamf-Abgleich')
            ->where('tokens.0.owner_name', 'Andrea Muster')
            ->missing('tokens.0.token_hash'));

        $this->deleteJson("/api-doku/tokens/{$created['record']['id']}")->assertOk();
        $this->getJson('/api/v1/me', ['Authorization' => "Bearer {$created['token']}"])->assertUnauthorized();
    }

    public function test_api_is_not_behind_the_site_password(): void
    {
        config(['intranet.access.password' => 'geheim']);
        $this->get('/start')->assertUnauthorized();

        $token = $this->token('admin');
        $this->getJson('/api/v1/me', ['Authorization' => "Bearer {$token}"])->assertOk();
    }
}
