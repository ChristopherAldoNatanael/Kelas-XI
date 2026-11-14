<?php

namespace App\Console\Commands;

use App\Services\ProductOrderService;
use Illuminate\Console\Command;

class ExpirePetShopOrders extends Command
{
    protected $signature = 'petshop:expire-orders';

    protected $description = 'Lepas reservasi stok pesanan Pet Shop yang melewati batas waktu';

    public function handle(ProductOrderService $orders): int
    {
        $count = $orders->expireStaleOrders();

        $this->info("Pesanan kedaluwarsa diproses: {$count}");

        return Command::SUCCESS;
    }
}
