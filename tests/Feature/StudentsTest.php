<?php

namespace Tests\Feature;

use App\Data\Contracts\UserRepository;
use App\Data\Store\JsonStore;
use App\Plugins\Support\PeopleImport;
use App\Support\ApiTokens;
use App\Support\PersonLinks;
use Illuminate\Support\Facades\Artisan;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Personenart, Seite «Schüler:innen», Datenherkunft, Laden in der Geräteausgabe. */
class StudentsTest extends TestCase
{
    private function person(string $email): array
    {
        $person = app(UserRepository::class)->findByEmail($email);
        $this->assertNotNull($person, $email);

        return $person;
    }

    public function test_students_page_shows_only_pupils(): void
    {
        $this->signInAs('lehrperson');

        $this->get('/schueler')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('people/index')
            ->where('scope.type', 'schueler')
            ->has('people', 12)
            ->where('people', fn ($people): bool => collect($people)->every(fn ($p): bool => $p['person_type'] === 'schueler')));
    }

    public function test_pupils_and_parents_are_hidden_without_right(): void
    {
        $this->signInAs('redaktion');
        $this->get('/schueler')->assertForbidden();
        $this->get('/personen/'.$this->person('lina.aebi@edu.sekbuelach.example')['id'])->assertNotFound();
        $this->get('/start')->assertInertia(fn (Assert $page) => $page
            ->where('navigation', fn ($groups): bool => ! collect($groups)->flatMap(fn ($g) => $g['items'])->contains('key', 'schueler')));

        $owner = collect(app(UserRepository::class)->active())->firstWhere('role', 'redaktion');
        $token = app(ApiTokens::class)->create('Test', ['read'], (string) $owner['id'], null)['token'];
        $this->getJson('/api/v1/people?person_type=schueler', ['Authorization' => "Bearer {$token}"])
            ->assertOk()->assertJsonPath('meta.total', 0);
    }

    public function test_pupils_and_parents_cannot_sign_in(): void
    {
        $this->get('/anmelden')->assertInertia(fn (Assert $page) => $page
            ->where('users', fn ($users): bool => ! collect($users)->contains('name', 'Lina Aebi') && collect($users)->contains('name', 'Daniel Probe')));

        $this->post('/anmelden', ['user_id' => $this->person('lina.aebi@edu.sekbuelach.example')['id']])
            ->assertSessionHasErrors('user_id');
        $this->assertGuest();
    }

    public function test_parent_without_email_and_new_pupil_preset(): void
    {
        $this->signInAs('admin');
        $this->get('/personen/neu?art=schueler')->assertInertia(fn (Assert $page) => $page
            ->where('defaults', ['person_type' => 'schueler', 'role' => 'kein-zugang']));

        $this->post('/personen', [
            'first_name' => 'Ida', 'last_name' => 'Ohnemail', 'person_type' => 'eltern', 'role' => 'kein-zugang',
            'school' => 'gesamt', 'active' => true,
        ])->assertSessionHasNoErrors();
        $this->post('/personen', [
            'first_name' => 'Ivo', 'last_name' => 'Ohnemail', 'person_type' => 'eltern', 'role' => 'kein-zugang',
            'school' => 'gesamt', 'active' => true,
        ])->assertSessionHasNoErrors();
    }

    public function test_provenance_is_shown_and_pending_after_change(): void
    {
        $this->signInAs('admin');
        $daniel = $this->person('daniel.probe@sekbuelach.example');

        $this->get("/personen/{$daniel['id']}")->assertInertia(fn (Assert $page) => $page
            ->has('links', 3)
            ->where('links.0.direction', 'source')
            ->where('links.0.external_id', 'T-014')
            ->where('links', fn ($links): bool => collect($links)->where('direction', 'target')->every(fn ($l): bool => $l['status'] === 'pending'))
            ->where('canManageLinks', true)
            ->has('systems.escola'));

        // Als abgeglichen markieren → aktuell; danach ändern → wieder fällig
        $entra = collect(app(PersonLinks::class)->forPerson($daniel['id'], $daniel))->firstWhere('system', 'entra');
        $this->post("/personen/{$daniel['id']}/herkunft/{$entra['id']}/abgeglichen")->assertRedirect();
        $fresh = $this->person('daniel.probe@sekbuelach.example');
        $this->assertSame('current', collect(app(PersonLinks::class)->forPerson($daniel['id'], $fresh))->firstWhere('system', 'entra')['status']);

        // Abgleich liegt eine Stunde zurück, dann wird die Person geändert
        app(JsonStore::class)->update(PersonLinks::COLLECTION, $entra['id'], ['last_sync_at' => now()->subHour()->toAtomString()]);
        $this->postJson("/personen/{$daniel['id']}/feld", ['field' => 'phone', 'value' => '044 000 00 99'])->assertOk();
        $fresh = $this->person('daniel.probe@sekbuelach.example');
        $this->assertSame('pending', collect(app(PersonLinks::class)->forPerson($daniel['id'], $fresh))->firstWhere('system', 'entra')['status']);

        $this->get('/personen')->assertInertia(fn (Assert $page) => $page
            ->where('people', fn ($people): bool => collect($people)->firstWhere('id', $daniel['id'])['sync_pending'] === 2));
    }

    public function test_provenance_rules_prevent_duplicates(): void
    {
        $this->signInAs('admin');
        $eva = $this->person('eva.fiktiv@sekbuelach.example');

        // Escola-ID T-014 gehört schon Daniel Probe
        $this->post("/personen/{$eva['id']}/herkunft", ['direction' => 'source', 'system' => 'escola', 'external_id' => 'T-014'])
            ->assertSessionHasErrors('external_id');
        // Quelle ohne ID nicht erlaubt
        $this->post("/personen/{$eva['id']}/herkunft", ['direction' => 'source', 'system' => 'escola'])
            ->assertSessionHasErrors('external_id');
        // Ziel ohne ID ist erlaubt (wird beim ersten Export gesetzt)
        $this->post("/personen/{$eva['id']}/herkunft", ['direction' => 'target', 'system' => 'jamf'])->assertSessionHasNoErrors();
        // Pro System und Richtung nur ein Eintrag
        $this->post("/personen/{$eva['id']}/herkunft", ['direction' => 'target', 'system' => 'jamf', 'external_id' => '99'])
            ->assertSessionHasErrors('system');

        $this->signInAs('lehrperson');
        $this->post("/personen/{$eva['id']}/herkunft", ['direction' => 'target', 'system' => 'ida'])->assertForbidden();
    }

    public function test_import_finds_person_by_source_id_even_with_new_email(): void
    {
        $this->signInAs('admin');
        $users = app(UserRepository::class);
        $before = count($users->all());

        $import = new PeopleImport($users, 'Import Escola', 'escola');
        $rows = $import->plan([[
            'first_name' => 'Daniel', 'last_name' => 'Probe', 'email' => 'd.probe.neu@sekbuelach.example', '_external_id' => 'T-014',
        ]], 'Escola');
        $row = collect($rows)->firstWhere('_key', 'd.probe.neu@sekbuelach.example');
        $this->assertSame('ändern', $row['_change'], 'Gleiche Escola-ID = gleiche Person, keine neue');
        $import->apply($rows, [$row['_key']]);

        $this->assertCount($before, $users->all());
        $this->assertNotNull($users->findByEmail('d.probe.neu@sekbuelach.example'));
    }

    public function test_device_handout_loads_only_selected_classes(): void
    {
        $this->signInAs('ict');

        $this->get('/geraeteausgabe')->assertInertia(fn (Assert $page) => $page
            ->where('classes', fn ($classes): bool => collect($classes)->pluck('name')->all() === ['H_A1a', 'H_A2a', 'M_A1a', 'M_B1b', 'M_C3a']
                && collect($classes)->firstWhere('name', 'M_A1a')['count'] === 3
                && collect($classes)->firstWhere('name', 'M_A1a')['year'] === 1
                && collect($classes)->firstWhere('name', 'M_A1a')['teacher'] === 'Eva Fiktiv'));

        $response = $this->getJson('/geraeteausgabe/schueler?klassen[]=M_A1a&klassen[]=H_A1a')->assertOk();
        $data = collect($response->json('data'));
        $this->assertCount(5, $data);
        $this->assertSame(['H_A1a', 'M_A1a'], $data->pluck('klasse')->unique()->values()->all());
        $this->assertSame('lina.aebi@edu.sekbuelach.example', $data->firstWhere('vorname', 'Lina')['benutzer']);
        $this->assertSame('Eva Fiktiv', $data->firstWhere('vorname', 'Lina')['lehrperson']);

        $this->getJson('/geraeteausgabe/schueler')->assertStatus(422);

        $this->signInAs('lehrperson');
        $this->getJson('/geraeteausgabe/schueler?klassen[]=M_A1a')->assertForbidden();
    }

    public function test_upgrade_adds_person_type_and_demo_pupils_to_existing_data(): void
    {
        $store = app(JsonStore::class);
        $users = array_values(array_filter($store->all('users'), fn (array $u): bool => ! in_array($u['person_type'] ?? null, ['schueler', 'eltern'], true)));
        $store->replace('users', array_map(function (array $u): array {
            unset($u['person_type']);

            return $u;
        }, $users));
        $store->replace(PersonLinks::COLLECTION, []);

        Artisan::call('intranet:upgrade');

        $all = collect($store->all('users'));
        $this->assertSame('lehrperson', $all->firstWhere('email', 'daniel.probe@sekbuelach.example')['person_type']);
        $this->assertCount(12, $all->where('person_type', 'schueler'));
        $this->assertNotEmpty($store->all(PersonLinks::COLLECTION));

        // Idempotent
        Artisan::call('intranet:upgrade');
        $this->assertCount(12, collect($store->all('users'))->where('person_type', 'schueler'));
    }
}
