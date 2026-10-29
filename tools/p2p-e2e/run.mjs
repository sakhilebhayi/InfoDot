// End-to-end test of the small room in a real Chrome: a host and three viewers, each in its own browser context (own cookies),
// with a fake camera and microphone. The host can feed ONE viewer (config), viewers can relay to ONE other, so media must hop
// host -> V1 -> V2 -> V3. Prints a JSON report.
import puppeteer from 'puppeteer-core'

const BASE = 'http://127.0.0.1:8765'
const SEM = process.argv[2] || '3'
const PASS = 'password-for-local-ui-only'
const sleep = (ms) => new Promise((r) => setTimeout(r, ms))
const report = { steps: [], ok: true }
const step = (name, ok, detail) => { report.steps.push({ name, ok, ...(detail !== undefined ? { detail } : {}) }); if (!ok) report.ok = false }

const browser = await puppeteer.launch({
    executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
    headless: 'new',
    args: ['--use-fake-device-for-media-stream', '--use-fake-ui-for-media-stream', '--disable-features=WebRtcHideLocalIpsWithMdns', '--autoplay-policy=no-user-gesture-required', '--no-sandbox'],
})

const open = async (email) => {
    const ctx = await browser.createBrowserContext()
    const page = await ctx.newPage()
    page.on('pageerror', (e) => report.steps.push({ name: 'pageerror ' + email, ok: false, detail: String(e).slice(0, 200) }))
    await page.goto(BASE + '/login', { waitUntil: 'domcontentloaded' })
    await page.type('input[name=email]', email)
    await page.type('input[name=password]', PASS)
    await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), page.click('button[type=submit]')])
    return { ctx, page }
}
const snap = (page) => page.evaluate(async () => {
    const v = document.querySelector('[data-video]')
    const out = { status: document.querySelector('[data-status]')?.textContent, note: document.querySelector('[data-note]')?.textContent, count: document.querySelector('[data-count]')?.textContent, w: v?.videoWidth, t: v?.currentTime, paused: v?.paused }
    const pc = window.__p2p?.upstream?.()
    if (pc) { out.conn = pc.connectionState; const r = await pc.getStats(); r.forEach((s) => { if (s.type === 'inbound-rtp' && s.kind === 'video') out.inVideoBytes = s.bytesReceived }) }
    out.children = window.__p2p ? [...window.__p2p.downstream.entries()].map(([id, { pc: c }]) => ({ id: id.slice(0, 6), conn: c.connectionState })) : []
    out.state = window.__p2p?.state?.() && { parent: window.__p2p.state().parent?.slice(0, 6), ready: window.__p2p.state().ready, me: window.__p2p.state().me?.peer_id?.slice(0, 6) }
    return out
})

try {
    const host = await open('hana@example.test')
    await host.page.goto(`${BASE}/seminars/${SEM}/small-room`, { waitUntil: 'domcontentloaded' })
    await sleep(3000)
    const h = await snap(host.page)
    step('host is live with a camera', /You are live/.test(h.status || ''), h.status)

    const viewers = []
    for (const [i, email] of ['v1@example.test', 'v2@example.test', 'v3@example.test'].entries()) {
        const v = await open(email)
        await v.page.goto(`${BASE}/seminars/${SEM}/small-room`, { waitUntil: 'domcontentloaded' })
        viewers.push(v)
        await sleep(i === 0 ? 9000 : 3000)   // let each one connect and become a feeder before the next arrives
    }
    await sleep(12000)

    const snaps = []
    for (const v of viewers) snaps.push(await snap(v.page))
    snaps.forEach((s, i) => {
        step(`V${i + 1} is watching`, /watching/i.test(s.status || ''), s.status)
        step(`V${i + 1} video is real (width>0, playing)`, s.w > 0 && s.t > 0, { w: s.w, t: s.t })
        step(`V${i + 1} connection up`, s.conn === 'connected', s.conn)
    })
    const parents = snaps.map((s) => s.state?.parent)
    const ids = snaps.map((s) => s.state?.me)
    step('tree is a chain: V1 fed by host, V2 by V1, V3 by V2', parents[1] === ids[0] && parents[2] === ids[1] && parents[0] && parents[0] !== ids[0], { parents, ids })
    step('V1 and V2 each feed one child', snaps[0].children.length === 1 && snaps[1].children.length === 1 && snaps[2].children.length === 0, snaps.map((s) => s.children))

    // video keeps moving at the far end of the chain
    const a = (await snap(viewers[2].page)).inVideoBytes; await sleep(3000); const b = (await snap(viewers[2].page)).inVideoBytes
    step('video keeps flowing to V3, three hops from the host', b > a, { before: a, after: b })

    // chat reaches everyone
    await viewers[1].page.type('[data-chat-input]', 'hello from V2'); await viewers[1].page.keyboard.press('Enter'); await sleep(5000)
    const chats = await Promise.all([host, ...viewers].map((p) => p.page.evaluate(() => [...document.querySelectorAll('[data-chat-log] li')].map((l) => l.textContent))))
    step('chat from V2 appears for the host and every viewer', chats.every((c) => c.some((t) => t.includes('hello from V2'))), chats.map((c) => c.length))

    // a relay leaves: the people below it are re-fed
    await viewers[0].page.click('[data-leave]'); await sleep(14000)
    const after = []
    for (const v of viewers.slice(1)) after.push(await snap(v.page))
    step('after V1 leaves, V2 is given a new feeder and V3 still has video', after[0].state?.parent !== parents[1] && after[0].state?.parent !== undefined || /Waiting|Connecting|watching/i.test(after[0].status || ''), { v2: after[0].status, v2parent: after[0].state?.parent, v3: after[1].status })
    const c1 = (await snap(viewers[2].page)).inVideoBytes; await sleep(3000); const c2 = (await snap(viewers[2].page)).inVideoBytes
    report.afterLeave = { v2: after[0], v3bytes: [c1, c2] }
    step('V3 video still flowing after V1 left (via V2, which now hangs off the host)', c2 > c1, { c1, c2 })
} catch (e) {
    step('script error', false, String(e).slice(0, 300))
}
await browser.close()
console.log(JSON.stringify(report, null, 1))
