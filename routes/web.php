<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EpicController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\EpicTrashController;
use App\Http\Controllers\EpicCommentController;
use App\Http\Controllers\EpicInactiveController;
use App\Http\Controllers\ProjectTrashController;
use App\Http\Controllers\CustomerTrashController;
use App\Http\Controllers\ProjectInactiveController;
use App\Http\Controllers\CustomerInactiveController;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::get('customers/inactive', [CustomerInactiveController::class, 'index'])->name('customers.inactive.index');
    Route::patch('customers/inactive/{customer}', [CustomerInactiveController::class, 'reactivate'])
        ->whereNumber('customer')
        ->name('customers.inactive.reactivate');
    Route::patch('customers/{customer}/deactivate', [CustomerInactiveController::class, 'deactivate'])
        ->whereNumber('customer')
        ->name('customers.deactivate');
    Route::get('customers/trash', [CustomerTrashController::class, 'index'])->name('customers.trash.index');
    Route::patch('customers/trash/{customer}', [CustomerTrashController::class, 'restore'])
        ->whereNumber('customer')
        ->name('customers.trash.restore');
    Route::delete('customers/trash/{customer}', [CustomerTrashController::class, 'destroy'])
        ->whereNumber('customer')
        ->name('customers.trash.destroy');
    Route::resource('customers', CustomerController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::get('projects/inactive', [ProjectInactiveController::class, 'index'])->name('projects.inactive.index');
    Route::patch('projects/inactive/{project}', [ProjectInactiveController::class, 'reactivate'])
        ->whereNumber('project')
        ->name('projects.inactive.reactivate');
    Route::patch('projects/{project}/deactivate', [ProjectInactiveController::class, 'deactivate'])
        ->whereNumber('project')
        ->name('projects.deactivate');
    Route::get('projects/trash', [ProjectTrashController::class, 'index'])->name('projects.trash.index');
    Route::patch('projects/trash/{project}', [ProjectTrashController::class, 'restore'])
        ->whereNumber('project')
        ->name('projects.trash.restore');
    Route::delete('projects/trash/{project}', [ProjectTrashController::class, 'destroy'])
        ->whereNumber('project')
        ->name('projects.trash.destroy');
    Route::resource('projects', ProjectController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::get('epics/inactive', [EpicInactiveController::class, 'index'])->name('epics.inactive.index');
    Route::patch('epics/inactive/{epic}', [EpicInactiveController::class, 'reactivate'])
        ->whereNumber('epic')
        ->name('epics.inactive.reactivate');
    Route::patch('epics/{epic}/deactivate', [EpicInactiveController::class, 'deactivate'])
        ->whereNumber('epic')
        ->name('epics.deactivate');
    Route::get('epics/trash', [EpicTrashController::class, 'index'])->name('epics.trash.index');
    Route::patch('epics/trash/{epic}', [EpicTrashController::class, 'restore'])
        ->whereNumber('epic')
        ->name('epics.trash.restore');
    Route::delete('epics/trash/{epic}', [EpicTrashController::class, 'destroy'])
        ->whereNumber('epic')
        ->name('epics.trash.destroy');
    Route::post('epics/{epic}/comments', [EpicCommentController::class, 'store'])->name('epics.comments.store');
    Route::resource('epics', EpicController::class)->only(['index', 'store', 'update', 'destroy']);
});

require __DIR__.'/settings.php';
