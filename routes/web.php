<?php

use App\Http\Controllers\Admin\WhatsAppConnectionController;
use App\Http\Controllers\Auth\AdminAuthController;
use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('guest')->group(function () {
    Route::get('/admin/login', [AdminAuthController::class, 'create'])
        ->name('login');

    Route::post('/admin/login', [AdminAuthController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('login.store');
});

Route::post('/admin/logout', [AdminAuthController::class, 'destroy'])
    ->middleware('auth')
    ->name('admin.logout');

Route::middleware(['auth', EnsureUserIsAdmin::class])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::view('/dashboard', 'admin.dashboard')
            ->name('dashboard');

        Route::get(
            '/whatsapp/status',
            [WhatsAppConnectionController::class, 'status']
        )->name('whatsapp.status');
    });
