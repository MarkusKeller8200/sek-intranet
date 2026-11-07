<?php

namespace Plugins\EduksxGlossary;

use App\Data\Contracts\GlossaryRepository;
use App\Support\Glossary;
use Closure;
use RuntimeException;

/**
 * Sync mit dem EduKSX-Glossary über die API v1 (#156), nach dem Referenzclient App\Glossary\SyncClient des Glossary
 * (docs/SYNC-INTRANET.md dort):
 *
 * 1. Erster Lauf (oder «neu»): Vollstand /api/v1/exports?format=json, Cursor merken.
 * 2. Danach: /api/v1/releases/latest – ist der Cursor gleich, ist nichts zu tun; sonst /api/v1/changes?since=…
 *    seitenweise bis has_more = false. Der Cursor wird nach jeder verarbeiteten Seite gespeichert.
 * 3. Ereignisse: «published» übernimmt den Begriff (inaktiv → ausgeblendet), «unpublished» blendet aus,
 *    «merged» blendet aus und führt die lokalen Daten auf «merged_into» über. Unbekannte Arten: überspringen.
 * 4. Listen über /categories und /sources; im Begriff zählt der Schlüssel «key».
 *
 * Die IDs im Glossary sind die IDs des Intranets. Gemeinsame Felder (Glossary::SHARED) kommen aus dem Glossary;
 * lokale Felder (eigene Formen, «Intern genutzt in», Notiz, Suchbegriffe, Doppelte) werden nie überschrieben.
 * Es wird nie etwas gelöscht – zurückgezogene oder zusammengeführte Begriffe sind nur ausgeblendet (inaktiv).
 * Wiederholbar: ein zweiter Lauf ohne Änderungen im Glossary ändert nichts.
 */
final class Sync
{
    public const SOURCE = 'EduKSX-Glossary, glossary.eduksx.ch';

    public const LICENSE = 'CC BY 4.0';

    /** Zustände eines übernommenen Begriffs (Feld glossary.zustand) */
    public const PUBLISHED = 'veroeffentlicht';

    public const WITHDRAWN = 'zurueckgezogen';

    public const MERGED = 'zusammengefuehrt';

    private const LISTS = ['kategorien' => 'categories', 'herkunft' => 'sources'];

    /** @var array<string, mixed> */
    private array $state;

    /** @var array{neu: int, geaendert: int, ausgeblendet: int, zusammengefuehrt: int, uebersprungen: int} */
    private array $count = ['neu' => 0, 'geaendert' => 0, 'ausgeblendet' => 0, 'zusammengefuehrt' => 0, 'uebersprungen' => 0];

    /**
     * @param  Closure(string, array<string, mixed>): array<mixed>  $fetch  Endpunkt-ID und Parameter → JSON
     */
    public function __construct(
        private readonly GlossaryRepository $glossary,
        private readonly Closure $fetch,
    ) {
        $this->state = $glossary->syncState();
    }

