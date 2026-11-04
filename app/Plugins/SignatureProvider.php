<?php

namespace App\Plugins;

/**
 * Anbieter für elektronische Unterschriften (z. B. DeepSign). Die Kern-Schnittstelle «Unterschriften»
 * (App\Support\Signatures) spricht nur über diese Methoden mit dem Plugin – Module kennen den Anbieter nicht.
 *
 * Status (vereinheitlicht): entwurf, versendet, teilweise, unterschrieben, abgelehnt, zurueckgezogen.
 * Status je Person: wartet (Reihenfolge), offen, unterschrieben, abgelehnt.
 * Position eines Unterschriftsfelds: {pageNumber (ab 1), x, y, width, height} in Punkten (72 dpi), x/y = linke untere
 * Ecke des Felds, gemessen ab der linken unteren Ecke der Seite (wie im PDF).
 */
interface SignatureProvider
{
    /**
     * Dokument hochladen und Unterzeichnende/Beobachtende hinzufügen (noch nicht gestartet).
     *
     * @param  array{title: string, file_name: string, comment?: string|null, signees: list<array{email: string, order: int, position?: array<string, int|float>|null}>, observers: list<string>}  $request
     * @param  array{url: string, secret: string}|null  $callback  Webhook (nur wenn von aussen erreichbar)
     * @return array{provider_id: string, signee_ids: list<string>}
     */
    public function signatureSend(PluginContext $ctx, array $request, string $pdf, ?array $callback): array;

    public function signatureStart(PluginContext $ctx, string $providerId): void;

    /**
     * @return array{status: string, signees: list<array{email: string, status: string, signed_at: string|null, provider_id: string}>}
     */
    public function signatureStatus(PluginContext $ctx, string $providerId): array;

    /** Unterschriebenes Dokument (PDF) oder null, wenn (noch) nicht verfügbar. */
    public function signatureDownload(PluginContext $ctx, string $providerId): ?string;

    public function signatureRemind(PluginContext $ctx, string $providerId, string $signeeId): void;

    public function signatureWithdraw(PluginContext $ctx, string $providerId): void;

    public function signatureDelete(PluginContext $ctx, string $providerId): void;

    /** Kurzlebiger Link zum Bearbeiten beim Anbieter (nur Administration) oder null. */
    public function signatureAccessUrl(PluginContext $ctx, string $providerId): ?string;

    /**
     * Webhook prüfen: Signatur mit dem Geheimnis des Dokuments. Danach fragt der Kern den Status selbst ab.
     *
     * @param  array<string, list<string|null>|string|null>  $headers
     */
    public function signatureVerifyCallback(array $headers, string $raw, string $secret): bool;

    /** Dokument-ID aus dem Webhook (nur sie wird übernommen). */
    public function signatureCallbackId(string $raw): ?string;

    /** Demo-Modus: nächste Person unterschreiben lassen. false, wenn kein Demo-Modus. */
    public function signatureSimulate(PluginContext $ctx, string $providerId): bool;
}
