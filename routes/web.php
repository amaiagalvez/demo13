<?php

use Illuminate\Support\Facades\Route;
use Projects13\Queries\Dashboard\DashboardSummaryQuery;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    // The summary belongs to projects13; the host only lays it out.
    Route::get('dashboard', fn (DashboardSummaryQuery $query) => view('dashboard', [
        'summary' => $query->summary(),
    ]))->name('dashboard');
});
