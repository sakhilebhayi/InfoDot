<?php

return [
    /*
    | Strict-Transport-Security max-age in seconds, sent on HTTPS responses only. 0 turns it off. Subdomains are deliberately not included:
    | the other Dot platforms set their own. Start with the default and raise it once the site has been stable on HTTPS.
    */
    /*
    | Content-Security-Policy in REPORT-ONLY mode: the browser reports what the policy would have blocked (to /csp-report) and blocks nothing.
    | The pages use inline scripts and Alpine/Livewire, so the policy allows 'unsafe-inline' and 'unsafe-eval' for now; the reports show which
    | outside hosts are really used, and the next step is a nonce-based policy that is enforced. Set false to send no header.
    */
    'csp_report_only' => (bool) env('SECURITY_CSP_REPORT_ONLY', true),

    'hsts_seconds' => (int) env('SECURITY_HSTS_SECONDS', 15552000),   // 180 days
];
