<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Payments;

use App\Exceptions\ApiException;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Payments\PayPalCaptureRequest;
use App\Http\Requests\Payments\PaymentOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Services\Payments\PaymentOrderResolver;
use App\Services\Payments\PaymentStatusMachine;
use App\Services\Payments\PayPalGateway;
use App\Services\Settings\PaymentSettingsService;
use Illuminate\Http\JsonResponse;

class PayPalPaymentController extends ApiController
{
    public function __construct(
        private readonly PayPalGateway $paypal,
        private readonly PaymentSettingsService $settings,
        private readonly PaymentOrderResolver $resolver,
        private readonly PaymentStatusMachine $machine,
    ) {}

    public function createOrder(PaymentOrderRequest $request): JsonResponse
    {
        $this->assertEnabled();

        $order = $this->resolver->payable(
            (string) $request->input('orderNumber'),
            $request->input('accessToken'),
            $request->user()
        );

        $frontend = rtrim((string) config('happykoop.frontend_url'), '/');

        $result = $this->paypal->createOrder(
            $order,
            $frontend.'/checkout/paypal/return?order='.urlencode((string) $order->order_number),
            $frontend.'/checkout/paypal/cancel?order='.urlencode((string) $order->order_number)
        );

        PaymentAttempt::query()->updateOrCreate(
            [
                'order_id' => $order->id,
                'provider' => 'paypal',
                'provider_reference' => (string) $result['paypalOrderId'],
            ],
            [
                'mode' => $this->settings->mode('paypal'),
                'status' => PaymentAttempt::STATUS_PROCESSING,
                'amount' => (int) $order->total_amount,
                'currency' => $order->currency,
            ]
        );

        return $this->data($result);
    }

    /**
     * Captures server-side and verifies status, amount, currency and the order
     * reference before any payment state changes. A frontend "approved"
     * callback alone never marks an order paid.
     */
    public function capture(PayPalCaptureRequest $request): JsonResponse
    {
        $this->assertEnabled();

        $order = $this->resolver->payable(
            (string) $request->input('orderNumber'),
            $request->input('accessToken'),
            $request->user()
        );

        $result = $this->paypal->captureOrder($order, (string) $request->input('paypalOrderId'));

        if (! $result['verified']) {
            PaymentAttempt::query()
                ->where('order_id', $order->id)
                ->where('provider', 'paypal')
                ->latest('id')
                ->first()
                ?->forceFill([
                    'status' => PaymentAttempt::STATUS_FAILED,
                    'error_code' => (string) $result['reason'],
                ])->save();

            throw ApiException::conflict('The payment could not be verified. Your order was not charged as paid.');
        }

        PaymentAttempt::query()
            ->where('order_id', $order->id)
            ->where('provider', 'paypal')
            ->latest('id')
            ->first()
            ?->forceFill([
                'status' => PaymentAttempt::STATUS_SUCCEEDED,
                'provider_reference' => $result['reference'],
            ])->save();

        $this->machine->applyPayment($order, Order::PAYMENT_PAID, 'paypal');

        $order->refresh()->load('items.product.primaryMedia');

        return $this->data(OrderResource::status($order));
    }

    private function assertEnabled(): void
    {
        if (! $this->settings->isEnabled('paypal') || ! $this->paypal->isConfigured()) {
            throw ApiException::paymentNotConfigured();
        }
    }
}
