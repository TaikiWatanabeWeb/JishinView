<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;

class EarthquakeController extends Controller
{
    public function getLatestEarthquake()
    {
        $response = Http::get('https://api.p2pquake.com/v2/history', [
            'codes' => 551,
            'limit' => 15
        ]);

        // JSONとして返す
        return $response->json();
    }

    public function index()
    {
        // resources/js/Pages/EarthquakeMap.vue を表示する
        return Inertia::render('EarthquakeMap');
    }
}
