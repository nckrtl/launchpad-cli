<?php

use App\Http\Controllers\Api\ProjectController;
use Illuminate\Support\Facades\Route;

// Health check
Route::get('/health', [ProjectController::class, 'health']);

// Project management
Route::post('/projects', [ProjectController::class, 'store']);
Route::delete('/projects/{slug}', [ProjectController::class, 'destroy']);
