<?php

use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DataStructureController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\PluginController;
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
    Route::middleware('can:manage-users')->group(function () {
        Route::get('personen/neu', [PersonController::class, 'create'])->name('people.create');
        Route::post('personen', [PersonController::class, 'store'])->name('people.store');
        Route::get('personen/{id}/bearbeiten', [PersonController::class, 'edit'])->name('people.edit');
        Route::put('personen/{id}', [PersonController::class, 'update'])->name('people.update');
        Route::post('personen/{id}/feld', [PersonController::class, 'updateField'])->middleware('throttle:240,1')->name('people.update-field');
        Route::post('personen/sammelaenderung', [PersonController::class, 'bulkUpdate'])->name('people.bulk-update');
        Route::post('personen/sammelloeschung', [PersonController::class, 'bulkDestroy'])->name('people.bulk-destroy');
        Route::delete('personen/{id}', [PersonController::class, 'destroy'])->name('people.destroy');

        Route::get('datenstrukturen', DataStructureController::class)->name('data-structures');
        Route::inertia('farbkonzept', 'color-concept')->name('color-concept');

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
    Route::get('personen', [PersonController::class, 'index'])->name('people.index');
    Route::get('personen/{id}', [PersonController::class, 'show'])->name('people.show');

    Route::redirect('einstellungen', '/einstellungen/darstellung');
    Route::inertia('einstellungen/darstellung', 'settings/appearance')->name('appearance.edit');
});
