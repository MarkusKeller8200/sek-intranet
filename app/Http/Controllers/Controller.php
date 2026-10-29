<?php

namespace App\Http\Controllers;

use Carbon\CarbonImmutable;
use Inertia\Inertia;

abstract class Controller
{
    /**
     * Meldung, die nach dem nächsten Seitenaufruf als Toast erscheint.
     */
    protected function toast(string $message, string $type = 'success'): void
    {
        Inertia::flash('toast', ['type' => $type, 'message' => $message]);
    }

    /**
     * Wandelt Formularwerte (YYYY-MM-DD oder YYYY-MM-DDTHH:mm) in ISO 8601 mit Zeitzone.
     */
    protected function toAtom(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return CarbonImmutable::parse($value, (string) config('app.timezone'))->toAtomString();
    }

    /**
     * Schlüssel einer Auswahlliste aus config/intranet.php.
     *
     * @return list<string>
     */
    protected function keys(string $list): array
    {
        $values = config("intranet.{$list}", []);

        return is_array($values) ? array_map('strval', array_keys($values)) : [];
    }
}
