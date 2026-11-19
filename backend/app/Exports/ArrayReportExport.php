<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ArrayReportExport implements FromArray, WithHeadings
{
    /**
     * @param  list<string>  $headings
     * @param  list<array<int, mixed>>  $rows
     */
    public function __construct(
        protected array $headings,
        protected array $rows
    ) {}

    public function headings(): array
    {
        return $this->headings;
    }

    public function array(): array
    {
        return $this->rows;
    }
}
