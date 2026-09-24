<?php

namespace App\Exports;

use App\Models\DailySerialNumber;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

class DailySheetExport implements
    FromCollection,
    WithHeadings,
    WithTitle,
    WithStyles,
    WithColumnWidths,
    WithEvents
{
    protected string $date;
    protected int $totalRows = 0;

    public function __construct(string $date)
    {
        $this->date = $date;
    }

    public function title(): string
    {
        return Carbon::parse($this->date)->format('d-m-Y');
    }

    public function headings(): array
    {
        return [
            'No.',
            'No. Tiket',
            'Jenis Kendaraan',
            'Tarif',
            'Jam Catat',
            'Petugas',
        ];
    }

    public function collection()
    {
        $transactions = Transaction::with('petugas')
            ->where('date', $this->date)
            ->orderBy('recorded_at')
            ->get();

        $serials = DailySerialNumber::where('date', $this->date)
            ->get()
            ->keyBy('vehicle_type');

        $labels = [
            'roda23'    => 'Roda 2 / 3',
            'roda4'     => 'Roda 4',
            'rodaplus4' => 'Roda +4',
            'bermalam'  => 'Bermalam',
        ];

        // Hitung urutan per kategori
        $counters = [
            'roda23'    => 0,
            'roda4'     => 0,
            'rodaplus4' => 0,
            'bermalam'  => 0,
        ];

        $rows = [];
        $no   = 1;

        foreach ($transactions as $trx) {
            $type   = $trx->vehicle_type;
            $serial = $serials->get($type);

            // Hitung no. tiket
            if ($type === 'bermalam' || !$serial) {
                $noTiket = '-';
            } else {
                $counters[$type]++;
                $startNum = (int) $serial->serial_start;
                $ticketNum = $startNum + ($counters[$type] - 1);
                $noTiket  = '04.' . str_pad(
                    $ticketNum,
                    strlen($serial->serial_start),
                    '0',
                    STR_PAD_LEFT
                );
            }

            $rows[] = [
                $no++,
                $noTiket,
                $labels[$type] ?? $type,
                'Rp ' . number_format($trx->tariff_amount, 0, ',', '.'),
                Carbon::parse($trx->recorded_at)
                    ->timezone('Asia/Jakarta')
                    ->format('H:i'),
                $trx->petugas->name ?? '-',
            ];
        }

        $this->totalRows = count($rows);

        // Summary per kategori
        $rows[] = ['', '', '', '', '', ''];
        $rows[] = ['', 'RINGKASAN', '', '', '', ''];

        $categoryTotals = Transaction::where('date', $this->date)
            ->selectRaw('vehicle_type, COUNT(*) as total, SUM(tariff_amount) as pendapatan')
            ->groupBy('vehicle_type')
            ->get()
            ->keyBy('vehicle_type');

        foreach ($labels as $type => $label) {
            $data       = $categoryTotals->get($type);
            $total      = $data ? (int) $data->total : 0;
            $pendapatan = $data ? (int) $data->pendapatan : 0;
            $rows[]     = [
                '',
                $label,
                "$total kendaraan",
                'Rp ' . number_format($pendapatan, 0, ',', '.'),
                '',
                '',
            ];
        }

        $grandTotal      = Transaction::where('date', $this->date)->sum('tariff_amount');
        $grandCount      = Transaction::where('date', $this->date)->count();
        $rows[]          = ['', '', '', '', '', ''];
        $rows[]          = [
            '',
            'TOTAL',
            "$grandCount kendaraan",
            'Rp ' . number_format($grandTotal, 0, ',', '.'),
            '',
            '',
        ];

        return collect($rows);
    }

    public function columnWidths(): array
    {
        return [
            'A' => 6,
            'B' => 18,
            'C' => 18,
            'D' => 16,
            'E' => 12,
            'F' => 20,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType'   => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '1A237E'],
                ],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Border untuk data rows
                $lastDataRow = $this->totalRows + 1;
                $sheet->getStyle("A1:F$lastDataRow")
                    ->getBorders()
                    ->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);

                // Style header tanggal di atas tabel
                $date = Carbon::parse($this->date)
                    ->locale('id')
                    ->isoFormat('dddd, D MMMM Y');

                $sheet->insertNewRowBefore(1, 2);
                $sheet->mergeCells('A1:F1');
                $sheet->setCellValue('A1', 'LAPORAN HARIAN TERMINAL — ' . strtoupper($date));
                $sheet->getStyle('A1')->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 13],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    'fill'      => [
                        'fillType'   => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'E8EAF6'],
                    ],
                ]);

                // Freeze header row
                $sheet->freezePane('A4');
            },
        ];
    }
}
