<?php

namespace App\Console\Commands;

use App\Plugins\PluginGenerator;
use App\Plugins\PluginManager;
use App\Plugins\PluginManifest;
use App\Support\ApiDocs;
use Illuminate\Console\Command;
use InvalidArgumentException;
use JsonException;

/**
 * Wandelt eine OpenAPI-Beschreibung (Datei oder Aufzeichnung des API-Recorders) in Plugin-Endpunkte um
 * (docs/KONZEPT-API-ZU-PLUGIN.md). Schreibt nur ins Repository (Entwicklung), nie auf dem Server.
 */
class PluginFromOpenApiCommand extends Command
{
    protected $signature = 'intranet:plugin-from-openapi
        {quelle : Pfad zu einer OpenAPI-Datei (JSON) oder ID einer Aufzeichnung im API-Recorder}
        {--plugin= : Bestehendes Plugin (id) um fehlende Endpunkte ergänzen}
        {--neu= : Neues Plugin im Ordner plugins/<Ordner> anlegen (z. B. EscolaWeb)}
        {--host= : Mit --plugin: neue Endpunkte einer weiteren Adresse zuordnen (Schlüssel aus service.hosts)}
        {--dry-run : Nur anzeigen, nichts schreiben}';

    protected $description = 'Ergänzt ein Plugin um Endpunkte aus OpenAPI oder legt ein neues Plugin an (Status partial)';

    public function handle(ApiDocs $docs, PluginManager $plugins): int
    {
        if (app()->isProduction() && ! $this->option('dry-run')) {
            $this->components->error('Nur in der Entwicklung ausführen: Plugins entstehen im Repository (Pull-Request), nicht auf dem Server.');

            return self::FAILURE;
        }

        try {
            [$spec, $doc] = $this->load((string) $this->argument('quelle'), $docs);
            $described = PluginGenerator::describe($spec);
        } catch (InvalidArgumentException|JsonException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $new = $this->option('neu');
        $target = $this->option('plugin') ?? ($new === null ? ($doc['plugin_id'] ?? null) : null);

        if (is_string($new) && $new !== '') {
            return $this->createPlugin($new, $described, $plugins->path());
        }
        if (is_string($target) && $target !== '') {
            return $this->extendPlugin($target, $described, $plugins);
        }

        $this->components->error('Ziel fehlt: --plugin=<id> (ergänzen) oder --neu=<Ordner> (neu anlegen).');

        return self::FAILURE;
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, mixed>|null}
     *
     * @throws JsonException
     */
    private function load(string $source, ApiDocs $docs): array
    {
        if (is_file($source)) {
            if (preg_match('/\.ya?ml$/i', $source) === 1) {
                throw new InvalidArgumentException('YAML wird nicht gelesen – bitte die OpenAPI-Datei als JSON verwenden (API-Recorder: «OpenAPI-Datei (JSON)»).');
            }
            $spec = json_decode((string) file_get_contents($source), true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($spec)) {
                throw new InvalidArgumentException('Datei enthält kein JSON-Objekt.');
            }

            /** @var array<string, mixed> $spec */
            return [$spec, null];
        }

        $doc = $docs->find($source) ?? throw new InvalidArgumentException("Weder Datei noch Aufzeichnung «{$source}» gefunden.");

        return [ApiDocs::toOpenApi($doc), $doc];
    }

    /**
     * @param  array{title: string, description: string, base_url: string, auth: array<string, mixed>, auth_settings: list<array<string, mixed>>, endpoints: list<array<string, mixed>>}  $described
     */
    private function extendPlugin(string $id, array $described, PluginManager $plugins): int
    {
        if (! array_key_exists($id, $plugins->all())) {
            $this->components->error("Plugin «{$id}» ist nicht installiert.");

            return self::FAILURE;
        }
        $file = $plugins->path().'/'.$plugins->manifest($id)->directory.'/plugin.json';
        $manifest = json_decode((string) file_get_contents($file), true);
        if (! is_array($manifest)) {
            $this->components->error("{$file}: kein gültiges JSON.");

            return self::FAILURE;
        }

        $host = $this->option('host');
        $host = is_string($host) && $host !== '' ? $host : null;

        /** @var array<string, mixed> $manifest */
        $result = PluginGenerator::merge($manifest, $described['endpoints'], $host);
        try {
            PluginManifest::fromArray($result['manifest'], basename(dirname($file)));
        } catch (InvalidArgumentException $e) {
            $this->components->error($e->getMessage().($host !== null ? " – «{$host}» zuerst unter service.hosts eintragen." : ''));

            return self::FAILURE;
        }

        foreach ($result['skipped'] as $line) {
            $this->line("  vorhanden: {$line}");
        }
        if ($result['added'] === []) {
            $this->components->info("Plugin «{$id}» kennt bereits alle Endpunkte. Nichts geändert.");

            return self::SUCCESS;
        }
        $this->table(['ID', 'Methode', 'Pfad', 'Beschreibung'], array_map(
            fn (array $e): array => [$e['id'], $e['method'], $e['path'], $e['label']],
            $result['added'],
        ));

        if ($this->option('dry-run')) {
            $this->components->info(count($result['added']).' Endpunkte würden ergänzt (--dry-run, nichts geschrieben).');

            return self::SUCCESS;
        }

        file_put_contents($file, PluginGenerator::encode($result['manifest']));
        $this->components->info(count($result['added'])." Endpunkte als «partial» ergänzt: {$file}. Mit der Hersteller-Doku prüfen, dann Pull-Request.");

        return self::SUCCESS;
    }

    /**
     * @param  array{title: string, description: string, base_url: string, auth: array<string, mixed>, auth_settings: list<array<string, mixed>>, endpoints: list<array<string, mixed>>}  $described
     */
    private function createPlugin(string $folder, array $described, string $root): int
    {
        try {
            $scaffold = PluginGenerator::scaffold($folder, $described);
        } catch (InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $dir = "{$root}/{$folder}";
        if (file_exists($dir)) {
            $this->components->error("plugins/{$folder} gibt es schon – vorhandene Plugins mit --plugin=<id> ergänzen.");

            return self::FAILURE;
        }

        $this->components->info("Neues Plugin «{$scaffold['manifest']['id']}» mit ".count($described['endpoints']).' Endpunkten (Status partial).');
        foreach (array_keys($scaffold['files']) as $name) {
            $this->line("  plugins/{$folder}/{$name}");
        }
        if ($this->option('dry-run')) {
            $this->line('');
            $this->line($scaffold['files']['plugin.json']);
            $this->components->info('--dry-run: nichts geschrieben.');

            return self::SUCCESS;
        }

        mkdir($dir, 0o755, true);
        foreach ($scaffold['files'] as $name => $content) {
            file_put_contents("{$dir}/{$name}", $content);
        }
        $this->components->info('Angelegt. Danach: composer dump-autoload, README.md im Ordner befolgen, Pull-Request. Im Intranet ist das Plugin zunächst ausgeschaltet.');

        return self::SUCCESS;
    }
}
