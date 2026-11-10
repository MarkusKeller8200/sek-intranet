<?php

namespace App\Support;

use App\Data\Store\JsonStore;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use ZipArchive;

/**
 * Sicherung und Wiederherstellung der ganzen Installation (#174).
 *
 * Inhalt: alle Sammlungen (Core und Module, je mit Sperre gelesen) und die Dateien der Disks «local» und «public»;
 * nicht: APP_KEY, Logs, Zwischenspeicher, Sitzungen, die Sicherungen selbst. Format: ZIP mit manifest.json
 * (Version, Inhalt, SHA-256 je Eintrag).
 *
 * Zugangsdaten (Plugin-Geheimnisse, Webhook-Geheimnisse) sind mit dem APP_KEY verschlüsselt und darum
 * auf einem anderen Server unlesbar. Je nach Art:
 * - «klartext»: nur mit Passwort – entschlüsselt ins Paket, das als Ganzes verschlüsselt wird; beim
 *   Wiederherstellen mit dem Schlüssel des Zielservers neu verschlüsselt.
 * - «ohne»: Download ohne Passwort – Zugangsdaten leer.
 * - «server»: Sicherung auf dem Server – Werte unverändert (gleicher APP_KEY).
 *
 * Verschlüsselung mit Passwort: Argon2id (libsodium) leitet den Schlüssel ab, XChaCha20-Poly1305 (secretstream)
 * verschlüsselt die ZIP-Datei in Blöcken. Eine falsche Eingabe oder Veränderung fällt beim Entschlüsseln auf.
 */
final class Backups
{
    public const FORMAT = 1;

    public const MIN_PASSWORD = 12;

    /** Ordner auf der Disk «local» */
    public const DIR = 'sicherungen';

    private const TEMP = 'sicherung-temp';

    private const MAGIC = 'SEKBAK01';

    private const CHUNK = 1048576;

    /** Obergrenzen beim Einlesen (Schutz vor «ZIP-Bomben») */
    private const MAX_BYTES = 4294967296;

    private const MAX_ENTRIES = 200000;

    /** Felder mit APP_KEY-verschlüsselten Werten: Sammlung => [Feld, Art (map = Liste von Werten, value = ein Wert)] */
    private const SECRET_FIELDS = [
        'plugin_states' => ['secrets', 'map'],
        'signature_requests' => ['callback_secret', 'value'],
    ];

    private const DATA_ENTRY = '#^data/((?:modules/[a-z][a-z0-9-]{1,40}/)?)([a-z][a-z0-9_]{0,63})\.json$#';

    private const FILE_ENTRY = '#^files/(private|public)/(.+)$#';

    public function __construct(private readonly JsonStore $store) {}

    /**
     * Sicherung erstellen. Ergebnis ist eine Datei im Arbeitsordner; der Aufrufer liefert sie aus oder legt sie ab.
     *
     * @return array{path: string, name: string, manifest: array<string, mixed>}
     */
    public function create(string $secrets, ?string $password, ?string $by): array
    {
        if (! in_array($secrets, ['klartext', 'ohne', 'server'], true)) {
            throw new RuntimeException("Unbekannte Art: {$secrets}");
        }
        if ($secrets === 'klartext' && ($password === null || $password === '')) {
            throw new RuntimeException('Zugangsdaten im Klartext nur mit Passwort.');
        }
        $zipPath = $this->tempDir().DIRECTORY_SEPARATOR.Str::random(24).'.zip';
        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
            throw new RuntimeException('Sicherung konnte nicht angelegt werden.');
        }

