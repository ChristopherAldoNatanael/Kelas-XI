<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ServicesImportErrorReportExport implements FromArray, WithHeadings
{
    public function __construct(private readonly array $rows)
    {
    }

    public function headings(): array
    {
        return ['row_number', 'name', 'description', 'price', 'duration', 'status', 'error_message'];
    }

    public function array(): array
    {
        return $this->rows;
    }
}
