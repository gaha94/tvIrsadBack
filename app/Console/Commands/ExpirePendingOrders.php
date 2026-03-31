<?php

namespace App\Console\Commands;

use App\Services\Orders\ExpirePendingOrdersService;
use Illuminate\Console\Command;

class ExpirePendingOrders extends Command
{
    protected $signature = 'orders:expire-pending {--minutes=30}';
    protected $description = 'Expira órdenes pending antiguas y libera stock reservado';

    public function handle(ExpirePendingOrdersService $service): int
    {
        $minutes = (int) $this->option('minutes');

        if ($minutes <= 0) {
            $minutes = 30;
        }

        $result = $service->execute($minutes);

        $this->info('Órdenes expiradas: ' . $result['expired_count']);
        $this->line('Cutoff: ' . $result['cutoff']);

        if (!empty($result['expired_order_ids'])) {
            $this->line('IDs: ' . implode(', ', $result['expired_order_ids']));
        }

        return self::SUCCESS;
    }
}