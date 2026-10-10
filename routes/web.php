<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EpicController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\PlanningController;
use App\Http\Controllers\TimelineController;
use App\Http\Controllers\EpicTrashController;
use App\Http\Controllers\EpicCommentController;
use App\Http\Controllers\EpicArchivedController;
use App\Http\Controllers\ProjectTrashController;
use App\Http\Controllers\ProjectArchivedController;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::get('planning', [PlanningController::class, 'index'])->name('planning');
    Route::get('timeline', [TimelineController::class, 'index'])->name('timeline');
    Route::get('projects/archived', [ProjectArchivedController::class, 'index'])->name('projects.archived.index');
    Route::patch('projects/archived/{project}', [ProjectArchivedController::class, 'activate'])
        ->whereNumber('project')
        ->name('projects.archived.activate');
    Route::patch('projects/{project}/archive', [ProjectArchivedController::class, 'archive'])
        ->whereNumber('project')
        ->name('projects.archive');
    Route::get('projects/trash', [ProjectTrashController::class, 'index'])->name('projects.trash.index');
    Route::patch('projects/trash/{project}', [ProjectTrashController::class, 'restore'])
        ->whereNumber('project')
        ->name('projects.trash.restore');
    Route::delete('projects/trash/{project}', [ProjectTrashController::class, 'destroy'])
        ->whereNumber('project')
        ->name('projects.trash.destroy');
    Route::get('projects/options', [ProjectController::class, 'selectOptions'])
        ->name('projects.options');
    Route::resource('projects', ProjectController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::get('epics/archived', [EpicArchivedController::class, 'index'])->name('epics.archived.index');
    Route::patch('epics/archived/{epic}', [EpicArchivedController::class, 'activate'])
        ->whereNumber('epic')
        ->name('epics.archived.activate');
    Route::patch('epics/{epic}/archive', [EpicArchivedController::class, 'archive'])
        ->whereNumber('epic')
        ->name('epics.archive');
    Route::get('epics/trash', [EpicTrashController::class, 'index'])->name('epics.trash.index');
    Route::patch('epics/trash/{epic}', [EpicTrashController::class, 'restore'])
        ->whereNumber('epic')
        ->name('epics.trash.restore');
    Route::delete('epics/trash/{epic}', [EpicTrashController::class, 'destroy'])
        ->whereNumber('epic')
        ->name('epics.trash.destroy');
    Route::get('epics/{epic}/comments', [EpicCommentController::class, 'show'])
        ->whereNumber('epic')
        ->name('epics.comments.index');
    Route::post('epics/{epic}/comments', [EpicCommentController::class, 'store'])
        ->whereNumber('epic')
        ->name('epics.comments.store');
    Route::resource('epics', EpicController::class)->only(['index', 'store', 'update', 'destroy']);
});

require __DIR__.'/settings.php';
