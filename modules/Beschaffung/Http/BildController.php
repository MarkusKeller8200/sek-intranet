<?php

namespace Modules\Beschaffung\Http;

use App\Http\Controllers\Controller;
use App\Support\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Beschaffung\Data\Contracts\ArtikelRepository;
use Modules\Beschaffung\Startinhalt;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Bilder der Katalogeinträge (#198): aus dem Startinhalt («start:<datei>») oder hochgeladen («upload:<pfad>»).
 * Hochgeladene Bilder liegen auf dem Server (Speicher «local», Ordner beschaffung/bilder), nicht im Web-Ordner.
 */
class BildController extends Controller
{
    private const ORDNER = 'beschaffung/bilder';

    public function __construct(
        private readonly ArtikelRepository $artikel,
        private readonly AuditLog $audit,
    ) {}

    public function show(string $id): BinaryFileResponse
    {
        $eintrag = $this->artikel->findOrFail($id);
        $bild = (string) ($eintrag['bild'] ?? '');
        $pfad = null;
        if (str_starts_with($bild, 'start:')) {
            $pfad = Startinhalt::bild(substr($bild, 6));
        } elseif (self::upload($eintrag) !== null) {
            $pfad = Storage::disk('local')->path((string) self::upload($eintrag));
        }
        abort_if($pfad === null, 404);

        return response()->file($pfad, [
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function store(Request $request, string $id): JsonResponse
    {
        $vorher = $this->artikel->findOrFail($id);
        $request->validate(
            ['file_bild' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048']],
            ['file_bild.required' => 'Bitte ein Bild wählen.', 'file_bild.mimes' => 'Nur JPG, PNG oder WebP.', 'file_bild.max' => 'Das Bild ist zu gross (höchstens 2 MB).'],
        );
        $datei = $request->file('file_bild');
        abort_if(! $datei instanceof UploadedFile, 422);

        self::dateiEntfernen($vorher);
        $pfad = $datei->storeAs(self::ORDNER, $id.'.'.strtolower($datei->extension()), 'local');
        abort_if(! is_string($pfad), 500, 'Bild konnte nicht gespeichert werden.');
        $nachher = $this->artikel->update($id, ['bild' => 'upload:'.$pfad, 'geaendert_von' => $request->user()?->getAuthIdentifier()]);
        $this->audit->record('bes_artikel', $vorher, $nachher, 'Beschaffung (Bild)', $request->user());

        return response()->json(['row' => KatalogController::darstellung($nachher)]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $vorher = $this->artikel->findOrFail($id);
        self::dateiEntfernen($vorher);
        $nachher = $this->artikel->update($id, ['bild' => '', 'geaendert_von' => $request->user()?->getAuthIdentifier()]);
        $this->audit->record('bes_artikel', $vorher, $nachher, 'Beschaffung (Bild)', $request->user());

        return response()->json(['row' => KatalogController::darstellung($nachher)]);
    }

    /**
     * Hochgeladene Bilddatei eines Eintrags entfernen (Startbilder bleiben im Modul).
     *
     * @param  array<string, mixed>  $eintrag
     */
    public static function dateiEntfernen(array $eintrag): void
    {
        $pfad = self::upload($eintrag);
        if ($pfad !== null) {
            Storage::disk('local')->delete($pfad);
        }
    }

    /**
     * Pfad des hochgeladenen Bildes, null wenn keines (oder ausserhalb des Bilderordners).
     *
     * @param  array<string, mixed>  $eintrag
     */
    private static function upload(array $eintrag): ?string
    {
        $bild = (string) ($eintrag['bild'] ?? '');
        $pfad = str_starts_with($bild, 'upload:') ? substr($bild, 7) : '';

        return $pfad !== '' && str_starts_with($pfad, self::ORDNER.'/') && ! str_contains($pfad, '..') && Storage::disk('local')->exists($pfad) ? $pfad : null;
    }
}
