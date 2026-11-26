<?php

namespace App\Policies;

use App\Models\Enquiry;
use App\Models\User;

/**
 * R-72 (Q-21): who may read and act on an enquiry, in one place. The customer who sent it reads it and replies as the customer; the
 * business's owner, its administrators and the member it is assigned to handle it. Controllers answer 404 when these say no, so a
 * stranger cannot tell an enquiry exists.
 */
class EnquiryPolicy
{
    public function view(User $user, Enquiry $enquiry): bool
    {
        return $enquiry->isCustomer($user) || $enquiry->isHandler($user);
    }

    /** Reply on the customer's side of the conversation. */
    public function replyAsCustomer(User $user, Enquiry $enquiry): bool
    {
        return $enquiry->isCustomer($user);
    }

    /** Read, reply, assign, change the status and decide an appointment request, for the business. */
    public function handle(User $user, Enquiry $enquiry): bool
    {
        return $enquiry->isHandler($user);
    }
}
