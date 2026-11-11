<?php

use Illuminate\Support\Facades\Route;
use Modules\Beschaffung\Http\BildController;
use Modules\Beschaffung\Http\KatalogController;
use Modules\Beschaffung\Kataloge;

// Routen der Beschaffung (nur geladen, wenn das Modul eingeschaltet ist; angemeldet)
$kataloge = implode('|', Kataloge::schluessel());

Route::middleware('can:edit-beschaffung')->group(function () use ($kataloge) {
    Route::post('beschaffung/bild/{id}', [BildController::class, 'store'])->middleware('throttle:60,1')->name('beschaffung.bild.store');
    Route::post('beschaffung/bild/{id}/entfernen', [BildController::class, 'destroy'])->name('beschaffung.bild.destroy');
    Route::post('beschaffung/{katalog}/zellen', [KatalogController::class, 'zellen'])->where('katalog', $kataloge)->middleware('throttle:240,1')->name('beschaffung.zellen');
    Route::post('beschaffung/{katalog}/zeile', [KatalogController::class, 'zeile'])->where('katalog', $kataloge)->name('beschaffung.zeile');
    Route::post('beschaffung/{katalog}/loeschen', [KatalogController::class, 'loeschen'])->where('katalog', $kataloge)->name('beschaffung.loeschen');
});

Route::middleware('can:view-beschaffung')->group(function () use ($kataloge) {
    Route::get('beschaffung', [KatalogController::class, 'index'])->name('beschaffung.index');
    Route::get('beschaffung/bild/{id}', [BildController::class, 'show'])->name('beschaffung.bild');
    Route::get('beschaffung/{katalog}', [KatalogController::class, 'show'])->where('katalog', $kataloge)->name('beschaffung.katalog');
});
