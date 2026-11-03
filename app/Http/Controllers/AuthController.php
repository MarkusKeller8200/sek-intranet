<?php

namespace App\Http\Controllers;

use App\Auth\IntranetUser;
use App\Data\Contracts\UserRepository;
use App\Support\PersonTypes;
use App\Support\RolePins;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Prototyp-Anmeldung: Rolle + 8-stelliger PIN (#69), danach Auswahl einer Demo-Person dieser Rolle.
 * Solange kein PIN der Administration gesetzt ist: Auswahl aller Demo-Personen (hinter Basic Auth).
 * Wird mit IDA ADR-004 durch OIDC (Entra ID, später Edulog) ersetzt.
 */
class AuthController extends Controller
{
    private const SESSION_ROLE = 'pin_role';

    /** Fehlversuche je Adresse: 5 pro Minute und 20 pro Stunde. */
    private const LIMITS = [['minute', 5, 60], ['hour', 20, 3600]];

    public function __construct(
        private readonly UserRepository $users,
        private readonly RolePins $pins,
    ) {}

    public function show(Request $request): Response
    {
        $enabled = (bool) config('intranet.demo_login');
        $pinMode = $enabled && $this->pins->active();
        $role = $pinMode ? $this->unlockedRole($request) : null;

        return Inertia::render('auth/login', [
            'demoLogin' => $enabled,
            'pinMode' => $pinMode,
            'roles' => $pinMode ? array_map(fn (array $r): array => ['label' => $r['label'], 'set' => $r['set']], $this->pins->overview()) : [],
            'role' => $role,
            'users' => $enabled && (! $pinMode || $role !== null) ? array_map(fn (array $user): array => [
                'id' => $user['id'],
                'name' => $user['name'] ?? '',
                'function' => $user['function'] ?? null,
                'role' => $user['role'] ?? 'mitarbeitende',
            ], $this->candidates($role)) : [],
        ]);
    }

    /** Rolle mit PIN freischalten. */
    public function unlock(Request $request): RedirectResponse
    {
        abort_unless((bool) config('intranet.demo_login') && $this->pins->active(), 404);
        $data = $request->validate([
            'role' => ['required', 'string'],
            'pin' => ['required', 'string'],
        ]);
        $key = 'pin-login:'.$request->ip();
        foreach (self::LIMITS as [$name, $max]) {
            if (RateLimiter::tooManyAttempts("{$key}:{$name}", $max)) {
                $seconds = RateLimiter::availableIn("{$key}:{$name}");
                throw ValidationException::withMessages(['pin' => "Zu viele Fehlversuche. Bitte in {$seconds} Sekunden nochmals versuchen."]);
            }
        }

        $role = (string) $data['role'];
        if (! array_key_exists($role, RolePins::roles()) || ! $this->pins->check($role, (string) $data['pin'])) {
            foreach (self::LIMITS as [$name, , $decay]) {
                RateLimiter::hit("{$key}:{$name}", $decay);
            }
            // Nur Rolle und Adresse protokollieren, nie den PIN
            Log::notice('PIN-Anmeldung abgewiesen', ['role' => $role, 'ip' => $request->ip()]);

            throw ValidationException::withMessages(['pin' => 'PIN falsch oder für diese Rolle nicht gesetzt.']);
        }

        foreach (self::LIMITS as [$name]) {
            RateLimiter::clear("{$key}:{$name}");
        }
        $request->session()->regenerate();
        $request->session()->put(self::SESSION_ROLE, $role);

        $candidates = $this->candidates($role);
        if (count($candidates) === 1) {
            return $this->signIn($request, $candidates[0]);
        }

        return redirect()->route('login');
    }

    /** Andere Rolle wählen (Freischaltung aufheben). */
    public function reset(Request $request): RedirectResponse
    {
        $request->session()->forget(self::SESSION_ROLE);

        return redirect()->route('login');
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless((bool) config('intranet.demo_login'), 403, 'Demo-Anmeldung ist deaktiviert.');

        $data = $request->validate(['user_id' => ['required', 'string']]);
        $pinMode = $this->pins->active();
        $role = $pinMode ? $this->unlockedRole($request) : null;
        if ($pinMode && $role === null) {
            throw ValidationException::withMessages(['user_id' => 'Bitte zuerst Rolle und PIN eingeben.']);
        }

        $record = collect($this->candidates($role))->firstWhere('id', $data['user_id']);
        if ($record === null) {
            throw ValidationException::withMessages(['user_id' => 'Diese Person kann sich nicht anmelden.']);
        }

        return $this->signIn($request, $record);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * Demo-Personen zur Auswahl: alle (ohne PIN-Modus oder als Administration), sonst nur die der Rolle.
     *
     * @return list<array<string, mixed>>
     */
    private function candidates(?string $role): array
    {
        $people = array_values(array_filter($this->users->active(), PersonTypes::canLogin(...)));
        if ($role === null || $role === RolePins::ADMIN) {
            return $people;
        }

        return array_values(array_filter($people, fn (array $p): bool => ($p['role'] ?? null) === $role));
    }

    private function unlockedRole(Request $request): ?string
    {
        $role = $request->session()->get(self::SESSION_ROLE);

        return is_string($role) && $this->pins->isSet($role) ? $role : null;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function signIn(Request $request, array $record): RedirectResponse
    {
        Auth::login(new IntranetUser($record));
        $request->session()->regenerate();
        $request->session()->forget(self::SESSION_ROLE);

        return redirect()->intended(route('dashboard'));
    }
}
