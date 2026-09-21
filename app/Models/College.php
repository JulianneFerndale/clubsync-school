<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class College extends Model
{
    protected $fillable = ['name'];

    public function clubs(): HasMany
    {
        return $this->hasMany(Club::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
