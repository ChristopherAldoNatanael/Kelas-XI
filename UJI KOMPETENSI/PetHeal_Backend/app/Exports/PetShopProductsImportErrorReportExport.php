<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Laporan baris import produk yang gagal, supaya admin memperbaiki baris
 * yang disebutkan alih-alih menebak.
 */
class PetShopProductsImportErrorReportExport implements FromArray, WithHeadings, ShouldAutoSize
{
    public function __construct(private readonly array $rows)
    {
    }

    public function headings(): array
    {
        return ['row_number', 'product_name', 'category', 'sku', 'variant_name', 'error_message'];
    }

    public function array(): array
    {
        return $this->rows;
    }
}
