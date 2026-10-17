<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    protected $fillable = ['slug', 'name', 'short_name'];

    /**
     * The college's seal, public/images/departments/<short name>.png (CCS → ccs.png).
     * Null when there is none, so a page falls back to the short name.
     */
    public function getLogoUrlAttribute(): ?string
    {
        $file = 'images/departments/' . strtolower((string) $this->short_name) . '.png';

        return is_file(public_path($file)) ? '/' . $file : null;
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
