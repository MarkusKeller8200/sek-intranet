<?php

namespace Tests\Feature;

use App\Modules\ModuleArchitecture;
use App\Modules\ModuleGenerator;
use App\Modules\ModuleManifest;
use App\Modules\ModuleRegistry;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Modulgerüst (docs/KONZEPT-MODULE.md, #7, #10–#14). In den Tests liegt das Beispielmodul unter
 * tests/Fixtures/modules (INTRANET_MODULES_PATH), erzeugt mit intranet:make-module.
 */
class ModulesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['inertia.testing.ensure_pages_exist' => false]);
    }

    private function registry(): ModuleRegistry
    {
        return app(ModuleRegistry::class);
    }

    public function test_example_module_is_found_valid_and_active(): void
    {
        $this->assertSame([], $this->registry()->errors());
        $manifest = $this->registry()->find('beispiel');
        $this->assertNotNull($manifest);
        $this->assertSame('Beispiel', $manifest->folder);
        $this->assertSame(['beispiel_eintraege'], array_keys($manifest->collections));
        $this->assertTrue($this->registry()->enabled('beispiel'));
        // Recht des Moduls ist im Rollenkonzept angekommen
        $this->assertArrayHasKey('use-beispiel', (array) config('intranet.abilities'));
    }

    public function test_menu_and_rights_come_from_the_manifest(): void
    {
        $this->signInAs('admin');
        $this->get('/start')->assertInertia(fn (Assert $page) => $page->where('navigation', function ($groups): bool {
            $items = collect($groups)->firstWhere('key', 'organisation')['items'] ?? [];

            return collect($items)->contains(fn ($i): bool => ($i['href'] ?? null) === '/beispiel');
        }));

        $this->signInAs('lehrperson');
        $this->get('/start')->assertInertia(fn (Assert $page) => $page->where('navigation', fn ($groups): bool => ! str_contains((string) json_encode($groups), '/beispiel')));
        $this->get('/beispiel')->assertForbidden();
    }

    public function test_module_page_and_data_live_in_its_own_store(): void
    {
        $this->signInAs('admin');
        $this->get('/beispiel')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Beispiel::start')->has('eintraege', 0));
        $this->post('/beispiel', ['titel' => 'Erster Eintrag'])->assertRedirect();

        $core = base_path((string) config('intranet.data_path'));
        $this->assertFileExists($core.'/modules/beispiel/beispiel_eintraege.json');
        $this->assertFileDoesNotExist($core.'/beispiel_eintraege.json');

        $this->get('/datenstrukturen')->assertInertia(fn (Assert $page) => $page->where('collections', function ($collections): bool {
            $c = collect($collections)->firstWhere('name', 'beispiel_eintraege');

            return $c !== null && $c['module'] === 'Beispielmodul' && $c['count'] === 1 && str_contains((string) $c['file'], 'modules/beispiel/');
        }));
    }

    public function test_switching_a_module_off_removes_menu_and_routes(): void
    {
        $this->signInAs('admin');
        $this->post('/einstellungen/module/beispiel', ['enabled' => false])->assertRedirect();
        $this->assertFalse($this->registry()->enabled('beispiel'));

        try {
            $this->refreshApplication();
            $this->withoutVite();
            config(['inertia.testing.ensure_pages_exist' => false]);
            $this->signInAs('admin');
            $this->get('/beispiel')->assertNotFound();
            $this->get('/start')->assertInertia(fn (Assert $page) => $page->where('navigation', fn ($groups): bool => ! str_contains((string) json_encode($groups), '/beispiel')));
            $this->assertArrayNotHasKey('use-beispiel', (array) config('intranet.abilities'));
            $this->get('/einstellungen/module')->assertInertia(fn (Assert $page) => $page
                ->component('module-management')
                ->where('modules', fn ($mods): bool => collect($mods)->firstWhere('key', 'beispiel')['enabled'] === false));
        } finally {
            app(ModuleRegistry::class)->setEnabled('beispiel', true, null);
        }
    }

    public function test_management_page_only_for_admins(): void
    {
        $this->signInAs('schulleitung');
        $this->get('/einstellungen/module')->assertForbidden();

        $this->signInAs('admin');
        $this->get('/einstellungen/module')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('modules', function ($mods): bool {
                $m = collect($mods)->firstWhere('key', 'beispiel');

                return $m['name'] === 'Beispielmodul'
                    && $m['rights']['use-beispiel']['roles'] === ['admin']
                    && str_contains((string) $m['sql'], 'CREATE SCHEMA IF NOT EXISTS beispiel');
            })
            ->where('errors', [])
            ->where('violations', []));
    }

    public function test_manifest_validation(): void
    {
        $valid = json_decode((string) file_get_contents(base_path('tests/Fixtures/modules/Beispiel/module.json')), true);
        // Rechte des Core ohne die bereits übernommenen Modulrechte
        $abilities = array_filter((array) config('intranet.abilities'), fn ($a): bool => ! isset($a['module']));
        $groups = array_keys((array) config('intranet.modules'));
        $cases = [
            'key' => ['key' => 'Falsch Key'],
            'version' => ['version' => '1.0'],
            'core' => ['core' => '>=99.0.0'],
            'datenklasse' => ['datenklasse' => 'geheim'],
            'Recht' => ['rechte' => ['manage-users' => ['label' => 'x', 'roles' => ['admin']]]],
            'Menü-Gruppe' => ['menu' => ['gruppe' => 'gibtsnicht', 'eintraege' => []]],
            'href' => ['menu' => ['gruppe' => 'organisation', 'eintraege' => ['x' => ['title' => 'X', 'href' => '/anderes']]]],
            'Sammlung' => ['sammlungen' => ['users' => ['fields' => []]]],
            'provider' => ['provider' => 'App\\Evil'],
        ];
        foreach ($cases as $expected => $override) {
            try {
                ModuleManifest::fromArray(array_merge($valid, $override), '/x/Beispiel', $abilities, $groups, '0.32.0');
                $this->fail("Ungültiges Manifest ({$expected}) wurde akzeptiert");
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString($expected === 'core' ? 'Benötigt' : $expected, $e->getMessage());
            }
        }
        $this->assertSame('beispiel', ModuleManifest::fromArray($valid, '/x/Beispiel', $abilities, $groups, '0.32.0')->key);
    }

    public function test_architecture_check_finds_cross_module_access(): void
    {
        $this->assertSame([], ModuleArchitecture::violations(base_path('tests/Fixtures/modules')));
        $this->assertSame([], ModuleArchitecture::violations(base_path('modules')));

        $dir = storage_path('framework/testing/arch-'.uniqid());
        File::ensureDirectoryExists($dir.'/Eins/resources/js/pages');
        File::ensureDirectoryExists($dir.'/Zwei');
        file_put_contents($dir.'/Eins/Service.php', "<?php\nuse Modules\\Zwei\\Data\\Geheim;\nuse App\\Data\\Json\\JsonUserRepository;\n\$s = app(JsonStore::class);\n");
        file_put_contents($dir.'/Eins/resources/js/pages/start.tsx', "import x from '../../../../../modules/Zwei/resources/js/pages/a';\nimport y from '../../../../Zwei/x';\n");
        try {
            $violations = implode("\n", ModuleArchitecture::violations($dir));
            $this->assertStringContainsString('Eins/Service.php:2 – verwendet Modul «Zwei»', $violations);
            $this->assertStringContainsString('Eins/Service.php:3 – JSON-Umsetzung des Core', $violations);
            $this->assertStringContainsString('Eins/Service.php:4 – Datenspeicher des Core', $violations);
            $this->assertStringContainsString('start.tsx:1 – importiert aus Modul «Zwei»', $violations);
            $this->assertStringContainsString('start.tsx:2 – relativer Import ausserhalb des Moduls', $violations);
        } finally {
            File::deleteDirectory($dir);
        }
    }

    public function test_generator_creates_a_valid_module(): void
    {
        $dir = storage_path('framework/testing/gen-'.uniqid());
        File::ensureDirectoryExists($dir);
        try {
            $files = ModuleGenerator::generate($dir, 'Zeiterfassung', 'Zeiterfassung', 'organisation', '0.32.0');
            $this->assertContains('module.json', $files);
            $manifest = ModuleManifest::fromArray(
                (array) json_decode((string) file_get_contents($dir.'/Zeiterfassung/module.json'), true),
                $dir.'/Zeiterfassung', array_filter((array) config('intranet.abilities'), fn ($a): bool => ! isset($a['module'])), array_keys((array) config('intranet.modules')), '0.32.0',
            );
            $this->assertSame('zeiterfassung', $manifest->key);
            $this->assertSame('zeiterfassung', $manifest->dbSchema);
            $this->assertSame([], ModuleArchitecture::violations($dir));
            foreach ($files as $file) {
                if (str_ends_with($file, '.php')) {
                    exec('php -l '.escapeshellarg($dir.'/Zeiterfassung/'.$file).' 2>&1', $out, $code);
                    $this->assertSame(0, $code, implode("\n", $out));
                }
            }
            $this->expectException(InvalidArgumentException::class);
            ModuleGenerator::generate($dir, 'Zeiterfassung', 'Zeiterfassung', 'organisation', '0.32.0');
        } finally {
            File::deleteDirectory($dir);
        }
    }

    public function test_commands(): void
    {
        $this->assertSame(0, Artisan::call('intranet:module-sql', ['key' => 'beispiel']));
        $this->assertStringContainsString('CREATE ROLE intranet_beispiel', Artisan::output());
        $this->assertSame(1, Artisan::call('intranet:make-module', ['name' => 'Neu']));
        $this->assertStringContainsString('--gruppe', Artisan::output());
    }
}
