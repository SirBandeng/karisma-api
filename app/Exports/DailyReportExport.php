<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class DailyReportExport implements WithMultipleSheets
{
    protected array $dates;
    protected int $terminalId;

    public function __construct(array $dates, int $terminalId)
    {
        $this->dates      = $dates;
        $this->terminalId = $terminalId;
    }

    public function sheets(): array
    {
        $sheets = [];
        foreach ($this->dates as $date) {
            $sheets[] = new DailySheetExport($date, $this->terminalId);
        }
        return $sheets;
    }
}
