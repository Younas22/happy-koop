<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Order;
use App\Models\OrderItem;

final class OrderResource
{
    /**
     * Totals block. `shipping` / `tax` stay null when they were never
     * determined, and are never rendered as a zero total.
     *
     * @return array<string, mixed>
     */
    public static function totals(Order $order): array
    {
        return [
            'subtotal' => MoneyResource::make($order->subtotal),
            'discount' => MoneyResource::make($order->discount),
            'shipping' => MoneyResource::nullable($order->shipping),
            'tax' => MoneyResource::nullable($order->tax),
            'total' => MoneyResource::make($order->total),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function item(OrderItem $item): array
    {
        return [
            'id' => (int) $item->id,
            'productId' => $item->product_id === null ? null : (int) $item->product_id,
            'variantId' => $item->product_variant_id === null ? null : (int) $item->product_variant_id,
            'name' => $item->name,
            'variantLabel' => $item->variant_label,
            'sku' => $item->sku,
            'options' => array_values((array) ($item->options ?? [])),
            'quantity' => (int) $item->quantity,
            'unitPrice' => MoneyResource::make($item->unit_price),
            'lineTotal' => MoneyResource::make($item->line_total),
            'slug' => $item->product?->slug,
            'imageUrl' => $item->product?->primaryMedia?->url(),
        ];
    }

    /**
     * Checkout-initialize / payment response shape, including the opaque
     * guest access token (returned once, to the creator).
     *
     * @return array<string, mixed>
     */
    public static function withAccessToken(Order $order): array
    {
        return self::make($order) + ['accessToken' => $order->access_token];
    }

    /**
     * @return array<string, mixed>
     */
    public static function make(Order $order): array
    {
        return [
            'id' => (int) $order->id,
            'orderNumber' => $order->order_number,
            'status' => $order->status,
            'paymentStatus' => $order->payment_status,
            'paymentProvider' => $order->payment_provider,
            'currency' => $order->currency,
            'email' => $order->email,
            'totals' => self::totals($order),
            'items' => $order->items->map(static fn (OrderItem $item): array => self::item($item))->values()->all(),
            'shippingAddress' => $order->shipping_address,
            'billingAddress' => $order->billing_address,
            'shippingMethod' => $order->shipping_method_code === null ? null : [
                'id' => $order->shipping_method_code,
                'label' => $order->shipping_method_label,
            ],
            'couponCode' => $order->coupon_code,
            'customerNote' => $order->customer_note,
            'tracking' => [
                'carrier' => $order->carrier,
                'trackingNumber' => $order->tracking_number,
                'trackingUrl' => $order->tracking_url,
            ],
            'placedAt' => $order->placed_at?->toIso8601String(),
            'paidAt' => $order->paid_at?->toIso8601String(),
            'createdAt' => $order->created_at?->toIso8601String(),
            'expiresAt' => $order->expires_at?->toIso8601String(),
        ];
    }

    /**
     * Public status lookup used by the success page.
     *
     * @return array<string, mixed>
     */
    public static function status(Order $order): array
    {
        return [
            'orderNumber' => $order->order_number,
            'status' => $order->status,
            'paymentStatus' => $order->payment_status,
            'currency' => $order->currency,
            'totals' => self::totals($order),
            'items' => $order->items->map(static fn (OrderItem $item): array => self::item($item))->values()->all(),
            'shippingAddress' => $order->shipping_address,
            'tracking' => [
                'carrier' => $order->carrier,
                'trackingNumber' => $order->tracking_number,
                'trackingUrl' => $order->tracking_url,
            ],
            'placedAt' => $order->placed_at?->toIso8601String(),
            'expiresAt' => $order->expires_at?->toIso8601String(),
        ];
    }

    /**
     * Account order list summary.
     *
     * @return array<string, mixed>
     */
    public static function summary(Order $order): array
    {
        return [
            'orderNumber' => $order->order_number,
            'status' => $order->status,
            'paymentStatus' => $order->payment_status,
            'currency' => $order->currency,
            'total' => MoneyResource::make($order->total),
            'itemCount' => (int) $order->items->sum('quantity'),
            'placedAt' => $order->placed_at?->toIso8601String(),
            'createdAt' => $order->created_at?->toIso8601String(),
        ];
    }

    /**
     * Admin view: adds internal notes and the refund ledger. Still contains
     * no payment credential of any kind.
     *
     * @return array<string, mixed>
     */
    public static function admin(Order $order): array
    {
        return self::make($order) + [
            'userId' => $order->user_id === null ? null : (int) $order->user_id,
            'phone' => $order->phone,
            'adminNote' => $order->admin_note,
            'refundedAmount' => MoneyResource::make($order->refunded),
            'refundableAmount' => MoneyResource::make($order->refundableAmount()),
            'cancelledAt' => $order->cancelled_at?->toIso8601String(),
            'inventoryCommittedAt' => $order->inventory_committed_at?->toIso8601String(),
            'updatedAt' => $order->updated_at?->toIso8601String(),
        ];
    }
}
