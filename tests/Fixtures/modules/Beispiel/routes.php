<?php

use Illuminate\Support\Facades\Route;
use Modules\Beispiel\Http\BeispielController;

// Routen des Moduls Beispielmodul (geladen nur, wenn das Modul eingeschaltet ist; angemeldet)
Route::middleware('can:use-beispiel')->group(function () {
    Route::get('beispiel', [BeispielController::class, 'index'])->name('beispiel.index');
    Route::post('beispiel', [BeispielController::class, 'store'])->name('beispiel.store');
});
