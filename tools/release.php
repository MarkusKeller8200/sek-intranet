<?php

/*
 * Versionen und Releases (Ablauf aus TagStudio, docs/KONZEPT-VERSIONIERUNG.md).
 *
 *   php tools/release.php 0.32.0            Version setzen: VERSION + CHANGELOG («Unveröffentlicht» → [0.32.0] – Datum)
 *   php tools/release.php 0.32.0 --commit   zusätzlich git commit «Version 0.32.0» (danach git push)
 *   php tools/release.php 0.32.0-beta.1     Vorabversion (CHANGELOG bleibt unter «Unveröffentlicht»)
 *   php tools/release.php --check           nur prüfen (Version, CHANGELOG, Plugin-Versionen seit dem letzten Tag)
 *   php tools/release.php --notes 0.32.0    Text des Abschnitts (für das GitHub-Release)
 *   php tools/release.php --planned         geplante nächste Version (Anzeige «Developer Release»)
 *
 * Nur nach ausdrücklicher Freigabe durch den Projektverantwortlichen ausführen. Nach dem Push legt der Workflow
 * «release» Tag v<Version> und GitHub-Release an, sobald das Deployment grün ist.
 */

require __DIR__.'/../app/Support/Release.php';

use App\Support\Release;

$root = dirname(__DIR__);
$release = new Release($root);
$args = array_slice($argv, 1);

function git(string $root, string ...$args): ?string
{
    $cmd = 'git -C '.escapeshellarg($root).' '.implode(' ', array_map('escapeshellarg', $args)).' 2>/dev/null';
    exec($cmd, $out, $code);

    return $code === 0 ? implode("\n", $out) : null;
}

try {
    if ($args === [] || in_array('--help', $args, true)) {
        fwrite(STDOUT, "Aufruf: php tools/release.php <Version> [--commit] | --check | --notes <Version> | --planned\n");
        exit($args === [] ? 1 : 0);
    }

    if ($args[0] === '--notes') {
        $notes = Release::notes((string) file_get_contents($root.'/CHANGELOG.md'), (string) ($args[1] ?? $release->current()));
        if ($notes === '') {
            $notes = Release::unreleasedText((string) file_get_contents($root.'/CHANGELOG.md'));
        }
        fwrite(STDOUT, $notes."\n");
        exit(0);
    }

    if ($args[0] === '--planned') {
        fwrite(STDOUT, Release::planned($release->current(), (string) file_get_contents($root.'/CHANGELOG.md'))."\n");
        exit(0);
    }

    if ($args[0] === '--check') {
        $tag = git($root, 'describe', '--tags', '--abbrev=0', '--match', 'v[0-9]*');
        $changed = [];
        $tagged = [];
        if ($tag !== null && $tag !== '') {
            foreach (explode("\n", (string) git($root, 'diff', '--name-only', $tag, 'HEAD', '--', 'plugins')) as $file) {
                if (preg_match('#^plugins/([^/]+)/#', $file, $m) === 1) {
                    $changed[$m[1]] = $m[1];
                }
            }
            foreach (array_keys($release->plugins()) as $folder) {
                $json = json_decode((string) git($root, 'show', "{$tag}:plugins/{$folder}/plugin.json"), true);
                if (is_array($json) && isset($json['version'])) {
                    $tagged[$folder] = (string) $json['version'];
                }
            }
        }
        $errors = $release->check(array_values($changed), $tagged);
        foreach ($errors as $error) {
            fwrite(STDERR, "✗ {$error}\n");
        }
        if ($errors !== []) {
            exit(1);
        }
        fwrite(STDOUT, '✓ Version '.$release->current().' – CHANGELOG und Plugin-Versionen passen'.($tag ? " (verglichen mit {$tag})" : ' (kein Tag gefunden)').".\n");
        exit(0);
    }

    $version = $args[0];
    foreach ($release->prepare($version, date('Y-m-d')) as $line) {
        fwrite(STDOUT, "✓ {$line}\n");
    }
    if (in_array('--commit', $args, true)) {
        if (git($root, 'add', 'VERSION', 'CHANGELOG.md') === null || git($root, 'commit', '-q', '-m', "Version {$version}") === null) {
            throw new RuntimeException('git commit fehlgeschlagen.');
        }
        fwrite(STDOUT, "✓ Commit «Version {$version}» – jetzt pushen; Tag und Release legt der Workflow «release» an.\n");
    } else {
        fwrite(STDOUT, "Weiter: prüfen, committen und pushen – Tag und Release legt der Workflow «release» an.\n");
    }
} catch (Throwable $e) {
    fwrite(STDERR, '✗ '.$e->getMessage()."\n");
    exit(1);
}
