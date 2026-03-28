<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VillageDailyStat extends Model
{
    use HasFactory;

    protected $fillable = [
        'village_id',
        'snapshot_date',
        'population',
        'population_change',
    ];

    protected $casts = [
        'snapshot_date' => 'date',
    ];

    public function village(): BelongsTo
    {
        return $this->belongsTo(Village::class);
    }
}
