<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EditorController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/editor', [EditorController::class, 'index'])->name('editor.index');
// NEW: The route that receives the saved data from the frontend
Route::post('/save-page', [EditorController::class, 'save'])->name('editor.save');
// The route that asks Google AI to generate UI code
Route::post('/generate-ui', [EditorController::class, 'generate'])->name('editor.generate');