# Seminar meeting links that do not fail (2026-10-29)

A typed meeting link is the commonest reason a seminar cannot be joined: a mistyped address, a made-up code, a link that needs a login. What changed:

1. **The link is checked before it is accepted.** An https link with a real-looking address; if it claims to be Google Meet, Zoom, Teams, Whereby or Jitsi its path must have that service's shape (a made-up Meet code is refused with "it should look like https://meet.google.com/abc-defg-hij"); the address must exist (DNS); the page must answer. **Refused:** not https, an IP address or `localhost`, a custom port, credentials in the link, a service pattern that is wrong, an address that does not exist, a private address, a page that answers 404 or 410. **Allowed with a note:** the service blocks automated checks (401/403/429), answers with a server error, or cannot be reached from InfoDot. The check follows up to three redirects, re-checks each address, never contacts a private address (so it cannot be pointed at InfoDot's own network), pins the request to the address it checked, and remembers an answer for ten minutes so the button cannot be used to hammer a site.
2. **"Test this link" on the form** shows the same answer before the host publishes.
3. **A short InfoDot link for every seminar**, `/j/<8 letters and digits>`, shown to the host as "Share this seminar". It always opens: it goes to the seminar page, asking people to sign in first. The host shares that instead of the meeting link, which stays private to the people who registered.
4. **"The link did not open"** for registered people once the seminar has opened. The host gets a notice (once every half hour), sees a red count on their seminar page, and the count clears when they change the link.

**What this cannot do.** It cannot prove that a Google Meet code someone made up in the right shape is real, or that a Zoom meeting is still running: those services answer the same for any well-formed code. The report button and the host's own test join cover that. The most reliable fix is the InfoDot room (large) or the small room (about a handful of people up to the host's connection), which never needs a typed link.

Also fixed in this change: when small rooms were switched on, the seminar form posted a zero upload speed for ordinary link seminars and refused to publish them. (Small rooms are off in production, so no one hit it.)

Settings: `SEMINARS_LINK_CHECK` (default on; the test suite turns it off), `config/seminars.php` `link_check`.
