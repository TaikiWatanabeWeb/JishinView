<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\WeatherController;
use App\Http\Controllers\Web\EarthquakeController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;


Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::get('/', [EarthquakeController::class, 'index'])->name('earthquake.index');
Route::get('/api/earthquake/history', [EarthquakeController::class, 'getLatestEarthquake']);
Route::post('/api/earthquake/save', [EarthquakeController::class, 'saveEarthquake']);
Route::get('/api/earthquake/saved', [EarthquakeController::class, 'getSavedEarthquakes']);
Route::delete('/api/earthquake/saved/{id}', [EarthquakeController::class, 'deleteSavedEarthquake']);


require __DIR__.'/auth.php';
