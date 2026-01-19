<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class WeatherController extends Controller
{
    /**
     * Open-Meteo APIのプロキシとして風データを取得する
     */
    public function getWindData(Request $request)
    {
        // POSTボディからデータを取得
        $latitude = $request->input('latitude');
        $longitude = $request->input('longitude');

        if (!$latitude || !$longitude) {
            return response()->json(['error' => 'Latitude and longitude are required.'], 400);
        }

        // Open-Meteoへリクエスト
        // サーバーサイドからのリクエストなのでURL長制限は緩いが、
        // Open-Meteo側の制限に引っかかる可能性はあるため、
        // フロントエンド側でチャンクサイズを適切に調整することが重要
        $response = Http::get('https://api.open-meteo.com/v1/forecast', [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'current_weather' => 'true',
            'windspeed_unit' => 'ms',
        ]);

        if ($response->failed()) {
            return response()->json(['error' => 'Failed to fetch data from Open-Meteo'], $response->status());
        }

        return $response->json();
    }
}
