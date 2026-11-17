<?php

use App\Http\Controllers\Admin\DepositReviewController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Customer\DepositController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'storefront.home')->name('home');

Route::get('/locale/{locale}', function (string $locale) {
    abort_unless(in_array($locale, config('app.supported_locales')), 404);
    request()->session()->put('locale', $locale);
    return back();
})->name('locale.switch');

// -----------------------------------------------------------------------
// Guest-only authentication routes. No public registration for admin/staff
// accounts exists anywhere in this file — see InstallAdminCommand.
// -----------------------------------------------------------------------
Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('register', [RegisteredUserController::class, 'store']);
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);
    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');
    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('reset-password', [NewPasswordController::class, 'store'])->name('password.store');
});

Route::middleware('auth')->group(function () {
    Route::get('verify-email', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('verify-email/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware('signed')->name('verification.verify');
    Route::post('email/verification-notification', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:6,1')->name('verification.send');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
});

// -----------------------------------------------------------------------
// Admin layout — super-admin | admin | staff
// -----------------------------------------------------------------------
Route::middleware(['auth', 'role:super-admin,admin,staff'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', fn () => view('admin.dashboard'))->name('dashboard');
    Route::get('/deposits', [DepositReviewController::class, 'index'])->name('deposits.index');
    Route::post('/deposits/{deposit}/approve', [DepositReviewController::class, 'approve'])->name('deposits.approve');
    Route::post('/deposits/{deposit}/reject', [DepositReviewController::class, 'reject'])->name('deposits.reject');
});

// -----------------------------------------------------------------------
// Customer layout
// -----------------------------------------------------------------------
Route::middleware(['auth', 'role:customer,distributor'])->prefix('account')->name('customer.')->group(function () {
    Route::get('/', fn () => view('customer.dashboard'))->name('dashboard');
    Route::get('/deposits/create', [DepositController::class, 'create'])->name('deposits.create');
    Route::post('/deposits', [DepositController::class, 'store'])->name('deposits.store');
});

// -----------------------------------------------------------------------
// Reseller layout
// -----------------------------------------------------------------------
Route::middleware(['auth', 'role:reseller,distributor'])->prefix('reseller')->name('reseller.')->group(function () {
    Route::get('/', fn () => view('reseller.dashboard'))->name('dashboard');
});
