<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DailySerialNumber;
use App\Models\Transaction;
use Illuminate\Http\Request;

class DailySerialController extends Controller
{
    public function index(Request $request)
    {
        $date       = $request->query('date', now()->toDateString());
        $auth       = $request->user();
        $terminalId = $auth->terminal_id;

        $serials = DailySerialNumber::where('date', $date)
            ->where('terminal_id', $terminalId)
            ->get();

        $result = [];
        foreach (['roda23', 'roda4', 'rodaplus4'] as $type) {
            $serial = $serials->firstWhere('vehicle_type', $type);

            if ($serial) {
                $count = Transaction::where('date', $date)
                    ->where('vehicle_type', $type)
                    ->whereHas('petugas', fn($q) => $q->where('terminal_id', $terminalId))
                    ->count();

                $result[$type] = [
                    'status'       => 'filled',
                    'serial_start' => $serial->serial_start,
                    'serial_end'   => $count > 0
                        ? str_pad(
                            (int)$serial->serial_start + ($count - 1),
                            strlen($serial->serial_start),
                            '0',
                            STR_PAD_LEFT
                        )
                        : $serial->serial_start,
                    'inputted_by'  => $serial->petugas->name,
                    'inputted_at'  => $serial->inputted_at,
                ];
            } else {
                $result[$type] = ['status' => 'empty'];
            }
        }

        return response()->json([
            'date'    => $date,
            'serials' => $result,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'vehicle_type' => 'required|in:roda23,roda4,rodaplus4',
            'serial_start' => 'required|string|min:1',
            'date'         => 'sometimes|date',
        ]);

        $date       = $request->date ?? now()->toDateString();
        $terminalId = $request->user()->terminal_id;

        $existing = DailySerialNumber::where('date', $date)
            ->where('vehicle_type', $request->vehicle_type)
            ->where('terminal_id', $terminalId)
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'No. seri untuk kategori ini sudah diinput hari ini.'
            ], 422);
        }

        $serial = DailySerialNumber::create([
            'date'         => $date,
            'vehicle_type' => $request->vehicle_type,
            'serial_start' => $request->serial_start,
            'inputted_by'  => $request->user()->id,
            'inputted_at'  => now(),
            'terminal_id'  => $terminalId,
        ]);

        return response()->json([
            'message' => 'No. seri berhasil disimpan.',
            'serial'  => $serial,
        ], 201);
    }
}
