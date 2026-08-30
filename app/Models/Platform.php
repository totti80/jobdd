<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Platform extends Model
{
    protected $fillable = [
        'name',
        'website_url',
        'platform_type',
    ];

    public function applicationRoutes(): HasMany
    {
        return $this->hasMany(ApplicationRoute::class);
    }
}
