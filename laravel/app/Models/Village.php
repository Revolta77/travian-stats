<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Village extends Model
{
    use HasFactory;

    protected $fillable = [
        'server_id',
        'external_id',
        'player_id',
        'alliance_id',
        'field_id',
        'x',
        'y',
        'tribe',
        'name',
        'region',
        'is_capital',
        'is_city',
        'has_harbor',
        'victory_points',
        'days_without_change',
        'last_seen_at',
    ];

    protected $casts = [
        'is_capital' => 'boolean',
        'is_city' => 'boolean',
        'has_harbor' => 'boolean',
        'last_seen_at' => 'datetime',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function alliance(): BelongsTo
    {
        return $this->belongsTo(Alliance::class);
    }

    public function dailyStats(): HasMany
    {
        return $this->hasMany(VillageDailyStat::class);
    }
}
