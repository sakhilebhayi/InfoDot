# Small-room end-to-end test (real Chrome, fake camera)

Runs a host and three viewers in separate Chrome profiles against a local InfoDot and checks that real video travels host -> V1 -> V2 -> V3, that chat reaches everyone, that a relay leaving re-feeds the people below it, that the host's form shows the capacity, and that removal works. Local only; it needs a seeded local database (users hana/v1/v2/v3@example.test, a published small-room seminar) and the server started with `SEMINARS_P2P_ENABLED=true SEMINARS_P2P_RELAY_SLOTS=1 PHP_CLI_SERVER_WORKERS=8 APP_DEBUG=true`.

    npm install puppeteer-core          # in a scratch folder, not in the project
    node run.mjs <seminar id>           # the media chain
    node run2.mjs <seminar id>          # the host's form, the number shown, publishing, removal
    node run3.mjs <audio seminar id>    # audio-only room

The scripts read the Chrome at `/Applications/Google Chrome.app`; change the path on another machine. Passwords are the local-only test value used by the project's local seed script, not a real credential.
