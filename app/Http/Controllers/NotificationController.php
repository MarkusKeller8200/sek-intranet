<?php

namespace App\Http\Controllers;

use App\Support\Notifications;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Benachrichtigungen der angemeldeten Person: Liste, öffnen (gelesen + weiter), alle gelesen, Stand für die Glocke.
 */
class NotificationController extends Controller
{
    public function __construct(private readonly Notifications $notifications) {}

    public function index(Request $request): Response
    {
        return Inertia::render('notifications', ['items' => $this->notifications->forUser($this->id($request), 100)]);
    }

    /** Stand für die Glocke (wird regelmässig abgefragt). */
    public function status(Request $request): JsonResponse
    {
        return response()->json($this->notifications->summary($this->id($request)));
    }

    public function open(Request $request, string $id): RedirectResponse
    {
        $n = $this->notifications->markRead($this->id($request), $id);
        abort_if($n === null, 404);

        return redirect((string) $n['href']);
    }

    public function readAll(Request $request): RedirectResponse
    {
        $count = $this->notifications->markAllRead($this->id($request));
        $this->toast($count === 1 ? '1 Benachrichtigung gelesen.' : "{$count} Benachrichtigungen gelesen.");

        return back();
    }

    private function id(Request $request): string
    {
        return (string) $request->user()?->getAuthIdentifier();
    }
}
