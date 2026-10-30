<?php

return [
    /*
    | Strict-Transport-Security max-age in seconds, sent on HTTPS responses only. 0 turns it off. Subdomains are deliberately not included:
    | the other Dot platforms set their own. Start with the default and raise it once the site has been stable on HTTPS.
    */
    'hsts_seconds' => (int) env('SECURITY_HSTS_SECONDS', 15552000),   // 180 days
];
