<?php

namespace App\Http\Controllers;

use App\Auth\IntranetUser;
use App\Data\Contracts\AnnouncementRepository;
use App\Data\Contracts\DocumentRepository;
use App\Data\Contracts\EventRepository;
use App\Data\Contracts\UserRepository;
use App\Plugins\PluginManager;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(
        AnnouncementRepository $announcements,
        EventRepository $events,
        DocumentRepository $documents,
        UserRepository $users,
        PluginManager $plugins,
        Request $request,
    ): Response {
        $names = $users->names();

        return Inertia::render('dashboard', [
            'announcements' => array_map(
                fn (array $a): array => $a + ['author_name' => $names[$a['author_id'] ?? ''] ?? null],
                $announcements->published(4),
            ),
            'events' => $events->upcoming(5),
            'links' => array_slice(array_values(array_filter(
                $documents->sorted(),
                fn (array $d): bool => ($d['category'] ?? null) === 'links',
            )), 0, 6),
            // Kacheln der Plugins: nachgeladen, damit langsame Dienste die Startseite nicht bremsen.
            'widgets' => Inertia::defer(fn (): array => $plugins->widgets($request->user() instanceof IntranetUser ? $request->user() : null)),
            'stats' => [
                'announcements' => $announcements->count(),
                'events' => count($events->upcoming()),
                'documents' => $documents->count(),
                'people' => count($users->active()),
            ],
        ]);
    }
}