        $sums = [];
        $collections = [];
        foreach ($this->dataDirectories() as $rel => $dir) {
            $store = $rel === '' ? $this->store : new JsonStore($dir, (string) config('app.timezone', 'Europe/Zurich'));
            foreach ($store->collections() as $collection) {
                $records = $store->all($collection);
                if ($rel === '') {
                    $records = $this->exportSecrets($collection, $records, $secrets);
                }
                $json = json_encode($records, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                $entry = "data/{$rel}{$collection}.json";
                $zip->addFromString($entry, $json);
                $sums[$entry] = hash('sha256', $json);
                $collections[$rel.$collection] = count($records);
            }
        }

        $files = 0;
        $bytes = 0;
        foreach ($this->fileRoots() as $disk => $root) {
            foreach ($this->filesIn($root) as $rel => $file) {
                $entry = "files/{$disk}/{$rel}";
                $zip->addFile($file->getPathname(), $entry);
                $sums[$entry] = (string) hash_file('sha256', $file->getPathname());
                $files++;
                $bytes += (int) $file->getSize();
            }
        }

        $manifest = [
            'format' => self::FORMAT,
            'app' => 'SekBülach Intranet',
            'version' => (string) config('intranet.version', '0.0.0'),
            'erstellt_am' => now()->toIso8601String(),
            'erstellt_von' => $by,
            'quelle' => (string) parse_url((string) config('app.url'), PHP_URL_HOST),
            'zugangsdaten' => $secrets,
            'sammlungen' => $collections,
            'dateien' => $files,
            'dateien_bytes' => $bytes,
            'pruefsummen' => $sums,
        ];
        $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        if (! $zip->close()) {
            @unlink($zipPath);
            throw new RuntimeException('Sicherung konnte nicht geschrieben werden.');
        }

        $stamp = now()->format('Y-m-d-Hi');
        $host = Str::slug((string) ($manifest['quelle'] !== '' ? $manifest['quelle'] : 'intranet'));
        if ($password !== null && $password !== '') {
            $encrypted = $zipPath.'.enc';
            $this->encryptFile($zipPath, $encrypted, $password);
            @unlink($zipPath);

            return ['path' => $encrypted, 'name' => "sicherung-{$host}-{$stamp}.intranet-backup", 'manifest' => $manifest];
        }

        return ['path' => $zipPath, 'name' => "sicherung-{$host}-{$stamp}.zip", 'manifest' => $manifest];
    }

    /**
     * Sicherung auf dem Server ablegen (Zugangsdaten unverändert, ohne Passwort).
     *
     * @return array{name: string, size: int, created_at: string, art: string}
     */
    public function createOnServer(string $art, ?string $by): array
    {
        $result = $this->create('server', null, $by);
        $name = $art.'-'.now()->format('Y-m-d-His').'.zip';
        $disk = Storage::disk('local');
        $disk->makeDirectory(self::DIR);
        if (! @rename($result['path'], $disk->path(self::DIR.'/'.$name))) {
            @unlink($result['path']);
            throw new RuntimeException('Sicherung konnte nicht abgelegt werden.');
        }

        return $this->serverInfo($name) ?? throw new RuntimeException('Sicherung fehlt nach dem Ablegen.');
    }

    /**
     * Sicherungen auf dem Server, neueste zuerst.
     *
     * @return list<array{name: string, size: int, created_at: string, art: string}>
     */
    public function serverBackups(): array
    {
        $list = [];
        foreach (Storage::disk('local')->files(self::DIR) as $file) {
            $info = $this->serverInfo(basename($file));
            if ($info !== null) {
                $list[] = $info;
            }
        }
        usort($list, fn (array $a, array $b): int => strcmp($b['created_at'], $a['created_at']));

        return $list;
    }

    /** Pfad einer Sicherung auf dem Server (nur gültige Namen), sonst null. */
    public function serverPath(string $name): ?string
    {
        if (preg_match('/^(manuell|vor-wiederherstellung)-\d{4}-\d{2}-\d{2}-\d{6}\.zip$/', $name) !== 1) {
            return null;
        }
        $path = Storage::disk('local')->path(self::DIR.'/'.$name);

        return is_file($path) ? $path : null;
    }

    public function deleteServerBackup(string $name): bool
    {
        $path = $this->serverPath($name);

        return $path !== null && @unlink($path);
    }

