<?php

namespace Tests\Feature;

use App\Data\Contracts\UserRepository;
use App\Plugins\Support\SpreadsheetReader;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Personen-Tabelle: Zellen gesammelt ändern (Einfügen/Rückgängig), Änderungsverlauf,
 * Wiederherstellen, gespeicherte Ansichten, Export.
 */
class PeopleGridProTest extends TestCase
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

    public function test_cells_are_saved_all_or_nothing(): void
    {
        $this->signInAs('admin');
        $daniel = $this->person('daniel.probe@sekbuelach.example');
        $eva = $this->person('eva.fiktiv@sekbuelach.example');

        $this->postJson('/personen/zellen', ['source' => 'Einfügen', 'changes' => [
            ['id' => $daniel['id'], 'field' => 'function', 'value' => 'Eingefügt 1'],
            ['id' => $eva['id'], 'field' => 'school', 'value' => 'mond'],
        ]])->assertStatus(422)->assertJsonPath('errors.0.field', 'school');
        $this->assertNotSame('Eingefügt 1', $this->person('daniel.probe@sekbuelach.example')['function'], 'nichts gespeichert');

        $this->postJson('/personen/zellen', ['source' => 'Einfügen', 'changes' => [
            ['id' => $daniel['id'], 'field' => 'function', 'value' => 'Eingefügt 1'],
            ['id' => $daniel['id'], 'field' => 'short', 'value' => 'DPR'],
            ['id' => $eva['id'], 'field' => 'school', 'value' => 'hinterbirch'],
        ]])->assertOk()->assertJsonCount(2, 'people');

        $this->assertSame('DPR', $this->person('daniel.probe@sekbuelach.example')['short']);
        $this->assertSame('hinterbirch', $this->person('eva.fiktiv@sekbuelach.example')['school']);
    }

    public function test_changes_are_logged_shown_to_admin_and_can_be_restored(): void
    {
        $this->signInAs('admin');
        $daniel = $this->person('daniel.probe@sekbuelach.example');
        $oldFunction = $daniel['function'];

        $this->postJson('/personen/zellen', ['changes' => [['id' => $daniel['id'], 'field' => 'function', 'value' => 'Neu im Verlauf']]])->assertOk();

        $history = null;
        $this->get("/personen/{$daniel['id']}")->assertInertia(function (Assert $page) use (&$history) {
            $page->has('history', 1)
                ->where('history.0.source', 'Tabelle')
                ->where('history.0.changes.function.new', 'Neu im Verlauf')
                ->where('history.0.user_name', 'Andrea Muster');
            $history = $page->toArray()['props']['history'];
        });

        $this->post("/personen/{$daniel['id']}/verlauf/{$history[0]['id']}/wiederherstellen", ['field' => 'function'])->assertRedirect();
        $this->assertSame($oldFunction, $this->person('daniel.probe@sekbuelach.example')['function']);

        $this->signInAs('mitarbeitende');
        $this->get("/personen/{$daniel['id']}")->assertInertia(fn (Assert $page) => $page->where('history', []));
        $this->post("/personen/{$daniel['id']}/verlauf/{$history[0]['id']}/wiederherstellen", ['field' => 'function'])->assertForbidden();
    }

    public function test_views_are_personal_or_shared_by_admin(): void
    {
        $this->signInAs('mitarbeitende');
        $state = ['filters' => [['column' => 'school', 'op' => 'in', 'values' => ['hinterbirch']]], 'grouping' => 'school'];

        $mine = $this->postJson('/ansichten', ['grid' => 'people', 'name' => 'Meine Hinterbirch', 'state' => $state])->assertOk()->json('view');
        $this->postJson('/ansichten', ['grid' => 'people', 'name' => 'Für alle', 'shared' => true, 'state' => $state])->assertForbidden();
        $this->get('/personen')->assertInertia(fn (Assert $page) => $page->has('views', 1)->where('views.0.name', 'Meine Hinterbirch'));

        $this->signInAs('admin');
        $this->get('/personen')->assertInertia(fn (Assert $page) => $page->has('views', 0));
        $this->deleteJson("/ansichten/{$mine['id']}")->assertForbidden();
        $this->postJson('/ansichten', ['grid' => 'people', 'name' => 'Lehrpersonen', 'shared' => true, 'state' => $state])->assertOk();

        $this->signInAs('mitarbeitende');
        $this->get('/personen')->assertInertia(fn (Assert $page) => $page->has('views', 2)->where('views.0.name', 'Lehrpersonen')->where('views.0.mine', false));
        $this->deleteJson("/ansichten/{$mine['id']}")->assertOk();
    }

    public function test_export_respects_field_visibility_and_formats_values(): void
    {
        $this->signInAs('admin');
        $daniel = $this->person('daniel.probe@sekbuelach.example');

        $csv = $this->post('/personen/export', ['format' => 'csv', 'columns' => ['last_name', 'school', 'entry_date', 'emergency_name', 'active'], 'ids' => [$daniel['id']]])
            ->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8')->getContent() ?: '';
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Nachname;Schulhaus;Eintritt;"Notfallkontakt (Person)";Aktiv', $csv);
        $this->assertStringContainsString('Probe;"Schulhaus Hinterbirch";15.08.2022;"Erfunden Person";Ja', $csv);

        $xlsx = (string) $this->post('/personen/export', ['format' => 'xlsx', 'columns' => ['last_name', 'email']])->assertOk()->getContent();
        $path = tempnam(sys_get_temp_dir(), 'x').'.xlsx';
        file_put_contents($path, $xlsx);
        $table = SpreadsheetReader::read($path, 'a.xlsx', ['Nachname', 'E-Mail']);
        $this->assertSame(['Nachname', 'E-Mail'], $table['header']);
        $this->assertCount(count(app(UserRepository::class)->all()), $table['rows']);

        $this->signInAs('mitarbeitende');
        $csv = $this->post('/personen/export', ['format' => 'csv', 'columns' => ['last_name', 'emergency_name', 'role']])->assertOk()->getContent() ?: '';
        $this->assertStringNotContainsString('Notfall', (string) $csv);
        $this->assertStringNotContainsString('Erfunden Person', (string) $csv);
        $this->assertStringNotContainsString('Exempel', (string) $csv, 'inaktive Personen nicht im Export');
        $this->post('/personen/export', ['format' => 'csv', 'columns' => ['emergency_name']])->assertStatus(422);
    }
}
