<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\SavedEarthquake;
use Illuminate\Http\Request;
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

    public function saveEarthquake(Request $request)
    {
        $data = $request->input('data');

        $savedEarthquake = SavedEarthquake::create([
            'earthquake_id' => $data['id'],
            'data' => $data,
        ]);

        return response()->json($savedEarthquake);
    }

    public function getSavedEarthquakes()
    {
        $savedEarthquakes = SavedEarthquake::orderBy('created_at', 'desc')->get();

        return response()->json($savedEarthquakes);
    }

    public function deleteSavedEarthquake($id)
    {
        SavedEarthquake::where('earthquake_id', $id)->delete();

        return response()->json(['success' => true]);
    }
}
