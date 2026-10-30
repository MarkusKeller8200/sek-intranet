<?php

namespace Tests\Feature;

use App\Data\Contracts\UserRepository;
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
}
