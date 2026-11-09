<?php

use Illuminate\Support\Facades\Route;
use Modules\Zeiterfassung\Http\VisumController;
use Modules\Zeiterfassung\Http\ZeiterfassungController;

// Routen der Zeiterfassung (nur geladen, wenn das Modul eingeschaltet ist; angemeldet)
Route::middleware('can:use-zeiterfassung')->group(function () {
    Route::get('zeiterfassung', [ZeiterfassungController::class, 'index'])->name('zeiterfassung.index');
    Route::post('zeiterfassung', [ZeiterfassungController::class, 'store'])->name('zeiterfassung.store');
    Route::put('zeiterfassung/eintraege/{id}', [ZeiterfassungController::class, 'update'])->name('zeiterfassung.update');
    Route::delete('zeiterfassung/eintraege/{id}', [ZeiterfassungController::class, 'destroy'])->name('zeiterfassung.destroy');
    Route::post('zeiterfassung/einreichen', [ZeiterfassungController::class, 'einreichen'])->name('zeiterfassung.einreichen');
    Route::post('zeiterfassung/aus-clicktime', [ZeiterfassungController::class, 'ausClicktime'])->middleware('throttle:10,1')->name('zeiterfassung.clicktime');
    Route::post('zeiterfassung/abschliessen', [ZeiterfassungController::class, 'abschliessen'])->middleware('throttle:10,1')->name('zeiterfassung.abschliessen');
    Route::get('zeiterfassung/monatsblatt/{person}/{monat}', [ZeiterfassungController::class, 'monatsblattPdf'])->name('zeiterfassung.monatsblatt');
    Route::get('zeiterfassung/unterschrift/{person}/{monat}', [ZeiterfassungController::class, 'unterschriftPdf'])->name('zeiterfassung.unterschrift-pdf');
});

Route::middleware('can:visum-zeiterfassung')->group(function () {
    Route::get('zeiterfassung/visum', [VisumController::class, 'index'])->name('zeiterfassung.visum');
    Route::get('zeiterfassung/visum/export', [VisumController::class, 'export'])->name('zeiterfassung.export');
    Route::get('zeiterfassung/buchungsarten', [VisumController::class, 'buchungsarten'])->name('zeiterfassung.buchungsarten');
    Route::put('zeiterfassung/buchungsarten', [VisumController::class, 'buchungsartenSpeichern'])->name('zeiterfassung.buchungsarten.update');
    Route::get('zeiterfassung/visum/{person}', [VisumController::class, 'show'])->name('zeiterfassung.person');
    Route::post('zeiterfassung/visum/{person}/visieren', [VisumController::class, 'visieren'])->name('zeiterfassung.visieren');
    Route::post('zeiterfassung/visum/{person}/zurueck', [VisumController::class, 'zurueck'])->name('zeiterfassung.zurueck');
    Route::post('zeiterfassung/visum/erinnerung', [VisumController::class, 'erinnerung'])->middleware('throttle:10,1')->name('zeiterfassung.erinnerung');
    Route::post('zeiterfassung/visum/{person}/abschliessen', [VisumController::class, 'abschliessen'])->middleware('throttle:10,1')->name('zeiterfassung.abschliessen-stellvertretend');
    Route::post('zeiterfassung/visum/{person}/vergleichen', [VisumController::class, 'vergleichen'])->middleware('throttle:10,1')->name('zeiterfassung.vergleichen');
    Route::post('zeiterfassung/visum/{person}/unterschrift', [VisumController::class, 'unterschrift'])->middleware('throttle:20,1')->name('zeiterfassung.unterschrift');
});
