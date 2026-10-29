import puppeteer from 'puppeteer-core'
const BASE='http://127.0.0.1:8765', SEM=process.argv[2], sleep=(ms)=>new Promise(r=>setTimeout(r,ms))
const browser = await puppeteer.launch({ executablePath:'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome', headless:'new', args:['--use-fake-device-for-media-stream','--use-fake-ui-for-media-stream','--disable-features=WebRtcHideLocalIpsWithMdns','--autoplay-policy=no-user-gesture-required','--no-sandbox'] })
const open = async (email)=>{ const ctx=await browser.createBrowserContext(); const page=await ctx.newPage(); await page.goto(BASE+'/login',{waitUntil:'domcontentloaded'}); await page.type('input[name=email]',email); await page.type('input[name=password]','password-for-local-ui-only'); await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}), page.click('button[type=submit]')]); return page }
const host=await open('hana@example.test'); await host.goto(`${BASE}/seminars/${SEM}/small-room`,{waitUntil:'domcontentloaded'}); await sleep(2500)
const v=await open('v1@example.test'); await v.goto(`${BASE}/seminars/${SEM}/small-room`,{waitUntil:'domcontentloaded'}); await sleep(9000)
const grab=()=>v.evaluate(async()=>{ const pc=window.__p2p.upstream(); let a=0, vid=0; const r=await pc.getStats(); r.forEach(s=>{ if(s.type==='inbound-rtp'&&s.kind==='audio') a=s.bytesReceived; if(s.type==='inbound-rtp'&&s.kind==='video') vid=s.bytesReceived }); return {conn:pc.connectionState, a, vid, status:document.querySelector('[data-status]').textContent, hostBtns:null} })
const s1=await grab(); await sleep(3000); const s2=await grab()
const hostBtns=await host.evaluate(()=>[...document.querySelectorAll('[data-toggle]')].map(b=>b.dataset.toggle))
console.log(JSON.stringify({s1,s2,audioFlowing:s2.a>s1.a, hostControls:hostBtns, hostHasNoCamButton:!hostBtns.includes('cam')}))
await browser.close()
