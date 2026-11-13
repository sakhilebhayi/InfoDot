<?php

/*
|--------------------------------------------------------------------------
| Platform subscriptions (R-50)
|--------------------------------------------------------------------------
| NOTHING HERE HOLDS A PRICE. Plans and their amounts are rows that a platform administrator creates and approves
| at /admin/platforms; the application ships with none.
|
| A paid plan becomes active only when Dot.Finance posts a signed confirmation to
| POST /api/finance/platform-subscription-events. While the secret below is unset that endpoint refuses everything,
| so nothing can be marked paid. There is no test or simulation switch.
*/

return [
    // The shared secret Dot.Finance signs its confirmations with. Its own key: not the seminar callback secret.
    'confirmation_secret' => env('PLATFORM_SUBSCRIPTION_CALLBACK_SECRET'),

    // How old a signed request may be, in seconds.
    'signature_window' => 300,

    // After a failed payment the company keeps the platform for this many days, unless Dot.Finance names its own date.
    'grace_days' => (int) env('PLATFORM_PAYMENT_GRACE_DAYS', 7),

    // The longest grace Dot.Finance may name, and the longest trial an administrator may give by hand, in days.
    'max_grace_days' => 30,
    'max_trial_days' => 90,
];
