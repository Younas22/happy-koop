<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ContactInquiryReceived;
use App\Mail\ContactInquiryAckMail;
use App\Mail\ContactInquiryAdminMail;
use App\Models\ContactInquiry;
use App\Services\Mail\MailContext;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Sends the administrator notification and the visitor acknowledgement.
 *
 * The listener runs synchronously but the mailables are queued, so the HTTP
 * response does not wait on a mail transport while the `notified_at` stamp
 * still lands inside the request - which is what makes a retried submission
 * cheap to detect.
 *
 * Idempotency is a conditional UPDATE, not a read-then-write: two concurrent
 * dispatches race on the same row and exactly one of them wins.
 */
class SendContactInquiryEmails
{
    public function __construct(private readonly MailContext $context) {}

    public function handle(ContactInquiryReceived $event): void
    {
        $inquiry = $event->inquiry;

        $claimed = ContactInquiry::query()
            ->whereKey($inquiry->getKey())
            ->whereNull('notified_at')
            ->update(['notified_at' => now()]);

        if ($claimed !== 1) {
            // Already notified - a replayed request, not a second message.
            return;
        }

        $admin = $this->context->adminRecipient();

        if ($admin === null) {
            // The message is safe in the database; only the copy is lost.
            Log::warning('contact.admin_notification.skipped', [
                'reason' => 'no administrator recipient configured',
                'inquiry_reference' => $inquiry->reference,
            ]);
        } else {
            $this->send(
                static fn () => Mail::to($admin)->send(new ContactInquiryAdminMail($inquiry)),
                'contact.admin_notification',
                $inquiry->reference,
            );
        }

        $this->send(
            static fn () => Mail::to($inquiry->email)->send(new ContactInquiryAckMail($inquiry)),
            'contact.acknowledgement',
            $inquiry->reference,
        );
    }

    /**
     * Queueing an email must not fail the request that created the inquiry.
     * The reference is logged; the visitor's address and message are not.
     */
    private function send(callable $dispatch, string $channel, string $reference): void
    {
        try {
            $dispatch();
        } catch (\Throwable $e) {
            Log::error($channel.'.failed', [
                'inquiry_reference' => $reference,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
