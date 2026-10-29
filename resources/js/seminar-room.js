// The seminar room: connects the browser to the LiveKit server with a short-lived token InfoDot hands out after checking the person registered.
// The host sends camera, microphone and screen; everyone else watches and chats. Nothing is recorded here.
import { Room, RoomEvent, Track, ConnectionState } from 'livekit-client'

const root = document.getElementById('seminar-room')

if (root) {
    const tokenUrl = root.dataset.tokenUrl
    const isHost = root.dataset.host === '1'
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content
    const stage = root.querySelector('[data-stage]')
    const statusEl = root.querySelector('[data-status]')
    const countEl = root.querySelector('[data-count]')
    const chatLog = root.querySelector('[data-chat-log]')
    const chatForm = root.querySelector('[data-chat-form]')
    const chatInput = root.querySelector('[data-chat-input]')
    const encoder = new TextEncoder()
    const decoder = new TextDecoder()
    const room = new Room({ adaptiveStream: true, dynacast: true })
    const say = (text) => { if (statusEl) statusEl.textContent = text }
    const showChat = (who, text) => {
        const li = document.createElement('li')
        const b = document.createElement('b')
        b.textContent = who + ': '
        li.append(b, document.createTextNode(text))
        chatLog.append(li)
        chatLog.scrollTop = chatLog.scrollHeight
    }
    const updateCount = () => { if (countEl) countEl.textContent = String(room.remoteParticipants.size + 1) }

    const attach = (track, participant) => {
        if (track.kind === Track.Kind.Video) {
            const el = track.attach()
            el.dataset.who = participant.identity
            el.setAttribute('playsinline', '')
            stage.append(el)
        } else if (track.kind === Track.Kind.Audio) {
            track.attach().dataset.who = participant.identity
        }
    }
    const detach = (track) => track.detach().forEach((el) => el.remove())

    room
        .on(RoomEvent.TrackSubscribed, (track, _pub, participant) => attach(track, participant))
        .on(RoomEvent.TrackUnsubscribed, (track) => detach(track))
        .on(RoomEvent.LocalTrackPublished, (pub, participant) => { if (pub.track) attach(pub.track, participant) })
        .on(RoomEvent.LocalTrackUnpublished, (pub) => { if (pub.track) detach(pub.track) })
        .on(RoomEvent.ParticipantConnected, updateCount)
        .on(RoomEvent.ParticipantDisconnected, updateCount)
        .on(RoomEvent.Reconnecting, () => say('Connection lost. Trying to reconnect…'))
        .on(RoomEvent.Reconnected, () => say('Back in the room.'))
        .on(RoomEvent.Disconnected, () => say('You have left the room. Reload the page to come back in.'))
        .on(RoomEvent.DataReceived, (payload, participant) => {
            try {
                const msg = JSON.parse(decoder.decode(payload))
                if (msg.t === 'chat' && typeof msg.text === 'string') showChat(participant?.name || 'Someone', msg.text.slice(0, 500))
            } catch (e) { /* ignore anything that is not our chat message */ }
        })

    const join = async () => {
        say('Connecting…')
        try {
            const res = await fetch(tokenUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' }, credentials: 'same-origin' })
            const body = await res.json()
            if (!res.ok) { say(body.error || 'You cannot enter the room right now.'); return }
            await room.connect(body.url, body.token)
            updateCount()
            say(isHost ? 'You are in the room. Turn on your camera or microphone below.' : 'You are in the room. The host will appear when they start.')
            root.querySelectorAll('[data-needs-room]').forEach((el) => { el.disabled = false })
        } catch (e) {
            say('Could not connect to the room. Check your connection and reload the page.')
        }
    }

    root.querySelectorAll('[data-toggle]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            if (room.state !== ConnectionState.Connected) return
            const kind = btn.dataset.toggle
            const on = btn.getAttribute('aria-pressed') !== 'true'
            try {
                if (kind === 'camera') await room.localParticipant.setCameraEnabled(on)
                if (kind === 'microphone') await room.localParticipant.setMicrophoneEnabled(on)
                if (kind === 'screen') await room.localParticipant.setScreenShareEnabled(on)
                btn.setAttribute('aria-pressed', on ? 'true' : 'false')
            } catch (e) {
                say('Your browser did not allow that. Check the permission prompt and try again.')
            }
        })
    })

    chatForm?.addEventListener('submit', async (ev) => {
        ev.preventDefault()
        const text = chatInput.value.trim().slice(0, 500)
        if (!text || room.state !== ConnectionState.Connected) return
        await room.localParticipant.publishData(encoder.encode(JSON.stringify({ t: 'chat', text })), { reliable: true })
        showChat('You', text)
        chatInput.value = ''
    })

    root.querySelector('[data-leave]')?.addEventListener('click', () => { room.disconnect(); say('You left the room.') })
    window.addEventListener('beforeunload', () => room.disconnect())

    join()
}
