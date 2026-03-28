<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerDailyStat extends Model
{
    protected $fillable = [
        'player_id',
        'snapshot_date',
        'total_population',
        'village_count',
        'population_change',
        'village_count_change',
        'days_without_change',
    ];

    protected $casts = [
        'snapshot_date' => 'date',
    ];

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
