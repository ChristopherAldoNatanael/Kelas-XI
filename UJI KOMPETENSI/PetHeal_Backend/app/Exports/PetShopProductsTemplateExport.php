<?php

namespace App\Exports;

use App\Models\ClinicProduct;
use App\Models\ProductCategory;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Template import sekaligus ekspor katalog yang sudah ada.
 *
 * Satu baris = satu varian; kolom produk diulang. Dengan bentuk yang sama,
 * admin bisa mengunduh katalog, mengubah harga/stok minimum massal, lalu
 * mengunggahnya kembali tanpa mempelajari format baru.
 */
class PetShopProductsTemplateExport implements FromCollection, WithHeadings, WithColumnWidths, ShouldAutoSize, WithStyles
{
    public function __construct(
        private readonly bool $withExisting = false,
        private readonly ?int $clinicId = null,
    ) {
    }

    public function headings(): array
    {
        return [
            'category',
            'product_name',
            'brand',
            'description',
            'sku',
            'variant_name',
            'price',
            'discount_price',
            'stock',
            'min_stock',
            'status',
        ];
    }

    public function collection()
    {
        if (!$this->withExisting) {
            // Contoh memakai nama kategori yang benar-benar ada supaya file
            // bisa langsung diuji. SKU sengaja berawalan CONTOH- agar tidak
            // bentrok dengan SKU nyata saat template diunggah percobaan.
            $categories = ProductCategory::active()->ordered()->limit(2)->pluck('name')->all();
            $food = $categories[0] ?? 'Makanan';
            $care = $categories[1] ?? $categories[0] ?? 'Perawatan';

            // Dua produk dengan beberapa varian: memperlihatkan bahwa baris
            // dengan nama produk sama otomatis digabung menjadi satu produk.
            return collect([
                [$food, 'Royal Canin Adult', 'Royal Canin', 'Makanan kering harian untuk kucing dewasa.', 'CONTOH-RC-2KG', '2 kg', 185000, 175000, 12, 4, 'active'],
                [$food, 'Royal Canin Adult', 'Royal Canin', 'Makanan kering harian untuk kucing dewasa.', 'CONTOH-RC-4KG', '4 kg', 340000, null, 8, 3, 'active'],
                [$care, 'Shampoo Anti Kutu', 'PetCare', 'Shampoo untuk kucing dan anjing.', 'CONTOH-SHP-250', '250 ml', 65000, null, 20, 5, 'active'],
            ]);
        }

        return ClinicProduct::query()
            ->with(['category:id,name', 'variants' => fn ($q) => $q->orderBy('name')])
            ->when($this->clinicId, fn ($q) => $q->where('clinic_id', $this->clinicId))
            ->orderBy('name')
            ->get()
            ->flatMap(fn (ClinicProduct $product) => $product->variants->map(fn ($variant) => [
                $product->category?->name,
                $product->name,
                $product->brand,
                $product->description,
                $variant->sku,
                $variant->name,
                (int) $variant->price,
                $variant->discount_price !== null ? (int) $variant->discount_price : null,
                (int) $variant->stock_on_hand,
                (int) $variant->low_stock_threshold,
                $variant->is_active ? 'active' : 'inactive',
            ]));
    }

    public function columnWidths(): array
    {
        return [
            'A' => 16,
            'B' => 28,
            'C' => 16,
            'D' => 48,
            'E' => 18,
            'F' => 16,
            'G' => 12,
            'H' => 14,
            'I' => 10,
            'J' => 11,
            'K' => 10,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->freezePane('A2');

        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
