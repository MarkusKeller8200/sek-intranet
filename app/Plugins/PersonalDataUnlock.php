<?php

namespace App\Plugins;

use App\Auth\IntranetUser;
use App\Support\AuditLog;
use App\Support\RolePins;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;

/**
 * Freischaltung der Personendaten-Endpunkte (`endpoints[].personal_data`) eines Plugins für einen Test auf einem
 * Server ausserhalb der Schweiz. Nur die Administration, nur mit dem Admin-PIN, zeitlich begrenzt und im
 * Änderungsverlauf festgehalten. Der PIN wird nur geprüft (Hash), nie gespeichert oder protokolliert.
 */
final class PersonalDataUnlock
{
    public const MINUTES = 60;

    private const KEY = 'plugin-personal-unlock:';

    public function __construct(private readonly RolePins $pins, private readonly AuditLog $audit) {}

    /** Freigeschaltet bis (ISO-Zeit) oder null. */
    public function until(string $pluginId): ?string
    {
        $value = Cache::get(self::KEY.$pluginId);

        return is_array($value) && is_string($value['until'] ?? null) && strtotime($value['until']) > time() ? $value['until'] : null;
    }

    public function active(string $pluginId): bool
    {
        return $this->until($pluginId) !== null;
    }

    public function unlock(string $pluginId, string $pin, IntranetUser $user): string
    {
        if (! $user->hasRole(RolePins::ADMIN)) {
            throw new RuntimeException('Nur die Administration kann Personendaten freischalten.');
        }
        $limit = 'plugin-personal-unlock-pin:'.$user->id();
        if (RateLimiter::tooManyAttempts($limit, 5)) {
            throw new RuntimeException('Zu viele Versuche. Bitte in '.RateLimiter::availableIn($limit).' Sekunden erneut versuchen.');
        }
        if (! $this->pins->isSet(RolePins::ADMIN)) {
            throw new RuntimeException('Es ist kein Admin-PIN gesetzt (Einstellungen → Zugang).');
        }
        if (! $this->pins->check(RolePins::ADMIN, trim($pin))) {
            RateLimiter::hit($limit, 900);

            throw new RuntimeException('Admin-PIN falsch.');
        }
        RateLimiter::clear($limit);

        $until = now()->addMinutes(self::MINUTES)->toIso8601String();
        Cache::put(self::KEY.$pluginId, ['until' => $until, 'by' => $user->id()], now()->addMinutes(self::MINUTES));
        $this->audit->record('plugin_states', ['id' => $pluginId, 'personendaten_freigeschaltet_bis' => null], ['id' => $pluginId, 'personendaten_freigeschaltet_bis' => $until], 'Plugin: Personendaten für Test freigeschaltet', $user);

        return $until;
    }

    public function lock(string $pluginId, ?IntranetUser $user): void
    {
        if ($this->active($pluginId)) {
            $this->audit->record('plugin_states', ['id' => $pluginId, 'personendaten_freigeschaltet_bis' => $this->until($pluginId)], ['id' => $pluginId, 'personendaten_freigeschaltet_bis' => null], 'Plugin: Personendaten wieder gesperrt', $user);
        }
        Cache::forget(self::KEY.$pluginId);
    }
}
