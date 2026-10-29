# Seminar video: setting up your LiveKit server (2026-10-29)

InfoDot's side is built and OFF (`SEMINARS_VIDEO_ENABLED`). It talks to a **LiveKit server you run** (open source, self-hosted); nothing about it can run on the shared cPanel host. **Written from LiveKit's documented behaviour; none of it has been run against a real server.** Treat the settings below as a starting point and check them against LiveKit's current documentation. Video is not "live" until the acceptance test at the end passes.

## What you need

- A small Linux server (a VPS) with a public IPv4 address, root access, and the ports below open. South African data-residency (POPIA section 72) is easier with a South African region.
- A subdomain pointing at it, for example `live.infodot.co.za`.
- Docker (or the LiveKit release binary) and a reverse proxy that gives you TLS (Caddy does it automatically).

## Ports to open on the server's firewall

| Port | Protocol | What for |
|---|---|---|
| 443 | TCP | the browser connects here (`wss://`), through the proxy to LiveKit's 7880 |
| 7881 | TCP | media over TCP when UDP is blocked |
| 3478 | UDP | TURN relay |
| 5349 | TCP | TURN over TLS (for the most locked-down networks) |
| 50000-60000 | UDP | media (narrow the range to match `rtc.port_range_*`) |

## Keys

On the server, generate a key pair (this prints the secret once; keep it out of chat, tickets and repositories):

    docker run --rm livekit/livekit-server generate-keys

## `livekit.yaml` (starting point)

    port: 7880
    rtc:
      tcp_port: 7881
      port_range_start: 50000
      port_range_end: 60000
      use_external_ip: true
    turn:
      enabled: true
      domain: live.infodot.co.za
      tls_port: 5349
      udp_port: 3478
      external_tls: true
    keys:
      <the API key>: <the API secret>
    webhook:
      api_key: <the API key>
      urls:
        - https://infodot.co.za/api/seminars/livekit-webhook
    room:
      max_participants: 100

`webhook` is how InfoDot learns who really connected (that is what marks attendance). `max_participants` is the cap per room; size the server for it: a 100-person seminar at 720p is on the order of 100 Mbit/s outbound.

## Proxy (Caddy) for `wss://`

    live.infodot.co.za {
      reverse_proxy localhost:7880
    }

## Run it

    docker run -d --name livekit --restart unless-stopped --network host -v $PWD/livekit.yaml:/livekit.yaml livekit/livekit-server --config /livekit.yaml

## Switching InfoDot on

On the InfoDot host, after the server is up, with the key pair in your shell (so it never appears in a command line or history):

    read -rs LIVEKIT_API_KEY; export LIVEKIT_API_KEY      # paste the key, Enter
    read -rs LIVEKIT_API_SECRET; export LIVEKIT_API_SECRET   # paste the secret, Enter
    tools/r13-enable.sh --infodot <the InfoDot folder> --stage video --livekit-url wss://live.infodot.co.za --apply --cache

## Acceptance test before anyone is told it is live

1. Two people on **different networks**, one on **mobile data**, enter a seminar room; both see and hear the host.
2. A person who did not register is refused the page and the token.
3. The token stops working after the seminar ends.
4. The host can remove a person and the person is disconnected.
5. After the test seminar, the two attendees show as attended without anyone clicking anything (this proves the webhook).
6. A load test at the configured cap holds.
7. The privacy policy names the media server host as an operator (POPIA section 21), and no recording is on.

## What InfoDot does and does not do

- Attendees watch and chat; only the host sends audio, video and screen. Chat uses the room's data channel and is not stored.
- No recording. If you ever add one, everyone must be told on screen first and the policy must say where it is kept.
- Tokens are short-lived, are fetched when someone connects, are never put in the page, and are never logged.
