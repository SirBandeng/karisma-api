<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tariff;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class TransactionController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'vehicle_type' => 'required|in:roda23,roda4,rodaplus4,bermalam',
        ]);

        $tariff = Tariff::where('vehicle_type', $request->vehicle_type)
            ->where('effective_date', '<=', now()->toDateString())
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->first();

        if (!$tariff) {
            return response()->json([
                'message' => 'Tarif untuk kendaraan ini belum diatur.'
            ], 422);
        }

        $now = Carbon::now();

        $transaction = Transaction::create([
            'user_id'       => $request->user()->id,
            'vehicle_type'  => $request->vehicle_type,
            'tariff_amount' => $tariff->amount,
            'date'          => $now->toDateString(),
            'recorded_at'   => $now,
        ]);

        return response()->json([
            'message'     => 'Transaksi berhasil dicatat.',
            'transaction' => $transaction,
        ], 201);
    }

    public function todaySummary(Request $request)
    {
        $today    = now()->toDateString();
        $auth     = $request->user();

        // Ambil terminal_id dari user yang login
        $terminalId = $auth->terminal_id;

        $summary = Transaction::where('date', $today)
            ->whereHas('petugas', fn($q) => $q->where('terminal_id', $terminalId))
            ->selectRaw('vehicle_type, COUNT(*) as total, SUM(tariff_amount) as pendapatan')
            ->groupBy('vehicle_type')
            ->get()
            ->keyBy('vehicle_type');

        $categories = ['roda23', 'roda4', 'rodaplus4', 'bermalam'];
        $result     = [];
        $grandTotal = 0;
        $grandCount = 0;

        foreach ($categories as $cat) {
            $data        = $summary->get($cat);
            $total       = $data ? (int) $data->total : 0;
            $pendapatan  = $data ? (int) $data->pendapatan : 0;
            $grandTotal += $pendapatan;
            $grandCount += $total;
            $result[$cat] = [
                'total'      => $total,
                'pendapatan' => $pendapatan,
            ];
        }

        return response()->json([
            'date'             => $today,
            'per_kategori'     => $result,
            'total_kendaraan'  => $grandCount,
            'total_pendapatan' => $grandTotal,
        ]);
    }

    public function destroy(Request $request, Transaction $transaction)
    {
        // Pastikan transaksi milik petugas yang login
        if ($transaction->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Kamu tidak bisa menghapus transaksi milik petugas lain.'
            ], 403);
        }

        // Hanya bisa hapus transaksi dalam 5 menit terakhir
        $limit = now()->subMinutes(5);
        if ($transaction->recorded_at < $limit) {
            return response()->json([
                'message' => 'Transaksi hanya bisa dibatalkan dalam 5 menit setelah dicatat.'
            ], 422);
        }

        $transaction->delete();

        return response()->json([
            'message' => 'Transaksi berhasil dibatalkan.'
        ]);
    }

    public function last(Request $request)
    {
        $transaction = Transaction::where('user_id', $request->user()->id)
            ->where('date', now()->toDateString())
            ->latest('recorded_at')
            ->first();

        if (!$transaction) {
            return response()->json(['transaction' => null]);
        }

        // Cek apakah masih dalam 5 menit
        $canCancel = $transaction->recorded_at >= now()->subMinutes(5);

        return response()->json([
            'transaction' => [
                'id'           => $transaction->id,
                'vehicle_type' => $transaction->vehicle_type,
                'tariff_amount' => $transaction->tariff_amount,
                'recorded_at'  => $transaction->recorded_at,
                'can_cancel'   => $canCancel,
            ],
        ]);
    }
}
