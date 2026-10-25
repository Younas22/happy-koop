<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Order;
use App\Services\Mail\OrderMailPresenter;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The customer's copy of the order.
 *
 * It confirms that the order was received, not that it was paid: the payment
 * line is whatever the backend verified, and no delivery date is promised
 * because nothing in this system records one.
 */
class OrderConfirmationMail extends StoreMail
{
    public function __construct(public readonly Order $order) {}

    public function envelope(): Envelope
    {
        return $this->senderEnvelope(
            'Your order '.$this->order->order_number,
            $this->context()->replyTo(),
        );
    }

    public function content(): Content
    {
        $presented = app(OrderMailPresenter::class)->present($this->order);

        return new Content(
            view: 'emails.order-confirmation',
            text: 'emails.text.order-confirmation',
            with: $this->withBranding(
                $presented + ['orderUrl' => $this->context()->customerOrderUrl($this->order)],
                'Your order '.$this->order->order_number,
                'We have received order '.$this->order->order_number.'.',
            ),
        );
    }
}
