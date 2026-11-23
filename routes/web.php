<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\GenerationController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => redirect(auth()->check() ? '/dashboard' : '/login'));
Route::middleware('guest')->group(function () {
    Route::get('/login', fn () => Inertia::render('Auth', ['mode' => 'login']))->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:20,1');
    Route::get('/register', fn () => Inertia::render('Auth', ['mode' => 'register']))->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
});
Route::post('/webhooks/pixelforge', WebhookController::class)->name('webhooks.pixelforge');
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [GenerationController::class, 'dashboard'])->name('dashboard');
    Route::get('/generations/create', [GenerationController::class, 'create'])->name('generations.create');
    Route::post('/generations/estimate', [GenerationController::class, 'estimate'])->middleware('throttle:40,1')->name('generations.estimate');
    Route::post('/generations', [GenerationController::class, 'store'])->middleware('throttle:10,1')->name('generations.store');
    Route::get('/generations', [GenerationController::class, 'index'])->name('generations.index');
    Route::get('/generations/{generation}', [GenerationController::class, 'show'])->name('generations.show');
    Route::get('/settings', [AuthController::class, 'settings'])->name('settings');
    Route::patch('/settings', [AuthController::class, 'update'])->middleware('throttle:10,1');
});
