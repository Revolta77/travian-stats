<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Alliance extends Model
{
    use HasFactory;

    protected $fillable = [
        'server_id',
        'external_id',
        'tag',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function villages(): HasMany
    {
        return $this->hasMany(Village::class);
    }

    public function dailyStats(): HasMany
    {
        return $this->hasMany(AllianceDailyStat::class);
    }
}
