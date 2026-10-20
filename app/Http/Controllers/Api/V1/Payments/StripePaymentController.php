<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Payments;

use App\Exceptions\ApiException;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Payments\PaymentOrderRequest;
use App\Models\PaymentAttempt;
use App\Services\Payments\PaymentOrderResolver;
use App\Services\Payments\StripeGateway;
use App\Services\Settings\PaymentSettingsService;
use Illuminate\Http\JsonResponse;

class StripePaymentController extends ApiController
{
    public function __construct(
        private readonly StripeGateway $stripe,
        private readonly PaymentSettingsService $settings,
        private readonly PaymentOrderResolver $resolver,
    ) {}

    public function intent(PaymentOrderRequest $request): JsonResponse
    {
        $this->assertEnabled();

        $order = $this->resolver->payable(
            (string) $request->input('orderNumber'),
            $request->input('accessToken'),
            $request->user()
        );

        // The amount is read from the stored order inside the gateway.
        $result = $this->stripe->createPaymentIntent($order);

        $this->recordAttempt($order->id, (string) $result['paymentIntentId'], (int) $order->total_amount, $order->currency);

        return $this->data($result);
    }

    public function checkoutSession(PaymentOrderRequest $request): JsonResponse
    {
        $this->assertEnabled();

        $order = $this->resolver->payable(
            (string) $request->input('orderNumber'),
            $request->input('accessToken'),
            $request->user()
        );

        $frontend = rtrim((string) config('happykoop.frontend_url'), '/');

        $result = $this->stripe->createCheckoutSession(
            $order,
            $frontend.'/checkout/success?order='.urlencode((string) $order->order_number),
            $frontend.'/checkout/cancelled?order='.urlencode((string) $order->order_number)
        );

        $this->recordAttempt($order->id, $result['sessionId'], (int) $order->total_amount, $order->currency);

        return $this->data($result);
    }

    private function assertEnabled(): void
    {
        if (! $this->settings->isEnabled('stripe') || ! $this->stripe->isConfigured()) {
            throw ApiException::paymentNotConfigured();
        }
    }

    private function recordAttempt(int $orderId, string $reference, int $amount, string $currency): void
    {
        PaymentAttempt::query()->updateOrCreate(
            [
                'order_id' => $orderId,
                'provider' => 'stripe',
                'provider_reference' => $reference,
            ],
            [
                'mode' => $this->settings->mode('stripe'),
                'status' => PaymentAttempt::STATUS_PROCESSING,
                'amount' => $amount,
                'currency' => $currency,
            ]
        );
    }
}
