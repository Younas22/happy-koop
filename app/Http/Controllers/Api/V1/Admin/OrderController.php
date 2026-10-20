<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Exceptions\ApiException;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Admin\OrderFulfilmentRequest;
use App\Http\Requests\Admin\OrderStatusRequest;
use App\Http\Requests\Admin\RefundRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Services\AuditLogger;
use App\Services\Payments\PaymentStatusMachine;
use App\Services\Payments\PayPalGateway;
use App\Services\Payments\StripeGateway;
use App\Support\Html;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends ApiController
{
    public function __construct(
        private readonly PaymentStatusMachine $machine,
        private readonly StripeGateway $stripe,
        private readonly PayPalGateway $paypal,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Order::class);

        $this->validateQuery($request, [
            'q' => ['sometimes', 'nullable', 'string', 'max:120'],
            'status' => ['sometimes', 'string', 'in:pending,processing,shipped,delivered,cancelled,refunded'],
            'payment_status' => ['sometimes', 'string', 'in:unpaid,authorized,paid,partially_refunded,refunded,failed,cancelled'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:60'],
        ]);

        $query = Order::query()->with('items')->orderByDesc('id');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($paymentStatus = $request->query('payment_status')) {
            $query->where('payment_status', $paymentStatus);
        }

        if ($from = $request->query('from')) {
            $query->where('created_at', '>=', $from);
        }

        if ($to = $request->query('to')) {
            $query->where('created_at', '<=', $to);
        }

        if ($term = trim((string) $request->query('q', ''))) {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
            $query->where(static function ($q) use ($like): void {
                $q->where('order_number', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('tracking_number', 'like', $like);
            });
        }

        return $this->paginated(
            $query->paginate($this->perPage($request)),
            static fn (Order $order): array => OrderResource::summary($order) + [
                'id' => (int) $order->id,
                'email' => $order->email,
                'paymentProvider' => $order->payment_provider,
            ]
        );
    }

    public function show(int $id): JsonResponse
    {
        $order = $this->find($id);

        $this->authorize('view', $order);

        return $this->data(OrderResource::admin($order));
    }

    public function updateStatus(OrderStatusRequest $request, int $id): JsonResponse
    {
        $order = $this->find($id);

        $this->authorize('update', $order);

        $changed = [];

        if ($request->has('paymentStatus')) {
            // Invalid transitions raise 409 CONFLICT inside the machine.
            $this->machine->applyPayment($order, (string) $request->input('paymentStatus'));
            $changed[] = 'payment_status';
        }

        if ($request->has('status')) {
            $this->machine->applyStatus($order->refresh(), (string) $request->input('status'));
            $changed[] = 'status';
        }

        if ($request->has('adminNote')) {
            $order->refresh()->forceFill([
                'admin_note' => Html::text($request->input('adminNote'), 5000),
            ])->save();
            $changed[] = 'admin_note';
        }

        $this->audit->log('order.status.updated', $request->user()?->id, $order, $changed, $request);

        return $this->data(OrderResource::admin($this->find($id)));
    }

    public function updateFulfilment(OrderFulfilmentRequest $request, int $id): JsonResponse
    {
        $order = $this->find($id);

        $this->authorize('update', $order);

        $attributes = [];

        foreach ([
            'carrier' => 'carrier',
            'trackingNumber' => 'tracking_number',
            'trackingUrl' => 'tracking_url',
        ] as $inputKey => $column) {
            if ($request->has($inputKey)) {
                $value = $request->input($inputKey);
                $attributes[$column] = is_string($value) && trim($value) !== '' ? trim($value) : null;
            }
        }

        $order->forceFill($attributes)->save();

        $this->audit->log('order.fulfilment.updated', $request->user()?->id, $order, array_keys($attributes), $request);

        return $this->data(OrderResource::admin($order->refresh()->load('items.product.primaryMedia')));
    }

    /**
     * Provider refund, capped at the order's refundable balance.
     */
    public function refund(RefundRequest $request, int $id): JsonResponse
    {
        $order = $this->find($id);

        $this->authorize('update', $order);

        if (! $order->isPaidOrAuthorized()
            && ! in_array($order->payment_status, [Order::PAYMENT_PARTIALLY_REFUNDED], true)) {
            throw ApiException::conflict('This order has no captured payment to refund.');
        }

        $requested = Money::of((int) $request->input('amount'), $order->currency);
        $refundable = $order->refundableAmount();

        if ($requested->greaterThan($refundable)) {
            throw ApiException::validation([
                'amount' => ['The refund amount exceeds the refundable balance for this order.'],
            ]);
        }

        $attempt = PaymentAttempt::query()
            ->where('order_id', $order->id)
            ->where('status', PaymentAttempt::STATUS_SUCCEEDED)
            ->whereNotNull('provider_reference')
            ->latest('id')
            ->first();

        if ($attempt === null) {
            throw ApiException::conflict(
                'No successful payment reference is recorded for this order, so it cannot be refunded automatically.'
            );
        }

        $reference = $order->payment_provider === 'paypal'
            ? $this->paypal->refund($requested, (string) $attempt->provider_reference)
            : $this->stripe->refund($order, $requested, (string) $attempt->provider_reference);

        $totalRefunded = (int) $order->refunded_amount + $requested->amount;

        $order->forceFill(['refunded_amount' => $totalRefunded])->save();

        $this->machine->applyPayment(
            $order->refresh(),
            $totalRefunded >= (int) $order->total_amount
                ? Order::PAYMENT_REFUNDED
                : Order::PAYMENT_PARTIALLY_REFUNDED
        );

        $this->audit->log(
            'order.refunded',
            $request->user()?->id,
            $order,
            ['refunded_amount', 'payment_status'],
            $request
        );

        return $this->data(OrderResource::admin($this->find($id)) + ['refundReference' => $reference]);
    }

    private function find(int $id): Order
    {
        /** @var Order|null $order */
        $order = Order::query()->with(['items.product.primaryMedia'])->find($id);

        abort_if($order === null, 404);

        return $order;
    }
}