    /**
     * @return array{art: string, neu: int, geaendert: int, ausgeblendet: int, zusammengefuehrt: int, uebersprungen: int, cursor: int}
     */
    public function run(bool $fresh = false): array
    {
        $cursor = $fresh || ! isset($this->state['cursor']) ? -1 : (int) $this->state['cursor'];
        if ($cursor < 0) {
            $export = ($this->fetch)('export', ['format' => 'json']);
            $this->syncLists('kategorien', self::items($export, 'categories'));
            $this->syncLists('herkunft', self::items($export, 'sources'));
            $seen = [];
            foreach (self::items($export, 'terms') as $term) {
                $seen[(string) ($term['id'] ?? '')] = true;
                $this->published($term);
            }
            // nicht mehr im Vollstand: zurückgezogen (nur Begriffe, die schon aus dem Glossary kamen)
            foreach ($this->glossary->all() as $entry) {
                if (self::synced($entry) && ! isset($seen[(string) $entry['id']]) && ($entry['glossary']['zustand'] ?? '') === self::PUBLISHED) {
                    $this->hide((string) $entry['id'], self::WITHDRAWN, null);
                }
            }
            $this->state['cursor'] = (int) ($export['cursor'] ?? 0);
            $this->state['release'] = (int) ($export['release'] ?? 0);
            $this->state['lizenz'] = is_array($export['license'] ?? null) ? $export['license'] : null;
            $this->save();
            $art = 'vollstand';
        } else {
            $latest = ($this->fetch)('releases-latest', []);
            $data = is_array($latest['data'] ?? null) ? $latest['data'] : $latest;
            if ((int) ($data['cursor'] ?? -1) === $cursor) {
                return ['art' => 'unveraendert'] + $this->count + ['cursor' => $cursor];
            }
            $this->syncLists('kategorien', self::items(($this->fetch)('categories', []), 'data'));
            $this->syncLists('herkunft', self::items(($this->fetch)('sources', []), 'data'));
            $art = 'aenderungen';
        }
        $this->changes();

        return ['art' => $art] + $this->count + ['cursor' => (int) $this->state['cursor']];
    }

    /**
     * Gespeicherter Stand (für die Anzeige).
     *
     * @return array<string, mixed>
     */
    public function state(): array
    {
        return $this->state;
    }

    private function changes(): void
    {
        for ($page = 0; $page < 200; $page++) {
            $json = ($this->fetch)('changes', ['since' => (int) $this->state['cursor'], 'limit' => 500]);
            foreach (self::items($json, 'data') as $event) {
                $id = (string) ($event['term_id'] ?? '');
                $action = $id === '' ? '' : (string) ($event['action'] ?? '');
                if ($action === 'published' && is_array($event['term'] ?? null)) {
                    $this->published($event['term']);
                } elseif ($action === 'published' || $action === 'unpublished') {
                    $this->hide($id, self::WITHDRAWN, null);
                } elseif ($action === 'merged') {
                    $this->merged($id, is_string($event['merged_into'] ?? null) ? $event['merged_into'] : null);
                } else {
                    // unbekannte Ereignisart: überspringen (neue Arten sind abwärtskompatibel)
                    $this->count['uebersprungen']++;
                }
                $this->state['cursor'] = max((int) $this->state['cursor'], (int) ($event['cursor'] ?? 0));
            }
            $meta = is_array($json['meta'] ?? null) ? $json['meta'] : [];
            $this->state['cursor'] = max((int) $this->state['cursor'], (int) ($meta['next_cursor'] ?? 0));
            $this->state['release'] = (int) ($meta['release'] ?? $this->state['release'] ?? 0);
            // Cursor erst nach der verarbeiteten Seite speichern
            $this->save();
            if (($meta['has_more'] ?? false) !== true) {
                return;
            }
        }
        throw new RuntimeException('Zu viele Seiten im Change-Feed – beim nächsten Lauf geht es weiter.');
    }

    /**
     * Begriff in genau dieser Revision übernehmen (lokale Felder bleiben).
     *
     * @param  array<mixed>  $term
     */
    private function published(array $term): void
    {
        $id = (string) ($term['id'] ?? '');
        if ($id === '') {
            return;
        }
        $data = $this->map($term);
        $existing = $this->glossary->find($id);
        if ($existing === null) {
            $this->glossary->create(['id' => $id] + $data + Glossary::blank());
            $this->count['neu']++;
        } elseif ($this->differs($existing, $data)) {
            $this->glossary->update($id, $data);
            $this->count['geaendert']++;
        }
        // wieder aufgenommen: keine Umleitung mehr; früher zusammengeführte Begriffe: lokale Daten hierher übernehmen
        $redirects = (array) ($this->state['umleitungen'] ?? []);
        unset($redirects[$id]);
        $this->state['umleitungen'] = $redirects;
        foreach ($redirects as $from => $to) {
            if ($to === $id) {
                $this->transferLocal((string) $from, $id);
            }
        }
    }

