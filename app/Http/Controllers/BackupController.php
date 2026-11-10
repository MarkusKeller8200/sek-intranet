<?php

namespace App\Http\Controllers;

use App\Auth\IntranetUser;
use App\Support\AuditLog;
use App\Support\Backups;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Einstellungen → Sicherung (#174): ganze Installation herunterladen (optional mit Passwort), auf dem Server ablegen
 * und wiederherstellen. Nur Administration. Passwörter werden nie gespeichert oder protokolliert.
 */
class BackupController extends Controller
{
    private const SESSION = 'sicherung_vorbereitung';

    public function __construct(
        private readonly Backups $backups,
        private readonly AuditLog $audit,
    ) {}

    public function index(Request $request): Response
    {
        $token = (string) $request->session()->get(self::SESSION, '');
        $vorschau = $token !== '' ? $this->backups->prepared($token) : null;
        if ($token !== '' && $vorschau === null) {
            $request->session()->forget(self::SESSION);
        }

        return Inertia::render('settings/backup', [
            'sicherungen' => $this->backups->serverBackups(),
            'vorschau' => $vorschau,
            'aktuell' => $vorschau !== null ? $this->backups->currentCounts() : null,
            'minPasswort' => Backups::MIN_PASSWORD,
            'maxUploadMb' => $this->maxUploadMb(),
        ]);
    }

    /** Sicherung herunterladen – mit Passwort verschlüsselt und mit Zugangsdaten, sonst als ZIP ohne Zugangsdaten. */
    public function download(Request $request): BinaryFileResponse
    {
        $data = $request->validate([
            'passwort' => ['nullable', 'string', 'min:'.Backups::MIN_PASSWORD, 'max:1024', 'confirmed'],
        ], [
            'passwort.min' => 'Das Passwort braucht mindestens '.Backups::MIN_PASSWORD.' Zeichen.',
            'passwort.confirmed' => 'Die beiden Eingaben stimmen nicht überein.',
        ]);
        $password = is_string($data['passwort'] ?? null) && $data['passwort'] !== '' ? $data['passwort'] : null;

        $result = $this->lock(fn (): array => $this->backups->create($password !== null ? 'klartext' : 'ohne', $password, $this->userName($request)));
        $this->record($request, 'Sicherung heruntergeladen', ['verschluesselt' => $password !== null, 'datei' => $result['name']]);

        return response()->download($result['path'], $result['name'], [
            'Content-Type' => $password !== null ? 'application/octet-stream' : 'application/zip',
            'Cache-Control' => 'no-store',
        ])->deleteFileAfterSend();
    }

    /** Sicherung auf dem Server ablegen (Zugangsdaten bleiben mit dem Schlüssel dieses Servers verschlüsselt). */
    public function store(Request $request): RedirectResponse
    {
        $info = $this->lock(fn (): array => $this->backups->createOnServer('manuell', $this->userName($request)));
        $this->record($request, 'Sicherung auf dem Server angelegt', ['datei' => $info['name']]);
        $this->toast('Sicherung auf dem Server angelegt.');

        return back();
    }

    public function serverDownload(Request $request, string $name): BinaryFileResponse
    {
        $path = $this->backups->serverPath($name);
        abort_if($path === null, 404);
        $this->record($request, 'Sicherung vom Server heruntergeladen', ['datei' => $name]);

        return response()->download($path, $name, ['Content-Type' => 'application/zip', 'Cache-Control' => 'no-store']);
    }

    public function destroy(Request $request, string $name): RedirectResponse
    {
        abort_if($this->backups->serverPath($name) === null, 404);
        $this->backups->deleteServerBackup($name);
        $this->record($request, 'Sicherung auf dem Server gelöscht', ['datei' => $name]);
        $this->toast('Sicherung gelöscht.');

        return back();
    }

    /** Hochgeladene Sicherung prüfen und für die Wiederherstellung bereitlegen (noch keine Änderung). */
    public function prepare(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'datei' => ['required', 'file', 'max:'.($this->maxUploadMb() * 1024)],
            'passwort' => ['nullable', 'string', 'max:1024'],
        ], ['datei.required' => 'Bitte eine Sicherungsdatei wählen.', 'datei.max' => 'Die Datei ist zu gross für den Upload.']);
        $file = $request->file('datei');
        abort_unless($file instanceof UploadedFile, 422);
        $password = is_string($data['passwort'] ?? null) && $data['passwort'] !== '' ? $data['passwort'] : null;

        @set_time_limit(900);
        $prepared = $this->backups->prepare((string) $file->getRealPath(), $password);
        $this->replaceSession($request, $prepared['token']);

        return back();
    }

    /** Sicherung vom Server als Quelle für die Wiederherstellung bereitlegen. */
    public function prepareServer(Request $request, string $name): RedirectResponse
    {
        $path = $this->backups->serverPath($name);
        abort_if($path === null, 404);
        $prepared = $this->backups->prepare($path, null);
        $this->replaceSession($request, $prepared['token']);

        return back();
    }

    public function cancel(Request $request): RedirectResponse
    {
        $this->backups->discard((string) $request->session()->pull(self::SESSION, ''));

        return back();
    }

    /** Wiederherstellen: ersetzt alle Daten. Danach ist eine neue Anmeldung nötig. */
    public function restore(Request $request): RedirectResponse
    {
        $request->validate(
            ['bestaetigung' => ['required', 'in:WIEDERHERSTELLEN']],
            ['bestaetigung.in' => 'Zur Bestätigung «WIEDERHERSTELLEN» eintippen.', 'bestaetigung.required' => 'Zur Bestätigung «WIEDERHERSTELLEN» eintippen.'],
        );
        $token = (string) $request->session()->get(self::SESSION, '');
        if ($token === '') {
            throw ValidationException::withMessages(['bestaetigung' => 'Keine vorbereitete Sicherung – bitte zuerst eine Datei hochladen.']);
        }
        $user = $request->user();
        $name = $this->userName($request);

        try {
            $result = $this->lock(fn (): array => $this->backups->restore($token, $name));
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['bestaetigung' => $e->getMessage()]);
        }
        // Erst nach dem Ersetzen protokollieren (der Verlauf gehört zu den wiederhergestellten Daten)
        $this->audit->record('sicherung', null, ['id' => 'wiederherstellung', 'name' => 'Sicherung wiederhergestellt',
            'stand' => $result['manifest']['erstellt_am'] ?? null, 'version' => $result['manifest']['version'] ?? null,
            'vorher_gesichert' => $result['vorher']], 'web', $user);
        Log::info('Intranet: Sicherung wiederhergestellt', ['durch' => $name, 'stand' => $result['manifest']['erstellt_am'] ?? null, 'vorher' => $result['vorher']]);

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Cache::flush();
        $this->toast('Sicherung wiederhergestellt. Bitte neu anmelden. Der vorherige Stand liegt als «'.$result['vorher'].'» auf dem Server.');

        return redirect('/anmelden');
    }

    /**
     * Nur ein Vorgang gleichzeitig (Sichern oder Wiederherstellen).
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function lock(callable $callback): mixed
    {
        @set_time_limit(900);
        $lock = Cache::lock('intranet-sicherung', 900);
        if (! $lock->get()) {
            throw ValidationException::withMessages(['datei' => 'Es läuft bereits eine Sicherung oder Wiederherstellung. Bitte später nochmals versuchen.']);
        }
        try {
            return $callback();
        } finally {
            $lock->release();
        }
    }

    private function replaceSession(Request $request, string $token): void
    {
        $old = (string) $request->session()->get(self::SESSION, '');
        if ($old !== '' && $old !== $token) {
            $this->backups->discard($old);
        }
        $request->session()->put(self::SESSION, $token);
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function record(Request $request, string $what, array $details): void
    {
        $this->audit->record('sicherung', null, ['id' => 'sicherung', 'name' => $what] + $details, 'web', $request->user());
    }

    private function userName(Request $request): ?string
    {
        $user = $request->user();

        return $user instanceof IntranetUser ? $user->name() : null;
    }

    /** Grösste Datei, die PHP annimmt (upload_max_filesize bzw. post_max_size), in MB. */
    private function maxUploadMb(): int
    {
        $toMb = function (string $value): int {
            $value = trim($value);
            $number = (int) $value;

            return match (strtolower(substr($value, -1))) {
                'g' => $number * 1024,
                'm' => $number,
                'k' => max(1, intdiv($number, 1024)),
                default => max(1, intdiv($number, 1048576)),
            };
        };

        return max(1, min($toMb((string) ini_get('upload_max_filesize')), $toMb((string) ini_get('post_max_size'))));
    }
}
