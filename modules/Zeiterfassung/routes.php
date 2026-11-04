<?php

use Illuminate\Support\Facades\Route;
use Modules\Zeiterfassung\Http\VisumController;
use Modules\Zeiterfassung\Http\ZeiterfassungController;

// Routen der Zeiterfassung (nur geladen, wenn das Modul eingeschaltet ist; angemeldet)
Route::middleware('can:use-zeiterfassung')->group(function () {
    Route::get('zeiterfassung', [ZeiterfassungController::class, 'index'])->name('zeiterfassung.index');
    Route::post('zeiterfassung', [ZeiterfassungController::class, 'store'])->name('zeiterfassung.store');
    Route::delete('zeiterfassung/eintraege/{id}', [ZeiterfassungController::class, 'destroy'])->name('zeiterfassung.destroy');
    Route::post('zeiterfassung/einreichen', [ZeiterfassungController::class, 'einreichen'])->name('zeiterfassung.einreichen');
    Route::get('zeiterfassung/unterschrift/{person}/{monat}', [ZeiterfassungController::class, 'unterschriftPdf'])->name('zeiterfassung.unterschrift-pdf');
});

Route::middleware('can:visum-zeiterfassung')->group(function () {
    Route::get('zeiterfassung/visum', [VisumController::class, 'index'])->name('zeiterfassung.visum');
    Route::get('zeiterfassung/visum/export', [VisumController::class, 'export'])->name('zeiterfassung.export');
    Route::get('zeiterfassung/visum/{person}', [VisumController::class, 'show'])->name('zeiterfassung.person');
    Route::post('zeiterfassung/visum/{person}/visieren', [VisumController::class, 'visieren'])->name('zeiterfassung.visieren');
    Route::post('zeiterfassung/visum/{person}/zurueck', [VisumController::class, 'zurueck'])->name('zeiterfassung.zurueck');
    Route::post('zeiterfassung/visum/{person}/unterschrift', [VisumController::class, 'unterschrift'])->middleware('throttle:20,1')->name('zeiterfassung.unterschrift');
});
