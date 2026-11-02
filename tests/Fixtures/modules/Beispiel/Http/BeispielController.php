<?php

namespace Modules\Beispiel\Http;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Beispiel\Data\Contracts\EintragRepository;

class BeispielController extends Controller
{
    public function __construct(private readonly EintragRepository $eintraege) {}

    public function index(): Response
    {
        return Inertia::render('Beispiel::start', [
            'eintraege' => $this->eintraege->neueste(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'titel' => ['required', 'string', 'max:200'],
            'notiz' => ['nullable', 'string', 'max:2000'],
        ], ['titel.required' => 'Bitte einen Titel eingeben.']);

        $this->eintraege->create($data);
        $this->toast('Eintrag gespeichert.');

        return back();
    }
}
