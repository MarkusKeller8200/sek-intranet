<?php

namespace App\Console\Commands;

use App\Data\Contracts\GlossaryRepository;
use App\Plugins\PluginManager;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Sync des Glossars mit dem EduKSX-Glossary (#156) über das Plugin «eduksx-glossary». Im Zeitplan alle fünf Minuten
 * aufgerufen (routes/console.php); synchronisiert wird nur, wenn das Plugin eingeschaltet ist und der Abstand aus
 * den Einstellungen (5–60 Minuten) erreicht ist. Fehler stehen im Stand und im Protokoll des Plugins.
 */
class GlossarySyncCommand extends Command
{
    public const PLUGIN = 'eduksx-glossary';

    protected $signature = 'intranet:glossar-sync {--jetzt : unabhängig vom Abstand} {--neu : Vollstand neu laden}';

    protected $description = 'Glossar mit dem EduKSX-Glossary synchronisieren';

    public function handle(PluginManager $plugins, GlossaryRepository $glossary): int
    {
        if (! isset($plugins->all()[self::PLUGIN]) || ! $plugins->state(self::PLUGIN)['enabled']) {
            $this->line('Plugin EduKSX-Glossary ist nicht eingeschaltet.');

            return self::SUCCESS;
        }
        $interval = max(5, min(60, (int) ($plugins->state(self::PLUGIN)['settings']['interval'] ?? 10)));
        $last = $glossary->syncState()['letzter_abruf'] ?? null;
        if ($this->option('jetzt') !== true && $this->option('neu') !== true && is_string($last)
            && Carbon::parse($last)->addMinutes($interval)->isFuture()) {
            return self::SUCCESS;
        }
        try {
            $result = $plugins->runAction(self::PLUGIN, 'sync', ['neu' => $this->option('neu') === true], null);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $message = (string) ($result['message'] ?? '');
        if (($result['status'] ?? 'success') !== 'success') {
            $this->warn($message);

            return self::FAILURE;
        }
        $this->info($message);

        return self::SUCCESS;
    }
}
