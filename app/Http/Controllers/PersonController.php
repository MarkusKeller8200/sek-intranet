<?php

namespace App\Http\Controllers;

use App\Data\Contracts\UserRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Inertia\Inertia;
use Inertia\Response;

class PersonController extends Controller
{
    public function __construct(private readonly UserRepository $users) {}

    public function index(): Response
    {
        $people = Gate::allows('manage-users') ? $this->users->all() : $this->users->active();

        usort($people, fn (array $a, array $b): int => strnatcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? '')));

        return Inertia::render('people/index', ['people' => $people]);
    }

    public function create(): Response
    {
        return Inertia::render('people/form', ['person' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->users->create($this->validated($request));
        $this->toast('Person gespeichert.');

        return redirect()->route('people.index');
    }

    public function edit(string $id): Response
    {
        return Inertia::render('people/form', ['person' => $this->users->findOrFail($id)]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $this->users->findOrFail($id);
        $data = $this->validated($request, $id);

        if ($id === (string) $request->user()?->getAuthIdentifier() && ($data['role'] !== 'admin' || $data['active'] !== true)) {
            return back()->withErrors(['role' => 'Die eigene Administrationsrolle kann nicht entfernt werden.']);
        }

        $this->users->update($id, $data);
        $this->toast('Person aktualisiert.');

        return redirect()->route('people.index');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        abort_if($id === (string) $request->user()?->getAuthIdentifier(), 422, 'Das eigene Konto kann nicht gelöscht werden.');

        $this->users->findOrFail($id);
        $this->users->delete($id);
        $this->toast('Person gelöscht.');

        return redirect()->route('people.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?string $ignoreId = null): array
    {
        $validator = validator($request->all(), [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:200'],
            'role' => ['required', Rule::in($this->keys('roles'))],
            'function' => ['nullable', 'string', 'max:200'],
            'school' => ['required', Rule::in($this->keys('schools'))],
            'phone' => ['nullable', 'string', 'max:50'],
            'active' => ['boolean'],
        ]);

        // Eindeutige E-Mail (in der Datenbank später ein UNIQUE-Index).
        $validator->after(function (Validator $validator) use ($request, $ignoreId): void {
            $existing = $this->users->findByEmail((string) $request->input('email'));

            if ($existing !== null && ($existing['id'] ?? null) !== $ignoreId) {
                $validator->errors()->add('email', 'Diese E-Mail-Adresse ist bereits erfasst.');
            }
        });

        $data = $validator->validate();
        $data['email'] = mb_strtolower(trim((string) $data['email']));
        $data['active'] = $request->boolean('active');
        $data['function'] = $data['function'] ?? null;
        $data['phone'] = $data['phone'] ?? null;

        return $data;
    }
}
