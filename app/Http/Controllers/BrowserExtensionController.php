<?php

namespace App\Http\Controllers;

use App\Support\AppVersion;
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
    private const FILES = ['manifest.json', 'background.js', 'intranet.js', 'popup.html', 'popup.js',
        'icons/icon-16.png', 'icons/icon-32.png', 'icons/icon-48.png', 'icons/icon-128.png'];

    public function show(): Response
    {
        return Inertia::render('browser-extension', [
            'version' => $this->version(),
            'origin' => $this->origin(),
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
                $content = strtr($content, ['__INTRANET_ORIGIN__' => $this->origin(), '__VERSION__' => $this->version()]);
            }
            $zip->addFromString('sbi-connector/'.$file, $content);
        }
        $zip->close();

        return response()->download($zipFile, 'sbi-connector-'.$this->version().'.zip', [
            'Content-Type' => 'application/zip',
            'Cache-Control' => 'no-store',
        ])->deleteFileAfterSend();
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
