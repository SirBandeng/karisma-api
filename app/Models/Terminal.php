<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Terminal extends Model
{
    protected $fillable = ['name', 'location', 'is_active'];

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
