<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PaymentMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $methods = [
            [
                'name' => 'QRIS',
                'type' => 'qris',
                'description' => 'Scan QR code untuk pembayaran',
                'icon' => 'qris',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Bank Transfer - BCA',
                'type' => 'bank',
                'description' => 'Transfer ke rekening BCA',
                'icon' => 'bank_bca',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Bank Transfer - Mandi',
                'type' => 'bank',
                'description' => 'Transfer ke rekening Mandiri',
                'icon' => 'bank_mandiri',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Bank Transfer - BRI',
                'type' => 'bank',
                'description' => 'Transfer ke rekening BRI',
                'icon' => 'bank_bri',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'GoPay',
                'type' => 'ewallet',
                'description' => 'Bayar menggunakan GoPay',
                'icon' => 'gopay',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'OVO',
                'type' => 'ewallet',
                'description' => 'Bayar menggunakan OVO',
                'icon' => 'ovo',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Dana',
                'type' => 'ewallet',
                'description' => 'Bayar menggunakan Dana',
                'icon' => 'dana',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'ShopeePay',
                'type' => 'ewallet',
                'description' => 'Bayar menggunakan ShopeePay',
                'icon' => 'shopeepay',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('payment_methods')->insert($methods);
    }
}
