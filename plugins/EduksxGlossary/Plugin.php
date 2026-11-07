<?php

namespace Plugins\EduksxGlossary;

use App\Data\Contracts\GlossaryRepository;
use App\Plugins\ActionResult;
use App\Plugins\BasePlugin;
use App\Plugins\PluginContext;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
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

        return ['ok' => true, 'message' => 'EduKSX-Glossary erreichbar: Datenstand '.((int) ($data['release'] ?? 0)).', '
            .((int) ($data['term_count'] ?? 0)).' Begriffe.'];
    }

    public function action(string $action, PluginContext $ctx, array $options): ActionResult
    {
        return match ($action) {
            'sync' => $this->sync($ctx, ($options['neu'] ?? false) === true || ($options['neu'] ?? null) === '1'),
            'sync-status' => ActionResult::ok($this->statusText(app(GlossaryRepository::class)->syncState())),
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
        $art = ['vollstand' => 'Vollstand', 'aenderungen' => 'Änderungen', 'unveraendert' => 'keine Änderungen'][$result['art']] ?? $result['art'];
        $message = "Sync ({$art}): {$result['neu']} neu, {$result['geaendert']} geändert, {$result['ausgeblendet']} ausgeblendet, "
            ."{$result['zusammengefuehrt']} zusammengeführt. Cursor {$result['cursor']}.";
        $ctx->log($message);

        return ActionResult::ok($message);
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
