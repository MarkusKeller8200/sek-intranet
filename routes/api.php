<?php

use App\Http\Controllers\Api\V1Controller;
use App\Http\Controllers\SignatureCallbackController;
use App\Http\Middleware\AuthenticateApiToken;
use App\Support\OpenApi;
use Illuminate\Support\Facades\Route;

/*
| REST-API (Präfix /api). Ohne Sitzung und ohne gemeinsames Seitenpasswort,
| Anmeldung nur mit API-Token. Beschreibung: /api/openapi.json
*/

Route::get('openapi.json', fn () => response()->json(OpenApi::spec(), 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
    ->middleware('throttle:60,1')
    ->name('api.openapi');

// Webhook der Unterschriften (#40): ohne Token, geprüft über die Signatur je Auftrag
Route::post('signaturen/{provider}/callback', SignatureCallbackController::class)
    ->middleware('throttle:60,1')
    ->where('provider', '[a-z0-9-]+')
    ->name('signatures.callback');

Route::prefix('v1')->middleware([AuthenticateApiToken::class, 'throttle:120,1'])->group(function () {
    Route::get('me', [V1Controller::class, 'me'])->name('api.me');
    Route::get('people', [V1Controller::class, 'people'])->name('api.people.index');
    Route::post('people', [V1Controller::class, 'createPerson'])->name('api.people.store');
    Route::get('people/{id}', [V1Controller::class, 'person'])->name('api.people.show');
    Route::patch('people/{id}', [V1Controller::class, 'updatePerson'])->name('api.people.update');
    Route::delete('people/{id}', [V1Controller::class, 'deletePerson'])->name('api.people.destroy');
    Route::get('announcements', [V1Controller::class, 'announcements'])->name('api.announcements');
    Route::get('events', [V1Controller::class, 'events'])->name('api.events');
    Route::get('documents', [V1Controller::class, 'documents'])->name('api.documents');
    Route::get('options', [V1Controller::class, 'options'])->name('api.options');
});
