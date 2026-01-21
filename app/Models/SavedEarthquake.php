<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SavedEarthquake extends Model
{
    use HasFactory;

    protected $fillable = [
        'earthquake_id',
        'data',
    ];

    protected $casts = [
        'data' => 'array',
    ];
}
