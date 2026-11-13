<?php

namespace App\Listeners;

use Illuminate\Mail\Events\MessageSending;

/**
 * Stops any email addressed to a top-level domain that can never receive one (R-49): .test, .example, .invalid
 * and .localhost (RFC 2606, RFC 6761). The demonstration accounts and businesses use .test addresses, so nothing
 * the application does on their behalf (an enquiry notice, a password reset) ever leaves the server; a message to
 * such an address could only bounce, and bounces cost the sending domain its reputation. Returning false cancels
 * the send; the in-app notice the message announced is unaffected.
 */
class SkipMailToReservedDomains
{
    private const TLDS = ['test', 'example', 'invalid', 'localhost'];

    public function handle(MessageSending $event): ?bool
    {
        foreach ($event->message->getTo() as $address) {
            if (self::reserved($address->getAddress())) {
                return false;
            }
        }

        return null;
    }

    public static function reserved(string $email): bool
    {
        $domain = strtolower((string) substr(strrchr($email, '@') ?: '', 1));
        if ($domain === '') {
            return false;
        }

        return in_array(substr((string) strrchr('.'.$domain, '.'), 1), self::TLDS, true);
    }
}
