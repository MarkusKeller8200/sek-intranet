<?php

namespace App\Support;

use App\Data\Contracts\GlossaryRepository;
use Closure;
use InvalidArgumentException;
use RuntimeException;

/**
 * Vorschläge ans EduKSX-Glossary (#162): neue Begriffe und Korrekturen über POST /api/v1/proposals (Servicekonto mit
 * Scope «propose»). Vorschläge landen immer in der Moderation des Glossary, nie direkt öffentlich. Gesendet werden nur
 * gemeinsame Fachdaten – eigene Formen, «Intern genutzt in», Notiz und Suchbegriffe bleiben lokal.
 *
 * Am Eintrag steht die Liste «vorschlaege» (Nummer, Art, Stand, Felder, Zeitpunkte); der Sync fragt offene Vorschläge
 * nach und verbindet einen angenommenen neuen Begriff mit dem veröffentlichten. Die Abrufe selbst macht das Plugin
 * EduKSX-Glossary (plugins/EduksxGlossary); hier nur Abbildung, Prüfung und Vermerk.
 */
final class GlossaryProposals
{
    /** Felder der API → Felder des Intranets (gemeinsame Fachdaten) */
    public const FIELDS = [
        'official_abbreviation' => 'abk_offiziell',
        'official_name' => 'bez_offiziell',
        'short_description' => 'kurzbeschreibung',
        'description' => 'beschreibung',
        'url' => 'url',
        'alternative_aliases' => 'aliase_alternativ',
        'categories' => 'kategorien',
    ];

    public const LABELS = [
        'official_abbreviation' => 'Abkürzung',
        'official_name' => 'Bezeichnung',
        'short_description' => 'Kurzbeschreibung',
        'description' => 'Beschreibung',
        'url' => 'URL',
        'alternative_aliases' => 'Alternative Schreibweisen',
        'categories' => 'Kategorien',
    ];

    /** offen = wird beim Sync nachgefragt */
    public const OPEN = ['eingereicht', 'rueckfrage'];

    public const STATUS = ['eingereicht' => 'eingereicht', 'rueckfrage' => 'Rückfrage', 'angenommen' => 'angenommen', 'abgelehnt' => 'abgelehnt', 'zurueckgezogen' => 'zurückgezogen'];

    private const MAX = ['official_abbreviation' => 40, 'official_name' => 500, 'short_description' => 160, 'description' => 20000, 'url' => 500];

    public function __construct(private readonly GlossaryRepository $glossary) {}

    /**
     * Vorgabewerte für das Formular: übernommene Begriffe mit den gemeinsamen Werten, lokale mit den offiziellen,
     * sonst den eigenen Formen. Kategorien als Schlüssel des Glossary (nur zugeordnete).
     *
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    public function defaults(array $entry): array
    {
        $text = fn (string $field): string => trim(is_scalar($entry[$field] ?? null) ? (string) $entry[$field] : '');

        return [
            'official_abbreviation' => $text('abk_offiziell') !== '' || is_array($entry['glossary'] ?? null) ? $text('abk_offiziell') : $text('abk_eigen'),
            'official_name' => $text('bez_offiziell') !== '' || is_array($entry['glossary'] ?? null) ? $text('bez_offiziell') : $text('bez_eigen'),
            'short_description' => $text('kurzbeschreibung'),
            'description' => $text('beschreibung'),
            'url' => $text('url'),
            'alternative_aliases' => array_values(array_map('strval', (array) ($entry['aliase_alternativ'] ?? []))),
            'categories' => array_values(array_filter(array_map(fn ($id): ?string => $this->categoryKeys()[(string) $id] ?? null, (array) ($entry['kategorien'] ?? [])))),
        ];
    }

    /**
     * Kategorien, die einen Schlüssel im Glossary haben: lokale ID → Schlüssel (Zuordnung des Syncs, sonst Feld
     * «schluessel» des Startinhalts).
     *
     * @return array<string, string>
     */
    public function categoryKeys(): array
    {
        $map = [];
        foreach ((array) ($this->glossary->syncState()['listen']['kategorien'] ?? []) as $key => $id) {
            $map[(string) $id] = (string) $key;
        }
        foreach ($this->glossary->listItems('kategorien') as $item) {
            $key = trim((string) ($item['schluessel'] ?? ''));
            if (! isset($map[(string) $item['id']]) && $key !== '') {
                $map[(string) $item['id']] = $key;
            }
        }

        return $map;
    }

