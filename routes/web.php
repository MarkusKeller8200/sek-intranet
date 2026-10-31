<?php

use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\ApiDocsController;
use App\Http\Controllers\ApiRecorderController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DataStructureController;
use App\Http\Controllers\DeviceHandoutController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\EmbedController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\GridViewController;
use App\Http\Controllers\McpConceptController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\PersonLinkController;
use App\Http\Controllers\PluginController;
use App\Http\Controllers\RoleConceptController;
use App\Http\Controllers\VersionController;
use Illuminate\Support\Facades\Route;

// Anmeldung (Prototyp: Demo-Auswahl; später OIDC Entra ID / Edulog)
Route::middleware('guest')->group(function () {
    Route::get('anmelden', [AuthController::class, 'show'])->name('login');
    Route::post('anmelden', [AuthController::class, 'store'])->name('login.store');
});

Route::post('abmelden', [AuthController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::redirect('/', '/start')->name('home');
    Route::get('start', DashboardController::class)->name('dashboard');
    Route::get('module/{key}', [ModuleController::class, 'show'])->where('key', '[a-z0-9-]+')->name('modules.show');
    Route::get('einbindung/{key}', [EmbedController::class, 'show'])->where('key', '[a-z0-9-]+')->name('embeds.show');

    // Mitteilungen
    Route::middleware('can:manage-content')->group(function () {
        Route::get('mitteilungen/neu', [AnnouncementController::class, 'create'])->name('announcements.create');
        Route::post('mitteilungen', [AnnouncementController::class, 'store'])->name('announcements.store');
        Route::get('mitteilungen/{id}/bearbeiten', [AnnouncementController::class, 'edit'])->name('announcements.edit');
        Route::put('mitteilungen/{id}', [AnnouncementController::class, 'update'])->name('announcements.update');
        Route::delete('mitteilungen/{id}', [AnnouncementController::class, 'destroy'])->name('announcements.destroy');
    });
    Route::get('mitteilungen', [AnnouncementController::class, 'index'])->name('announcements.index');
    Route::get('mitteilungen/{id}', [AnnouncementController::class, 'show'])->name('announcements.show');

    // Termine
    Route::middleware('can:manage-content')->group(function () {
        Route::get('termine/neu', [EventController::class, 'create'])->name('events.create');
        Route::post('termine', [EventController::class, 'store'])->name('events.store');
        Route::get('termine/{id}/bearbeiten', [EventController::class, 'edit'])->name('events.edit');
        Route::put('termine/{id}', [EventController::class, 'update'])->name('events.update');
        Route::delete('termine/{id}', [EventController::class, 'destroy'])->name('events.destroy');
    });
    Route::get('termine', [EventController::class, 'index'])->name('events.index');

    // Dokumente & Links (Upload: POST mit _method=PUT beim Bearbeiten)
    Route::middleware('can:manage-content')->group(function () {
        Route::get('dokumente/neu', [DocumentController::class, 'create'])->name('documents.create');
        Route::post('dokumente', [DocumentController::class, 'store'])->name('documents.store');
        Route::get('dokumente/{id}/bearbeiten', [DocumentController::class, 'edit'])->name('documents.edit');
        Route::put('dokumente/{id}', [DocumentController::class, 'update'])->name('documents.update');
        Route::delete('dokumente/{id}', [DocumentController::class, 'destroy'])->name('documents.destroy');
    });
    Route::get('dokumente', [DocumentController::class, 'index'])->name('documents.index');
    Route::get('dokumente/{id}/download', [DocumentController::class, 'download'])->name('documents.download');

    // Personen
    // Fachstelle ICT (Recht use-ict)
    Route::middleware('can:use-ict')->group(function () {
        Route::get('geraeteausgabe', DeviceHandoutController::class)->name('device-handout');
        Route::get('geraeteausgabe/schueler', [DeviceHandoutController::class, 'students'])->middleware('throttle:60,1')->name('device-handout.students');
    });

    Route::middleware('can:manage-users')->group(function () {
        Route::get('personen/neu', [PersonController::class, 'create'])->name('people.create');
        Route::post('personen', [PersonController::class, 'store'])->name('people.store');
        Route::get('personen/{id}/bearbeiten', [PersonController::class, 'edit'])->name('people.edit');
        Route::put('personen/{id}', [PersonController::class, 'update'])->name('people.update');
        Route::post('personen/{id}/feld', [PersonController::class, 'updateField'])->middleware('throttle:240,1')->name('people.update-field');
        Route::post('personen/sammelaenderung', [PersonController::class, 'bulkUpdate'])->name('people.bulk-update');
        Route::post('personen/sammelloeschung', [PersonController::class, 'bulkDestroy'])->name('people.bulk-destroy');
        Route::post('personen/zellen', [PersonController::class, 'cells'])->middleware('throttle:240,1')->name('people.cells');
        Route::post('personen/{id}/verlauf/{entry}/wiederherstellen', [PersonController::class, 'restore'])->name('people.restore');
        Route::delete('personen/{id}', [PersonController::class, 'destroy'])->name('people.destroy');
        Route::post('personen/{id}/herkunft', [PersonLinkController::class, 'store'])->name('people.links.store');
        Route::put('personen/{id}/herkunft/{link}', [PersonLinkController::class, 'update'])->name('people.links.update');
        Route::post('personen/{id}/herkunft/{link}/abgeglichen', [PersonLinkController::class, 'synced'])->name('people.links.synced');
        Route::delete('personen/{id}/herkunft/{link}', [PersonLinkController::class, 'destroy'])->name('people.links.destroy');

        Route::get('datenstrukturen', DataStructureController::class)->name('data-structures');
        Route::inertia('farbkonzept', 'color-concept')->name('color-concept');
        Route::inertia('sprachkonzept', 'language-concept')->name('language-concept');
        Route::get('rollenkonzept', RoleConceptController::class)->name('role-concept');
        Route::get('mcp-konzept', McpConceptController::class)->name('mcp-concept');

        // REST-API: Beschreibung mit Tester und Tokens
        Route::get('api-doku', [ApiDocsController::class, 'show'])->name('api-docs');
        Route::get('api-recorder', [ApiRecorderController::class, 'index'])->name('api-recorder');
        Route::get('api-recorder/neu', [ApiRecorderController::class, 'create'])->name('api-recorder.create');
        Route::get('api-recorder/{id}', [ApiRecorderController::class, 'show'])->name('api-recorder.show');
        Route::get('api-recorder/{id}/plugin-entwurf', [ApiRecorderController::class, 'pluginDraft'])->name('api-recorder.plugin-draft');
        Route::post('api-recorder', [ApiRecorderController::class, 'store'])->middleware('throttle:120,1')->name('api-recorder.store');
        Route::delete('api-recorder/{id}', [ApiRecorderController::class, 'destroy'])->name('api-recorder.destroy');
        Route::post('api-doku/tokens', [ApiDocsController::class, 'store'])->middleware('throttle:20,1')->name('api-docs.tokens.store');
        Route::delete('api-doku/tokens/{id}', [ApiDocsController::class, 'destroy'])->name('api-docs.tokens.destroy');

        // Plugins (Anbindung anderer Systeme per API)
        Route::get('plugins', [PluginController::class, 'index'])->name('plugins.index');
        Route::get('plugins/{id}', [PluginController::class, 'show'])->name('plugins.show');
        Route::post('plugins/{id}/schalter', [PluginController::class, 'toggle'])->name('plugins.toggle');
        Route::put('plugins/{id}/einstellungen', [PluginController::class, 'settings'])->name('plugins.settings');
        Route::middleware('throttle:60,1')->group(function () {
            Route::post('plugins/{id}/test', [PluginController::class, 'test'])->name('plugins.test');
            Route::post('plugins/{id}/aktion/{action}', [PluginController::class, 'action'])->name('plugins.action');
            Route::post('plugins/{id}/api', [PluginController::class, 'api'])->name('plugins.api');
            Route::post('plugins/{id}/zugriff', [PluginController::class, 'access'])->name('plugins.access');
        });
    });
    Route::get('versionen', VersionController::class)->name('versions');
    Route::get('version.json', [VersionController::class, 'current'])->name('versions.current');
    Route::get('personen', [PersonController::class, 'index'])->name('people.index');
    Route::get('schueler', [PersonController::class, 'students'])->middleware('can:view-students')->name('people.students');
    Route::post('personen/export', [PersonController::class, 'export'])->middleware('throttle:30,1')->name('people.export');
    Route::post('ansichten', [GridViewController::class, 'store'])->middleware('throttle:60,1')->name('grid-views.store');
    Route::delete('ansichten/{id}', [GridViewController::class, 'destroy'])->name('grid-views.destroy');
    Route::get('personen/{id}', [PersonController::class, 'show'])->name('people.show');

    Route::redirect('einstellungen', '/einstellungen/darstellung');
    Route::inertia('einstellungen/darstellung', 'settings/appearance')->name('appearance.edit');
});
