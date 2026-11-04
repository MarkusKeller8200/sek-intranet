<?php

namespace App\Support;

use App\Data\Contracts\DocumentRepository;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * Dokumente & Links: eingeschränkte Sichtbarkeit (Feld «ability» = Recht, leer = alle) und Ablage von Dateien aus
 * Plugins (#94, z. B. unterschriebene PDFs aus DeepSign). Die Herkunft («source») verhindert doppeltes Ablegen.
 */
final class DocumentAccess
{
    /** Erlaubte Einschränkungen (Recht → Bezeichnung). */
    public const ABILITIES = ['manage-signatures' => 'nur Verwaltung (Recht «Unterschriften verwalten»)'];

    public function __construct(private readonly DocumentRepository $documents) {}

    /**
     * @param  array<string, mixed>  $document
     */
    public static function visible(array $document, ?Authenticatable $user): bool
    {
        $ability = $document['ability'] ?? null;
        if (! is_string($ability) || $ability === '') {
            return true;
        }

        return $user !== null && Gate::forUser($user)->allows($ability);
    }

    /**
     * @param  list<array<string, mixed>>  $documents
     * @return list<array<string, mixed>>
     */
    public static function filter(array $documents, ?Authenticatable $user): array
    {
        return array_values(array_filter($documents, fn (array $d): bool => self::visible($d, $user)));
    }

    /**
     * Bereits abgelegtes Dokument dieser Herkunft.
     *
     * @return array<string, mixed>|null
     */
    public function bySource(string $source): ?array
    {
        foreach ($this->documents->all() as $d) {
            if (($d['source'] ?? null) === $source) {
                return $d;
            }
        }

        return null;
    }

    /**
     * Datei als Dokument ablegen.
     *
     * @return array<string, mixed> neues Dokument
     */
    public function store(string $title, string $fileName, string $content, string $category, ?string $ability, string $source, ?string $description, ?Authenticatable $by): array
    {
        $directory = (string) config('intranet.upload_directory', 'intranet/documents');
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) ?: 'pdf';
        $path = $directory.'/'.bin2hex(random_bytes(16)).'.'.preg_replace('/[^a-z0-9]/', '', $extension);
        Storage::disk('local')->put($path, $content);

        return $this->documents->create([
            'title' => mb_substr($title, 0, 200),
            'description' => $description,
            'category' => $category,
            'kind' => 'file',
            'url' => null,
            'file_path' => $path,
            'file_name' => $fileName,
            'file_size' => strlen($content),
            'ability' => $ability,
            'source' => $source,
            'created_by' => $by !== null ? (string) $by->getAuthIdentifier() : 'system',
        ]);
    }
}
