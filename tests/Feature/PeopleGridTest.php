<?php

namespace Tests\Feature;

use App\Data\Contracts\UserRepository;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Personen-Tabelle: direktes Bearbeiten einzelner Felder und Sammeländerungen.
 */
class PeopleGridTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function person(string $email): array
    {
        $person = app(UserRepository::class)->findByEmail($email);
        $this->assertNotNull($person);

        return $person;
    }

    public function test_admin_updates_a_single_field_inline(): void
    {
        $this->signInAs('admin');
        $daniel = $this->person('daniel.probe@sekbuelach.example');

        $this->postJson("/personen/{$daniel['id']}/feld", ['field' => 'function', 'value' => 'Fachlehrperson Mathematik'])
            ->assertOk()
            ->assertJsonPath('person.function', 'Fachlehrperson Mathematik')
            ->assertJsonPath('person.name', $daniel['name']);

        $this->postJson("/personen/{$daniel['id']}/feld", ['field' => 'school', 'value' => 'mettmenriet'])->assertOk();
        $this->postJson("/personen/{$daniel['id']}/feld", ['field' => 'active', 'value' => false])->assertOk();
        $this->postJson("/personen/{$daniel['id']}/feld", ['field' => 'phone', 'value' => ''])->assertOk()->assertJsonPath('person.phone', null);

        $saved = $this->person('daniel.probe@sekbuelach.example');
        $this->assertSame('mettmenriet', $saved['school']);
        $this->assertFalse($saved['active']);
        $this->assertSame($daniel['email'], $saved['email']);
    }

    public function test_inline_updates_are_validated(): void
    {
        $this->signInAs('admin');
        $daniel = $this->person('daniel.probe@sekbuelach.example');
        $other = collect(app(UserRepository::class)->all())->firstWhere('id', '!=', $daniel['id']);

        $this->postJson("/personen/{$daniel['id']}/feld", ['field' => 'school', 'value' => 'mond'])->assertStatus(422);
        $this->postJson("/personen/{$daniel['id']}/feld", ['field' => 'email', 'value' => 'keine-mail'])->assertStatus(422);
        $this->postJson("/personen/{$daniel['id']}/feld", ['field' => 'name', 'value' => ''])->assertStatus(422);
        $this->postJson("/personen/{$daniel['id']}/feld", ['field' => 'email', 'value' => $other['email']])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Diese E-Mail-Adresse ist bereits erfasst.');
        $this->postJson("/personen/{$daniel['id']}/feld", ['field' => 'id', 'value' => 'x'])->assertStatus(422);

        $this->postJson("/personen/{$daniel['id']}/feld", ['field' => 'email', 'value' => ' Daniel.Neu@SekBuelach.example '])
            ->assertOk()
            ->assertJsonPath('person.email', 'daniel.neu@sekbuelach.example');
    }

    public function test_admin_cannot_remove_own_admin_role_inline(): void
    {
        $admin = $this->signInAs('admin');

        $this->postJson("/personen/{$admin->getAuthIdentifier()}/feld", ['field' => 'role', 'value' => 'mitarbeitende'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Die eigene Administrationsrolle kann nicht entfernt werden.');
        $this->postJson("/personen/{$admin->getAuthIdentifier()}/feld", ['field' => 'active', 'value' => false])->assertStatus(422);
    }

    public function test_bulk_update_skips_own_account(): void
    {
        $admin = $this->signInAs('admin');
        $ids = array_column(app(UserRepository::class)->all(), 'id');

        $this->postJson('/personen/sammelaenderung', ['ids' => $ids, 'field' => 'active', 'value' => false])
            ->assertOk()
            ->assertJsonPath('message', (count($ids) - 1).' Personen geändert, 1 übersprungen (eigenes Konto oder nicht gefunden).');

        foreach (app(UserRepository::class)->all() as $person) {
            $this->assertSame($person['id'] === $admin->getAuthIdentifier(), $person['active']);
        }

        $this->postJson('/personen/sammelaenderung', ['ids' => $ids, 'field' => 'school', 'value' => 'mond'])->assertStatus(422);
        $this->postJson('/personen/sammelaenderung', ['ids' => $ids, 'field' => 'email', 'value' => 'a@b.ch'])->assertStatus(422);
        $this->postJson('/personen/sammelaenderung', ['ids' => [], 'field' => 'school', 'value' => 'gesamt'])->assertStatus(422);
    }

    public function test_staff_can_neither_edit_inline_nor_bulk(): void
    {
        $this->signInAs('mitarbeitende');
        $daniel = $this->person('daniel.probe@sekbuelach.example');

        $this->postJson("/personen/{$daniel['id']}/feld", ['field' => 'function', 'value' => 'x'])->assertForbidden();
        $this->postJson('/personen/sammelaenderung', ['ids' => [$daniel['id']], 'field' => 'active', 'value' => false])->assertForbidden();
    }

    public function test_names_are_composed_and_split(): void
    {
        $this->signInAs('admin');
        $daniel = $this->person('daniel.probe@sekbuelach.example');
        $this->assertSame('Daniel', $daniel['first_name']);
        $this->assertSame('Probe', $daniel['last_name']);

        $this->postJson("/personen/{$daniel['id']}/feld", ['field' => 'last_name', 'value' => 'Muster-Probe'])
            ->assertOk()
            ->assertJsonPath('person.name', 'Daniel Muster-Probe')
            ->assertJsonPath('person.first_name', 'Daniel');

        $this->postJson("/personen/{$daniel['id']}/feld", ['field' => 'name', 'value' => 'X'])->assertStatus(422);
        $this->postJson("/personen/{$daniel['id']}/feld", ['field' => 'last_name', 'value' => ''])->assertStatus(422);

        // Formular mit nur «name» (ältere Clients, Tests): wird aufgeteilt
        $this->post('/personen', ['name' => 'Ida Maria Neu', 'email' => 'ida.neu@sekbuelach.example', 'role' => 'mitarbeitende', 'school' => 'gesamt', 'active' => true])
            ->assertRedirect('/personen');
        $ida = $this->person('ida.neu@sekbuelach.example');
        $this->assertSame(['Ida Maria', 'Neu', 'Ida Maria Neu'], [$ida['first_name'], $ida['last_name'], $ida['name']]);
    }

    public function test_extra_fields_are_validated_and_admin_only_fields_hidden_from_staff(): void
    {
        $this->signInAs('admin');
        $daniel = $this->person('daniel.probe@sekbuelach.example');

        $this->postJson("/personen/{$daniel['id']}/feld", ['field' => 'entry_date', 'value' => '31.12.2020'])->assertStatus(422);
        $this->postJson("/personen/{$daniel['id']}/feld", ['field' => 'entry_date', 'value' => '2020-12-31'])->assertOk();
        $this->postJson("/personen/{$daniel['id']}/feld", ['field' => 'private_email', 'value' => 'kein-mail'])->assertStatus(422);
        $this->postJson("/personen/{$daniel['id']}/feld", ['field' => 'notes', 'value' => 'Nur für die Administration'])->assertOk();

        $this->get("/personen/{$daniel['id']}")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('people/show')
            ->where('person.notes', 'Nur für die Administration')
            ->where('groups', fn ($groups): bool => collect($groups)->pluck('key')->all() === ['person', 'organisation', 'contact', 'private', 'system']));

        $this->signInAs('mitarbeitende');
        foreach (["/personen/{$daniel['id']}", '/personen'] as $url) {
            $content = (string) $this->get($url)->assertOk()->getContent();
            foreach (['Nur für die Administration', 'Erfunden Person', 'Testdorf', '079 000 99 99'] as $secret) {
                $this->assertStringNotContainsString($secret, $content, "{$url} zeigt Admin-Feld");
            }
        }
        $this->get("/personen/{$daniel['id']}")->assertInertia(fn (Assert $page) => $page
            ->where('groups', fn ($groups): bool => collect($groups)->pluck('key')->all() === ['person', 'organisation', 'contact'])
            ->where('person.entry_date', '2020-12-31'));

        $inactive = collect(app(UserRepository::class)->all())->firstWhere('active', false);
        $this->get("/personen/{$inactive['id']}")->assertNotFound();
    }

    public function test_bulk_delete_skips_own_account(): void
    {
        $admin = $this->signInAs('admin');
        $ids = array_column(app(UserRepository::class)->all(), 'id');

        $others = array_values(array_diff($ids, [$admin->getAuthIdentifier()]));
        $this->postJson('/personen/sammelloeschung', ['ids' => array_slice($others, 0, 3)])
            ->assertOk()->assertJsonPath('message', '3 Personen gelöscht.');
        $this->postJson('/personen/sammelloeschung', ['ids' => $ids])->assertOk()
            ->assertJsonPath('message', (count($ids) - 4).' Personen gelöscht, 4 übersprungen (eigenes Konto oder nicht gefunden).');

        $this->assertSame([$admin->getAuthIdentifier()], array_column(app(UserRepository::class)->all(), 'id'));

        $this->signInAs('admin');
        $this->postJson('/personen/sammelloeschung', ['ids' => []])->assertStatus(422);
    }
}
