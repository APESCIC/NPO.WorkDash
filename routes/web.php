<?php

use App\Http\Controllers\Admin\InstallationController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\UpdateFeedController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::get('updates/feed/{channel}', UpdateFeedController::class)
    ->middleware('throttle:60,1')
    ->name('updates.feed');

Route::middleware(['not-installed', 'throttle:120,1'])->prefix('install')->name('install.')->group(function () {
    Route::get('/', [InstallController::class, 'show'])->name('show');
    Route::post('{step}', [InstallController::class, 'store'])->name('store');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

Route::middleware(['auth', 'verified', 'can:super-admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('installation', [InstallationController::class, 'show'])->name('installation.show');
    Route::delete('installation/installer-files', [InstallationController::class, 'removeInstallerFiles'])->name('installation.cleanup');
});

require __DIR__.'/settings.php';
