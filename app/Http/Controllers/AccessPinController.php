<?php

namespace App\Http\Controllers;

use App\Support\AuditLog;
use App\Support\RolePins;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Einstellungen → Zugang: PIN je Rolle setzen oder entfernen (#69). Der PIN wird nie angezeigt.
 */
class AccessPinController extends Controller
{
    public function __construct(
        private readonly RolePins $pins,
        private readonly AuditLog $audit,
    ) {}

    public function index(): Response
    {
        return Inertia::render('settings/access', [
            'rollen' => $this->pins->overview(),
            'aktiv' => $this->pins->active(),
            'basicAuth' => (string) config('intranet.access.password', '') !== '',
        ]);
    }

    public function update(Request $request, string $role): RedirectResponse
    {
        abort_unless(array_key_exists($role, RolePins::roles()), 404);
        $data = $request->validate(
            ['pin' => ['required', 'string', 'regex:'.RolePins::PATTERN, 'confirmed']],
            ['pin.regex' => 'Der PIN muss genau 8 Ziffern haben.', 'pin.confirmed' => 'Die beiden Eingaben stimmen nicht überein.'],
        );
        $pin = (string) $data['pin'];
        if ($this->pins->usedByOther($role, $pin)) {
            throw ValidationException::withMessages(['pin' => 'Diesen PIN hat schon eine andere Rolle. Jede Rolle braucht einen eigenen PIN.']);
        }

        $before = $this->pins->isSet($role);
        $this->pins->set($role, $pin, $request->user()?->getAuthIdentifier());
        // Im Änderungsverlauf nur «gesetzt», nie PIN oder Hash
        $this->audit->record(RolePins::COLLECTION, $before ? ['id' => $role, 'status' => 'gesetzt'] : null, ['id' => $role, 'status' => $before ? 'geändert' : 'gesetzt'], 'web', $request->user());
        $this->toast('PIN für «'.RolePins::roles()[$role].'» gespeichert.');

        return back();
    }

    public function destroy(Request $request, string $role): RedirectResponse
    {
        abort_unless(array_key_exists($role, RolePins::roles()), 404);
        if ($this->pins->isSet($role)) {
            $this->pins->clear($role);
            $this->audit->record(RolePins::COLLECTION, ['id' => $role, 'status' => 'gesetzt'], ['id' => $role, 'status' => 'entfernt'], 'web', $request->user());
        }
        $this->toast('PIN für «'.RolePins::roles()[$role].'» entfernt.');

        return back();
    }
}
