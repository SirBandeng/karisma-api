<?php

namespace App\Exports;

use App\Models\DailySerialNumber;
use App\Models\Terminal;
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
    protected int $terminalId;
    protected string $terminalName;
    protected int $totalRows = 0;

    public function __construct(string $date, int $terminalId)
    {
        $this->date         = $date;
        $this->terminalId   = $terminalId;
        $this->terminalName = Terminal::find($terminalId)?->name ?? 'Terminal';
    }

    public function title(): string
    {
        // Nama sheet Excel: maks 31 karakter, tidak boleh ada / \ ? * [ ]
        $prefix = substr($this->terminalName, 0, 17);
        return $prefix . ' - ' . Carbon::parse($this->date)->format('d-m-Y');
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
            ->whereHas('petugas', fn($q) => $q->where('terminal_id', $this->terminalId))
            ->orderBy('recorded_at')
            ->get();

        $serials = DailySerialNumber::where('date', $this->date)
            ->where('terminal_id', $this->terminalId)
            ->get()
            ->keyBy('vehicle_type');

        $labels = [
            'roda23'    => 'Roda 2 / 3',
            'roda4'     => 'Roda 4',
            'rodaplus4' => 'Roda +4',
            'bermalam'  => 'Bermalam',
        ];

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

            if ($type === 'bermalam' || !$serial) {
                $noTiket = '-';
            } else {
                $counters[$type]++;
                $startNum  = (int) $serial->serial_start;
                $ticketNum = $startNum + ($counters[$type] - 1);
                $noTiket   = '04.' . str_pad(
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

        // Baris pemisah sebelum ringkasan
        $rows[] = ['', '', '', '', '', ''];
        $rows[] = ['', 'RINGKASAN', '', '', '', ''];

        $categoryTotals = Transaction::active()
            ->where('date', $this->date)
            ->whereHas('petugas', fn($q) => $q->where('terminal_id', $this->terminalId))
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

        $grandTotal = Transaction::active()
            ->where('date', $this->date)
            ->whereHas('petugas', fn($q) => $q->where('terminal_id', $this->terminalId))
            ->sum('tariff_amount');

        $grandCount = Transaction::active()
            ->where('date', $this->date)
            ->whereHas('petugas', fn($q) => $q->where('terminal_id', $this->terminalId))
            ->count();

        $rows[] = ['', '', '', '', '', ''];
        $rows[] = [
            '',
            'TOTAL',
            "$grandCount kendaraan",
            'Rp ' . number_format($grandTotal, 0, ',', '.'),
            '',
            '',
        ];

        // Tambah note harian kalau ada
        $note = \App\Models\DailyNote::where('date', $this->date)
            ->where('terminal_id', $this->terminalId)
            ->first();

        if ($note && $note->note) {
            $rows[] = ['', '', '', '', '', ''];
            $rows[] = ['', 'CATATAN', '', '', '', ''];
            $rows[] = ['', $note->note, '', '', '', ''];
        }

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
            // Baris 1 adalah judul besar (diinsert di AfterSheet),
            // baris 3 adalah heading kolom (setelah insert 2 baris)
            3 => [
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

                // Border untuk area data (sebelum insert baris judul)
                // +1 karena heading, +1 untuk row pertama data
                $lastDataRow = $this->totalRows + 1;
                $sheet->getStyle("A1:F{$lastDataRow}")
                    ->getBorders()
                    ->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);

                // Insert 2 baris di atas: baris 1 = judul, baris 2 = kosong
                $sheet->insertNewRowBefore(1, 2);

                // Judul laporan dengan nama terminal
                $date = Carbon::parse($this->date)
                    ->locale('id')
                    ->isoFormat('dddd, D MMMM Y');

                $sheet->mergeCells('A1:F1');
                $sheet->setCellValue(
                    'A1',
                    'LAPORAN HARIAN ' . strtoupper($this->terminalName) . ' — ' . strtoupper($date)
                );
                $sheet->getStyle('A1')->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 13],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    'fill'      => [
                        'fillType'   => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'E8EAF6'],
                    ],
                ]);

                // Autofilter di baris header (baris 3 setelah insert 2 baris)
                $sheet->setAutoFilter('A3:F3');

                // Freeze di baris 4 agar judul + header tetap terlihat saat scroll
                $sheet->freezePane('A4');
            },
        ];
    }
}
