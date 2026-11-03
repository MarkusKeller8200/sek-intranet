<?php

use Illuminate\Support\Facades\Route;
use Modules\Ablaeufe\Http\AblaeufeController;
use Modules\Ablaeufe\Http\EinstellungenController;
use Modules\Ablaeufe\Http\GruppenController;

// Routen der Abläufe (nur geladen, wenn das Modul eingeschaltet ist; angemeldet)
Route::middleware('can:config-ablaeufe')->group(function () {
    Route::get('ablaeufe/einstellungen', [EinstellungenController::class, 'index'])->name('ablaeufe.einstellungen');
    Route::post('ablaeufe/einstellungen/{liste}', [EinstellungenController::class, 'store'])->name('ablaeufe.einstellungen.store');
    Route::post('ablaeufe/einstellungen/{liste}/reihenfolge', [EinstellungenController::class, 'sort'])->name('ablaeufe.einstellungen.sort');
    Route::put('ablaeufe/einstellungen/{liste}/{id}', [EinstellungenController::class, 'update'])->name('ablaeufe.einstellungen.update');
    Route::delete('ablaeufe/einstellungen/{liste}/{id}', [EinstellungenController::class, 'destroy'])->name('ablaeufe.einstellungen.destroy');
});

Route::middleware('can:edit-ablaeufe')->group(function () {
    Route::get('ablaeufe/neu', [AblaeufeController::class, 'create'])->name('ablaeufe.create');
    Route::post('ablaeufe', [AblaeufeController::class, 'store'])->name('ablaeufe.store');
    Route::get('ablaeufe/gruppen', [GruppenController::class, 'index'])->name('ablaeufe.gruppen');
    Route::post('ablaeufe/gruppen', [GruppenController::class, 'store'])->name('ablaeufe.gruppen.store');
    Route::post('ablaeufe/gruppen/reihenfolge', [GruppenController::class, 'sort'])->name('ablaeufe.gruppen.sort');
    Route::put('ablaeufe/gruppen/{id}', [GruppenController::class, 'update'])->name('ablaeufe.gruppen.update');
    Route::delete('ablaeufe/gruppen/{id}', [GruppenController::class, 'destroy'])->name('ablaeufe.gruppen.destroy');
    Route::get('ablaeufe/{id}/bearbeiten', [AblaeufeController::class, 'edit'])->name('ablaeufe.edit');
    Route::put('ablaeufe/{id}', [AblaeufeController::class, 'update'])->name('ablaeufe.update');
    Route::delete('ablaeufe/{id}', [AblaeufeController::class, 'destroy'])->name('ablaeufe.destroy');
});

Route::middleware('can:view-ablaeufe')->group(function () {
    Route::get('ablaeufe', [AblaeufeController::class, 'index'])->name('ablaeufe.index');
    Route::get('ablaeufe/{id}', [AblaeufeController::class, 'show'])->name('ablaeufe.show');
});
