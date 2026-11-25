<?php

namespace App\Services\Notifications;

use App\Models\NotificationPreference;
use App\Models\User;
use App\Models\UserNotice;

/**
 * What each person chose to hear, and how (R-64 phase 8). Every notice still lands on the person's list; the choices decide two things only: whether a
 * new one pops up while a page is open, and how email reaches them (as it happens, once a day in a summary, or not at all).
 *
 * Account protection is not a choice: security notices always pop up and security email always goes at once. An invitation always pops up, because
 * it is something to answer. A category that has no email as it happens (nothing is sent per event for it) offers only the summary or nothing.
 */
class NotificationPreferences
{
    public const INSTANT = 'instant';

    public const DIGEST = 'digest';

    public const OFF = 'off';

    /**
     * Per category: its words, what the person may change, and the default. `popup` is false where it cannot be turned off; `modes` lists the email
     * choices (empty: this category has no email of its own).
     *
     * @var array<string, array{name: string, about: string, popup_locked: bool, modes: list<string>, default: string}>
     */
    public const CATEGORIES = [
        'security' => ['name' => 'Account and security', 'about' => 'Sign-in and identity warnings. Protecting your account is not optional, so these always appear and are always emailed at once.', 'popup_locked' => true, 'modes' => [], 'default' => self::INSTANT],
        'invitations' => ['name' => 'Invitations', 'about' => 'An invitation to join a company, which you answer from the notice. The invitation itself also reaches you by email.', 'popup_locked' => true, 'modes' => [], 'default' => self::OFF],
        'business' => ['name' => 'Business workspace', 'about' => 'Enquiries to your companies and what happens to their pages: verification, reviews, ownership. Enquiries are emailed as they arrive unless you choose otherwise.', 'popup_locked' => false, 'modes' => [self::INSTANT, self::DIGEST, self::OFF], 'default' => self::INSTANT],
        'bookings' => ['name' => 'Bookings and appointments', 'about' => 'Requests, confirmations, moves, cancellations and reminders for appointments you make or your business takes.', 'popup_locked' => false, 'modes' => [self::INSTANT, self::DIGEST, self::OFF], 'default' => self::INSTANT],
        'knowledge' => ['name' => 'Questions and solutions', 'about' => 'Answers to your questions, your answers accepted, mentions, improvements and recommendations.', 'popup_locked' => false, 'modes' => [self::DIGEST, self::OFF], 'default' => self::DIGEST],
        'seminars' => ['name' => 'Seminars', 'about' => 'Seminars you booked, paid for or host: changes, cancellations, refunds, reminders.', 'popup_locked' => false, 'modes' => [self::DIGEST, self::OFF], 'default' => self::DIGEST],
        'platforms' => ['name' => 'Platforms', 'about' => 'A platform switched on, off, granted or restored for your company.', 'popup_locked' => false, 'modes' => [self::DIGEST, self::OFF], 'default' => self::DIGEST],
    ];

    /**
     * The categories and what each offers (the table above, read as plain data).
     *
     * @return array<string, array{name: string, about: string, popup_locked: bool, modes: list<string>, default: string}>
     */
    public static function categories(): array
    {
        return self::CATEGORIES;
    }

    /** Words for the email choices. */
    public const MODE_WORDS = [
        self::INSTANT => 'Email me as it happens',
        self::DIGEST => 'One daily summary',
        self::OFF => 'No email',
    ];

    /**
     * The person's choices for every category, defaults filled in.
     *
     * @return array<string, array{popup: bool, email: string, locked: bool}>
     */
    public function for(User $user): array
    {
        $stored = NotificationPreference::where('user_id', $user->getKey())->get()->keyBy('category');
        $out = [];

        foreach (self::categories() as $key => $meta) {
            $row = $stored->get($key);
            $mode = $row instanceof NotificationPreference ? $row->email : null;

            $out[$key] = [
                'popup' => $meta['popup_locked'] ? true : ($row instanceof NotificationPreference ? $row->popup : true),
                'email' => $meta['modes'] === [] ? $meta['default'] : (in_array($mode, $meta['modes'], true) ? $mode : $meta['default']),
                'locked' => $meta['modes'] === [] && $meta['popup_locked'],
            ];
        }

        return $out;
    }

    /**
     * Saves the choices from the settings form. Only choices the category offers are taken; anything else (a locked category, an unknown mode) is ignored.
     * `knowledge.in_app` is the older switch that stops contribution notices being recorded at all, kept in one place.
     *
     * @param  array<string, mixed>  $input
     */
    public function save(User $user, array $input): void
    {
        foreach (self::categories() as $key => $meta) {
            if ($meta['modes'] === [] && $meta['popup_locked']) {
                continue;
            }

            $row = (array) ($input[$key] ?? []);
            $values = [];

            if (! $meta['popup_locked']) {
                $values['popup'] = filter_var($row['popup'] ?? false, FILTER_VALIDATE_BOOLEAN);
            }
            if ($meta['modes'] !== []) {
                $mode = (string) ($row['email'] ?? '');
                $values['email'] = in_array($mode, $meta['modes'], true) ? $mode : $meta['default'];
            }

            NotificationPreference::updateOrCreate(['user_id' => $user->getKey(), 'category' => $key], $values);
        }

        if (array_key_exists('knowledge_in_app', $input)) {
            User::whereKey($user->getKey())->update(['notices_in_app' => filter_var($input['knowledge_in_app'], FILTER_VALIDATE_BOOLEAN)]);
        }
    }

    /** Whether email for this kind of notice goes as it happens (the person's choice for its category). */
    public function instantEmail(User $user, string $kind): bool
    {
        $category = UserNotice::categoryOf($kind);

        return ($this->for($user)[$category]['email'] ?? self::OFF) === self::INSTANT;
    }

    /**
     * The kinds whose new notices should not pop up for this person.
     *
     * @return list<string>
     */
    public function mutedPopupKinds(User $user): array
    {
        $muted = array_keys(array_filter($this->for($user), fn (array $p, string $key) => ! $p['popup'] && ! self::categories()[$key]['popup_locked'], ARRAY_FILTER_USE_BOTH));

        return array_values(array_filter(UserNotice::KINDS, fn (string $kind) => in_array(UserNotice::categoryOf($kind), $muted, true)));
    }

    /**
     * The categories this person gets in the daily summary.
     *
     * @return list<string>
     */
    public function digestCategories(User $user): array
    {
        return array_keys(array_filter($this->for($user), fn (array $p) => $p['email'] === self::DIGEST));
    }

    /** Turns every summary off: the categories that are set to a summary become "no email"; categories with email as it happens are left alone. */
    public function turnSummariesOff(User $user): void
    {
        foreach ($this->digestCategories($user) as $key) {
            NotificationPreference::updateOrCreate(['user_id' => $user->getKey(), 'category' => $key], ['email' => self::OFF]);
        }
    }
}
