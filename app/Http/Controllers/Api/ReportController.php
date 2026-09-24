<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DailySerialNumber;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\DailyReportExport;

class ReportController extends Controller
{
    public function daily(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
        ]);

        $date       = $request->query('date');
        $categories = ['roda23', 'roda4', 'rodaplus4', 'bermalam'];

        $summary = Transaction::where('date', $date)
            ->selectRaw('vehicle_type, COUNT(*) as total, SUM(tariff_amount) as pendapatan')
            ->groupBy('vehicle_type')
            ->get()
            ->keyBy('vehicle_type');

        $serials = DailySerialNumber::where('date', $date)
            ->get()
            ->keyBy('vehicle_type');

        $result     = [];
        $grandTotal = 0;
        $grandCount = 0;

        foreach ($categories as $cat) {
            $data       = $summary->get($cat);
            $total      = $data ? (int) $data->total : 0;
            $pendapatan = $data ? (int) $data->pendapatan : 0;
            $grandTotal += $pendapatan;
            $grandCount += $total;

            $serialData = $serials->get($cat);
            $serialInfo = null;

            if ($serialData) {
                $serialInfo = [
                    'serial_start' => $serialData->serial_start,
                    'serial_end' => $total > 0
                        ? str_pad(
                            (int)$serialData->serial_start + ($total - 1),
                            strlen($serialData->serial_start),
                            '0',
                            STR_PAD_LEFT
                        )
                        : $serialData->serial_start,
                ];
            }

            $result[] = [
                'vehicle_type' => $cat,
                'total'        => $total,
                'pendapatan'   => $pendapatan,
                'serial'       => $serialInfo,
            ];
        }

        return response()->json([
            'date'             => $date,
            'per_kategori'     => $result,
            'total_kendaraan'  => $grandCount,
            'total_pendapatan' => $grandTotal,
        ]);
    }

    public function monthly(Request $request)
    {
        $request->validate([
            'month' => 'required|integer|between:1,12',
            'year'  => 'required|integer|min:2024',
        ]);

        $month = $request->query('month');
        $year  = $request->query('year');

        $summary = Transaction::whereMonth('date', $month)
            ->whereYear('date', $year)
            ->selectRaw('vehicle_type, COUNT(*) as total, SUM(tariff_amount) as pendapatan')
            ->groupBy('vehicle_type')
            ->get()
            ->keyBy('vehicle_type');

        $categories = ['roda23', 'roda4', 'rodaplus4', 'bermalam'];
        $result     = [];
        $grandTotal = 0;
        $grandCount = 0;

        foreach ($categories as $cat) {
            $data       = $summary->get($cat);
            $total      = $data ? (int) $data->total : 0;
            $pendapatan = $data ? (int) $data->pendapatan : 0;
            $grandTotal += $pendapatan;
            $grandCount += $total;

            $result[] = [
                'vehicle_type' => $cat,
                'total'        => $total,
                'pendapatan'   => $pendapatan,
            ];
        }

        return response()->json([
            'month'            => (int) $month,
            'year'             => (int) $year,
            'per_kategori'     => $result,
            'total_kendaraan'  => $grandCount,
            'total_pendapatan' => $grandTotal,
        ]);
    }

    public function exportExcel(Request $request)
{
    $request->validate([
        'date_from' => 'required|date',
        'date_to'   => 'required|date|after_or_equal:date_from',
    ]);

    $dateFrom = $request->query('date_from');
    $dateTo   = $request->query('date_to');

    // Generate array of dates
    $dates  = [];
    $current = \Carbon\Carbon::parse($dateFrom);
    $end     = \Carbon\Carbon::parse($dateTo);

    while ($current->lte($end)) {
        $dates[] = $current->toDateString();
        $current->addDay();
    }

    // Maksimal 31 hari
    if (count($dates) > 31) {
        return response()->json([
            'message' => 'Rentang maksimal 31 hari.'
        ], 422);
    }

    $filename = 'laporan-' . $dateFrom . '-sd-' . $dateTo . '.xlsx';

    return \Maatwebsite\Excel\Facades\Excel::download(
        new \App\Exports\DailyReportExport($dates),
        $filename
    );
}
}
