<?php

namespace App\Modules;

use InvalidArgumentException;

/**
 * Legt ein neues Modul als Gerüst an (php artisan intranet:make-module): module.json, ServiceProvider,
 * Repository (Schnittstelle + JSON), Controller, Routen, Seite, Übersetzungen, Test, CHANGELOG und README.
 * Das Gerüst besteht composer ci:check ohne Änderung und dient als Muster für eigene Funktionen.
 */
final class ModuleGenerator
{
    /**
     * @return list<string> angelegte Dateien (relativ zum Modulordner)
     */
    public static function generate(string $modulesPath, string $folder, string $title, string $group, string $coreVersion, ?string $key = null, ?string $schema = null): array
    {
        if (preg_match('/^[A-Z][A-Za-z0-9]{1,40}$/', $folder) !== 1) {
            throw new InvalidArgumentException('Name muss mit einem Grossbuchstaben beginnen und nur Buchstaben/Ziffern enthalten (z. B. Zeiterfassung).');
        }
        $key ??= strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $folder) ?? $folder);
        $schema ??= substr(preg_replace('/[^a-z0-9]/', '', $key) ?? $key, 0, 20);
        $dir = rtrim($modulesPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$folder;
        if (is_dir($dir)) {
            throw new InvalidArgumentException("Modul «{$folder}» gibt es schon.");
        }
        $ability = 'use-'.$key;
        $collection = $schema.'_eintraege';
        $class = $folder;

        $manifest = [
            'key' => $key,
            'name' => $title,
            'version' => '0.1.0',
            'core' => '>='.$coreVersion,
            'description' => "{$title} – Modul im SekBülach Intranet.",
            'aktiv' => true,
            'datenklasse' => 'intern',
            'db_schema' => $schema,
            'provider' => "Modules\\{$folder}\\ModuleServiceProvider",
            'rechte' => [$ability => ['label' => "{$title} verwenden", 'roles' => ['admin']]],
            'menu' => ['gruppe' => $group, 'eintraege' => ['start' => ['title' => $title, 'icon' => 'Boxes', 'href' => "/{$key}", 'can' => $ability]]],
            'sammlungen' => [$collection => [
                'label' => "{$title}: Einträge",
                'description' => 'Beispiel-Sammlung des Gerüsts – an das Fachthema anpassen.',
                'fields' => [
                    'titel' => ['type' => 'string', 'required' => true, 'label' => 'Titel'],
                    'notiz' => ['type' => 'text', 'required' => false, 'label' => 'Notiz'],
                ],
            ]],
            'ereignisse' => ['sendet' => [], 'empfaengt' => []],
            'plugins' => [],
        ];

        $files = [
            'module.json' => json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n",
            'ModuleServiceProvider.php' => <<<PHP
<?php

namespace Modules\\{$folder};

use App\\Modules\\ModuleProvider;
use Modules\\{$folder}\\Data\\Contracts\\EintragRepository;
use Modules\\{$folder}\\Data\\Json\\JsonEintragRepository;
use RuntimeException;

/**
 * {$title}: bindet die Repositories an den eigenen Datenspeicher des Moduls.
 */
class ModuleServiceProvider extends ModuleProvider
{
    protected string \$module = '{$key}';

    public function register(): void
    {
        \$this->app->singleton(EintragRepository::class, function (): EintragRepository {
            return match (\$this->storeType()) {
                'json' => new JsonEintragRepository(\$this->store()),
                default => throw new RuntimeException('{$title}: Speicher «'.\$this->storeType().'» ist noch nicht umgesetzt.'),
            };
        });
    }
}

PHP,
            'Data/Contracts/EintragRepository.php' => <<<PHP
<?php

namespace Modules\\{$folder}\\Data\\Contracts;

use App\\Data\\Contracts\\Repository;

/**
 * Einträge des Moduls {$title} (Sammlung {$collection}).
 */
interface EintragRepository extends Repository
{
    /**
     * @return list<array<string, mixed>>
     */
    public function neueste(int \$limit = 20): array;
}

PHP,
            'Data/Json/JsonEintragRepository.php' => <<<PHP
<?php

namespace Modules\\{$folder}\\Data\\Json;

use App\\Data\\Json\\JsonRepository;
use Modules\\{$folder}\\Data\\Contracts\\EintragRepository;

class JsonEintragRepository extends JsonRepository implements EintragRepository
{
    protected string \$collection = '{$collection}';

    public function neueste(int \$limit = 20): array
    {
        return array_slice(\$this->sortBy(\$this->all(), 'created_at', true), 0, \$limit);
    }
}

PHP,
            "Http/{$class}Controller.php" => <<<PHP
<?php

namespace Modules\\{$folder}\\Http;

use App\\Http\\Controllers\\Controller;
use Illuminate\\Http\\RedirectResponse;
use Illuminate\\Http\\Request;
use Inertia\\Inertia;
use Inertia\\Response;
use Modules\\{$folder}\\Data\\Contracts\\EintragRepository;

class {$class}Controller extends Controller
{
    public function __construct(private readonly EintragRepository \$eintraege) {}

    public function index(): Response
    {
        return Inertia::render('{$folder}::start', [
            'eintraege' => \$this->eintraege->neueste(),
        ]);
    }

    public function store(Request \$request): RedirectResponse
    {
        \$data = \$request->validate([
            'titel' => ['required', 'string', 'max:200'],
            'notiz' => ['nullable', 'string', 'max:2000'],
        ], ['titel.required' => 'Bitte einen Titel eingeben.']);

        \$this->eintraege->create(\$data);
        \$this->toast('Eintrag gespeichert.');

        return back();
    }
}

PHP,
            'routes.php' => <<<PHP
<?php

use Illuminate\\Support\\Facades\\Route;
use Modules\\{$folder}\\Http\\{$class}Controller;

// Routen des Moduls {$title} (geladen nur, wenn das Modul eingeschaltet ist; angemeldet)
Route::middleware('can:{$ability}')->group(function () {
    Route::get('{$key}', [{$class}Controller::class, 'index'])->name('{$key}.index');
    Route::post('{$key}', [{$class}Controller::class, 'store'])->name('{$key}.store');
});

PHP,
            'resources/js/pages/start.tsx' => <<<TSX
import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import FormField from '@/components/form-field';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { formatDateTime } from '@/lib/format';

type Eintrag = {
    id: string;
    titel: string;
    notiz?: string | null;
    created_at: string;
};

/** {$title}: Startseite des Moduls (Gerüst – an das Fachthema anpassen). */
export default function Start({ eintraege }: { eintraege: Eintrag[] }) {
    const form = useForm({ titel: '', notiz: '' });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/{$key}', { preserveScroll: true, onSuccess: () => form.reset() });
    };

    return (
        <>
            <Head title="{$title}" />
            <div className="flex max-w-4xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader title="{$title}" description="Modul {$title}" />
                <Card>
                    <CardContent>
                        <form onSubmit={submit} className="flex flex-wrap items-end gap-3">
                            <FormField id="{$key}-titel" label="Titel *" error={form.errors.titel}>
                                <Input
                                    id="{$key}-titel"
                                    value={form.data.titel}
                                    onChange={(e) => form.setData('titel', e.target.value)}
                                />
                            </FormField>
                            <Button type="submit" disabled={form.processing}>
                                Speichern
                            </Button>
                        </form>
                    </CardContent>
                </Card>
                {eintraege.length === 0 ? (
                    <p className="text-sm text-muted-foreground">Noch keine Einträge.</p>
                ) : (
                    <ul className="grid gap-2">
                        {eintraege.map((e) => (
                            <li key={e.id} className="rounded-md border p-3 text-sm">
                                <span className="font-medium" translate="no">
                                    {e.titel}
                                </span>
                                <span className="ml-2 text-xs text-muted-foreground">
                                    {formatDateTime(e.created_at)}
                                </span>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

Start.layout = {
    breadcrumbs: [{ title: '{$title}', href: '/{$key}' }],
};

TSX,
            "resources/js/i18n/{$key}.json" => json_encode([
                $title => [$title, $title],
                "Modul {$title}" => ["Module {$title}", "Modul {$title}"],
                'Titel *' => ['Title *', 'Titel *'],
                'Speichern' => ['Save', 'Speichere'],
                'Noch keine Einträge.' => ['No entries yet.', 'No kei Iiträg.'],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n",
            "Tests/{$class}Test.php" => <<<PHP
<?php

namespace Modules\\{$folder}\\Tests;

use Tests\\TestCase;

class {$class}Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Modulseiten liegen nicht unter resources/js/pages
        config(['inertia.testing.ensure_pages_exist' => false]);
    }

    public function test_admin_sees_page_and_can_add_entry(): void
    {
        \$this->signInAs('admin');

        \$this->get('/{$key}')->assertOk()->assertInertia(fn (\$page) => \$page->component('{$folder}::start'));
        \$this->post('/{$key}', ['titel' => 'Erster Eintrag'])->assertRedirect();
        \$this->get('/{$key}')->assertInertia(fn (\$page) => \$page->where('eintraege.0.titel', 'Erster Eintrag'));
    }

    public function test_others_have_no_access(): void
    {
        \$this->signInAs('lehrperson');

        \$this->get('/{$key}')->assertForbidden();
    }
}

PHP,
            'CHANGELOG.md' => "# Changelog – Modul {$title}\n\nVersionen des Moduls nach SemVer (module.json → «version»).\n\n## [Unveröffentlicht]\n\n## [0.1.0]\n\n### Neu\n\n- Gerüst angelegt (intranet:make-module).\n",
            'README.md' => "# Modul {$title}\n\nSchlüssel `{$key}`, Datenbank-Schema `{$schema}`, Recht `{$ability}`. Regeln in `docs/KONZEPT-MODULE.md`.\n\n- Seiten: `resources/js/pages` (Inertia-Name `{$folder}::seite`)\n- Daten: eigene Repositories unter `Data/`, Speicher `<data_path>/modules/{$key}`\n- Kein Zugriff auf Klassen oder Daten anderer Module\n",
        ];

        foreach ($files as $path => $content) {
            $file = $dir.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);
            if (! is_dir(dirname($file))) {
                mkdir(dirname($file), 0775, true);
            }
            file_put_contents($file, (string) $content);
        }

        return array_keys($files);
    }
}
