<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Contact form messages: written by the public endpoint, read and handled by
 * admins only.
 */
class ContactInquiryPolicy extends AdminPolicy
{
    /** Inquiries are created by the public contact endpoint, not by an admin. */
    public function create(User $user): bool
    {
        return false;
    }
}
