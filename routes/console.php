<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Unterschriften (#40): offene Aufträge abfragen, unterschriebene Dokumente abholen.
// Läuft im Container über «php artisan schedule:work» (docker/entrypoint.sh).
Schedule::command('intranet:signatures-refresh')->everyFifteenMinutes()->withoutOverlapping();

// Glossar (#156): Sync mit dem EduKSX-Glossary; der Abstand (5–60 Minuten) steht in den Einstellungen des Plugins.
Schedule::command('intranet:glossar-sync')->everyFiveMinutes()->withoutOverlapping();