    /**
     * Sicherung prüfen und für die Wiederherstellung bereitlegen (entpackt in den Arbeitsordner).
     * Verschlüsselte Dateien brauchen das Passwort.
     *
     * @return array{token: string, manifest: array<string, mixed>, aktuell: array<string, int>}
     *
     * @throws ValidationException bei falschem Passwort, beschädigter oder ungültiger Datei
     */
    public function prepare(string $file, ?string $password): array
    {
        $this->cleanupTemp();
        $zipPath = $file;
        $decrypted = null;
        $head = (string) @file_get_contents($file, false, null, 0, 8);
        if ($head === self::MAGIC) {
            if ($password === null || $password === '') {
                throw ValidationException::withMessages(['passwort' => 'Diese Sicherung ist mit einem Passwort geschützt.']);
            }
            $decrypted = $this->tempDir().DIRECTORY_SEPARATOR.Str::random(24).'.zip';
            try {
                $this->decryptFile($file, $decrypted, $password);
            } catch (RuntimeException) {
                @unlink($decrypted);
                throw ValidationException::withMessages(['passwort' => 'Passwort falsch oder Datei beschädigt.']);
            }
            $zipPath = $decrypted;
        } elseif (! str_starts_with($head, "PK\x03\x04")) {
            throw ValidationException::withMessages(['datei' => 'Keine Sicherung des Intranets (.zip oder .intranet-backup).']);
        }

        $token = Str::random(40);
        $target = $this->tempDir().DIRECTORY_SEPARATOR.$token;
        try {
            $manifest = $this->extract($zipPath, $target);
        } catch (ValidationException $e) {
            $this->removeDir($target);
            throw $e;
        } finally {
            if ($decrypted !== null) {
                @unlink($decrypted);
            }
        }

        return ['token' => $token, 'manifest' => $this->summary($manifest), 'aktuell' => $this->currentCounts()];
    }

    /** Vorbereitete Sicherung verwerfen. */
    public function discard(string $token): void
    {
        if (preg_match('/^[A-Za-z0-9]{40}$/', $token) === 1) {
            $this->removeDir($this->tempDir().DIRECTORY_SEPARATOR.$token);
        }
    }

    /**
     * Vorbereitete Sicherung wiederherstellen: zuerst den aktuellen Stand auf dem Server sichern, dann alle
     * Sammlungen und Dateien ersetzen und die Daten auf den Stand dieser Version bringen.
     *
     * @return array{vorher: string, manifest: array<string, mixed>}
     */
    public function restore(string $token, ?string $by): array
    {
        if (preg_match('/^[A-Za-z0-9]{40}$/', $token) !== 1) {
            throw new RuntimeException('Ungültige Vorbereitung.');
        }
        $dir = $this->tempDir().DIRECTORY_SEPARATOR.$token;
        $manifest = $this->readManifest($dir.DIRECTORY_SEPARATOR.'manifest.json');
        if ($manifest === null) {
            throw new RuntimeException('Die vorbereitete Sicherung ist nicht mehr vorhanden. Bitte nochmals hochladen.');
        }

        $before = $this->createOnServer('vor-wiederherstellung', $by);
        $mode = (string) ($manifest['zugangsdaten'] ?? 'ohne');

        // Sammlungen: alle aus der Sicherung schreiben, übrige leeren
        $restored = [];
        $dataDir = $dir.DIRECTORY_SEPARATOR.'data';
        foreach ($this->jsonFiles($dataDir) as $rel => $file) {
            if (preg_match(self::DATA_ENTRY, 'data/'.$rel, $m) !== 1) {
                continue;
            }
            $records = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($records) || ! array_is_list($records)) {
                continue;
            }
            /** @var list<array<string, mixed>> $records */
            $records = array_values(array_filter($records, 'is_array'));
            if ($m[1] === '') {
                $records = $this->importSecrets($m[2], $records, $mode);
            }
            $this->storeFor($m[1])->replace($m[2], $records);
            $restored[$m[1].$m[2]] = true;
        }
        foreach ($this->dataDirectories() as $rel => $path) {
            $store = $this->storeFor($rel);
            foreach ($store->collections() as $collection) {
                if (! isset($restored[$rel.$collection])) {
                    $store->replace($collection, []);
                }
            }
        }

        // Dateien ersetzen (ohne Sicherungen und Arbeitsordner)
        foreach ($this->fileRoots() as $disk => $root) {
            foreach ($this->filesIn($root) as $file) {
                @unlink($file->getPathname());
            }
            $source = $dir.DIRECTORY_SEPARATOR.'files'.DIRECTORY_SEPARATOR.$disk;
            if (is_dir($source)) {
                foreach ($this->allFiles($source) as $rel => $file) {
                    $targetFile = $root.DIRECTORY_SEPARATOR.$rel;
                    if (! is_dir(dirname($targetFile))) {
                        mkdir(dirname($targetFile), 0775, true);
                    }
                    copy($file->getPathname(), $targetFile);
                }
            }
        }

