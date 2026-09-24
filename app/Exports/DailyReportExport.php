<?php

namespace App\Exports;

use App\Models\DailySerialNumber;
use App\Models\Transaction;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;


class DailyReportExport implements WithMultipleSheets
{
    protected array $dates;

    public function __construct(array $dates)
    {
        $this->dates = $dates;
    }

    public function sheets(): array
    {
        $sheets = [];
        foreach ($this->dates as $date) {
            $sheets[] = new DailySheetExport($date);
        }
        return $sheets;
    }
}
