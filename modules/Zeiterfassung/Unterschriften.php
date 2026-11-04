<?php

namespace Modules\Zeiterfassung;

use App\Support\Notifications;
use Illuminate\Support\Facades\Storage;
use Modules\Zeiterfassung\Data\Contracts\MonatRepository;

/**
 * Ereignisse der Kern-Schnittstelle «Unterschriften» für die Zeiterfassung (#43). Bezug: «person:<id>:<JJJJ-MM>».
 * Das unterschriebene PDF liegt im Speicher des Moduls (Disk «local», Ordner modules/zeiterfassung/unterschrieben).
 */
final class Unterschriften
{
    public const ORDNER = 'modules/zeiterfassung/unterschrieben';

    public function __construct(
        private readonly MonatRepository $monate,
        private readonly Notifications $notifications,
    ) {}

    public static function bezug(string $person, string $monat): string
    {
        return "person:{$person}:{$monat}";
    }

    public static function datei(string $person, string $monat): string
    {
        return self::ORDNER.'/'.preg_replace('/[^a-zA-Z0-9-]/', '', $person).'-'.preg_replace('/[^0-9-]/', '', $monat).'.pdf';
    }

    /**
     * @param  array<string, mixed>  $auftrag
     */
    public function ereignis(string $ereignis, array $auftrag, ?string $pdf): ?bool
    {
        if (preg_match('/^person:([^:]+):(\d{4}-\d{2})$/', (string) $auftrag['reference'], $m) !== 1) {
            return null;
        }
        [, $person, $monat] = $m;

        switch ($ereignis) {
            case 'signatur.unterschrieben':
                $this->monate->setzen($person, $monat, ['unterschrift_status' => 'teilweise']);

                return true;
            case 'signatur.abgeschlossen':
                if ($pdf === null) {
                    return false;
                }
                $pfad = self::datei($person, $monat);
                if (! Storage::disk('local')->put($pfad, $pdf)) {
                    return false;
                }
                $this->monate->setzen($person, $monat, [
                    'unterschrift_status' => 'unterschrieben',
                    'unterschrift_at' => now()->toIso8601String(),
                    'unterschrift_pdf' => $pfad,
                ]);
                $this->notifications->toUsers([$person], 'zeit.monat_unterschrieben', "Monatsrapport {$monat} unterschrieben",
                    'Der Monatsrapport ist von allen unterschrieben und liegt in deiner Zeiterfassung.', "/zeiterfassung?monat={$monat}", null);

                return true;
            case 'signatur.abgelehnt':
                $status = in_array($auftrag['status'], ['abgelehnt', 'zurueckgezogen', 'abgelaufen'], true) ? (string) $auftrag['status'] : 'abgelehnt';
                $this->monate->setzen($person, $monat, ['unterschrift_status' => $status]);
                $this->notifications->toAbility('visum-zeiterfassung', 'zeit.unterschrift_abgelehnt', "Monatsrapport {$monat}: Unterschrift {$status}",
                    'Der Monatsrapport kann im Visum neu zur Unterschrift gesendet werden.', "/zeiterfassung/visum/{$person}?monat={$monat}", null);

                return true;
            default:
                return null;
        }
    }
}
