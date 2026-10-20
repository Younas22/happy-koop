<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\PaymentEvent;
use App\Services\Payments\PaymentEventProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Webhook side effects run on the queue so the provider gets its 200 quickly.
 */
class ProcessPaymentEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public readonly int $paymentEventId) {}

    public function handle(PaymentEventProcessor $processor): void
    {
        $event = PaymentEvent::query()->find($this->paymentEventId);

        if ($event === null) {
            return;
        }

        $processor->process($event);
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 30, 120, 600];
    }
}
