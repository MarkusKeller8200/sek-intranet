<?php

namespace App\Console\Commands;

use App\Support\Signatures;
use Illuminate\Console\Command;

/**
 * Regelmässige Abfrage der Unterschriften (#40): Der Webhook von DeepSign wird nicht wiederholt, darum fragt das
 * Intranet offene Aufträge selbst ab (Zeitplan alle 15 Minuten, routes/console.php) und holt unterschriebene
 * Dokumente ab (#41).
 */
class RefreshSignaturesCommand extends Command
{
    protected $signature = 'intranet:signatures-refresh {--alle : alle offenen sofort abfragen, unabhängig vom Abstand}';

    protected $description = 'Status offener Unterschriften abfragen und unterschriebene Dokumente abholen';

    public function handle(Signatures $signatures): int
    {
        $result = $signatures->refreshDue($this->option('alle') === true ? 0 : (int) config('intranet.signatures.poll_minutes', 15));
        $this->info("Abgefragt: {$result['checked']}, abgeholt: {$result['fetched']}, Warnungen: {$result['warned']}, Fehler: {$result['errors']}");

        return $result['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
