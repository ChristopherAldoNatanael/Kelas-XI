<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ServicesTemplateExport implements FromArray, WithHeadings
{
    public function __construct(private readonly array $rows = [])
    {
    }

    public function headings(): array
    {
        return ['name', 'description', 'price', 'duration', 'status'];
    }

    public function array(): array
    {
        return $this->rows ?: [
            ['General Checkup', 'Pemeriksaan umum hewan', 120000, 30, 'active'],
            ['Vaccination', 'Vaksinasi dasar hewan', 150000, 20, 'active'],
            ['Grooming', 'Perawatan bulu dan kebersihan', 80000, 45, 'active'],
        ];
    }
}
