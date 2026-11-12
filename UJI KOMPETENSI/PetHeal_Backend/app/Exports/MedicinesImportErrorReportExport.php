<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Laporan baris yang gagal diimpor, agar admin bisa memperbaiki file asal
 * alih-alih menebak baris mana yang bermasalah.
 */
class MedicinesImportErrorReportExport implements FromArray, WithHeadings, ShouldAutoSize
{
    public function __construct(private readonly array $rows)
    {
    }

    public function headings(): array
    {
        return ['row_number', 'name', 'unit', 'stock', 'min_stock', 'price', 'expiry_date', 'error_message'];
    }

    public function array(): array
    {
        return $this->rows;
    }
}
