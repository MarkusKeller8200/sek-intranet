<?php

namespace App\Plugins;

/**
 * Anbieter für elektronische Dossiers (z. B. CMI). Die Kern-Schnittstelle «Dossier» (App\Support\Dossiers) spricht nur
 * über diese Methoden mit dem Plugin – Module kennen den Anbieter nicht. Endpunkte nur aus der Hersteller-Doku.
 */
interface DossierProvider
{
    /** System der Datenherkunft, dessen ID die Dossier-ID ist (z. B. «cmi»). */
    public function dossierSystem(): string;

    /**
     * Dokument (PDF) im Dossier ablegen.
     *
     * @return string|null ID des Dokuments im Dossier, falls bekannt
     */
    public function dossierUpload(PluginContext $ctx, string $dossierId, string $title, string $fileName, string $pdf): ?string;
}
