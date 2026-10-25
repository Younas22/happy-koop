<?php

declare(strict_types=1);

namespace App\Mail;

use App\Services\Mail\MailContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Base for every transactional email this store sends.
 *
 * Queued, because a mail transport must never hold up a checkout response or a
 * contact form submission - and because a transient Resend failure should be
 * retried by the queue rather than surfacing as a failed order.
 *
 * Branding is resolved at render time through the container, not stored on the
 * mailable: a queued payload carries the model and nothing else, so it stays
 * small and cannot serialise a settings repository or a database connection.
 */
abstract class StoreMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** Retried by the queue; after this the job lands in failed_jobs. */
    public int $tries = 3;

    /** Spaced out, so a provider blip is not hammered three times in a second. */
    public array $backoff = [30, 120];

    protected function context(): MailContext
    {
        return app(MailContext::class);
    }

    /**
     * The sender every email shares.
     *
     * The From address stays the configured, domain-verified sender; the
     * store's own contact address is only ever a Reply-To, because Resend will
     * refuse a sender it cannot verify.
     */
    protected function senderEnvelope(string $subject, ?string $replyTo = null): Envelope
    {
        $context = $this->context();

        return new Envelope(
            from: new Address($context->fromAddress(), $context->fromName()),
            replyTo: $replyTo !== null ? [new Address($replyTo)] : [],
            subject: $subject,
        );
    }

    /**
     * Branding plus the subject line, for the shared layout.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function withBranding(array $data, string $subjectLine, ?string $preheader = null): array
    {
        return $data + $this->context()->branding() + [
            'subjectLine' => $subjectLine,
            'preheader' => $preheader,
        ];
    }
}
