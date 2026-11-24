<?php

use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\ApiTokenController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\ShiftController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\TwoFactorController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login');

Route::middleware('guest')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1')->name('register');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:3,1')->name('password.email');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1')->name('password.update');
});

Route::get('/email/verify/{id}', [AuthController::class, 'verifyEmail'])
    ->middleware('signed')
    ->name('verification.verify');

// 2FA challenge: verifikasi TOTP sebelum sesi dibuat.
Route::post('/two-factor/challenge', [TwoFactorController::class, 'challenge'])
    ->middleware('throttle:10,1')
    ->name('two-factor.challenge');

Route::middleware(['auth'])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Profil
    Route::middleware('throttle:20,1')->group(function () {
        Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::put('/profile/password', [ProfileController::class, 'changePassword'])->name('profile.password');
        Route::post('/profile/avatar', [ProfileController::class, 'uploadAvatar'])->name('profile.avatar');
        Route::delete('/profile/avatar', [ProfileController::class, 'destroyAvatar'])->name('profile.avatar.destroy');
        Route::post('/email/verification-notification', [AuthController::class, 'sendVerification'])
            ->middleware('throttle:3,1')
            ->name('verification.send');
    });

    // 2FA
    Route::get('/two-factor/setup', [TwoFactorController::class, 'setup'])->name('two-factor.setup');
    Route::post('/two-factor/confirm', [TwoFactorController::class, 'confirm'])->name('two-factor.confirm');
    Route::delete('/two-factor', [TwoFactorController::class, 'disable'])->name('two-factor.disable');

    // API tokens (Bearer) — dikelola lewat session web.
    Route::get('/api-tokens', [ApiTokenController::class, 'index'])->name('api-tokens.index');
    Route::post('/api-tokens', [ApiTokenController::class, 'store'])->name('api-tokens.store');
    Route::delete('/api-tokens/{token}', [ApiTokenController::class, 'destroy'])->name('api-tokens.destroy');

    // Settings
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');

    Route::middleware('permission:users.view')->group(function () {
        Route::get('/users', [UserController::class, 'index']);
        Route::get('/users/export', [UserController::class, 'export'])->name('users.export');
        Route::get('/users/trash', [UserController::class, 'trash']);
    });
    Route::middleware('permission:users.create')->group(function () {
        Route::post('/users', [UserController::class, 'store']);
        Route::post('/users/import', [UserController::class, 'import'])->name('users.import');
    });
    Route::middleware('permission:users.update')->group(function () {
        Route::put('/users/{user}', [UserController::class, 'update']);
        Route::post('/users/{id}/restore', [UserController::class, 'restore'])->whereNumber('id');
    });
    Route::middleware('permission:users.delete')->group(function () {
        Route::delete('/users/{user}', [UserController::class, 'destroy']);
        Route::delete('/users/{id}/force', [UserController::class, 'forceDelete'])->whereNumber('id');
    });

    Route::middleware('permission:roles.view')->group(function () {
        Route::get('/roles', [RoleController::class, 'index']);
    });
    Route::middleware('permission:roles.create')->group(function () {
        Route::post('/roles', [RoleController::class, 'store']);
    });
    Route::middleware('permission:roles.update')->group(function () {
        Route::put('/roles/{role}', [RoleController::class, 'update']);
    });
    Route::middleware('permission:roles.delete')->group(function () {
        Route::delete('/roles/{role}', [RoleController::class, 'destroy']);
    });

    Route::middleware('permission:permissions.view')->group(function () {
        Route::get('/permissions', [PermissionController::class, 'index']);
    });
    Route::middleware('permission:permissions.create')->group(function () {
        Route::post('/permissions', [PermissionController::class, 'store']);
    });
    Route::middleware('permission:permissions.update')->group(function () {
        Route::put('/permissions/{permission}', [PermissionController::class, 'update']);
    });
    Route::middleware('permission:permissions.delete')->group(function () {
        Route::delete('/permissions/{permission}', [PermissionController::class, 'destroy']);
    });

    Route::middleware('permission:activity.view')->group(function () {
        Route::get('/activity-logs', [ActivityLogController::class, 'index']);
    });

    // ===== POS (Kasir) =====

    // Katalog & sync: dipakai PWA offline (katalog snapshot/delta + push outbox).
    Route::middleware('permission:pos.sell')->group(function () {
        Route::get('/pos/catalog', [CatalogController::class, 'index'])->name('pos.catalog');
        Route::post('/pos/sync', [SyncController::class, 'push'])->name('pos.sync');
        Route::post('/pos/transactions', [TransactionController::class, 'store'])->name('pos.transactions.store');
        Route::get('/pos/transactions', [TransactionController::class, 'index'])->name('pos.transactions.index');
        Route::get('/pos/shifts/active', [ShiftController::class, 'active'])->name('pos.shifts.active');
        Route::post('/pos/shifts', [ShiftController::class, 'open'])->name('pos.shifts.open');
        Route::post('/pos/shifts/{shift}/close', [ShiftController::class, 'close'])->name('pos.shifts.close');
        Route::get('/pos/shifts/{shift}', [ShiftController::class, 'show'])->name('pos.shifts.show');
        Route::get('/pos/reports', [ReportController::class, 'index'])->name('pos.reports');
    });

    Route::middleware('permission:products.view')->group(function () {
        Route::get('/pos/products', [ProductController::class, 'index']);
        Route::get('/pos/categories', [CategoryController::class, 'index']);
    });
    Route::middleware('permission:products.create')->group(function () {
        Route::post('/pos/products', [ProductController::class, 'store']);
        Route::post('/pos/categories', [CategoryController::class, 'store']);
    });
    Route::middleware('permission:products.update')->group(function () {
        Route::put('/pos/products/{product}', [ProductController::class, 'update']);
        Route::put('/pos/categories/{category}', [CategoryController::class, 'update']);
    });
    Route::middleware('permission:products.delete')->group(function () {
        Route::delete('/pos/products/{product}', [ProductController::class, 'destroy']);
        Route::delete('/pos/categories/{category}', [CategoryController::class, 'destroy']);
    });
});

// Endpoint yang bisa diautentikasi via Bearer token (mobile / integrasi).
Route::middleware(['api-token', 'throttle:60,1'])->group(function () {
    Route::get('/token/me', [AuthController::class, 'me'])->name('token.me');
    Route::get('/token/dashboard', [DashboardController::class, 'index'])->name('token.dashboard');
});
