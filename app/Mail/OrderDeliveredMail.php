<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Order;
use App\Services\Mail\OrderMailPresenter;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Sent when an order actually reaches the delivered state - never on creation,
 * payment or dispatch.
 */
class OrderDeliveredMail extends StoreMail
{
    public function __construct(public readonly Order $order) {}

    public function envelope(): Envelope
    {
        return $this->senderEnvelope(
            'Order '.$this->order->order_number.' has been delivered',
            $this->context()->replyTo(),
        );
    }

    public function content(): Content
    {
        $presented = app(OrderMailPresenter::class)->present($this->order);

        return new Content(
            view: 'emails.order-delivered',
            text: 'emails.text.order-delivered',
            with: $this->withBranding(
                $presented + ['orderUrl' => $this->context()->customerOrderUrl($this->order)],
                'Order '.$this->order->order_number.' has been delivered',
                'Your order has been delivered.',
            ),
        );
    }
}
