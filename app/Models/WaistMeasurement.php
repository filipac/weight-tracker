<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaistMeasurement extends Model
{
    protected $fillable = [
        'date',
        'waist_cm',
    ];

    protected $casts = [
        'date' => 'date',
        'waist_cm' => 'decimal:1',
    ];
}
