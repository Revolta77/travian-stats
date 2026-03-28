<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Server extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'base_url',
        'timezone',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function mapSqlUrl(): ?string
    {
        if ($this->base_url === null || trim($this->base_url) === '') {
            return null;
        }

        return rtrim($this->base_url, '/').'/map.sql';
    }

    public function players(): HasMany
    {
        return $this->hasMany(Player::class);
    }

    public function alliances(): HasMany
    {
        return $this->hasMany(Alliance::class);
    }

    public function villages(): HasMany
    {
        return $this->hasMany(Village::class);
    }
}