    private function hide(string $id, string $zustand, ?string $mergedInto): void
    {
        $entry = $this->glossary->find($id);
        if ($entry === null) {
            return;
        }
        $new = is_array($entry['glossary'] ?? null) ? $entry['glossary'] : [];
        $new['zustand'] = $zustand;
        unset($new['merged_into']);
        if ($mergedInto !== null) {
            $new['merged_into'] = $mergedInto;
        }
        $data = ['aktiv' => false, 'glossary' => $new];
        if ($this->differs($entry, $data)) {
            $this->glossary->update($id, $data);
            $this->count[$zustand === self::MERGED ? 'zusammengefuehrt' : 'ausgeblendet']++;
        }
    }

    private function merged(string $id, ?string $into): void
    {
        $this->hide($id, self::MERGED, $into);
        if ($into === null || $into === $id) {
            return;
        }
        $redirects = (array) ($this->state['umleitungen'] ?? []);
        $redirects[$id] = $into;
        $this->state['umleitungen'] = $redirects;
        $this->transferLocal($id, $into);
    }

    /**
     * Lokale Daten eines zusammengeführten Begriffs auf den verbleibenden übertragen: «Intern genutzt in» und
     * Doppelte vereinigen, eigene Formen und Notiz nur, wo das Ziel leer ist; alte ID als frühere ID vermerken.
     */
    private function transferLocal(string $from, string $to): void
    {
        $source = $this->glossary->find($from);
        $target = $this->glossary->find($to);
        if ($source === null || $target === null) {
            return;
        }
        $data = [];
        foreach (['genutzt_in', 'dubletten_ok'] as $field) {
            $data[$field] = array_values(array_unique([...array_map('strval', (array) ($target[$field] ?? [])), ...array_map('strval', (array) ($source[$field] ?? []))]));
        }
        foreach (Glossary::LOCAL_TEXT as $field) {
            if (trim((string) ($target[$field] ?? '')) === '' && trim((string) ($source[$field] ?? '')) !== '') {
                $data[$field] = $source[$field];
            }
        }
        $data['fruehere_ids'] = array_values(array_unique([...array_map('strval', (array) ($target['fruehere_ids'] ?? [])), $from]));
        if ($this->differs($target, $data)) {
            $this->glossary->update($to, $data);
        }
    }

    /**
     * Begriff der API → gemeinsame Felder des Intranets und Kennzeichen «aus EduKSX-Glossary».
     *
     * @param  array<mixed>  $term
     * @return array<string, mixed>
     */
    private function map(array $term): array
    {
        $text = fn (string $key): string => is_scalar($term[$key] ?? null) ? trim((string) $term[$key]) : '';
        $list = fn (string $key): array => array_values(array_unique(array_filter(array_map(fn ($v): string => is_scalar($v) ? trim((string) $v) : '', (array) ($term[$key] ?? [])), fn (string $v): bool => $v !== '')));
        $date = fn (string $key): ?string => preg_match('/^\d{4}-\d{2}-\d{2}/', $text($key), $m) === 1 ? $m[0] : null;
        $status = $text('status');

        return [
            'abk_offiziell' => $text('abbreviation'),
            'bez_offiziell' => $text('name'),
            'aliase_offiziell' => $list('official_aliases'),
            'aliase_alternativ' => $list('alternative_aliases'),
            'kurzbeschreibung' => $text('short_description'),
            'beschreibung' => $text('description'),
            'url' => $text('url'),
            'kategorien' => $this->refs('kategorien', $term['categories'] ?? []),
            'herkunft' => $this->refs('herkunft', $term['sources'] ?? []),
            'status' => isset(Glossary::STATUS[$status]) ? $status : 'aktuell',
            'aktiv' => ($term['is_active'] ?? true) !== false,
            'gueltig_ab' => $date('valid_from'),
            'gueltig_bis' => $date('valid_until'),
            'glossary' => array_filter([
                'zustand' => self::PUBLISHED,
                'revision' => is_int($term['revision'] ?? null) ? $term['revision'] : null,
                'slug' => $text('slug'),
                'web_url' => str_starts_with($text('web_url'), 'https://') ? $text('web_url') : '',
                'published_at' => $text('published_at'),
            ], fn ($v): bool => $v !== null && $v !== ''),
        ];
    }

