<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Order;
use App\Services\Mail\OrderMailPresenter;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Tells the store an order exists.
 *
 * The subject carries the payment state, because an order may be created
 * before payment is confirmed and the difference matters to whoever is about
 * to pack it.
 */
class OrderPlacedAdminMail extends StoreMail
{
    public function __construct(public readonly Order $order) {}

    public function envelope(): Envelope
    {
        $paid = $this->order->payment_status === Order::PAYMENT_PAID;

        return $this->senderEnvelope(
            sprintf(
                'New order %s (%s)',
                $this->order->order_number,
                $paid ? 'paid' : str_replace('_', ' ', (string) $this->order->payment_status),
            ),
            filter_var($this->order->email, FILTER_VALIDATE_EMAIL) ? $this->order->email : null,
        );
    }

    public function content(): Content
    {
        $presented = app(OrderMailPresenter::class)->present($this->order);

        return new Content(
            view: 'emails.order-admin',
            text: 'emails.text.order-admin',
            with: $this->withBranding(
                $presented + ['adminUrl' => $this->context()->adminOrderUrl($this->order)],
                'New order '.$this->order->order_number,
                'Order '.$this->order->order_number.' needs attention.',
            ),
        );
    }
}
