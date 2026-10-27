<?php

use App\Http\Controllers\UpdateFeedController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::get('updates/feed/{channel}', UpdateFeedController::class)
    ->middleware('throttle:60,1')
    ->name('updates.feed');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
