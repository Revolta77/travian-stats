<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AllianceDailyStat extends Model
{
    protected $fillable = [
        'alliance_id',
        'snapshot_date',
        'total_population',
        'village_count',
        'member_count',
        'population_change',
        'village_count_change',
        'member_count_change',
        'days_without_change',
    ];

    protected $casts = [
        'snapshot_date' => 'date',
    ];

    public function alliance(): BelongsTo
    {
        return $this->belongsTo(Alliance::class);
    }
}
