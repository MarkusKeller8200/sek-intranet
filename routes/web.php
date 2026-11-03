<?php

use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\ApiDocsController;
use App\Http\Controllers\ApiRecorderController;
use App\Http\Controllers\ApiViewController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BrowserExtensionController;
use App\Http\Controllers\ConnectorController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DataStructureController;
use App\Http\Controllers\DeviceHandoutController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\EmbedController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\GlossaryController;
use App\Http\Controllers\GridViewController;
use App\Http\Controllers\GuiPrototypeController;
use App\Http\Controllers\McpConceptController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\ModuleManagementController;
use App\Http\Controllers\ModuleSettingsController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\PersonLinkController;
use App\Http\Controllers\PluginController;
use App\Http\Controllers\RoleConceptController;
use App\Http\Controllers\ThemeController;
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
    Route::post('einbindung/{key}/kontext', [ConnectorController::class, 'context'])->middleware('throttle:120,1')->name('embed.context');

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

    // Benachrichtigungen (Glocke), für alle Angemeldeten
    Route::get('benachrichtigungen', [NotificationController::class, 'index'])->name('notifications');
    Route::get('benachrichtigungen/stand', [NotificationController::class, 'status'])->middleware('throttle:120,1')->name('notifications.status');
    Route::get('benachrichtigungen/{id}', [NotificationController::class, 'open'])->name('notifications.open');
    Route::post('benachrichtigungen/gelesen', [NotificationController::class, 'readAll'])->name('notifications.read-all');

    // Menü «GUI-Prototypen» (Recht use-gui; Versionen, Status und Überarbeitung prüft der Controller)
    Route::middleware('can:use-gui')->group(function () {
        Route::get('gui-prototypen', [GuiPrototypeController::class, 'contents'])->name('gui.contents');
        Route::get('gui-prototypen/freigaben', [GuiPrototypeController::class, 'index'])->name('gui.index');
        Route::get('gui-prototypen/{key}', [GuiPrototypeController::class, 'show'])->name('gui.show');
        Route::post('gui-prototypen/{key}/versionen', [GuiPrototypeController::class, 'storeVersion'])->name('gui.versions.store');
        Route::post('gui-prototypen/{key}/status', [GuiPrototypeController::class, 'transition'])->name('gui.transition');
        Route::post('gui-prototypen/{key}/ueberarbeitung', [GuiPrototypeController::class, 'rework'])->name('gui.rework');
        Route::post('gui-prototypen/{key}/wuensche', [GuiPrototypeController::class, 'wish'])->name('gui.wishes.store');
    });

    Route::middleware('can:manage-users')->group(function () {
        Route::post('versionen/neuerungen', [VersionController::class, 'announcements'])->name('versions.announcements');
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
        Route::get('farbkonzept', [ThemeController::class, 'index'])->name('color-concept');
        Route::get('farbkonzept/themes/neu', [ThemeController::class, 'create'])->name('themes.create');
        Route::post('farbkonzept/themes', [ThemeController::class, 'store'])->name('themes.store');
        Route::get('farbkonzept/themes/{id}/bearbeiten', [ThemeController::class, 'edit'])->name('themes.edit');
        Route::put('farbkonzept/themes/{id}', [ThemeController::class, 'update'])->name('themes.update');
        Route::post('farbkonzept/themes/{id}/aktivieren', [ThemeController::class, 'activate'])->name('themes.activate');
        Route::delete('farbkonzept/themes/{id}', [ThemeController::class, 'destroy'])->name('themes.destroy');
        Route::inertia('sprachkonzept', 'language-concept')->name('language-concept');
        Route::get('rollenkonzept', RoleConceptController::class)->name('role-concept');
        Route::get('mcp-konzept', McpConceptController::class)->name('mcp-concept');

        // REST-API: Beschreibung mit Tester und Tokens
        // Menü «Einstellungen»: Einstellungen der Menüpunkte, Browser-Erweiterung
        Route::get('einstellungen/browser-erweiterung', [BrowserExtensionController::class, 'show'])->name('browser-extension');
        Route::get('einstellungen/browser-erweiterung/download', [BrowserExtensionController::class, 'download'])->name('browser-extension.download');
        Route::get('einstellungen/{section}', [ModuleSettingsController::class, 'index'])->whereIn('section', ['fachanwendungen', 'einbindungen'])->name('module-settings');
        Route::get('einstellungen/menuepunkt/{key}/pruefen', [ModuleSettingsController::class, 'check'])->middleware('throttle:30,1')->name('module-settings.check');
        Route::put('einstellungen/menuepunkt/{key}', [ModuleSettingsController::class, 'update'])->name('module-settings.update');
        Route::delete('einstellungen/menuepunkt/{key}', [ModuleSettingsController::class, 'destroy'])->name('module-settings.destroy');

        Route::get('api-doku', [ApiDocsController::class, 'show'])->name('api-docs');
        Route::get('api-doku/referenz', [ApiViewController::class, 'own'])->name('api-docs.reference');
        Route::get('api-recorder', [ApiRecorderController::class, 'index'])->name('api-recorder');
        Route::get('api-recorder/neu', [ApiRecorderController::class, 'create'])->name('api-recorder.create');
        Route::get('api-recorder/{id}', [ApiRecorderController::class, 'show'])->name('api-recorder.show');
        Route::get('api-recorder/{id}/ansicht', [ApiViewController::class, 'recording'])->name('api-recorder.view');
        Route::get('api-recorder/{id}/plugin-entwurf', [ApiRecorderController::class, 'pluginDraft'])->name('api-recorder.plugin-draft');
        Route::post('api-recorder', [ApiRecorderController::class, 'store'])->middleware('throttle:120,1')->name('api-recorder.store');
        Route::delete('api-recorder/{id}', [ApiRecorderController::class, 'destroy'])->name('api-recorder.destroy');
        Route::post('api-doku/tokens', [ApiDocsController::class, 'store'])->middleware('throttle:20,1')->name('api-docs.tokens.store');
        Route::delete('api-doku/tokens/{id}', [ApiDocsController::class, 'destroy'])->name('api-docs.tokens.destroy');

        // Plugins (Anbindung anderer Systeme per API)
        Route::get('einstellungen/module', [ModuleManagementController::class, 'index'])->name('modules.manage');
        Route::post('einstellungen/module/{key}', [ModuleManagementController::class, 'toggle'])->name('modules.toggle');
        Route::post('einstellungen/module/{key}/rechte/{ability}', [ModuleManagementController::class, 'rights'])->name('modules.rights');
        Route::get('plugins', [PluginController::class, 'index'])->name('plugins.index');
        Route::post('plugins/verbindungen-testen', [PluginController::class, 'testAll'])->middleware('throttle:10,1')->name('plugins.test-all');
        Route::get('plugins/{id}', [PluginController::class, 'show'])->name('plugins.show');
        Route::get('plugins/{id}/api-ansicht', [ApiViewController::class, 'plugin'])->name('plugins.api-view');
        Route::post('plugins/{id}/schalter', [PluginController::class, 'toggle'])->name('plugins.toggle');
        Route::put('plugins/{id}/einstellungen', [PluginController::class, 'settings'])->name('plugins.settings');
        Route::middleware('throttle:60,1')->group(function () {
            Route::post('plugins/{id}/test', [PluginController::class, 'test'])->name('plugins.test');
            Route::post('plugins/{id}/aktion/{action}', [PluginController::class, 'action'])->name('plugins.action');
            Route::post('plugins/{id}/api', [PluginController::class, 'api'])->name('plugins.api');
            Route::post('plugins/{id}/zugriff', [PluginController::class, 'access'])->name('plugins.access');
        });
    });
    // Glossar (docs/KONZEPT-GLOSSAR.md)
    Route::middleware('can:edit-glossary')->group(function () {
        Route::get('glossar/neu', [GlossaryController::class, 'create'])->name('glossary.create');
        Route::post('glossar', [GlossaryController::class, 'store'])->name('glossary.store');
        Route::post('glossar/zeile', [GlossaryController::class, 'newRow'])->middleware('throttle:60,1')->name('glossary.row');
        Route::post('glossar/zellen', [GlossaryController::class, 'cells'])->middleware('throttle:120,1')->name('glossary.cells');
        Route::get('glossar/listen', [GlossaryController::class, 'lists'])->name('glossary.lists');
        Route::post('glossar/listen/{list}', [GlossaryController::class, 'storeListItem'])->name('glossary.lists.store');
        Route::put('glossar/listen/{list}/{id}', [GlossaryController::class, 'updateListItem'])->name('glossary.lists.update');
        Route::delete('glossar/listen/{list}/{id}', [GlossaryController::class, 'destroyListItem'])->name('glossary.lists.destroy');
        Route::get('glossar/doppelte', [GlossaryController::class, 'duplicates'])->name('glossary.duplicates');
        Route::post('glossar/doppelte/zusammenfuehren', [GlossaryController::class, 'merge'])->name('glossary.merge');
        Route::post('glossar/doppelte/belassen', [GlossaryController::class, 'keepApart'])->name('glossary.keep');
        Route::post('glossar/import', [GlossaryController::class, 'importPreview'])->middleware('throttle:20,1')->name('glossary.import');
        Route::post('glossar/import/{token}', [GlossaryController::class, 'importApply'])->name('glossary.import.apply');
        Route::get('glossar/{id}/bearbeiten', [GlossaryController::class, 'edit'])->name('glossary.edit');
        Route::put('glossar/{id}', [GlossaryController::class, 'update'])->name('glossary.update');
        Route::delete('glossar/{id}', [GlossaryController::class, 'destroy'])->name('glossary.destroy');
    });
    Route::middleware('can:view-glossary')->group(function () {
        Route::get('glossar', [GlossaryController::class, 'index'])->name('glossary.index');
        Route::post('glossar/export', [GlossaryController::class, 'export'])->middleware('throttle:30,1')->name('glossary.export');
        Route::get('glossar/{id}', [GlossaryController::class, 'show'])->name('glossary.show');
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
