<?php

namespace App\Console\Commands;

use App\Models\DailySerialNumber;
use App\Models\Terminal;
use App\Models\Transaction;
use Google\Client;
use Google\Service\Sheets;
use Google\Service\Sheets\ValueRange;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class SyncToGoogleSheets extends Command
{
    protected $signature   = 'karisma:sync-sheets {--date= : Tanggal spesifik (Y-m-d), default hari ini}';
    protected $description = 'Sync data harian semua terminal KARISMA ke Google Sheets';

    // Map terminal name ke env key
    protected array $terminalSheetMap = [
        'Terminal Madureso'       => 'GOOGLE_SPREADSHEET_MADURESO',
        'Terminal Drs. Prajitno'  => 'GOOGLE_SPREADSHEET_PRAJITNO',
        'Terminal Kutoarjo'       => 'GOOGLE_SPREADSHEET_KUTOARJO',
    ];

    public function handle()
    {
        $date = $this->option('date') ?? now()->timezone('Asia/Jakarta')->toDateString();

        $this->info("Syncing data untuk $date...");

        // Ambil semua terminal aktif
        $terminals = Terminal::where('is_active', true)->get();

        foreach ($terminals as $terminal) {
            $envKey        = $this->terminalSheetMap[$terminal->name] ?? null;
            $spreadsheetId = $envKey ? env($envKey) : null;

            if (!$spreadsheetId) {
                $this->warn("Spreadsheet ID tidak ditemukan untuk terminal: {$terminal->name} — skip.");
                continue;
            }

            $this->info("Syncing terminal: {$terminal->name}...");

            try {
                $this->syncTerminal($terminal, $date, $spreadsheetId);
                $this->info("✓ {$terminal->name} berhasil disync!");
            } catch (\Exception $e) {
                $this->error("✗ {$terminal->name} gagal: " . $e->getMessage());
                $this->error("File: " . $e->getFile() . " Line: " . $e->getLine());
                Log::error("Sheets sync failed for {$terminal->name}", [
                    'message' => $e->getMessage(),
                    'file'    => $e->getFile(),
                    'line'    => $e->getLine(),
                ]);
            }
        }

        $this->info("Selesai sync semua terminal untuk $date.");
        return 0;
    }

    protected function syncTerminal(Terminal $terminal, string $date, string $spreadsheetId): void
    {
        $carbon     = Carbon::parse($date)->timezone('Asia/Jakarta');
        $yearTab = $carbon->format('Y');
        $categories = ['roda23', 'roda4', 'rodaplus4', 'bermalam'];

        // Ambil transaksi terminal ini
        $transactions = Transaction::active()
            ->where('date', $date)
            ->whereHas('petugas', fn($q) => $q->where('terminal_id', $terminal->id))
            ->selectRaw('vehicle_type, COUNT(*) as total, SUM(tariff_amount) as pendapatan')
            ->groupBy('vehicle_type')
            ->get()
            ->keyBy('vehicle_type');

        // Ambil serial
        $serials = DailySerialNumber::where('date', $date)
            ->where('terminal_id', $terminal->id)
            ->get()
            ->keyBy('vehicle_type');

        // Format row
        $data = [];
        foreach ($categories as $cat) {
            $trx        = $transactions->get($cat);
            $serial     = $serials->get($cat);
            $total      = $trx ? (int) $trx->total : 0;
            $pendapatan = $trx ? (int) $trx->pendapatan : 0;

            $serialStart = $serial ? $serial->serial_start : '-';
            $serialEnd   = '-';

            if ($serial && $total > 0) {
                $serialEnd = str_pad(
                    (int) $serial->serial_start + ($total - 1),
                    strlen($serial->serial_start),
                    '0',
                    STR_PAD_LEFT
                );
            } elseif ($serial) {
                $serialEnd = $serial->serial_start;
            }

            $data[$cat] = [
                'total'        => $total,
                'pendapatan'   => $pendapatan,
                'serial_start' => $serialStart,
                'serial_end'   => $serialEnd,
            ];
        }

        $totalPendapatan = collect($data)->sum('pendapatan');

        $row = [
            $carbon->format('F'),        // Bulan (September, October, dst)
            $carbon->format('m/d/Y'),    // Tanggal format MM/DD/YYYY
            $data['roda23']['serial_start'],
            $data['roda23']['serial_end'],
            $data['roda23']['total'],
            $data['roda23']['pendapatan'],
            $data['roda4']['serial_start'],
            $data['roda4']['serial_end'],
            $data['roda4']['total'],
            $data['roda4']['pendapatan'],
            $data['rodaplus4']['serial_start'],
            $data['rodaplus4']['serial_end'],
            $data['rodaplus4']['total'],
            $data['rodaplus4']['pendapatan'],
            $data['bermalam']['total'],
            $data['bermalam']['pendapatan'],
            $totalPendapatan,
        ];

        // Koneksi Google Sheets
        $client = new Client();
        $credentialsPath = storage_path('app/google-credentials.json');
        if (file_exists($credentialsPath)) {
            $client->setAuthConfig($credentialsPath);
        } else {
            $credentials = json_decode(env('GOOGLE_CREDENTIALS_JSON'), true);
            $client->setAuthConfig($credentials);
        }
        $client->addScope(Sheets::SPREADSHEETS);
        $service = new Sheets($client);

        // Cek/buat tab bulan
        $spreadsheet = $service->spreadsheets->get($spreadsheetId);
        $sheetTitles = collect($spreadsheet->getSheets())
            ->map(fn($s) => $s->getProperties()->getTitle())
            ->toArray();

        if (!in_array($yearTab, $sheetTitles)) {
            $this->createYearTab($service, $spreadsheetId, $yearTab);
        }

        // Pastikan header ada
        $this->ensureHeader($service, $spreadsheetId, $yearTab);

        // Cari baris yang sudah ada untuk tanggal ini
        $range    = "$yearTab!B:B";
        $response = $service->spreadsheets_values->get($spreadsheetId, $range);
        $values   = $response->getValues() ?? [];

        $targetRow = null;
        $dateStr   = $carbon->format('m/d/Y');

        foreach ($values as $i => $val) {
            if (isset($val[0]) && $val[0] === $dateStr) {
                $targetRow = $i + 1;
                break;
            }
        }

        $body = new ValueRange(['values' => [$row]]);

        if ($targetRow === null) {
            $service->spreadsheets_values->append(
                $spreadsheetId,
                "$yearTab!A:Q",
                $body,
                ['valueInputOption' => 'RAW']
            );
        } else {
            $service->spreadsheets_values->update(
                $spreadsheetId,
                "$yearTab!A{$targetRow}:Q{$targetRow}",
                $body,
                ['valueInputOption' => 'RAW']
            );
        }
    }

    private function createYearTab($service, $spreadsheetId, $yearTab): void
    {
        $requests = [
            new \Google\Service\Sheets\Request([
                'addSheet' => [
                    'properties' => ['title' => $yearTab],
                ],
            ]),
        ];

        $batchUpdate = new \Google\Service\Sheets\BatchUpdateSpreadsheetRequest([
            'requests' => $requests,
        ]);

        $service->spreadsheets->batchUpdate($spreadsheetId, $batchUpdate);
    }

    private function ensureHeader($service, $spreadsheetId, $yearTab): void
    {
        $range    = "$yearTab!A1:Q1";
        $response = $service->spreadsheets_values->get($spreadsheetId, $range);
        $values   = $response->getValues() ?? [];

        if (empty($values) || empty($values[0])) {
            $header = [[
                'Bulan',
                'Tanggal',
                'Seri Awal R2/3',
                'Seri Akhir R2/3',
                'Jumlah R2/3',
                'Pendapatan R2/3',
                'Seri Awal R4',
                'Seri Akhir R4',
                'Jumlah R4',
                'Pendapatan R4',
                'Seri Awal R+4',
                'Seri Akhir R+4',
                'Jumlah R+4',
                'Pendapatan R+4',
                'Jumlah Bermalam',
                'Pendapatan Bermalam',
                'Total Pendapatan',
            ]];

            $body = new ValueRange(['values' => $header]);
            $service->spreadsheets_values->update(
                $spreadsheetId,
                "$yearTab!A1:Q1",
                $body,
                ['valueInputOption' => 'RAW']
            );
        }
    }
}
