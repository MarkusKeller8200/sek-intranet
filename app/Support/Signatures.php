<?php

namespace App\Support;

use App\Auth\IntranetUser;
use App\Data\Store\JsonStore;
use App\Plugins\PluginManager;
use App\Plugins\SignatureProvider;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Kern-Schnittstelle «Unterschriften» (#38, docs/KONZEPT-DEEPSIGN.md): Module lassen Dokumente unterschreiben,
 * ohne den Anbieter zu kennen. Der Auftrag (Sammlung signature_requests) hält Status und Unterzeichnende; das PDF
 * selbst speichert der Kern nicht – das unterschriebene Dokument geht über das Ereignis «signatur.abgeschlossen»
 * an das auslösende Modul, das es in seinem eigenen Speicher ablegt.
 *
 * Ereignisse an das Modul (Signatures::listen): signatur.unterschrieben (eine Person), signatur.abgeschlossen
 * (mit PDF; true zurückgeben, wenn abgelegt), signatur.abgelehnt (abgelehnt, zurückgezogen, abgelaufen).
 */
final class Signatures
{
    public const COLLECTION = 'signature_requests';

    /** Aufträge ohne Modul (von Hand gesendet); das unterschriebene PDF legt der Kern unter signatures/ ab. */
    public const MANUAL = 'unterschriften';

    private const MANUAL_DIR = 'signatures';

    public const STATUS = [
        'entwurf' => 'Entwurf',
        'versendet' => 'versendet',
        'teilweise' => 'teilweise unterschrieben',
        'unterschrieben' => 'unterschrieben',
        'abgelehnt' => 'abgelehnt',
        'zurueckgezogen' => 'zurückgezogen',
        'abgelaufen' => 'abgelaufen',
        'fehler' => 'Fehler',
    ];

    public const SIGNEE_STATUS = ['wartet' => 'wartet', 'offen' => 'offen', 'unterschrieben' => 'unterschrieben', 'abgelehnt' => 'abgelehnt'];

    /** Offene Aufträge (werden regelmässig abgefragt). */
    public const OPEN = ['versendet', 'teilweise'];

    /** Abschlüsse, nach denen nichts mehr geschieht (ausser Abholen beim Status «unterschrieben»). */
    public const CLOSED = ['unterschrieben', 'abgelehnt', 'zurueckgezogen', 'abgelaufen'];

    /** @var array<string, callable(string, array<string, mixed>, string|null): (bool|null)> */
    private static array $listeners = [];

    public function __construct(
        private readonly JsonStore $store,
        private readonly PluginManager $plugins,
        private readonly Notifications $notifications,
        private readonly AuditLog $audit,
    ) {}

    /**
     * Modul meldet sich für seine Ereignisse an (im ServiceProvider des Moduls, boot()).
     *
     * @param  callable(string, array<string, mixed>, string|null): (bool|null)  $listener
     */
    public static function listen(string $module, callable $listener): void
    {
        self::$listeners[$module] = $listener;
    }

    /** Nur für Tests. */
    public static function forgetListeners(): void
    {
        self::$listeners = [];
    }

    /**
     * Ist ein Anbieter eingerichtet? Name und Demo-Modus für die Anzeige.
     *
     * @return array{id: string, name: string, demo: bool}|null
     */
    public function provider(): ?array
    {
        $provider = $this->plugins->signatureProvider();
        if ($provider === null) {
            return null;
        }

        return ['id' => $provider['id'], 'name' => $provider['name'], 'demo' => $this->plugins->context($provider['id'], null)->demo()];
    }

    /**
     * @return list<array<string, mixed>> neueste zuerst
     */
    public function all(): array
    {
        $all = $this->store->all(self::COLLECTION);
        usort($all, fn (array $a, array $b): int => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));

        return array_map(fn (array $r): array => self::public($r), $all);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $id): ?array
    {
        $record = $this->store->find(self::COLLECTION, $id);

        return $record === null ? null : self::public($record);
    }

    /**
     * Aufträge eines Moduls zu einem Bezug (z. B. «person:<id>:2026-10»), neueste zuerst.
     *
     * @return list<array<string, mixed>>
     */
    public function forReference(string $module, string $reference): array
    {
        return array_values(array_filter($this->all(), fn (array $r): bool => $r['module'] === $module && $r['reference'] === $reference));
    }

    /**
     * Auftrag anlegen, beim Anbieter hochladen und (Standard) gleich starten.
     *
     * @param  array{module: string, reference: string, title: string, file_name: string, comment?: string|null, signees: list<array{person_id?: string|null, name: string, email: string, order: int, position?: array<string, int|float>|null}>, observers?: list<string>}  $data
     * @return array<string, mixed>
     */
    public function create(array $data, string $pdf, ?IntranetUser $by, bool $start = true): array
    {
        $provider = $this->plugins->signatureProvider() ?? throw new RuntimeException('Kein Anbieter für Unterschriften eingerichtet (Plugin DeepSign einschalten und einrichten).');
        if ($data['signees'] === []) {
            throw new InvalidArgumentException('Mindestens eine unterzeichnende Person ist nötig.');
        }
        foreach ($data['signees'] as $s) {
            if (filter_var($s['email'], FILTER_VALIDATE_EMAIL) === false) {
                throw new InvalidArgumentException("Ungültige E-Mail für «{$s['name']}».");
            }
        }
        if (! str_starts_with($pdf, '%PDF')) {
            throw new InvalidArgumentException('Das Dokument ist kein PDF.');
        }

        $secret = Str::random(48);
        $record = $this->store->insert(self::COLLECTION, [
            'module' => $data['module'],
            'reference' => $data['reference'],
            'title' => $data['title'],
            'file_name' => $data['file_name'],
            'provider' => $provider['id'],
            'provider_id' => null,
            'status' => 'entwurf',
            'signees' => array_map(fn (array $s): array => [
                'person_id' => $s['person_id'] ?? null,
                'name' => $s['name'],
                'email' => $s['email'],
                'order' => $s['order'],
                'status' => 'wartet',
                'signed_at' => null,
                'provider_id' => null,
                'position' => $s['position'] ?? null,
            ], $data['signees']),
            'observers' => $data['observers'] ?? [],
            'comment' => $data['comment'] ?? null,
            'callback_secret' => Crypt::encryptString($secret),
            'created_by' => $by !== null ? (string) $by->getAuthIdentifier() : null,
        ]);

        $callback = (bool) config('intranet.signatures.webhook')
            ? ['url' => route('signatures.callback', ['provider' => $provider['id']]), 'secret' => $secret]
            : null;

        try {
            /** @var array{provider_id: string, signee_ids: list<string>} $sent */
            $sent = $this->plugins->runWith($provider['id'], $by, 'Unterschrift: hochladen', fn ($ctx): array => $provider['plugin']->signatureSend($ctx, [
                'title' => $data['title'],
                'file_name' => $data['file_name'],
                'comment' => $data['comment'] ?? null,
                'signees' => array_map(fn (array $s): array => ['email' => $s['email'], 'order' => $s['order'], 'position' => $s['position'] ?? null], $data['signees']),
                'observers' => $data['observers'] ?? [],
            ], $pdf, $callback));
        } catch (Throwable $e) {
            $this->store->update(self::COLLECTION, (string) $record['id'], ['status' => 'fehler', 'error' => mb_substr($e->getMessage(), 0, 500)]);

            throw new RuntimeException('Senden an '.$provider['name'].' fehlgeschlagen: '.$e->getMessage(), 0, $e);
        }

        $signees = (array) $record['signees'];
        foreach ($sent['signee_ids'] as $i => $signeeId) {
            if (isset($signees[$i]) && is_array($signees[$i])) {
                $signees[$i]['provider_id'] = $signeeId;
            }
        }
        $record = (array) $this->store->update(self::COLLECTION, (string) $record['id'], ['provider_id' => $sent['provider_id'], 'signees' => $signees]);
        $this->audit->record(self::COLLECTION, null, self::public($record), 'unterschriften', $by);

        if ($start) {
            return $this->start((string) $record['id'], $by);
        }

        return self::public($record);
    }

    /**
     * @return array<string, mixed>
     */
    public function start(string $id, ?IntranetUser $by): array
    {
        $record = $this->record($id);
        if ($record['status'] !== 'entwurf' || ! is_string($record['provider_id'] ?? null)) {
            throw new InvalidArgumentException('Nur Entwürfe lassen sich starten.');
        }
        [$provider, $plugin] = $this->providerFor($record);
        $this->plugins->runWith($provider, $by, 'Unterschrift: starten', fn ($ctx) => $plugin->signatureStart($ctx, (string) $record['provider_id']));
        $this->update($record, ['status' => 'versendet', 'sent_at' => now()->toIso8601String()], $by);

        return $this->refresh($id, $by);
    }

    /**
     * Status beim Anbieter abfragen, Ereignisse auslösen, unterschriebenes PDF abholen.
     *
     * @return array<string, mixed>
     */
    public function refresh(string $id, ?IntranetUser $by = null): array
    {
        $record = $this->record($id);
        if (! is_string($record['provider_id'] ?? null) || $record['status'] === 'fehler') {
            return self::public($record);
        }
        [$provider, $plugin] = $this->providerFor($record);

        if (! in_array($record['status'], self::CLOSED, true)) {
            /** @var array{status: string, signees: list<array{email: string, status: string, signed_at: string|null, provider_id: string}>} $state */
            $state = $this->plugins->runWith($provider, $by, 'Unterschrift: Status', fn ($ctx): array => $plugin->signatureStatus($ctx, (string) $record['provider_id']));
            $record = $this->apply($record, $state, $by);
        }

        if ($record['status'] === 'unterschrieben' && ($record['fetched_at'] ?? null) === null) {
            $record = $this->fetch($record, $provider, $plugin, $by);
        }

        return self::public($record);
    }

    /**
     * Regelmässige Abfrage (intranet:signatures-refresh, alle 15 Minuten): offene Aufträge, deren letzte Abfrage
     * mindestens $minutes zurückliegt, und unterschriebene, die noch nicht abgeholt sind. Warnt, wenn ein
     * unterschriebenes Dokument zu lange nicht abgeholt werden konnte (DeepSign löscht nach 14 Tagen).
     *
     * @return array{checked: int, fetched: int, warned: int, errors: int}
     */
    public function refreshDue(int $minutes = 15): array
    {
        $result = ['checked' => 0, 'fetched' => 0, 'warned' => 0, 'errors' => 0];
        $warnAfter = (int) config('intranet.signatures.fetch_warning_hours', 24);

        foreach ($this->store->all(self::COLLECTION) as $record) {
            $open = in_array($record['status'], self::OPEN, true);
            $unfetched = $record['status'] === 'unterschrieben' && ($record['fetched_at'] ?? null) === null;
            $last = is_string($record['last_checked_at'] ?? null) ? strtotime($record['last_checked_at']) : false;
            if (! $unfetched && (! $open || ($last !== false && $last > now()->getTimestamp() - $minutes * 60))) {
                continue;
            }

            try {
                $after = $this->refresh((string) $record['id']);
                $result['checked']++;
                if ($unfetched && ($after['fetched_at'] ?? null) !== null) {
                    $result['fetched']++;
                }
            } catch (Throwable $e) {
                $result['errors']++;
                Log::warning('Unterschrift '.$record['id'].': '.$e->getMessage());
            }

            $current = $this->store->find(self::COLLECTION, (string) $record['id']) ?? $record;
            $completed = is_string($current['completed_at'] ?? null) ? strtotime($current['completed_at']) : false;
            if ($current['status'] === 'unterschrieben' && ($current['fetched_at'] ?? null) === null && ($current['warned_at'] ?? null) === null
                && $completed !== false && $completed < now()->getTimestamp() - $warnAfter * 3600) {
                $this->notifications->toAbility('manage-signatures', 'signatur.nicht_abgeholt', 'Unterschriebenes Dokument nicht abgeholt',
                    "«{$current['title']}» ist seit über {$warnAfter} Stunden unterschrieben, konnte aber nicht abgelegt werden. DeepSign löscht es nach 14 Tagen.",
                    '/unterschriften', null);
                $this->store->update(self::COLLECTION, (string) $current['id'], ['warned_at' => now()->toIso8601String()]);
                $result['warned']++;
            }
        }

        return $result;
    }

    /**
     * Dokument von Hand zur Unterschrift senden (Seite «Unterschriften», Plugin-Aktion). Unterzeichnende als Liste
     * {name, email}; Reihenfolge wie aufgelistet oder alle gleichzeitig.
     *
     * @param  list<array{name: string, email: string}>  $signees
     * @param  list<string>  $observers
     * @return array<string, mixed>
     */
    public function sendManual(string $title, string $fileName, string $pdf, array $signees, bool $parallel, array $observers, ?string $comment, ?IntranetUser $by): array
    {
        $title = trim($title) !== '' ? trim($title) : pathinfo($fileName, PATHINFO_FILENAME);
        foreach ($observers as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                throw new InvalidArgumentException("Ungültige E-Mail bei «Zur Kenntnis an»: {$email}");
            }
        }

        return $this->create([
            'module' => self::MANUAL,
            'reference' => 'manuell:'.Str::uuid()->toString(),
            'title' => mb_substr($title, 0, 200),
            'file_name' => preg_replace('/[^\w.\- ]+/u', '_', $fileName) ?: 'Dokument.pdf',
            'comment' => $comment !== null && trim($comment) !== '' ? mb_substr(trim($comment), 0, 1000) : null,
            'signees' => array_values(array_map(fn (array $s, int $i): array => ['name' => $s['name'], 'email' => $s['email'], 'order' => $parallel ? 0 : $i], $signees, array_keys($signees))),
            'observers' => $observers,
        ], $pdf, $by);
    }

    /**
     * Zeilen «Name; E-Mail» (oder nur E-Mail) in Unterzeichnende umwandeln.
     *
     * @return list<array{name: string, email: string}>
     */
    public static function parseSignees(string $text): array
    {
        $signees = [];
        foreach (preg_split('/\R/', $text) ?: [] as $line) {
            $parts = array_map('trim', preg_split('/[;,\t]/', $line) ?: []);
            $parts = array_values(array_filter($parts, fn (string $p): bool => $p !== ''));
            if ($parts === []) {
                continue;
            }
            $email = array_pop($parts);
            $signees[] = ['name' => $parts !== [] ? implode(' ', $parts) : $email, 'email' => $email];
        }

        return $signees;
    }

    /**
     * Ereignisse der von Hand gesendeten Aufträge: unterschriebenes PDF ablegen.
     *
     * @param  array<string, mixed>  $request
     */
    public function manualEvent(string $event, array $request, ?string $pdf): ?bool
    {
        if ($event !== 'signatur.abgeschlossen') {
            return true;
        }
        if ($pdf === null) {
            return false;
        }
        $path = self::MANUAL_DIR.'/'.preg_replace('/[^a-zA-Z0-9-]/', '', (string) $request['id']).'.pdf';
        if (! Storage::disk('local')->put($path, $pdf)) {
            return false;
        }
        $this->store->update(self::COLLECTION, (string) $request['id'], ['pdf_path' => $path]);
        if (is_string($request['created_by'] ?? null)) {
            $this->notifications->toUsers([$request['created_by']], 'signatur.abgeschlossen', 'Dokument unterschrieben',
                "«{$request['title']}» ist von allen unterschrieben und liegt unter Unterschriften.", '/unterschriften', null);
        }

        return true;
    }

    /** Pfad des abgelegten PDF eines von Hand gesendeten Auftrags (oder null). */
    public function manualPdf(string $id): ?string
    {
        $record = $this->store->find(self::COLLECTION, $id);
        $path = $record['pdf_path'] ?? null;

        return is_string($path) && str_starts_with($path, self::MANUAL_DIR.'/') && Storage::disk('local')->exists($path) ? $path : null;
    }

    public function remind(string $id, int $index, ?IntranetUser $by): void
    {
        $record = $this->record($id);
        $signee = ((array) $record['signees'])[$index] ?? null;
        if (! in_array($record['status'], self::OPEN, true) || ! is_array($signee) || $signee['status'] !== 'offen' || ! is_string($signee['provider_id'] ?? null)) {
            throw new InvalidArgumentException('Erinnern geht nur bei Personen, die jetzt unterschreiben sollen.');
        }
        [$provider, $plugin] = $this->providerFor($record);
        $this->plugins->runWith($provider, $by, 'Unterschrift: erinnern', fn ($ctx) => $plugin->signatureRemind($ctx, (string) $record['provider_id'], (string) $signee['provider_id']));
        $this->audit->record(self::COLLECTION, self::public($record), self::public($record) + ['erinnert' => $signee['name']], 'unterschriften: erinnert', $by);
    }

    public function withdraw(string $id, ?IntranetUser $by): void
    {
        $record = $this->record($id);
        if (! in_array($record['status'], self::OPEN, true)) {
            throw new InvalidArgumentException('Zurückziehen geht nur bei laufenden Aufträgen.');
        }
        [$provider, $plugin] = $this->providerFor($record);
        $this->plugins->runWith($provider, $by, 'Unterschrift: zurückziehen', fn ($ctx) => $plugin->signatureWithdraw($ctx, (string) $record['provider_id']));
        $record = $this->update($record, ['status' => 'zurueckgezogen', 'withdrawn_by' => $by !== null ? (string) $by->getAuthIdentifier() : null, 'completed_at' => now()->toIso8601String()], $by);
        $this->dispatch($record, 'signatur.abgelehnt', null);
    }

    /**
     * Entwurf, Fehler oder abgeschlossenen Auftrag löschen (beim Anbieter nur Entwurf bzw. zurückgezogen).
     */
    public function delete(string $id, ?IntranetUser $by): void
    {
        $record = $this->record($id);
        if (in_array($record['status'], self::OPEN, true)) {
            throw new InvalidArgumentException('Laufende Aufträge zuerst zurückziehen.');
        }
        if (in_array($record['status'], ['entwurf', 'zurueckgezogen'], true) && is_string($record['provider_id'] ?? null)) {
            [$provider, $plugin] = $this->providerFor($record);
            try {
                $this->plugins->runWith($provider, $by, 'Unterschrift: löschen', fn ($ctx) => $plugin->signatureDelete($ctx, (string) $record['provider_id']));
            } catch (Throwable $e) {
                Log::info('Unterschrift beim Anbieter nicht gelöscht: '.$e->getMessage());
            }
        }
        $this->store->delete(self::COLLECTION, $id);
        $this->audit->record(self::COLLECTION, self::public($record), null, 'unterschriften', $by);
    }

    public function accessUrl(string $id, ?IntranetUser $by): ?string
    {
        $record = $this->record($id);
        if (! is_string($record['provider_id'] ?? null)) {
            return null;
        }
        [$provider, $plugin] = $this->providerFor($record);
        $url = $this->plugins->runWith($provider, $by, 'Unterschrift: Link beim Anbieter', fn ($ctx): ?string => $plugin->signatureAccessUrl($ctx, (string) $record['provider_id']));

        return is_string($url) && str_starts_with($url, 'https://') ? $url : null;
    }

    /**
     * Demo-Modus: nächste Person unterschreibt.
     *
     * @return array<string, mixed>
     */
    public function simulate(string $id, ?IntranetUser $by): array
    {
        $record = $this->record($id);
        [$provider, $plugin] = $this->providerFor($record);
        $done = $this->plugins->runWith($provider, $by, 'Unterschrift: Demo', fn ($ctx): bool => $plugin->signatureSimulate($ctx, (string) $record['provider_id']));
        if ($done !== true) {
            throw new InvalidArgumentException('Nur im Demo-Modus möglich.');
        }

        return $this->refresh($id, $by);
    }

    /**
     * Webhook des Anbieters (#40): Signatur prüfen, nur bekannte IDs, danach Status selbst abfragen.
     *
     * @param  array<string, list<string|null>|string|null>  $headers
     * @return int HTTP-Status der Antwort
     */
    public function callback(string $providerId, array $headers, string $raw): int
    {
        $provider = $this->plugins->signatureProvider();
        if ($provider === null || $provider['id'] !== $providerId) {
            return 404;
        }
        $documentId = $provider['plugin']->signatureCallbackId($raw);
        if ($documentId === null) {
            return 400;
        }
        $record = null;
        foreach ($this->store->all(self::COLLECTION) as $r) {
            if (($r['provider_id'] ?? null) === $documentId && $r['provider'] === $providerId) {
                $record = $r;
            }
        }
        if ($record === null) {
            return 404;
        }

        try {
            $secret = Crypt::decryptString((string) $record['callback_secret']);
        } catch (Throwable) {
            return 401;
        }
        if (! $provider['plugin']->signatureVerifyCallback($headers, $raw, $secret)) {
            return 401;
        }

        try {
            $this->refresh((string) $record['id']);
        } catch (Throwable $e) {
            Log::warning('Webhook Unterschrift: '.$e->getMessage());
        }

        return 204;
    }

    // ---------- intern ----------

    /**
     * @param  array<string, mixed>  $record
     * @param  array{status: string, signees: list<array{email: string, status: string, signed_at: string|null, provider_id: string}>}  $state
     * @return array<string, mixed>
     */
    private function apply(array $record, array $state, ?IntranetUser $by): array
    {
        $signees = (array) $record['signees'];
        $newlySigned = [];
        foreach ($signees as $i => $signee) {
            if (! is_array($signee)) {
                continue;
            }
            foreach ($state['signees'] as $remote) {
                $same = ($signee['provider_id'] ?? null) !== null ? $remote['provider_id'] === $signee['provider_id'] : strcasecmp($remote['email'], (string) $signee['email']) === 0;
                if (! $same) {
                    continue;
                }
                if ($remote['status'] === 'unterschrieben' && $signee['status'] !== 'unterschrieben') {
                    $newlySigned[] = (string) $signee['name'];
                }
                $signees[$i]['status'] = array_key_exists($remote['status'], self::SIGNEE_STATUS) ? $remote['status'] : 'offen';
                $signees[$i]['signed_at'] = $remote['signed_at'];
                $signees[$i]['provider_id'] = $remote['provider_id'];
            }
        }

        $status = array_key_exists($state['status'], self::STATUS) ? $state['status'] : $record['status'];
        // DeepSign zieht unvollständige Aufträge nach 30 Tagen selbst zurück
        if ($status === 'zurueckgezogen' && ($record['withdrawn_by'] ?? null) === null && $record['status'] !== 'zurueckgezogen') {
            $status = 'abgelaufen';
        }
        $before = $record['status'];
        $changes = ['signees' => $signees, 'status' => $status, 'last_checked_at' => now()->toIso8601String()];
        if (in_array($status, self::CLOSED, true) && ! in_array($before, self::CLOSED, true)) {
            $changes['completed_at'] = now()->toIso8601String();
        }
        $record = $this->update($record, $changes, $by, audit: $before !== $status || $newlySigned !== []);

        foreach ($newlySigned as $name) {
            $this->dispatch($record + ['signed_name' => $name], 'signatur.unterschrieben', null);
        }
        if ($before !== $status && in_array($status, ['abgelehnt', 'zurueckgezogen', 'abgelaufen'], true)) {
            $this->dispatch($record, 'signatur.abgelehnt', null);
        }

        return $record;
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private function fetch(array $record, string $provider, SignatureProvider $plugin, ?IntranetUser $by): array
    {
        $pdf = $this->plugins->runWith($provider, $by, 'Unterschrift: abholen', fn ($ctx): ?string => $plugin->signatureDownload($ctx, (string) $record['provider_id']));
        if (! is_string($pdf) || ! str_starts_with($pdf, '%PDF')) {
            return $record;
        }
        if ($this->dispatch($record, 'signatur.abgeschlossen', $pdf) !== true) {
            return $record;
        }

        return $this->update($record, ['fetched_at' => now()->toIso8601String()], $by);
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function dispatch(array $record, string $event, ?string $pdf): ?bool
    {
        $listener = self::$listeners[(string) $record['module']] ?? null;
        if ($listener === null) {
            Log::info("Unterschriften: kein Empfänger für {$event} (Modul {$record['module']}).");

            return null;
        }

        try {
            return $listener($event, self::public($record), $pdf);
        } catch (Throwable $e) {
            Log::warning("Unterschriften: {$event} im Modul {$record['module']} fehlgeschlagen: ".$e->getMessage());

            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>
     */
    private function update(array $record, array $changes, ?IntranetUser $by, bool $audit = true): array
    {
        $after = (array) $this->store->update(self::COLLECTION, (string) $record['id'], $changes);
        if ($audit) {
            $this->audit->record(self::COLLECTION, self::public($record), self::public($after), 'unterschriften', $by);
        }

        return $after;
    }

    /**
     * @return array<string, mixed>
     */
    private function record(string $id): array
    {
        return $this->store->find(self::COLLECTION, $id) ?? throw new InvalidArgumentException('Auftrag nicht gefunden.');
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array{0: string, 1: SignatureProvider}
     */
    private function providerFor(array $record): array
    {
        $provider = $this->plugins->signatureProvider();
        if ($provider === null || $provider['id'] !== $record['provider']) {
            throw new RuntimeException('Der Anbieter «'.$record['provider'].'» ist nicht eingeschaltet oder nicht eingerichtet.');
        }

        return [$provider['id'], $provider['plugin']];
    }

    /**
     * Ohne Geheimnis (Callback-Secret) für Anzeige, Ereignisse und Verlauf.
     *
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private static function public(array $record): array
    {
        unset($record['callback_secret']);

        return $record + ['module' => '', 'reference' => '', 'title' => '', 'status' => 'entwurf', 'signees' => [], 'observers' => []];
    }
}
