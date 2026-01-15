<?php

use App\Http\Controllers\Web\EarthquakeController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('map');
});


Route::get('/api/earthquake/history', [EarthquakeController::class, 'getLatestEarthquake']);
