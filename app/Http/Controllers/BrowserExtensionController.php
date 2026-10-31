<?php

namespace App\Http\Controllers;

use App\Support\AppVersion;
use App\Support\ModuleNavigation;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

/**
 * Einstellungen → Browser-Erweiterung «SBI-Connector» (Prototyp, browser-extension/):
 * Beschreibung, Installation und Download als ZIP – mit der Adresse dieses Intranets im Manifest.
 */
class BrowserExtensionController extends Controller
{
    private const FILES = ['manifest.json', 'background.js', 'intranet.js', 'page-hook.js', 'page-relay.js', 'popup.html', 'popup.js',
        'icons/icon-16.png', 'icons/icon-32.png', 'icons/icon-48.png', 'icons/icon-128.png'];

    public function show(): Response
    {
        return Inertia::render('browser-extension', [
            'version' => $this->version(),
            'origin' => $this->origin(),
            'apps' => $this->appOrigins(),
        ]);
    }

    public function download(): BinaryFileResponse
    {
        $zipFile = tempnam(sys_get_temp_dir(), 'sbi-connector-');
        if ($zipFile === false) {
            throw new RuntimeException('Temporäre Datei nicht möglich.');
        }

        $zip = new ZipArchive;
        $zip->open($zipFile, ZipArchive::OVERWRITE);
        foreach (self::FILES as $file) {
            $content = (string) file_get_contents(base_path('browser-extension/'.$file));
            if ($file === 'manifest.json') {
                $content = $this->manifest($content);
            }
            $zip->addFromString('sbi-connector/'.$file, $content);
        }
        $zip->close();

        return response()->download($zipFile, 'sbi-connector-'.$this->version().'.zip', [
            'Content-Type' => 'application/zip',
            'Cache-Control' => 'no-store',
        ])->deleteFileAfterSend();
    }

    /**
     * Manifest mit Intranet-Adresse, Version und den Adressen der Fachanwendungen (Zugriff ohne Rückfrage).
     * Andere Websites bleiben optional und brauchen eine Freigabe im Browser.
     */
    private function manifest(string $template): string
    {
        /** @var array<string, mixed> $manifest */
        $manifest = (array) json_decode(strtr($template, ['__INTRANET_ORIGIN__' => $this->origin(), '__VERSION__' => $this->version()]), true);
        $hosts = (array) ($manifest['host_permissions'] ?? []);
        foreach ($this->appOrigins() as $origin) {
            $hosts[] = $origin.'/*';
        }
        $manifest['host_permissions'] = array_values(array_unique($hosts));

        return (string) json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";
    }

    /**
     * Ursprünge der Fachanwendungen (Menü «Fachanwendungen»), ohne das Intranet selbst.
     *
     * @return list<string>
     */
    public function appOrigins(): array
    {
        $origins = [];
        foreach (app(ModuleNavigation::class)->embedSettings() as $item) {
            if ($item['group'] !== 'fachanwendungen' || ! is_string($item['url'])) {
                continue;
            }
            $parts = parse_url($item['url']);
            if (($parts['scheme'] ?? '') === 'https' && isset($parts['host'])) {
                $origins[] = 'https://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
            }
        }

        return array_values(array_unique(array_diff($origins, [$this->origin()])));
    }

    /** Ursprung des Intranets (Schema + Host), z. B. https://intranet.sekbuelach.ch */
    private function origin(): string
    {
        $url = (string) config('app.url');
        $parts = parse_url($url);
        $origin = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? 'localhost').(isset($parts['port']) ? ':'.$parts['port'] : '');

        return rtrim($origin, '/');
    }

    /** Erweiterungen verlangen Zahlen mit Punkten (z. B. 0.23.0). */
    private function version(): string
    {
        $version = (string) AppVersion::info()['version'];

        return preg_match('/^\d+(\.\d+){0,3}$/', $version) === 1 ? $version : '0.0.0';
    }
}
