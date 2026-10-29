# Small seminar rooms: video between browsers, no media server (2026-10-29)

**Built, off by default (`SEMINARS_P2P_ENABLED`). Proven end to end on one computer; not yet proven across real networks.** Read "What was and was not verified" before telling anyone it works.

## What it is

The host's browser sends audio and video straight to the browsers of the people watching. InfoDot's server carries no video: it decides who feeds whom, passes the small connection messages, and carries the chat. It runs on the existing shared host.

## How many people it can hold, and why the host is told

The host's upload speed is the limit, and so is their computer (a browser encodes once per viewer). When setting a seminar up the host presses **Test my connection**: the browser uploads two small files to InfoDot and times them. The form then shows, for each quality, **the number of people the seminar can have**, for example "up to 28 people", and that number becomes the seminar's place limit (the host can only lower it). The same number is shown on the seminar page, the seminars list and inside the room. Anyone beyond it joins the waiting list.

To hold more people than the host can feed directly, viewers who are able (a computer, not on mobile data, 4 or more processor cores) **pass the stream on to up to 3 others**, up to 4 hops below the host. The promised number is 60% of what that model says is possible, because real viewers drop out, sit behind strict networks, and use phones.

| Upload | Quality | Fed by the host directly | Promised capacity |
|---|---|---|---|
| 5 Mbps | Audio only | 30 | 200 (the configured ceiling) |
| 5 Mbps | Standard video (360p) | 6 | 28 |
| 5 Mbps | Sharp video (720p) | 2 | 1 |
| 2 Mbps | Sharp video (720p) | 1 | 1 |
| 0.3 Mbps | Standard video | 0: too slow, the form says so | none |

Every number is configuration (`config/seminars.php`, `seminars.p2p`). They are starting assumptions, not measurements.

## Making it hold up

- A viewer whose feeder leaves, or never connects within 15 seconds, is given another at the next poll.
- The host's browser watches its own outgoing traffic every 5 seconds. If it runs over 90% of the measured upload twice in a row it lowers the video bitrate by 25% (down to 150 kbps), and as a last step drops to audio only, and tells the host.
- A viewer can only pass the stream on once their own video is really flowing.
- Polling is slow (every 8 seconds) for viewers who are not feeding anyone and fast (1.5 seconds) for the host and for viewers who feed others. **Server load:** roughly 40 requests a minute for the host and for each relaying viewer, 8 for the rest. A 100-person seminar is about 15 requests a second against a shared host. Hosting limits can bite before the video does.

## Privacy and who sees what

- Browsers find each other through STUN servers: they learn their own public address and nothing else, and no video passes through them. The default is **Google's public STUN server**, a third party. To depend on nobody, run your own (coturn) and set `P2P_STUN_URLS`. Say which one you use in the privacy policy.
- Peers connect directly, so **a viewer can see the IP address of whoever feeds them**, and the host can see the addresses of the people they feed. Say so in the privacy policy and in the room (the room page does not yet carry that sentence).
- Chat is stored on InfoDot for the seminar; it is not otherwise used.
- No recording.
- A relaying viewer re-encodes and re-sends the video; they use their own bandwidth and processor while the room is open.

## What was and was not verified

**Automated tests (24, plus the whole suite):** the capacity maths; the form's rules; who may enter and when; the tree (host feeds first, relays feed next, nobody is their own feeder, depth recorded); reassignment when a feeder leaves, goes silent, or is reported failing; the room-full limit; message delivery and its checks; removal; chat; attendance marking; the numbers shown on every page; and that nothing that changes state can be done with a plain link.

**Real browser, one computer (`tools/p2p-e2e/`):** a host and three viewers, each in its own Chrome profile with a fake camera and microphone, on a local InfoDot. Verified: real 1280 x 720 video reaching all three viewers through a chain **host -> V1 -> V2 -> V3** (V1 and V2 each relaying to one), video still flowing three hops away; chat from one viewer reaching everyone; V1 leaving and V2 being given a new feeder while V3 kept receiving video through V2; the host's form measuring an upload speed and showing the number of people for each quality; the published seminar page stating the room size; the host removing a viewer, who is told and disconnected; an audio-only room (audio flowing, no camera control offered). This run found and fixed a real bug: a viewer re-sending its "hello" made the host restart the connection each time, so media never connected.

**Not verified:**
- People on **different networks**, behind home routers, or on **mobile data**. Everything above ran on one computer's local network, where the browsers can reach each other directly. Many real connections will not do that without a relay (TURN). Expect some failures until `P2P_TURN_*` is set up.
- **More than four** peers, or any load test. The relay tree is proven with a chain, not with a wide tree.
- Real cameras and microphones, Safari, Firefox, and phones.
- A real seminar with real people.

Before real use:

1. Two people on **different networks**, one on **mobile data**: both see and hear the host.
2. A 10-person test over a week-night connection, with a mix of computers and phones.
3. The host drops their connection and returns.
4. A person who did not register cannot enter.

## Turning it on

    tools/r13-enable.sh --infodot <the InfoDot folder> --stage p2p --apply --cache

Optional: `--stun-urls "stun:your.server:3478"`. Nothing else needs setting up; no server, no ports.
