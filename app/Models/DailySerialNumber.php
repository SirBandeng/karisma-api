<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailySerialNumber extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'terminal_id',
        'date',
        'vehicle_type',
        'serial_start',
        'inputted_by',
        'inputted_at',
    ];

    public function terminal()
    {
        return $this->belongsTo(Terminal::class);
    }

    protected function casts(): array
    {
        return [
            'date'         => 'date',
            'inputted_at'  => 'datetime',
            'serial_start' => 'string',
        ];
    }

    public function petugas()
    {
        return $this->belongsTo(User::class, 'inputted_by');
    }
}
