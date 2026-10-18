<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'vehicle_type',
        'tariff_amount',
        'date',
        'recorded_at',
        'cancelled_at',
        'cancelled_by',
        'cancel_reason',
    ];

    protected function casts(): array
    {
        return [
            'recorded_at'  => 'datetime',
            'cancelled_at' => 'datetime',
            'date'         => 'date',
        ];
    }

    // Relasi ke petugas yang batalkan
    public function cancelledBy()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    // Scope — transaksi aktif (belum dibatalkan)
    public function scopeActive($query)
    {
        return $query->whereNull('cancelled_at');
    }

    public function petugas()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
