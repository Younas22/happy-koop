<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Settings\SettingsRepository;
use App\Support\Money;
use App\Support\MoneyFormatter;

/**
 * Turns an order into the view data its emails need.
 *
 * Money is formatted here, once, from the order's own frozen currency and the
 * store locale - never in a Blade template and never re-derived from a cart.
 * Shipping and tax appear only when the order actually carries them, so an
 * email cannot imply a charge the backend did not calculate.
 */
class OrderMailPresenter
{
    public function __construct(private readonly SettingsRepository $settings) {}

    /**
     * @return array<string, mixed>
     */
    public function present(Order $order): array
    {
        $order->loadMissing('items');
        $currency = (string) ($order->currency ?: 'USD');
        $locale = (string) $this->settings->getString('locale.locale', 'en-US');

        $format = fn (int $amount): string => MoneyFormatter::format(
            Money::of($amount, $currency),
            $locale,
        );

        $lines = $order->items
            ->map(static fn (OrderItem $item): array => [
                'name' => (string) $item->name,
                'variant' => $item->variant_label,
                'sku' => $item->sku,
                'quantity' => (int) $item->quantity,
                'unitPrice' => MoneyFormatter::format(
                    Money::of((int) $item->unit_price_amount, (string) ($item->currency ?: $currency)),
                    $locale,
                ),
                'lineTotal' => MoneyFormatter::format(
                    Money::of((int) $item->line_total_amount, (string) ($item->currency ?: $currency)),
                    $locale,
                ),
            ])
            ->values()
            ->all();

        $totals = [
            ['label' => 'Subtotal', 'value' => $format((int) $order->subtotal_amount), 'strong' => false],
        ];

        if ((int) $order->discount_amount > 0) {
            $totals[] = [
                'label' => 'Discount',
                'value' => '-'.$format((int) $order->discount_amount),
                'strong' => false,
            ];
        }

        if ($order->shipping_amount !== null) {
            $totals[] = [
                'label' => $order->shipping_method_label
                    ? 'Shipping ('.$order->shipping_method_label.')'
                    : 'Shipping',
                'value' => $format((int) $order->shipping_amount),
                'strong' => false,
            ];
        }

        if ($order->tax_amount !== null) {
            $totals[] = ['label' => 'Tax', 'value' => $format((int) $order->tax_amount), 'strong' => false];
        }

        $totals[] = [
            'label' => 'Total ('.$currency.')',
            'value' => $format((int) $order->total_amount),
            'strong' => true,
        ];

        return [
            'order' => $order,
            'lines' => $lines,
            'totals' => $totals,
            'customerName' => $this->customerName($order),
            'paymentLabel' => $this->paymentLabel($order),
            'shippingAddress' => $this->addressText($order->shipping_address),
            'shippingMethod' => $order->shipping_method_label,
            'placedAt' => $this->dateText($order->placed_at ?? $order->created_at),
            'deliveredAt' => $this->dateText($order->delivered_at),
        ];
    }

    private function customerName(Order $order): ?string
    {
        $address = is_array($order->shipping_address) ? $order->shipping_address : [];
        $name = trim(
            (string) ($address['firstName'] ?? '').' '.(string) ($address['lastName'] ?? '')
        );

        return $name !== '' ? $name : null;
    }

    /**
     * The payment state in words, straight from the stored value. "Unpaid"
     * stays "unpaid": nothing here upgrades a pending payment.
     */
    private function paymentLabel(Order $order): string
    {
        return ucfirst(str_replace('_', ' ', (string) $order->payment_status));
    }

    /**
     * The stored address snapshot as printable lines.
     *
     * Only the parts that are present are printed - a missing company or
     * region leaves no blank line behind.
     *
     * @param  mixed  $address
     */
    private function addressText($address): ?string
    {
        if (! is_array($address)) {
            return null;
        }

        $name = trim((string) ($address['firstName'] ?? '').' '.(string) ($address['lastName'] ?? ''));
        $cityLine = trim(implode(' ', array_filter([
            (string) ($address['postalCode'] ?? ''),
            (string) ($address['city'] ?? ''),
        ], static fn (string $part): bool => $part !== '')));

        $lines = array_filter([
            $name,
            (string) ($address['company'] ?? ''),
            (string) ($address['line1'] ?? ''),
            (string) ($address['line2'] ?? ''),
            $cityLine,
            (string) ($address['region'] ?? ''),
            (string) ($address['countryCode'] ?? ''),
        ], static fn (string $line): bool => trim($line) !== '');

        return $lines === [] ? null : implode("\n", $lines);
    }

    private function dateText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $timezone = (string) $this->settings->getString('locale.timezone', 'UTC');

        // Stored in UTC; displayed in the store's own timezone.
        return $value->copy()->setTimezone($timezone)->format('j M Y, H:i').' ('.$timezone.')';
    }
}
