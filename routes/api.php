<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\TaskController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);

        Route::get('dashboard', DashboardController::class)->name('dashboard');

        Route::apiResource('projects', ProjectController::class);

        // Every task the user owns, across all of their projects.
        Route::get('tasks', [TaskController::class, 'all'])->name('tasks.all');

        // Shallow: creating and listing need the project for context, while a
        // task is addressable on its own once it exists.
        Route::apiResource('projects.tasks', TaskController::class)->shallow();
    });
});
