<?php

namespace App\Support;

use App\Auth\IntranetUser;
use App\Plugins\DossierProvider;
use App\Plugins\PluginContext;
use App\Plugins\PluginManager;
use Throwable;

/**
 * Kern-Schnittstelle «Dossier» (#208): Kopie eines Dokuments ins elektronische Dossier einer Person (z. B. CMI).
 * Gesendet wird nur, wenn (1) die Person in der Datenherkunft mit einer Dossier-ID verknüpft ist und (2) ein Plugin mit
 * DossierProvider für dieses System eingeschaltet und eingerichtet ist. Sonst bleibt das Dokument «ausstehend» und kann
 * später erneut gesendet werden.
 */
final class Dossiers
{
    public const SYSTEM = 'cmi';

    /** Ergebnis: abgelegt, kein_dossier (Person nicht verknüpft), ausstehend (kein Plugin aktiv), fehler. */
    public const STATUS = ['abgelegt' => 'abgelegt', 'kein_dossier' => 'kein Dossier', 'ausstehend' => 'ausstehend', 'fehler' => 'Fehler'];

    public function __construct(private readonly PluginManager $plugins, private readonly PersonLinks $links) {}

    /** Ist ein Plugin für das System eingeschaltet und eingerichtet? */
    public function active(string $system = self::SYSTEM): bool
    {
        return $this->provider($system) !== null;
    }

    /**
     * Dokument ins Dossier der Person legen.
     *
     * @return array{status: string, message: string, document_id: string|null}
     */
    public function store(string $personId, string $title, string $fileName, string $pdf, ?IntranetUser $by, string $system = self::SYSTEM): array
    {
        $dossier = $this->links->externalIdFor($personId, $system);
        if ($dossier === null) {
            return ['status' => 'kein_dossier', 'message' => 'Die Person ist mit keinem Dossier verknüpft.', 'document_id' => null];
        }
        $provider = $this->provider($system);
        if ($provider === null) {
            return ['status' => 'ausstehend', 'message' => 'Keine Dossier-Schnittstelle eingeschaltet.', 'document_id' => null];
        }
        try {
            /** @var string|null $id */
            $id = $this->plugins->runWith($provider['id'], $by, 'Dossier: '.$title,
                fn (PluginContext $ctx): ?string => $provider['plugin']->dossierUpload($ctx, $dossier, $title, $fileName, $pdf));
        } catch (Throwable $e) {
            report($e);

            return ['status' => 'fehler', 'message' => 'Ablage im Dossier fehlgeschlagen.', 'document_id' => null];
        }

        return ['status' => 'abgelegt', 'message' => 'Im Dossier abgelegt.', 'document_id' => $id];
    }

    /**
     * @return array{id: string, plugin: DossierProvider}|null
     */
    private function provider(string $system): ?array
    {
        foreach ($this->plugins->all() as $id => $entry) {
            $plugin = $entry['plugin'];
            if ($plugin instanceof DossierProvider && $plugin->dossierSystem() === $system
                && $this->plugins->state((string) $id)['enabled'] && $this->plugins->isConfigured((string) $id)) {
                return ['id' => (string) $id, 'plugin' => $plugin];
            }
        }

        return null;
    }
}
