<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\ContactInquiry;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Tells the store that someone used the contact form.
 *
 * Reply-To is the visitor's address, so answering is one click. The visitor
 * never chooses the recipient: the listener resolves it from store settings,
 * which is what keeps this from becoming an open relay to any address an
 * attacker puts in a form field.
 */
class ContactInquiryAdminMail extends StoreMail
{
    public function __construct(public readonly ContactInquiry $inquiry) {}

    public function envelope(): Envelope
    {
        return $this->senderEnvelope(
            'New contact inquiry '.$this->inquiry->reference,
            filter_var($this->inquiry->email, FILTER_VALIDATE_EMAIL) ? $this->inquiry->email : null,
        );
    }

    public function content(): Content
    {
        $data = $this->withBranding(
            [
                'inquiry' => $this->inquiry,
                'receivedAt' => $this->inquiry->created_at?->format('j M Y, H:i').' UTC',
            ],
            'New contact inquiry '.$this->inquiry->reference,
            $this->inquiry->excerpt(80),
        );

        return new Content(
            view: 'emails.contact-admin',
            text: 'emails.text.contact-admin',
            with: $data,
        );
    }
}
