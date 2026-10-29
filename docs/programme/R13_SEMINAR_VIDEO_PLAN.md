# R-13 seminar video: our own room (plan, 2026-10-29)

Owner decision: build our own video provider for seminars. **Nothing here is built and no live video is claimed anywhere in InfoDot.** Seminars today are free, registered, reminded and rated, and the host supplies their own meeting link.

## What "our own" can honestly mean

- **Not from scratch.** Writing a WebRTC media router (SFU) is months of specialised, security-sensitive work. The sensible reading of "our own" is **our own server running open-source media software**, with the experience, accounts, registration, chat, recording consent and data all ours and no third party in the path.
- **Not on the current host.** The shared cPanel host cannot run long-lived media processes, open UDP port ranges or a TURN relay. A separate small server is required.

## Proposed shape

1. A server the owner provisions (a VPS with a public IP, UDP ports open, a subdomain such as `live.infodot.co.za`, TLS). Software: LiveKit (single Go binary, built-in TURN) or Janus; LiveKit is recommended for its token model and browser SDK.
2. InfoDot mints a short-lived, single-use join token (signed JWT, room = seminar id, identity = registered person, publish rights only for the host) when `seminars.join` is opened inside the window. The room link is never in a page; the token is never in a URL that is stored.
3. The seminar page embeds the room (browser SDK) for people who registered; the host's meeting-link field becomes optional ("use InfoDot room" or "use my own link").
4. Attendance comes from the media server's participant-joined webhook (signed), replacing the click-through proxy.
5. Chat and Q and A through the same SDK data channel, kept for the seminar only; recording off unless the host starts it and everyone is told on screen first.

## What the owner has to decide or provide

- The server: provider, region and budget. South African data-residency matters for POPIA section 72; a host in South Africa avoids a cross-border transfer for the media itself.
- Bandwidth plan: a 100-person seminar at 720p is on the order of 100 Mbit/s outbound for the SFU; capacity per seminar must be a configured cap.
- Recording: whether it exists at all, retention, and consent wording.
- Whether paid seminars (Dot.Billing) come before or after video.

## Acceptance before it can be called live video

Two people on different networks (one on mobile data) see and hear the host; a registered person without a token is refused; the token expires; the host can remove a person; a load test at the configured cap holds; the privacy policy names the media server as an operator.