    /**
     * Eingaben prüfen und den Body für POST /api/v1/proposals bilden. Bei Korrekturen nur geänderte Felder.
     *
     * @param  array<string, mixed>  $entry
     * @param  array<string, mixed>  $input  Felder wie in self::FIELDS (Listen als Liste oder Text mit «;»)
     * @return array{term?: string, changes: array<string, mixed>, reason: string}
     *
     * @throws InvalidArgumentException mit verständlicher Meldung
     */
    public function body(array $entry, array $input, string $reason): array
    {
        $changes = [];
        foreach (array_keys(self::FIELDS) as $field) {
            if (in_array($field, ['alternative_aliases', 'categories'], true)) {
                $value = $input[$field] ?? [];
                $items = is_array($value) ? $value : Glossary::split(is_scalar($value) ? (string) $value : '');
                $changes[$field] = array_values(array_unique(array_filter(array_map(fn ($v): string => trim(is_scalar($v) ? (string) $v : ''), $items), fn (string $v): bool => $v !== '')));
            } else {
                $changes[$field] = trim(is_scalar($input[$field] ?? null) ? (string) $input[$field] : '');
                if (mb_strlen($changes[$field]) > self::MAX[$field]) {
                    throw new InvalidArgumentException(self::LABELS[$field].' ist zu lang (max. '.self::MAX[$field].' Zeichen).');
                }
            }
        }
        if ($changes['url'] !== '' && preg_match('#^https?://\S+$#i', $changes['url']) !== 1) {
            throw new InvalidArgumentException('Die URL muss mit http:// oder https:// beginnen.');
        }
        $known = array_flip(array_values($this->categoryKeys()));
        foreach ($changes['categories'] as $key) {
            if (! isset($known[$key])) {
                throw new InvalidArgumentException("Kategorie «{$key}» gibt es im EduKSX-Glossary nicht.");
            }
        }
        $reason = trim($reason);
        if (mb_strlen($reason) > 2000) {
            throw new InvalidArgumentException('Die Begründung ist zu lang (max. 2000 Zeichen).');
        }

        if (is_array($entry['glossary'] ?? null)) {
            // Korrektur: nur, was sich gegenüber dem veröffentlichten Stand ändert
            $current = $this->defaults($entry);
            $changes = array_filter($changes, fn ($v, string $f): bool => $v != ($current[$f] ?? null), ARRAY_FILTER_USE_BOTH);
            if ($changes === []) {
                throw new InvalidArgumentException('Keine Änderung gegenüber dem veröffentlichten Begriff.');
            }

            return ['term' => (string) $entry['id'], 'changes' => $changes, 'reason' => $reason];
        }
        if ($changes['official_abbreviation'] === '' && $changes['official_name'] === '') {
            throw new InvalidArgumentException('Abkürzung oder Bezeichnung ist nötig.');
        }

        return ['changes' => array_filter($changes, fn ($v): bool => $v !== '' && $v !== []), 'reason' => $reason];
    }

    /**
     * Einreichen und am Eintrag vermerken.
     *
     * @param  Closure(array<string, mixed>): array<mixed>  $post  Body → Antwort (Vorschlag)
     * @param  array<string, mixed>  $entry
     * @param  array{term?: string, changes: array<string, mixed>, reason: string}  $body
     * @return array<string, mixed> Vermerk am Eintrag
     */
    public function submit(Closure $post, array $entry, array $body): array
    {
        $response = $post($body);
        $proposal = is_array($response['data'] ?? null) ? $response['data'] : $response;
        if (! is_numeric($proposal['id'] ?? null)) {
            throw new RuntimeException('Antwort des EduKSX-Glossary ohne Nummer des Vorschlags.');
        }
        $now = now()->toIso8601String();
        $note = [
            'id' => (int) $proposal['id'],
            'art' => isset($body['term']) ? 'korrektur' : 'neu',
            'status' => (string) ($proposal['status'] ?? 'eingereicht'),
            'term_id' => is_string($proposal['term_id'] ?? null) ? $proposal['term_id'] : null,
            'felder' => array_keys($body['changes']),
            'eingereicht_at' => $now,
            'aktualisiert_at' => $now,
            'resolved_at' => null,
        ];
        $this->glossary->update((string) $entry['id'], ['vorschlaege' => [...array_values((array) ($entry['vorschlaege'] ?? [])), $note]]);

        return $note;
    }

    /**
     * Offene Vorschläge nachfragen (GET /api/v1/proposals/{id}) und Stand, Begriff und Abschluss am Eintrag nachführen.
     *
     * @param  Closure(int): (array<mixed>|null)  $get  Nummer → Vorschlag (null = nicht gefunden)
     * @return int Anzahl geänderter Vorschläge
     */
    public function refresh(Closure $get, int $limit = 50): int
    {
        $changed = 0;
        foreach ($this->glossary->all() as $entry) {
            $notes = array_values((array) ($entry['vorschlaege'] ?? []));
            $dirty = false;
            foreach ($notes as $i => $note) {
                if (! is_array($note) || ! in_array($note['status'] ?? '', self::OPEN, true) || $limit <= 0) {
                    continue;
                }
                $limit--;
                $remote = $get((int) ($note['id'] ?? 0));
                $proposal = is_array($remote['data'] ?? null) ? $remote['data'] : $remote;
                if (! is_array($proposal)) {
                    continue;
                }
                $status = (string) ($proposal['status'] ?? $note['status']);
                $termId = is_string($proposal['term_id'] ?? null) ? $proposal['term_id'] : ($note['term_id'] ?? null);
                if ($status !== $note['status'] || $termId !== ($note['term_id'] ?? null)) {
                    $notes[$i] = ['status' => $status, 'term_id' => $termId, 'aktualisiert_at' => now()->toIso8601String(),
                        'resolved_at' => is_string($proposal['resolved_at'] ?? null) ? $proposal['resolved_at'] : null] + $note;
                    $dirty = true;
                    $changed++;
                }
            }
            if ($dirty) {
                $this->glossary->update((string) $entry['id'], ['vorschlaege' => $notes]);
            }
        }

        return $changed;
    }
}