    /**
     * Schlüssel der Listen (im Begriff als {key, …} oder als Text) → IDs der Listen im Intranet.
     *
     * @return list<string>
     */
    private function refs(string $list, mixed $items): array
    {
        $map = (array) ($this->state['listen'][$list] ?? []);
        $ids = [];
        foreach ((array) $items as $item) {
            $key = is_array($item) ? (string) ($item['key'] ?? '') : (is_scalar($item) ? (string) $item : '');
            if ($key !== '') {
                $ids[] = (string) ($map[$key] ?? $key);
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Listen abgleichen: Schlüssel = ID, sonst Kürzel, Label oder Bezeichnung (ohne Gross-/Kleinschreibung).
     * Bezeichnung und URL kommen aus dem Glossary; Farbe, Label, Beschreibung und Notiz bleiben lokal. Fehlende
     * Einträge werden mit dem Schlüssel als ID angelegt. Nie löschen.
     *
     * @param  list<array<mixed>>  $remote
     */
    private function syncLists(string $list, array $remote): void
    {
        $local = $this->glossary->listItems($list);
        $map = [];
        foreach ($remote as $item) {
            $key = trim((string) ($item['key'] ?? ''));
            if ($key === '') {
                continue;
            }
            $name = trim((string) ($item['name'] ?? $key));
            $label = trim((string) ($item['label'] ?? $key));
            $url = is_string($item['url'] ?? null) && str_starts_with($item['url'], 'https://') ? $item['url'] : '';
            $found = null;
            foreach ([['id', $key], ['kuerzel', $key], ['kuerzel', $label], ['label', $label], ['bezeichnung', $name]] as [$field, $value]) {
                foreach ($local as $row) {
                    if (mb_strtolower(trim((string) ($row[$field] ?? ''))) === mb_strtolower($value)) {
                        $found = $row;
                        break 2;
                    }
                }
            }
            if ($found === null) {
                $found = $this->glossary->saveListItem($list, null, [
                    'id' => $key, 'kuerzel' => mb_substr($label, 0, 20), 'label' => mb_substr($label, 0, 30), 'farbe' => '',
                    'bezeichnung' => $name, 'beschreibung' => '', 'url' => $url, 'notiz' => '',
                ]);
                $local[] = $found;
            } elseif ((string) ($found['bezeichnung'] ?? '') !== $name || (string) ($found['url'] ?? '') !== $url) {
                $this->glossary->saveListItem($list, (string) $found['id'], ['bezeichnung' => $name, 'url' => $url]);
            }
            $map[$key] = (string) $found['id'];
        }
        $lists = (array) ($this->state['listen'] ?? []);
        $lists[$list] = $map;
        $this->state['listen'] = $lists;
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  array<string, mixed>  $data
     */
    private function differs(array $entry, array $data): bool
    {
        foreach ($data as $field => $value) {
            if (($entry[$field] ?? null) != $value) {
                return true;
            }
        }

        return false;
    }

    private function save(): void
    {
        $this->glossary->saveSyncState($this->state);
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    public static function synced(array $entry): bool
    {
        return is_array($entry['glossary'] ?? null);
    }

    /**
     * @param  array<mixed>  $json
     * @return list<array<mixed>>
     */
    private static function items(array $json, string $key): array
    {
        $items = $json[$key] ?? null;
        if (! is_array($items)) {
            throw new RuntimeException("Antwort des Glossary ohne «{$key}».");
        }

        return array_values(array_filter($items, 'is_array'));
    }
}
