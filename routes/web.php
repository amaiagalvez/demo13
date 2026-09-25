<?php

use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerTrashController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::get('customers/trash', [CustomerTrashController::class, 'index'])->name('customers.trash.index');
    Route::patch('customers/trash/{customer}', [CustomerTrashController::class, 'restore'])
        ->whereNumber('customer')
        ->name('customers.trash.restore');
    Route::delete('customers/trash/{customer}', [CustomerTrashController::class, 'destroy'])
        ->whereNumber('customer')
        ->name('customers.trash.destroy');
    Route::resource('customers', CustomerController::class)->only(['index', 'store', 'update', 'destroy']);
});

require __DIR__.'/settings.php';
