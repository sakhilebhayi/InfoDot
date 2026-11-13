<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the OLD address when the email on an account is changed. From that moment password resets
 * and sign-in codes go to the new address, so the old one is the only place the owner would hear
 * about a change they did not make. The new address is deliberately not named in it.
 */
class EmailAddressChanged extends Notification
{
    public function __construct(private readonly string $name) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('The email address on your InfoDot account was changed')
            ->greeting('Hello '.$this->name.',')
            ->line('The email address on your InfoDot account was changed. Messages about the account now go to the new address, not to this one.')
            ->line('If you made this change, there is nothing more to do.')
            ->action('Contact support', route('contact'))
            ->line('If you did not make this change, contact support straight away so the account can be checked.');
    }
}
