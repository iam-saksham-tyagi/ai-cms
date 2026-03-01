<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EditorController;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard.index');
Route::get('/templates', [DashboardController::class, 'templates'])->name('dashboard.templates');
Route::get('/settings', [DashboardController::class, 'settings'])->name('dashboard.settings');
Route::post('/project/create', [DashboardController::class, 'create'])->name('dashboard.create');
Route::delete('/project/{id}', [DashboardController::class, 'destroy'])->name('dashboard.destroy');
Route::get('/p/{id}', [DashboardController::class, 'live'])->name('dashboard.live');

Route::get('/editor/{id}', [EditorController::class, 'index'])->name('editor.index');
Route::post('/save-page/{id}', [EditorController::class, 'save'])->name('editor.save');
// The route that asks Google AI to generate UI code
Route::post('/generate-ui', [EditorController::class, 'generate'])->name('editor.generate');