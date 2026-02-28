<?php

use App\Http\Controllers\Admin\CountryController as AdminCountryController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\GiftController as AdminGiftController;
use App\Http\Controllers\Admin\LoginController as AdminLoginController;
use App\Http\Controllers\Admin\PackageController as AdminPackageController;
use App\Http\Controllers\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\WealthPrivilegeController as AdminWealthPrivilegeController;
use App\Http\Controllers\Admin\RoomThemeController as AdminRoomThemeController;
use App\Http\Controllers\Admin\WithdrawalRequestController as AdminWithdrawalRequestController;
use Illuminate\Support\Facades\Route;

// Public pages for Google Play Console & app landing
Route::get('/', function () {
    return view('landing');
})->name('home');

Route::get('/privacy-policy', function () {
    return view('privacy-policy');
})->name('privacy-policy');

Route::get('/terms-and-conditions', function () {
    return view('terms-and-conditions');
})->name('terms-and-conditions');

Route::get('/delete-account', function () {
    return view('delete-account');
})->name('delete-account');

Route::get('/child-safety', function () {
    return view('child-safety');
})->name('child-safety');

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('login', [AdminLoginController::class, 'showLoginForm'])->name('login');
    Route::post('login', [AdminLoginController::class, 'login'])->name('login.post');

    Route::middleware('admin')->group(function () {
        Route::post('logout', [AdminLoginController::class, 'logout'])->name('logout');
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('settings', [AdminSettingsController::class, 'index'])->name('settings.index');
        Route::put('settings', [AdminSettingsController::class, 'update'])->name('settings.update');

        Route::resource('packages', AdminPackageController::class)->except(['show']);
        Route::resource('gifts', AdminGiftController::class)->except(['show']);
        Route::resource('themes', AdminRoomThemeController::class)->except(['show']);
        Route::resource('privileges', AdminWealthPrivilegeController::class)->except(['show']);
        Route::resource('countries', AdminCountryController::class)->except(['show']);

        Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
        Route::get('users/create', [AdminUserController::class, 'create'])->name('users.create');
        Route::post('users', [AdminUserController::class, 'store'])->name('users.store');
        Route::delete('users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');
        Route::post('users/add-credit', [AdminUserController::class, 'addCredit'])->name('users.add-credit');

        Route::get('withdrawals', [AdminWithdrawalRequestController::class, 'index'])->name('withdrawals.index');
        Route::get('withdrawals/{withdrawal}/approve', [AdminWithdrawalRequestController::class, 'showApproveForm'])->name('withdrawals.approve-form');
        Route::post('withdrawals/{withdrawal}/approve', [AdminWithdrawalRequestController::class, 'approve'])->name('withdrawals.approve');
        Route::get('withdrawals/{withdrawal}/reject', [AdminWithdrawalRequestController::class, 'showRejectForm'])->name('withdrawals.reject-form');
        Route::post('withdrawals/{withdrawal}/reject', [AdminWithdrawalRequestController::class, 'reject'])->name('withdrawals.reject');

        Route::get('reports', [AdminReportController::class, 'index'])->name('reports.index');
        Route::get('reports/ledger', [AdminReportController::class, 'ledger'])->name('reports.ledger');
        Route::get('reports/revenue', [AdminReportController::class, 'revenue'])->name('reports.revenue');
    });
});
