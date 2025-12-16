<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\OrderArchive;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ArchiveOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:archive {--days=30 : 归档多少天前的订单}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '归档指定天数前的已完成订单';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoffDate = now()->subDays($days);

        $this->info("开始归档 {$days} 天前的订单 (截止日期: {$cutoffDate})...");

        // 只归档已完成状态的订单
        $completedStatuses = [
            Order::STATUS_COMPLETED,
            Order::STATUS_CANCELLED,
            Order::STATUS_FAILED,
            Order::STATUS_SETTLED,
        ];

        $totalArchived = 0;
        $batchSize = 1000;

        do {
            $archived = DB::transaction(function () use ($cutoffDate, $completedStatuses, $batchSize) {
                $orders = Order::whereIn('status', $completedStatuses)
                    ->where('created_at', '<', $cutoffDate)
                    ->limit($batchSize)
                    ->get();

                if ($orders->isEmpty()) {
                    return 0;
                }

                $archiveData = $orders->map(function ($order) {
                    return [
                        'user_id' => $order->user_id,
                        'game_id' => $order->game_id,
                        'brand_id' => $order->brand_id,
                        'amount' => $order->amount,
                        'payout' => $order->payout,
                        'status' => $order->status,
                        'currency' => $order->currency,
                        'payment_currency' => $order->payment_currency,
                        'payment_amount' => $order->payment_amount,
                        'payment_payout' => $order->payment_payout,
                        'notes' => $order->notes,
                        'finished_at' => $order->finished_at,
                        'order_id' => $order->order_id,
                        'out_id' => $order->out_id,
                        'version' => $order->version,
                        'created_at' => $order->created_at,
                        'updated_at' => $order->updated_at,
                        'archived_at' => now(),
                    ];
                })->toArray();

                // 批量插入到归档表
                OrderArchive::insert($archiveData);

                // 删除原表数据
                Order::whereIn('id', $orders->pluck('id'))->delete();

                return count($orders);
            });

            $totalArchived += $archived;

            if ($archived > 0) {
                $this->info("已归档 {$archived} 条订单...");
            }
        } while ($archived === $batchSize);

        $this->info("归档完成，共归档 {$totalArchived} 条订单");

        return 0;
    }
}

