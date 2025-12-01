<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderService
{
    /**
     * Get user orders with filters and pagination.
     *
     * @param int $userId
     * @param array $filters Supported filters: currency, status, game_id, period (24h, 7d, 30d)
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getUserOrdersPaginated(int $userId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Order::query()
            ->where('user_id', $userId)
            ->with(['user', 'game', 'brand'])
            ->orderBy('created_at', 'desc');

        // Apply filters
        if (isset($filters['currency'])) {
            $query->forCurrency($filters['currency']);
        }

        if (isset($filters['status'])) {
            $query->byStatus($filters['status']);
        }

        if (isset($filters['game_id'])) {
            $query->where('game_id', $filters['game_id']);
        }

        // Apply time period filter (24h, 7d, 30d)
        if (isset($filters['period'])) {
            $endDate = Carbon::now();
            $startDate = match($filters['period']) {
                '24h' => $endDate->copy()->subDay(),
                '7d' => $endDate->copy()->subDays(7),
                '30d' => $endDate->copy()->subDays(30),
                default => null,
            };
            
            if ($startDate) {
                $query->whereBetween('created_at', [$startDate, $endDate]);
            }
        }

        return $query->paginate($perPage);
    }

    /**
     * Format order for API response.
     *
     * @param Order $order
     * @param bool $includeDetails Include additional details
     * @return array
     */
    public function formatOrderForResponse(Order $order, bool $includeDetails = false): array
    {
        $data = [
            'id' => $order->id,
            'order_id' => $order->order_id,
            'out_id' => $order->out_id,
            'currency' => $order->currency,
            'amount' => (float)$order->amount,
            'payout' => $order->payout ? (float)$order->payout : null,
            'status' => $order->status,
            'finished_at' => $order->finished_at ? $order->finished_at->format('Y-m-d H:i:s') : null,
            'created_at' => $order->created_at->format('Y-m-d H:i:s'),
        ];

        if ($order->game) {
            $data['game'] = [
                'id' => $order->game->id,
                'name' => $order->game->name,
                'key' => $order->game->key,
            ];
        }

        if ($order->brand) {
            $data['brand'] = [
                'id' => $order->brand->id,
                'name' => $order->brand->name,
                'provider' => $order->brand->provider,
            ];
        }

        if ($order->notes) {
            $data['notes'] = $order->notes;
        }

        if ($order->payment_currency) {
            $data['payment_currency'] = $order->payment_currency;
            $data['payment_amount'] = $order->payment_amount ? (float)$order->payment_amount : null;
            $data['payment_payout'] = $order->payment_payout ? (float)$order->payment_payout : null;
        }

        if ($includeDetails) {
            $data['updated_at'] = $order->updated_at->format('Y-m-d H:i:s');
            $data['version'] = $order->version;
        }

        return $data;
    }

    /**
     * Get order by order_id for a specific user.
     *
     * @param int $userId
     * @param string $orderId
     * @return Order|null
     */
    public function getOrderByOrderId(int $userId, string $orderId): ?Order
    {
        return Order::where('user_id', $userId)
            ->where('order_id', $orderId)
            ->with(['user', 'game', 'brand'])
            ->first();
    }

    public function bet($userId, $amount, $currency, $game, $roundId)
    {
        $order = Order::where('user_id', $userId)
            ->where('game_id', $game->id)
            ->where('out_id', $roundId)
            ->first();

        if (!$order) {
            $order = Order::create([
                'order_id' => Str::ulid()->toString(),
                'user_id' => $userId,
                'amount' => $amount,
                'currency' => $currency,
                'game_id' => $game->id,
                'brand_id' => $game->brand_id,
                'status' => Order::STATUS_PENDING,
                'out_id' => $roundId,
            ]);
        } else {
            $order->amount += $amount;
            $order->save();
        }

        return $order;
    }

    public function payout($userId, $amount, $game, $roundId, $isFinished)
    {
        $order = Order::where('user_id', $userId)
            ->where('game_id', $game->id)
            ->where('out_id', $roundId)
            ->first();

        if (!$order) {
            return null;
        }

        if ($order->status != Order::STATUS_PENDING) {
            return null;
        }

        $order->payout += $amount;
        $order->status = $isFinished ? Order::STATUS_COMPLETED : Order::STATUS_PENDING;
        $order->finished_at = $isFinished ? Carbon::now() : null;
        $order->save();

        return $order;
    }

    public function refund($userId, $game, $roundId)
    {
        $order = Order::where('user_id', $userId)
            ->where('game_id', $game->id)
            ->where('out_id', $roundId)
            ->first();

        if (!$order) {
            return null;
        }

        if ($order->status == Order::STATUS_COMPLETED) {
            return null;
        }

        $order->status = Order::STATUS_CANCELLED;
        $order->finished_at = Carbon::now();
        $order->save();

        return $order;
    }
}