        $this->removeDir($dir);
        Artisan::call('intranet:upgrade');

        return ['vorher' => $before['name'], 'manifest' => $this->summary($manifest)];
    }

    /**
     * Angaben zur vorbereiteten Sicherung (für die Vorschau nach einem Neuladen der Seite).
     *
     * @return array<string, mixed>|null
     */
    public function prepared(string $token): ?array
    {
        if (preg_match('/^[A-Za-z0-9]{40}$/', $token) !== 1) {
            return null;
        }
        $manifest = $this->readManifest($this->tempDir().DIRECTORY_SEPARATOR.$token.DIRECTORY_SEPARATOR.'manifest.json');

        return $manifest !== null ? $this->summary($manifest) : null;
    }

    /**
     * Anzahl Datensätze je Sammlung im aktuellen Stand (für den Vergleich in der Vorschau).
     *
     * @return array<string, int>
     */
    public function currentCounts(): array
    {
        $counts = [];
        foreach ($this->dataDirectories() as $rel => $dir) {
            $store = $this->storeFor($rel);
            foreach ($store->collections() as $collection) {
                $counts[$rel.$collection] = $store->count($collection);
            }
        }

        return $counts;
    }

    // ---------------------------------------------------------------- Einlesen

    /**
     * ZIP prüfen und entpacken: nur erwartete Einträge, Grössen begrenzt, Prüfsummen und Version stimmen.
     *
     * @return array<string, mixed>
     */
    private function extract(string $zipPath, string $target): array
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::RDONLY) !== true) {
            throw ValidationException::withMessages(['datei' => 'Die Datei lässt sich nicht öffnen (keine gültige ZIP-Datei).']);
        }
        try {
            $raw = $zip->getFromName('manifest.json');
            $manifest = is_string($raw) ? json_decode($raw, true) : null;
            if (! is_array($manifest) || ($manifest['format'] ?? null) !== self::FORMAT || ! is_array($manifest['pruefsummen'] ?? null)) {
                throw ValidationException::withMessages(['datei' => 'Keine Sicherung des Intranets oder unbekanntes Format.']);
            }
            $version = (string) ($manifest['version'] ?? '');
            if ($version === '' || version_compare($version, (string) config('intranet.version', '0.0.0'), '>')) {
                throw ValidationException::withMessages(['datei' => "Die Sicherung stammt von einer neueren Version ({$version}). Zuerst das Intranet aktualisieren."]);
            }
            if (! in_array($manifest['zugangsdaten'] ?? null, ['klartext', 'ohne', 'server'], true)) {
                throw ValidationException::withMessages(['datei' => 'Unbekannte Angabe zu den Zugangsdaten in der Sicherung.']);
            }
            /** @var array<string, string> $sums */
            $sums = array_filter($manifest['pruefsummen'], 'is_string');
            if ($zip->numFiles > self::MAX_ENTRIES || count($sums) + 1 !== $zip->numFiles) {
                throw ValidationException::withMessages(['datei' => 'Die Sicherung ist unvollständig oder enthält fremde Dateien.']);
            }

            $total = 0;
            $hasUsers = false;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if ($stat === false) {
                    throw ValidationException::withMessages(['datei' => 'Die Sicherung ist beschädigt.']);
                }
                $name = (string) $stat['name'];
                if ($name === 'manifest.json') {
                    continue;
                }
                if (! $this->validEntry($name) || ! isset($sums[$name])) {
                    throw ValidationException::withMessages(['datei' => "Unerwarteter Eintrag in der Sicherung: {$name}"]);
                }
                $total += (int) $stat['size'];
                if ($total > self::MAX_BYTES) {
                    throw ValidationException::withMessages(['datei' => 'Die Sicherung ist zu gross.']);
                }
                $out = $target.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $name);
                if (! is_dir(dirname($out))) {
                    mkdir(dirname($out), 0700, true);
                }
                $in = $zip->getStream($name);
                $fh = fopen($out, 'wb');
                if ($in === false || $fh === false) {
                    throw ValidationException::withMessages(['datei' => 'Die Sicherung ist beschädigt.']);
                }
                stream_copy_to_stream($in, $fh);
                fclose($in);
                fclose($fh);
                if (! hash_equals($sums[$name], (string) hash_file('sha256', $out))) {
                    throw ValidationException::withMessages(['datei' => "Prüfsumme stimmt nicht: {$name}"]);
                }
                if (str_starts_with($name, 'data/')) {
                    $records = json_decode((string) file_get_contents($out), true);
                    if (! is_array($records) || ! array_is_list($records)) {
                        throw ValidationException::withMessages(['datei' => "Ungültige Daten: {$name}"]);
                    }
                    if ($name === 'data/users.json' && $records !== []) {
                        $hasUsers = true;
                    }
                }
            }
            if (! $hasUsers) {
                throw ValidationException::withMessages(['datei' => 'Die Sicherung enthält keine Personen – Wiederherstellen nicht möglich.']);
            }
            file_put_contents($target.DIRECTORY_SEPARATOR.'manifest.json', (string) $raw);

            return $manifest;
        } finally {
            $zip->close();
        }
    }

    private function validEntry(string $name): bool
    {
        if (str_contains($name, '\\') || str_contains($name, "\0") || str_starts_with($name, '/')) {
            return false;
        }
        if (preg_match(self::DATA_ENTRY, $name) === 1) {
            return true;
        }
        if (preg_match(self::FILE_ENTRY, $name, $m) !== 1) {
            return false;
        }
        foreach (explode('/', $m[2]) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return false;
            }
        }

        return ! ($m[1] === 'private' && (str_starts_with($m[2], self::DIR.'/') || str_starts_with($m[2], self::TEMP.'/')));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readManifest(string $path): ?array
    {
        if (! is_file($path)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? $data : null;
    }

    /**
     * Angaben für die Oberfläche (ohne Prüfsummen).
     *
     * @param  array<string, mixed>  $manifest
     * @return array<string, mixed>
     */
    private function summary(array $manifest): array
    {
        unset($manifest['pruefsummen']);

        return $manifest;
    }

    // ---------------------------------------------------------------- Zugangsdaten

    /**
     * @param  list<array<string, mixed>>  $records
     * @return list<array<string, mixed>>
     */
    private function exportSecrets(string $collection, array $records, string $mode): array
    {
        if ($mode === 'server' || ! isset(self::SECRET_FIELDS[$collection])) {
            return $records;
        }
        [$field, $kind] = self::SECRET_FIELDS[$collection];
        foreach ($records as $i => $record) {
            if (! array_key_exists($field, $record)) {
                continue;
            }
            if ($mode === 'ohne') {
                if ($kind === 'map') {
                    $records[$i][$field] = [];
                } else {
                    unset($records[$i][$field]);
                }

                continue;
            }
            $records[$i][$field] = $kind === 'map'
                ? array_filter(array_map(fn ($v): ?string => is_string($v) ? $this->decrypt($v) : null, is_array($record[$field]) ? $record[$field] : []), 'is_string')
                : (is_string($record[$field]) ? $this->decrypt($record[$field]) : null);
        }

        return $records;
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @return list<array<string, mixed>>
     */
    private function importSecrets(string $collection, array $records, string $mode): array
    {
        if ($mode !== 'klartext' || ! isset(self::SECRET_FIELDS[$collection])) {
            return $records;
        }
        [$field, $kind] = self::SECRET_FIELDS[$collection];
        foreach ($records as $i => $record) {
            if (! array_key_exists($field, $record)) {
                continue;
            }
            if ($kind === 'map') {
                $values = is_array($record[$field]) ? $record[$field] : [];
                $records[$i][$field] = array_map(fn ($v): string => Crypt::encryptString((string) $v), array_filter($values, 'is_string'));
            } elseif (is_string($record[$field])) {
                $records[$i][$field] = Crypt::encryptString($record[$field]);
            } else {
                unset($records[$i][$field]);
            }
        }

        return $records;
    }

    private function decrypt(string $value): ?string
    {
        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            return null;
        }
    }

    // ---------------------------------------------------------------- Verschlüsselung

    private function encryptFile(string $source, string $target, string $password): void
    {
        $salt = random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES);
        $ops = SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE;
        $mem = SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE;
        $key = sodium_crypto_pwhash(SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES, $password, $salt, $ops, $mem, SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13);
        [$state, $header] = sodium_crypto_secretstream_xchacha20poly1305_init_push($key);
        sodium_memzero($key);

        $in = fopen($source, 'rb');
        $out = fopen($target, 'wb');
        if ($in === false || $out === false) {
            throw new RuntimeException('Verschlüsseln nicht möglich.');
        }
        fwrite($out, self::MAGIC.pack('P', $ops).pack('P', $mem).$salt.$header);
        // Mit Vorausschau lesen: der letzte Block bekommt die Endmarke (auch bei einer leeren Datei)
        $chunk = $this->readChunk($in);
        while (true) {
            $next = $chunk === '' ? '' : $this->readChunk($in);
            $last = $next === '';
            fwrite($out, sodium_crypto_secretstream_xchacha20poly1305_push($state, $chunk, '', $last ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE));
            if ($last) {
                break;
            }
            $chunk = $next;
        }
        fclose($in);
        fclose($out);
    }

    private function decryptFile(string $source, string $target, string $password): void
    {
        $in = fopen($source, 'rb');
        if ($in === false) {
            throw new RuntimeException('Datei nicht lesbar.');
        }
        try {
            $headLength = strlen(self::MAGIC) + 16 + SODIUM_CRYPTO_PWHASH_SALTBYTES + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES;
            $head = (string) fread($in, $headLength);
            if (strlen($head) !== $headLength || ! str_starts_with($head, self::MAGIC)) {
                throw new RuntimeException('Ungültiger Kopf.');
            }
            $offset = strlen(self::MAGIC);
            $ops = self::uint64(substr($head, $offset, 8));
            $mem = self::uint64(substr($head, $offset + 8, 8));
            // Grenzen, damit eine manipulierte Datei den Server nicht lahmlegt
            if ($ops < 1 || $ops > SODIUM_CRYPTO_PWHASH_OPSLIMIT_SENSITIVE || $mem < 8192 || $mem > SODIUM_CRYPTO_PWHASH_MEMLIMIT_MODERATE) {
                throw new RuntimeException('Ungültige Parameter.');
            }
            $salt = substr($head, $offset + 16, SODIUM_CRYPTO_PWHASH_SALTBYTES);
            $header = substr($head, $offset + 16 + SODIUM_CRYPTO_PWHASH_SALTBYTES);
            $key = sodium_crypto_pwhash(SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES, $password, $salt, $ops, $mem, SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13);
            $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull($header, $key);
            sodium_memzero($key);

            $out = fopen($target, 'wb');
            if ($out === false) {
                throw new RuntimeException('Ziel nicht beschreibbar.');
            }
            $final = false;
            while (! feof($in)) {
                $chunk = $this->readChunk($in, self::CHUNK + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES);
                if ($chunk === '') {
                    break;
                }
                if ($final) {
                    throw new RuntimeException('Daten nach dem Ende.');
                }
                $result = sodium_crypto_secretstream_xchacha20poly1305_pull($state, $chunk);
                if ($result === false) {
                    throw new RuntimeException('Entschlüsseln fehlgeschlagen.');
                }
                fwrite($out, $result[0]);
                $final = $result[1] === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL;
            }
            fclose($out);
            if (! $final) {
                throw new RuntimeException('Datei unvollständig.');
            }
        } finally {
            fclose($in);
        }
    }

    /** Genau einen Block lesen (fread liefert bei Streams teils weniger). */
    private function readChunk(mixed $handle, int $size = self::CHUNK): string
    {
        $data = '';
        while (strlen($data) < $size && is_resource($handle) && ! feof($handle)) {
            $part = fread($handle, max(1, $size - strlen($data)));
            if ($part === false || $part === '') {
                break;
            }
            $data .= $part;
        }

        return $data;
    }

    private static function uint64(string $bytes): int
    {
        $values = unpack('P', $bytes);

        return is_array($values) && is_int($values[1] ?? null) ? $values[1] : 0;
    }

    // ---------------------------------------------------------------- Dateisystem

    /**
     * Ordner mit Sammlungen: Core ('') und Module ('modules/<key>/').
     *
     * @return array<string, string>
     */
    private function dataDirectories(): array
    {
        $root = $this->store->path();
        $dirs = ['' => $root];
        foreach (glob($root.DIRECTORY_SEPARATOR.'modules'.DIRECTORY_SEPARATOR.'*', GLOB_ONLYDIR) ?: [] as $dir) {
            $key = basename($dir);
            if (preg_match('/^[a-z][a-z0-9-]{1,40}$/', $key) === 1) {
                $dirs["modules/{$key}/"] = $dir;
            }
        }

        return $dirs;
    }

    private function storeFor(string $rel): JsonStore
    {
        if ($rel === '') {
            return $this->store;
        }
        $dir = $this->store->path().DIRECTORY_SEPARATOR.rtrim(str_replace('/', DIRECTORY_SEPARATOR, $rel), DIRECTORY_SEPARATOR);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return new JsonStore($dir, (string) config('app.timezone', 'Europe/Zurich'));
    }

    /**
     * @return array<string, string>
     */
    private function fileRoots(): array
    {
        return ['private' => Storage::disk('local')->path(''), 'public' => Storage::disk('public')->path('')];
    }

    /**
     * Dateien einer Disk ohne Sicherungen, Arbeitsordner und versteckte Dateien.
     *
     * @return array<string, SplFileInfo>
     */
    private function filesIn(string $root): array
    {
        $root = rtrim($root, DIRECTORY_SEPARATOR);
        $isPrivate = $root === rtrim(Storage::disk('local')->path(''), DIRECTORY_SEPARATOR);
        $files = [];
        foreach ($this->allFiles($root) as $rel => $file) {
            if ($isPrivate && (str_starts_with($rel, self::DIR.'/') || str_starts_with($rel, self::TEMP.'/'))) {
                continue;
            }
            if (str_starts_with(basename($rel), '.')) {
                continue;
            }
            $files[$rel] = $file;
        }

        return $files;
    }

    /**
     * @return array<string, SplFileInfo>
     */
    private function allFiles(string $root): array
    {
        $root = rtrim($root, DIRECTORY_SEPARATOR);
        if (! is_dir($root)) {
            return [];
        }
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && ! $file->isLink()) {
                $rel = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($root) + 1));
                $files[$rel] = $file;
            }
        }
        ksort($files);

        return $files;
    }

    /**
     * @return array<string, string>
     */
    private function jsonFiles(string $root): array
    {
        $list = [];
        foreach ($this->allFiles($root) as $rel => $file) {
            if (str_ends_with($rel, '.json')) {
                $list[$rel] = $file->getPathname();
            }
        }

        return $list;
    }

    /**
     * @return array{name: string, size: int, created_at: string, art: string}|null
     */
    private function serverInfo(string $name): ?array
    {
        $path = $this->serverPath($name);
        if ($path === null) {
            return null;
        }

        return [
            'name' => $name,
            'size' => (int) filesize($path),
            'created_at' => date(DATE_ATOM, (int) filemtime($path)),
            'art' => str_starts_with($name, 'manuell-') ? 'manuell' : 'vor-wiederherstellung',
        ];
    }

    private function tempDir(): string
    {
        $disk = Storage::disk('local');
        $disk->makeDirectory(self::TEMP);

        return rtrim($disk->path(self::TEMP), DIRECTORY_SEPARATOR);
    }

    /** Vorbereitungen und Reste älter als zwei Stunden entfernen. */
    private function cleanupTemp(): void
    {
        $limit = time() - 7200;
        foreach (glob($this->tempDir().DIRECTORY_SEPARATOR.'*') ?: [] as $entry) {
            if ((int) filemtime($entry) < $limit) {
                is_dir($entry) ? $this->removeDir($entry) : @unlink($entry);
            }
        }
    }

    private function removeDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $entry) {
            if ($entry instanceof SplFileInfo) {
                $entry->isDir() && ! $entry->isLink() ? @rmdir($entry->getPathname()) : @unlink($entry->getPathname());
            }
        }
        @rmdir($dir);
    }
}
