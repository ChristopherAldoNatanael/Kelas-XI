<?php

namespace App\Console\Commands;

use App\Services\PaymentReconciliationService;
use Illuminate\Console\Command;

class ReconcilePayments extends Command
{
    protected $signature = 'payments:reconcile {--limit=50 : Jumlah payment yang diperiksa}';

    protected $description = 'Sinkronkan status payment pending ke provider (server-to-server)';

    public function handle(PaymentReconciliationService $reconciliation): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $stats = $reconciliation->syncPending($limit);

        $this->info(sprintf(
            'Rekonsiliasi: %d diperiksa, %d berubah, %d gagal',
            $stats['checked'],
            $stats['changed'],
            $stats['failed']
        ));

        return Command::SUCCESS;
    }
}
