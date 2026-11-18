<?php

namespace App\Http\Controllers;

use App\Support\AuditLog;
use App\Support\Branding;
use App\Support\Themes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Einstellungen → Erscheinungsbild (#216, White-Labeling): Marke (EduKSX, MarKusSXCH oder eigenes Logo), Name der
 * Instanz, eigene Bilder. Das Farbkonzept (Theme) wählt die Administration unter Farbkonzept.
 */
class BrandingController extends Controller
{
    public function __construct(
        private readonly Branding $branding,
        private readonly Themes $themes,
        private readonly AuditLog $audit,
    ) {}

    public function index(): Response
    {
        $theme = $this->themes->find($this->themes->activeId());

        return Inertia::render('settings/branding', [
            'einstellung' => $this->branding->einstellung(),
            'marken' => Branding::MARKEN,
            'slots' => Branding::SLOTS,
            'theme' => ['id' => (string) ($theme['id'] ?? Themes::STANDARD), 'name' => (string) ($theme['name'] ?? '')],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'marke' => ['required', Rule::in(array_keys(Branding::MARKEN))],
            'name' => ['nullable', 'string', 'max:80'],
        ]);
        try {
            $r = $this->branding->speichern((string) $data['marke'], (string) ($data['name'] ?? ''), (string) $request->user()?->getAuthIdentifier());
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['marke' => $e->getMessage()]);
        }
        $this->audit->record(Branding::COLLECTION, $r['before'], $r['after'], 'Erscheinungsbild', $request->user());
        $this->toast('Erscheinungsbild gespeichert.');

        return back();
    }

    public function upload(Request $request, string $slot): RedirectResponse
    {
        abort_unless(isset(Branding::SLOTS[$slot]), 404);
        $request->validate(['datei' => ['required', 'file', 'max:'.Branding::MAX_KB]], ['datei.max' => 'Datei zu gross (höchstens 1 MB).']);
        $datei = $request->file('datei');
        abort_unless($datei instanceof UploadedFile, 422);
        try {
            $r = $this->branding->hochladen($slot, $datei, (string) $request->user()?->getAuthIdentifier());
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['datei' => $e->getMessage()]);
        }
        $this->audit->record(Branding::COLLECTION, $r['before'], $r['after'], 'Erscheinungsbild', $request->user());
        $this->toast(Branding::SLOTS[$slot].' hochgeladen.');

        return back();
    }

    public function destroy(Request $request, string $slot): RedirectResponse
    {
        abort_unless(isset(Branding::SLOTS[$slot]), 404);
        $r = $this->branding->entfernen($slot, (string) $request->user()?->getAuthIdentifier());
        $this->audit->record(Branding::COLLECTION, $r['before'], $r['after'], 'Erscheinungsbild', $request->user());
        $this->toast(Branding::SLOTS[$slot].' entfernt.');

        return back();
    }

    /** Bild ausliefern (ohne Anmeldung, für die Anmeldeseite). SVG in einer Sandbox ohne Skripte. */
    public function show(string $slot): HttpResponse
    {
        $bild = $this->branding->bild($slot);
        abort_if($bild === null, 404);

        return response((string) Storage::disk('local')->get($bild['pfad']), 200, [
            'Content-Type' => $bild['typ'],
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; img-src data:; sandbox",
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
