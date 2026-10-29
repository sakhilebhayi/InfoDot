// A small seminar room with no media server. The host's browser sends audio and video straight to the browsers of the people watching.
// Viewers who can (a computer, not on mobile data) pass the stream on to a few others, so the room holds more people than the host's own
// upload could feed. The InfoDot server only says who feeds whom, passes the small connection messages and carries the chat. Nothing is recorded.
const root = document.getElementById('p2p-room')

if (root) {
    const cfg = { ...root.dataset }
    const isHost = cfg.host === '1'
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content
    const $ = (sel) => root.querySelector(sel)
    const video = $('[data-video]')
    const statusEl = $('[data-status]')
    const noteEl = $('[data-note]')
    const countEl = $('[data-count]')
    const chatLog = $('[data-chat-log]')
    const chatForm = $('[data-chat-form]')
    const chatInput = $('[data-chat-input]')
    const params = new URLSearchParams(location.search)
    const useFake = cfg.allowFake === '1' && params.get('fake') === '1'

    let me = null                         // { peer_id, quality, ice, ... } from the server
    let parent = null                     // peer id feeding me
    let upstream = null                   // RTCPeerConnection from my parent
    let remote = { audio: null, video: null }   // the tracks I receive (viewer) or send (host)
    let local = { audio: null, video: null }
    const downstream = new Map()          // child peer id -> { pc }
    const pendingIce = new Map()          // peer id -> [candidate JSON] waiting for a remote description
    let lastChat = 0
    let stopped = false
    let ready = false
    let pollTimer = null
    let upstreamBadSince = null
    let helloAt = 0
    const qualityKbps = Number(cfg.kbps || 0)
    let maxBitrate = qualityKbps * 1000

    const say = (t) => { if (statusEl) statusEl.textContent = t }
    const note = (t) => { if (noteEl) noteEl.textContent = t }
    const canRelay = () => {
        const c = navigator.connection || {}
        const mobile = /Mobi|Android|iPhone|iPad/i.test(navigator.userAgent)
        return !isHost && !mobile && !c.saveData && c.type !== 'cellular' && (navigator.hardwareConcurrency || 2) >= 4
    }
    const api = async (method, url, body) => {
        const res = await fetch(url, { method, headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json', ...(body ? { 'Content-Type': 'application/json' } : {}) }, body: body ? JSON.stringify(body) : undefined, credentials: 'same-origin' })
        const json = await res.json().catch(() => ({}))
        if (!res.ok) throw Object.assign(new Error('http'), { status: res.status, json })
        return json
    }
    const signal = (to, type, payload) => api('POST', cfg.signalUrl, { to, type, payload: payload ? JSON.stringify(payload) : null }).catch(() => {})
    const newPc = () => new RTCPeerConnection({ iceServers: me.ice })

    // ---- host: where the media comes from ----
    const fakeSource = () => {
        const canvas = Object.assign(document.createElement('canvas'), { width: 320, height: 180 })
        const g = canvas.getContext('2d')
        let n = 0
        setInterval(() => { n++; g.fillStyle = '#123'; g.fillRect(0, 0, 320, 180); g.fillStyle = '#fc0'; g.fillRect((n * 4) % 280, 70, 40, 40); g.fillStyle = '#fff'; g.font = '20px sans-serif'; g.fillText('test source ' + n, 10, 30) }, 66)
        const ctx = new AudioContext(); const dest = ctx.createMediaStreamDestination(); const osc = ctx.createOscillator(); osc.connect(dest); osc.start()
        return { video: canvas.captureStream(15).getVideoTracks()[0], audio: dest.stream.getAudioTracks()[0] }
    }
    const startHostMedia = async (wantVideo) => {
        if (useFake) { const f = fakeSource(); local = f; if (cfg.quality === 'audio') local.video = null; return }
        const constraints = { audio: { echoCancellation: true, noiseSuppression: true }, video: wantVideo ? (cfg.quality === 'medium' ? { width: 1280, height: 720, frameRate: 24 } : { width: 640, height: 360, frameRate: 24 }) : false }
        const stream = await navigator.mediaDevices.getUserMedia(constraints)
        local = { audio: stream.getAudioTracks()[0] || null, video: stream.getVideoTracks()[0] || null }
    }
    const showLocal = () => { const s = new MediaStream([local.audio, local.video].filter(Boolean)); video.srcObject = s; video.muted = true }
    const outTracks = () => (isHost ? local : remote)

    // ---- passing the stream on to one child (host or relay) ----
    const capBitrate = async (pc) => {
        if (!maxBitrate) return
        for (const s of pc.getSenders()) {
            if (s.track?.kind !== 'video' && s.__kind !== 'video') continue
            const p = s.getParameters(); p.encodings = p.encodings?.length ? p.encodings : [{}]; p.encodings[0].maxBitrate = maxBitrate
            await s.setParameters(p).catch(() => {})
        }
    }
    const serveChild = async (childId) => {
        const existing = downstream.get(childId)
        // A repeated hello while the first handshake is still under way must not restart it, or the two sides chase each other forever.
        if (existing && existing.pc.connectionState !== 'failed' && existing.pc.connectionState !== 'closed' && Date.now() - existing.startedAt < 12000) return
        if (existing) { try { existing.pc.close() } catch (e) { /* replaced */ } }
        const pc = newPc()
        downstream.set(childId, { pc, startedAt: Date.now() })
        const tracks = outTracks()
        for (const kind of ['audio', 'video']) {
            const t = pc.addTransceiver(kind, { direction: 'sendonly' })
            t.sender.__kind = kind
            if (tracks[kind]) await t.sender.replaceTrack(tracks[kind])
        }
        await capBitrate(pc)
        pc.onicecandidate = (e) => { if (e.candidate) signal(childId, 'ice', e.candidate) }
        pc.onconnectionstatechange = () => { if (['failed', 'closed'].includes(pc.connectionState)) { downstream.delete(childId); try { pc.close() } catch (e) { /* gone */ } } }
        setTimeout(() => { if (downstream.get(childId)?.pc === pc && pc.connectionState !== 'connected') { downstream.delete(childId); try { pc.close() } catch (e) { /* gone */ } } }, 20000)
        await pc.setLocalDescription(await pc.createOffer())
        await signal(childId, 'offer', { sdp: pc.localDescription.sdp, type: pc.localDescription.type })
    }

    // ---- receiving from my parent (viewer) ----
    const markReady = async () => {
        if (ready) return
        ready = true
        await api('POST', cfg.readyUrl).catch(() => {})
        say('You are watching.')
        scheduleNext()
    }
    // Only a viewer whose video is really arriving can pass it on: both tracks present and the connection up.
    const tryReady = () => { if (upstream && upstream.connectionState === 'connected' && remote.audio && remote.video) markReady() }
    const connectUpstream = async (offer, from) => {
        closeUpstream()
        const pc = newPc()
        upstream = pc
        pc.ontrack = (e) => {
            remote[e.track.kind] = e.track
            video.srcObject = new MediaStream([remote.audio, remote.video].filter(Boolean))
            video.play().catch(() => { note('Click the video to start the sound.') })
            for (const { pc: dpc } of downstream.values()) for (const s of dpc.getSenders()) if (s.__kind === e.track.kind) s.replaceTrack(e.track).catch(() => {})
            tryReady()
        }
        pc.onicecandidate = (e) => { if (e.candidate) signal(from, 'ice', e.candidate) }
        // A feeder that never connects is replaced instead of leaving the viewer staring at nothing.
        setTimeout(() => { if (pc === upstream && pc.connectionState !== 'connected') feederFailed() }, 15000)
        pc.onconnectionstatechange = () => {
            if (pc !== upstream) return
            if (pc.connectionState === 'connected') { upstreamBadSince = null; tryReady() }
            if (['disconnected', 'failed'].includes(pc.connectionState)) upstreamBadSince = upstreamBadSince || Date.now()
            if (pc.connectionState === 'failed') feederFailed()
        }
        await pc.setRemoteDescription(offer)
        for (const c of pendingIce.get(from) || []) await pc.addIceCandidate(c).catch(() => {})
        pendingIce.delete(from)
        await pc.setLocalDescription(await pc.createAnswer())
        await signal(from, 'answer', { sdp: pc.localDescription.sdp, type: pc.localDescription.type })
    }
    const closeUpstream = () => { if (upstream) { try { upstream.close() } catch (e) { /* gone */ } upstream = null }; ready = false }
    const feederFailed = async () => {
        if (stopped || isHost) return
        say('The connection to the feed dropped. Finding another…')
        closeUpstream()
        try { const r = await api('POST', cfg.reportUrl); setParent(r.parent) } catch (e) { /* the next poll tries again */ }
    }
    const setParent = (id) => {
        if (id === parent) return
        parent = id
        closeUpstream()
        if (parent) { say('Connecting…'); helloAt = Date.now(); signal(parent, 'hello') } else say('Waiting for a free place in the room…')
    }

    // ---- messages from the server ----
    const handle = async (m) => {
        const payload = m.payload ? JSON.parse(m.payload) : null
        try {
            if (m.type === 'hello') await serveChild(m.from)
            else if (m.type === 'offer' && m.from === parent) await connectUpstream(payload, m.from)
            else if (m.type === 'answer') { const d = downstream.get(m.from); if (d) { await d.pc.setRemoteDescription(payload); for (const c of pendingIce.get(m.from) || []) await d.pc.addIceCandidate(c).catch(() => {}); pendingIce.delete(m.from) } }
            else if (m.type === 'ice') {
                const target = m.from === parent ? upstream : downstream.get(m.from)?.pc
                if (target?.remoteDescription) await target.addIceCandidate(payload).catch(() => {})
                else pendingIce.set(m.from, [...(pendingIce.get(m.from) || []), payload])
            }
        } catch (e) { /* a bad message from one peer never stops the room */ }
    }
    const addChat = (name, text) => {
        const li = document.createElement('li'); const b = document.createElement('b'); b.textContent = name + ': '
        li.append(b, document.createTextNode(text)); chatLog.append(li); chatLog.scrollTop = chatLog.scrollHeight
    }
    const poll = async () => {
        if (stopped) return
        try {
            const r = await api('POST', cfg.pollUrl + '?chat=' + lastChat)
            if (r.removed) { stopped = true; say('The host removed you from this room.'); closeUpstream(); return }
            if (countEl) countEl.textContent = String(r.viewers + 1)
            for (const c of r.chat) { lastChat = Math.max(lastChat, c.id); addChat(c.name, c.body) }
            if (!isHost) {
                if (r.parent !== parent) setParent(r.parent)
                else if (parent && !ready && !upstream && Date.now() - helloAt > 8000) { helloAt = Date.now(); signal(parent, 'hello') }
                if (!r.host_here && !parent) say('Waiting for the host to start…')
                if (upstreamBadSince && Date.now() - upstreamBadSince > 6000) { upstreamBadSince = null; await feederFailed() }
            }
            for (const m of r.signals) await handle(m)
        } catch (e) {
            if (e.status === 403) { stopped = true; say('You can no longer be in this room.'); return }
        }
        scheduleNext()
    }
    const scheduleNext = () => {
        clearTimeout(pollTimer)
        if (stopped) return
        const fast = isHost || downstream.size > 0 || !ready || canRelay()
        pollTimer = setTimeout(poll, fast ? me.poll_fast : me.poll_slow)
    }

    // ---- the host watches their own connection and lowers quality before it breaks ----
    let lastBytes = 0; let lastAt = 0; let over = 0
    const watchUplink = async () => {
        if (!isHost || stopped) return
        let bytes = 0
        for (const { pc } of downstream.values()) { const stats = await pc.getStats().catch(() => null); stats?.forEach((s) => { if (s.type === 'outbound-rtp') bytes += s.bytesSent || 0 }) }
        const now = performance.now()
        if (lastAt) {
            const kbps = ((bytes - lastBytes) * 8) / ((now - lastAt) / 1000) / 1000
            const cap = Number(cfg.uplinkKbps) * 0.9
            over = kbps > cap ? over + 1 : 0
            if (over >= 2 && maxBitrate > 150000) {
                maxBitrate = Math.max(150000, Math.round(maxBitrate * 0.75)); over = 0
                for (const { pc } of downstream.values()) await capBitrate(pc)
                note('Your connection is struggling, so video quality was lowered to keep everyone connected.')
            } else if (over >= 2 && local.video) {
                for (const { pc } of downstream.values()) for (const s of pc.getSenders()) if (s.__kind === 'video') s.replaceTrack(null).catch(() => {})
                local.video = null; over = 0; note('Your connection could not keep up with video, so the room is audio only now.')
            }
        }
        lastBytes = bytes; lastAt = now
        setTimeout(watchUplink, 5000)
    }

    // ---- start ----
    const start = async () => {
        say('Connecting…')
        try {
            if (isHost) {
                try { await startHostMedia(cfg.quality !== 'audio') } catch (e) { say('Your browser did not allow the camera or microphone. Allow it and reload the page.'); return }
                showLocal()
            }
            me = await api('POST', cfg.joinUrl, { can_relay: canRelay() })
            if (isHost) { say('You are live. People who registered can join now.'); watchUplink() } else setParent(me.parent)
            scheduleNext()
        } catch (e) {
            say(e.json?.errors?.room?.[0] || e.json?.message || 'You cannot enter the room right now.')
        }
    }

    $('[data-toggle="mic"]')?.addEventListener('click', (ev) => { const on = ev.currentTarget.getAttribute('aria-pressed') !== 'true'; if (local.audio) local.audio.enabled = on; ev.currentTarget.setAttribute('aria-pressed', on ? 'true' : 'false') })
    $('[data-toggle="cam"]')?.addEventListener('click', (ev) => { const on = ev.currentTarget.getAttribute('aria-pressed') !== 'true'; if (local.video) local.video.enabled = on; ev.currentTarget.setAttribute('aria-pressed', on ? 'true' : 'false') })
    $('[data-toggle="screen"]')?.addEventListener('click', async (ev) => {
        try {
            const d = await navigator.mediaDevices.getDisplayMedia({ video: true }); const t = d.getVideoTracks()[0]
            local.video = t; for (const { pc } of downstream.values()) for (const s of pc.getSenders()) if (s.__kind === 'video') s.replaceTrack(t).catch(() => {})
            showLocal(); ev.currentTarget.setAttribute('aria-pressed', 'true'); t.onended = () => ev.currentTarget.setAttribute('aria-pressed', 'false')
        } catch (e) { note('Screen sharing was not started.') }
    })
    video?.addEventListener('click', () => { video.muted = false; video.play().catch(() => {}) })
    $('[data-unmute]')?.addEventListener('click', () => { video.muted = false; video.play().catch(() => {}); note('') })
    chatForm?.addEventListener('submit', async (ev) => { ev.preventDefault(); const text = chatInput.value.trim(); if (!text) return; chatInput.value = ''; await api('POST', cfg.chatUrl, { text }).catch(() => {}) })
    $('[data-leave]')?.addEventListener('click', () => { stopped = true; closeUpstream(); downstream.forEach(({ pc }) => pc.close()); api('POST', cfg.leaveUrl).catch(() => {}); say('You left the room.') })
    window.addEventListener('beforeunload', () => { const f = new FormData(); f.append('_token', csrf); navigator.sendBeacon(cfg.leaveUrl, f) })

    if (cfg.allowFake === '1') window.__p2p = { upstream: () => upstream, downstream, state: () => ({ me, parent, ready, stopped }) }   // for local debugging only (APP_DEBUG)

    start()
}
