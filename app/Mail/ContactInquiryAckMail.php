<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\ContactInquiry;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Confirms to the visitor that their message arrived.
 *
 * It quotes the reference and repeats what they wrote, and promises no
 * response time, because the store has not configured one.
 */
class ContactInquiryAckMail extends StoreMail
{
    public function __construct(public readonly ContactInquiry $inquiry) {}

    public function envelope(): Envelope
    {
        return $this->senderEnvelope(
            'We received your message ('.$this->inquiry->reference.')',
            $this->context()->replyTo(),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.contact-ack',
            text: 'emails.text.contact-ack',
            with: $this->withBranding(
                ['inquiry' => $this->inquiry],
                'We received your message',
                'Your reference is '.$this->inquiry->reference.'.',
            ),
        );
    }
}
