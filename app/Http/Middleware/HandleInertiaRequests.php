<?php

namespace App\Http\Middleware;

use App\Support\AppVersion;
use App\Support\ModuleNavigation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                'can' => [
                    'manageContent' => $user !== null && Gate::forUser($user)->allows('manage-content'),
                    'manageUsers' => $user !== null && Gate::forUser($user)->allows('manage-users'),
                ],
            ],
            'options' => fn (): array => [
                'roles' => config('intranet.roles'),
                'personTypes' => config('intranet.person_types'),
                'schools' => config('intranet.schools'),
                'audiences' => config('intranet.audiences'),
                'announcementCategories' => config('intranet.announcement_categories'),
                'eventCategories' => config('intranet.event_categories'),
                'documentCategories' => config('intranet.document_categories'),
                'guiStatuses' => config('intranet.gui_statuses'),
                'colors' => config('intranet.colors'),
            ],
            'navigation' => fn (): array => app(ModuleNavigation::class)->groups($user),
            'store' => config('intranet.store'),
            'appVersion' => AppVersion::info(),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
