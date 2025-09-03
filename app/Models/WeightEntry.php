<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WeightEntry extends Model
{
    protected $fillable = [
        'date',
        'weight_kg',
    ];

    protected $casts = [
        'date' => 'date',
        'weight_kg' => 'decimal:2',
    ];
}
