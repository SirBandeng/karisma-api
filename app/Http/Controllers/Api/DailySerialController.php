<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DailySerialNumber;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DailySerialController extends Controller
{
    public function index(Request $request)
    {
        $date       = $request->query('date', now()->toDateString());
        $terminalId = $request->user()->terminal_id;
        $serials    = DailySerialNumber::where('date', $date)
            ->where('terminal_id', $terminalId)
            ->orderBy('session')
            ->get();

        $result = [];
        foreach (['roda23', 'roda4', 'rodaplus4'] as $type) {
            $typeSerials = $serials->where('vehicle_type', $type)->values();

            if ($typeSerials->isEmpty()) {
                $result[$type] = ['status' => 'empty', 'sessions' => []];
                continue;
            }

            $sessions = [];
            foreach ($typeSerials as $serial) {
                // Hitung count transaksi untuk sesi ini
                // Sesi berikutnya mulai dari serial_start sesi berikutnya
                $nextSerial = $typeSerials->firstWhere('session', $serial->session + 1);

                $count = Transaction::active()
                    ->where('date', $date)
                    ->where('vehicle_type', $type)
                    ->whereHas('petugas', fn($q) => $q->where('terminal_id', $terminalId))
                    ->when($nextSerial, function ($q) use ($serial, $nextSerial) {
                        $q->where('recorded_at', '>=', $serial->inputted_at);
                        $q->where('recorded_at', '<', $nextSerial->inputted_at);
                    }, function ($q) use ($serial) {
                        $q->where('recorded_at', '>=', $serial->inputted_at);
                    })
                    ->count();

                // Debug sementara
                Log::info("Session {$serial->session} - inputted_at: {$serial->inputted_at} - count: {$count}");

                $sessions[] = [
                    'session'      => $serial->session,
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
                    'count'        => $count,
                ];
            }

            $result[$type] = [
                'status'   => 'filled',
                'sessions' => $sessions,
            ];
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
            'is_new_session' => 'sometimes|boolean',
        ]);

        $date       = $request->date ?? now()->toDateString();
        $terminalId = $request->user()->terminal_id;

        // Hitung sesi berikutnya
        $lastSession = DailySerialNumber::where('date', $date)
            ->where('vehicle_type', $request->vehicle_type)
            ->where('terminal_id', $terminalId)
            ->max('session') ?? 0;

        // Kalau bukan new session dan sudah ada sesi 1, tolak
        if (!$request->is_new_session && $lastSession >= 1) {
            return response()->json([
                'message' => 'No. seri untuk kategori ini sudah diinput hari ini.'
            ], 422);
        }

        $newSession = $lastSession + 1;

        $serial = DailySerialNumber::create([
            'date'         => $date,
            'vehicle_type' => $request->vehicle_type,
            'serial_start' => $request->serial_start,
            'session'      => $newSession,
            'inputted_by'  => $request->user()->id,
            'inputted_at'  => now(),
            'terminal_id'  => $terminalId,
        ]);

        return response()->json([
            'message' => 'No. seri berhasil disimpan.',
            'serial'  => $serial,
            'session' => $newSession,
        ], 201);
    }
}
