<?php

namespace App\Http\Controllers;

use App\Auth\IntranetUser;
use App\Data\Contracts\UserRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Prototyp-Anmeldung: Auswahl eines Demo-Benutzers.
 * Wird mit IDA ADR-004 durch OIDC (Entra ID, später Edulog) ersetzt.
 */
class AuthController extends Controller
{
    public function __construct(private readonly UserRepository $users) {}

    public function show(): Response
    {
        $enabled = (bool) config('intranet.demo_login');

        return Inertia::render('auth/login', [
            'demoLogin' => $enabled,
            'users' => $enabled ? array_map(fn (array $user): array => [
                'id' => $user['id'],
                'name' => $user['name'] ?? '',
                'function' => $user['function'] ?? null,
                'role' => $user['role'] ?? 'mitarbeitende',
            ], $this->users->active()) : [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless((bool) config('intranet.demo_login'), 403, 'Demo-Anmeldung ist deaktiviert.');

        $data = $request->validate(['user_id' => ['required', 'string']]);
        $record = $this->users->find($data['user_id']);

        if ($record === null || ($record['active'] ?? true) !== true) {
            throw ValidationException::withMessages(['user_id' => 'Diese Person kann sich nicht anmelden.']);
        }

        Auth::login(new IntranetUser($record));
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
