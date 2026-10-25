<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\ContactInquiry;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A visitor's contact message has been accepted and stored.
 *
 * `ShouldDispatchAfterCommit` is the important part: the listener only runs
 * once the inquiry is really in the database. Without it, a rolled-back
 * request could still have emailed the store about a message that does not
 * exist, and the acknowledgement would quote a reference nobody can look up.
 */
class ContactInquiryReceived implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly ContactInquiry $inquiry) {}
}
