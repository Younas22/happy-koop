<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\MoneyResource;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Services\Catalog\CatalogContext;
use App\Support\Money;
use Illuminate\Http\JsonResponse;

/**
 * Real measured metrics only. No projections, no forecasts, no invented
 * comparisons (api-contract.md 8).
 */
class DashboardController extends ApiController
{
    public function __construct(private readonly CatalogContext $context) {}

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Order::class);

        $currency = $this->context->currency();

        $paidStatuses = [Order::PAYMENT_PAID, Order::PAYMENT_PARTIALLY_REFUNDED];

        $revenueAllTime = (int) Order::query()
            ->whereIn('payment_status', $paidStatuses)
            ->sum('total_amount');

        $revenueLast30 = (int) Order::query()
            ->whereIn('payment_status', $paidStatuses)
            ->where('created_at', '>=', now()->subDays(30))
            ->sum('total_amount');

        $refunded = (int) Order::query()->sum('refunded_amount');

        return $this->data([
            'currency' => $currency,
            'orders' => [
                'total' => Order::query()->count(),
                'pending' => Order::query()->where('status', Order::STATUS_PENDING)->count(),
                'processing' => Order::query()->where('status', Order::STATUS_PROCESSING)->count(),
                'shipped' => Order::query()->where('status', Order::STATUS_SHIPPED)->count(),
                'last30Days' => Order::query()->where('created_at', '>=', now()->subDays(30))->count(),
            ],
            'revenue' => [
                'allTime' => MoneyResource::make(Money::of($revenueAllTime, $currency)),
                'last30Days' => MoneyResource::make(Money::of($revenueLast30, $currency)),
                'refundedAllTime' => MoneyResource::make(Money::of($refunded, $currency)),
            ],
            'catalog' => [
                'products' => Product::query()->count(),
                'published' => Product::query()->published()->count(),
                'drafts' => Product::query()->where('status', Product::STATUS_DRAFT)->count(),
                'outOfStock' => Product::query()
                    ->where('inventory_policy', Product::POLICY_TRACK)
                    ->where('stock_quantity', '<=', 0)
                    ->count(),
            ],
            'customers' => [
                'total' => User::query()->count(),
                'last30Days' => User::query()->where('created_at', '>=', now()->subDays(30))->count(),
            ],
            'reviews' => [
                'pending' => Review::query()->where('status', Review::STATUS_PENDING)->count(),
                'approved' => Review::query()->approved()->count(),
            ],
            'recentOrders' => Order::query()
                ->with('items')
                ->orderByDesc('id')
                ->limit(10)
                ->get()
                ->map(static fn (Order $order): array => OrderResource::summary($order))
                ->values()
                ->all(),
        ]);
    }
}
