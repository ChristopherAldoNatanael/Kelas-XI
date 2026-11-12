<?php

namespace App\Exports;

use App\Models\Medicine;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Template import obat sekaligus "export" obat yang sudah ada.
 *
 * Dua hal ini sengaja satu kelas: admin sering ingin mengunduh isi stok
 * sekarang, mengubah harganya di Excel, lalu mengunggahnya kembali. Kalau
 * kolomnya sama persis, alur itu tidak perlu penjelasan tambahan.
 */
class MedicinesTemplateExport implements FromCollection, WithHeadings, WithColumnWidths, ShouldAutoSize, WithStyles
{
    public function __construct(
        private readonly bool $withExisting = false,
        private readonly ?int $clinicId = null,
    ) {
    }

    public function headings(): array
    {
        return [
            'name',
            'unit',
            'form',
            'stock',
            'min_stock',
            'price',
            'expiry_date',
            'manufacturer',
            'status',
            'notes',
        ];
    }

    public function collection()
    {
        if (! $this->withExisting) {
            // Contoh baris: admin melihat bentuk data yang benar tanpa
            // harus membaca dokumentasi.
            return collect([
                ['Amoxicillin 500 mg', 'tablet', 'Tablet', 120, 20, 3500, '2027-06-30', 'Kimia Farma', 'active', 'Diberikan sesudah makan'],
                ['Meloxicam Injeksi', 'ampul', 'Injeksi', 15, 5, 25000, '2026-12-31', 'Sanbe', 'active', ''],
                ['Ringer Laktat', 'botol', 'Infus', 40, 10, 18000, '', 'Otsuka', 'active', 'Simpan suhu ruang'],
            ]);
        }

        return Medicine::query()
            ->when($this->clinicId, fn ($q) => $q->where('clinic_id', $this->clinicId))
            ->orderBy('name')
            ->get()
            ->map(fn (Medicine $m) => [
                $m->name,
                $m->unit,
                $m->form,
                (float) $m->stock,
                (float) $m->min_stock,
                (float) $m->price,
                $m->expiry_date?->format('Y-m-d'),
                $m->manufacturer,
                $m->is_active ? 'active' : 'inactive',
                $m->notes,
            ]);
    }

    /** Lebar kolom dibuat cukup agar angka & tanggal tidak terpotong. */
    public function columnWidths(): array
    {
        return [
            'A' => 32,
            'B' => 10,
            'C' => 12,
            'D' => 10,
            'E' => 12,
            'F' => 14,
            'G' => 14,
            'H' => 20,
            'I' => 10,
            'J' => 36,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->freezePane('A2');

        return [
            // Header tebal; baris data tidak diwarnai agar angka mudah dibaca.
            1 => ['font' => ['bold' => true]],
        ];
    }
}
