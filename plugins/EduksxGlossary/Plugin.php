<?php

namespace Plugins\EduksxGlossary;

use App\Data\Contracts\GlossaryRepository;
use App\Plugins\ActionResult;
use App\Plugins\BasePlugin;
use App\Plugins\PluginContext;
use App\Support\GlossaryProposals;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

/**
 * EduKSX-Glossary (#156): Begriffe aus dem führenden Glossary übernehmen und aktuell halten (Plugins\EduksxGlossary\Sync).
 * Aufruf von Hand («Jetzt synchronisieren») oder im Zeitplan (php artisan intranet:glossar-sync). Ohne Verbindung
 * bleibt der lokale Stand; Fehler stehen im Stand und im Protokoll.
 */
class Plugin extends BasePlugin
{
    public function test(PluginContext $ctx): array
    {
        try {
            $latest = $this->fetch($ctx)('releases-latest', []);
        } catch (RuntimeException $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
        $data = is_array($latest['data'] ?? null) ? $latest['data'] : $latest;
        $message = 'EduKSX-Glossary erreichbar: Datenstand '.((int) ($data['release'] ?? 0)).', '.((int) ($data['term_count'] ?? 0)).' Begriffe.';
        if ($ctx->secret('token') === null) {
            return ['ok' => true, 'message' => $message.' Ohne Token: nur lesen (Vorschläge brauchen ein Servicekonto).'];
        }
        // mit Token (#160): Servicekonto und Scopes prüfen
        try {
            $who = $this->fetch($ctx)('service-whoami', []);
        } catch (RuntimeException $e) {
            return ['ok' => false, 'message' => $message.' Token ungültig, abgelaufen oder widerrufen: '.$e->getMessage()];
        }
        $account = is_array($who['service_account'] ?? null) ? $who['service_account'] : [];
        $scopes = array_map('strval', (array) ($account['scopes'] ?? []));

        return ['ok' => in_array('propose', $scopes, true), 'message' => $message.' Servicekonto «'.((string) ($account['name'] ?? '?')).'», Scopes: '
            .($scopes === [] ? '–' : implode(', ', $scopes)).(in_array('propose', $scopes, true) ? '.' : ' – Scope «propose» fehlt für Vorschläge.')];
    }

    public function action(string $action, PluginContext $ctx, array $options): ActionResult
    {
        $flag = fn (string $key): bool => in_array($options[$key] ?? false, [true, 1, '1', 'true'], true);

        return match ($action) {
            'sync' => $flag('probelauf') ? $this->dryRun($ctx, $flag('neu')) : $this->sync($ctx, $flag('neu')),
            'sync-status' => ActionResult::ok($this->statusText(app(GlossaryRepository::class)->syncState())),
            'propose' => $this->propose($ctx, $options),
            default => parent::action($action, $ctx, $options),
        };
    }

    public function status(PluginContext $ctx): ?string
    {
        return $this->statusText(app(GlossaryRepository::class)->syncState());
    }

    private function sync(PluginContext $ctx, bool $fresh): ActionResult
    {
        $repo = app(GlossaryRepository::class);
        $sync = new Sync($repo, $this->fetch($ctx));
        $now = now()->toIso8601String();
        try {
            $result = $sync->run($fresh);
        } catch (RuntimeException $e) {
            // ohne Verbindung mit dem lokalen Stand weiterarbeiten; Stand bis zum Fehler bleibt gespeichert
            $repo->saveSyncState(['letzter_abruf' => $now, 'fehler' => $e->getMessage()]);
            Log::warning('EduKSX-Glossary: Sync fehlgeschlagen', ['message' => $e->getMessage()]);
            $ctx->log('Fehler: '.$e->getMessage());

            return ActionResult::warning('Sync nicht möglich, der lokale Stand bleibt: '.$e->getMessage());
        }
        $repo->saveSyncState(['letzter_abruf' => $now, 'letzter_erfolg' => $now, 'fehler' => null]);
        $message = self::summary($result);
        // offene Vorschläge nachfragen (#162), nur mit Token; Fehler dabei brechen den Sync nicht ab
        if ($ctx->secret('token') !== null) {
            try {
                $updated = (new GlossaryProposals($repo))->refresh($this->proposalGetter($ctx));
                if ($updated > 0) {
                    $message .= " {$updated} Vorschläge mit neuem Stand.";
                }
            } catch (RuntimeException $e) {
                $ctx->log('Vorschläge nicht nachgefragt: '.$e->getMessage());
            }
        }
        $ctx->log($message);

        return ActionResult::ok($message);
    }

    /** Probelauf (#161): wie der Sync, aber nur im Speicher – zeigt, was sich ändern würde. */
    private function dryRun(PluginContext $ctx, bool $fresh): ActionResult
    {
        $repo = new DryRunRepository(app(GlossaryRepository::class));
        try {
            $result = (new Sync($repo, $this->fetch($ctx)))->run($fresh);
        } catch (RuntimeException $e) {
            return ActionResult::warning('Probelauf nicht möglich: '.$e->getMessage());
        }
        $message = 'Probelauf – nichts gespeichert. '.self::summary($result)
            .($result['lokal'] > 0 ? " {$result['lokal']} lokale Begriffe ohne Gegenstück im Glossary bleiben lokal." : '');

        return ActionResult::table($message, [
            ['key' => 'art', 'label' => 'Art'],
            ['key' => 'liste', 'label' => 'Bereich'],
            ['key' => 'titel', 'label' => 'Eintrag'],
            ['key' => 'felder', 'label' => 'Felder'],
        ], $repo->log());
    }

    /**
     * Vorschlag einreichen (#162). Optionen: «eintrag» (ID), «begruendung», «_changes» (Felder aus dem Formular).
     *
     * @param  array<string, mixed>  $options
     */
    private function propose(PluginContext $ctx, array $options): ActionResult
    {
        if ($ctx->secret('token') === null) {
            return ActionResult::warning('Für Vorschläge fehlt das Token des Servicekontos (Einstellungen des Plugins).');
        }
        $repo = app(GlossaryRepository::class);
        $entry = $repo->find((string) ($options['eintrag'] ?? ''));
        if ($entry === null) {
            return ActionResult::warning('Eintrag nicht gefunden.');
        }
        $proposals = new GlossaryProposals($repo);
        try {
            $body = $proposals->body($entry, is_array($options['_changes'] ?? null) ? $options['_changes'] : [], (string) ($options['begruendung'] ?? ''));
            $note = $proposals->submit(function (array $body) use ($ctx): array {
                $response = $ctx->call('proposals-create', [], $body);
                if ($response->status() === 401) {
                    throw new RuntimeException('Token ungültig oder ohne Scope «propose».');
                }
                if ($response->status() === 404) {
                    throw new RuntimeException('Der Begriff ist im EduKSX-Glossary nicht (mehr) veröffentlicht.');
                }
                if ($response->status() === 422) {
                    $json = $response->json();
                    throw new RuntimeException('Vom EduKSX-Glossary abgelehnt: '.(is_array($json) && is_string($json['message'] ?? null) ? $json['message'] : 'ungültige Angaben').'.');
                }
                if (! $response->successful()) {
                    throw new RuntimeException("EduKSX-Glossary antwortet mit HTTP {$response->status()}.");
                }

                return (array) $response->json();
            }, $entry, $body);
        } catch (InvalidArgumentException|RuntimeException $e) {
            return ActionResult::warning($e->getMessage());
        }
        $ctx->log("Vorschlag {$note['id']} eingereicht ({$note['art']}).");

        return ActionResult::ok("Vorschlag Nr. {$note['id']} eingereicht – er wird im EduKSX-Glossary moderiert.");
    }

    /**
     * @return \Closure(int): (array<mixed>|null)
     */
    private function proposalGetter(PluginContext $ctx): \Closure
    {
        return function (int $id) use ($ctx): ?array {
            $response = $ctx->call('proposal', ['id' => $id]);
            if ($response->status() === 404) {
                return null;
            }
            if (! $response->successful()) {
                throw new RuntimeException("Vorschlag {$id}: HTTP {$response->status()}.");
            }
            $json = $response->json();

            return is_array($json) ? $json : null;
        };
    }

    /**
     * @param  array<string, int|string>  $result
     */
    private static function summary(array $result): string
    {
        $art = ['vollstand' => 'Vollstand', 'aenderungen' => 'Änderungen', 'unveraendert' => 'keine Änderungen'][$result['art']] ?? $result['art'];

        return "Sync ({$art}): {$result['neu']} neu, {$result['geaendert']} geändert, {$result['ausgeblendet']} ausgeblendet, "
            ."{$result['zusammengefuehrt']} zusammengeführt. Cursor {$result['cursor']}.";
    }

    /**
     * Abruf eines Endpunkts als JSON; Fehler als RuntimeException mit verständlicher Meldung.
     *
     * @return \Closure(string, array<string, mixed>): array<mixed>
     */
    private function fetch(PluginContext $ctx): \Closure
    {
        return function (string $endpoint, array $params) use ($ctx): array {
            try {
                $response = $ctx->call($endpoint, $params);
            } catch (ConnectionException) {
                throw new RuntimeException('EduKSX-Glossary nicht erreichbar (Verbindung).');
            }
            if ($response->status() === 429) {
                throw new RuntimeException('Zu viele Abrufe beim EduKSX-Glossary – erneut in '.((int) $response->header('Retry-After') ?: 60).' Sekunden.');
            }
            if (! $response->successful()) {
                throw new RuntimeException("EduKSX-Glossary antwortet mit HTTP {$response->status()}.");
            }
            $json = $response->json();
            if (! is_array($json)) {
                throw new RuntimeException('Antwort des EduKSX-Glossary ist kein JSON.');
            }

            return $json;
        };
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public static function statusText(array $state): string
    {
        if (! isset($state['cursor'])) {
            return 'Noch nicht synchronisiert.';
        }
        $text = 'Letzter Sync: '.self::time($state['letzter_erfolg'] ?? null).', Datenstand '.((int) ($state['release'] ?? 0))
            .', Cursor '.((int) $state['cursor']).'.';
        if (is_string($state['fehler'] ?? null) && $state['fehler'] !== '') {
            $text .= ' Letzter Abruf '.self::time($state['letzter_abruf'] ?? null).' mit Fehler: '.$state['fehler'];
        }

        return $text;
    }

    private static function time(mixed $iso): string
    {
        if (! is_string($iso) || $iso === '') {
            return '–';
        }

        return Carbon::parse($iso)->timezone('Europe/Zurich')->format('d.m.Y H:i');
    }
}
