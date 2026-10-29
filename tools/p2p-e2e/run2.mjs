import puppeteer from 'puppeteer-core'
const BASE='http://127.0.0.1:8765', SEM=process.argv[2]||'3', sleep=(ms)=>new Promise(r=>setTimeout(r,ms))
const report={steps:[],ok:true}; const step=(n,ok,d)=>{report.steps.push({name:n,ok,...(d!==undefined?{detail:d}:{})}); if(!ok) report.ok=false}
const browser = await puppeteer.launch({ executablePath:'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome', headless:'new', args:['--use-fake-device-for-media-stream','--use-fake-ui-for-media-stream','--disable-features=WebRtcHideLocalIpsWithMdns','--autoplay-policy=no-user-gesture-required','--no-sandbox'] })
const open = async (email)=>{ const ctx=await browser.createBrowserContext(); const page=await ctx.newPage(); page.on('pageerror',e=>step('pageerror '+email,false,String(e).slice(0,200)));
  await page.goto(BASE+'/login',{waitUntil:'domcontentloaded'}); await page.type('input[name=email]',email); await page.type('input[name=password]','password-for-local-ui-only');
  await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}), page.click('button[type=submit]')]); return page }
try {
  const host = await open('hana@example.test')
  // ---- the host's form: connection test and the number they are told
  await host.goto(BASE+'/seminars/new',{waitUntil:'domcontentloaded'}); await sleep(1500)
  await host.evaluate(()=>{ document.querySelector('input[name=delivery][value=p2p]').click() }); await sleep(500)
  const before = await host.evaluate(()=>({ btn: [...document.querySelectorAll('button')].some(b=>/Test my connection/.test(b.textContent)), hint: /depends on your upload speed/.test(document.body.innerText) }))
  step('the form offers the small room and a connection test', before.btn && before.hint, before)
  await host.evaluate(()=>{ [...document.querySelectorAll('button')].find(b=>/Test my connection/.test(b.textContent)).click() })
  await host.waitForFunction(()=>/This seminar can have up to \d+ people/.test(document.body.innerText), {timeout:20000})
  const txt = await host.evaluate(()=>document.body.innerText)
  const m = txt.match(/Your upload speed: ([\d.]+) Mbps/)
  step('the connection test measures a speed', !!m, m && m[0])
  step('each quality shows how many people it can hold', /Audio only: up to \d+ people/.test(txt) && /Video, standard \(360p\): (up to \d+ people|too slow)/.test(txt), (txt.match(/(Audio only|Video, standard \(360p\)|Video, sharp \(720p\)):[^\n]*/g)||[]))
  step('the host is told the seminar capacity in a sentence', /This seminar can have up to \d+ people/.test(txt), (txt.match(/This seminar can have up to \d+ people[^.]*\./)||[''])[0].slice(0,160))
  // ---- submitting the form for real
  await host.evaluate(()=>{ const set=(id,v)=>{const e=document.getElementById(id); e.value=v; e.dispatchEvent(new Event('input',{bubbles:true}))}; set('title','Created from the form'); set('summary','Made in a real browser.'); set('duration_minutes','45');
    const d=new Date(Date.now()+3*86400000); const pad=n=>String(n).padStart(2,'0'); set('starts_at', `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`) })
  await Promise.all([host.waitForNavigation({waitUntil:'domcontentloaded'}), host.evaluate(()=>{ [...document.querySelectorAll('button[type=submit]')].find(b=>/Publish/.test(b.textContent)).click() })])
  const page2 = await host.evaluate(()=>document.body.innerText)
  step('the published seminar page states the room size', /Small room: up to \d+ people/.test(page2), (page2.match(/Small room: up to \d+ people[^\n]*/)||[''])[0])
  // ---- removal by the host
  const v1 = await open('v1@example.test')
  await host.goto(`${BASE}/seminars/${SEM}/small-room`,{waitUntil:'domcontentloaded'}); await host.evaluate(()=>document.querySelector('[data-consent-ok]')?.click()); await sleep(2500)
  await v1.goto(`${BASE}/seminars/${SEM}/small-room`,{waitUntil:'domcontentloaded'}); await v1.evaluate(()=>document.querySelector('[data-consent-ok]')?.click()); await sleep(9000)
  step('a viewer is watching before removal', /watching/i.test(await v1.evaluate(()=>document.querySelector('[data-status]').textContent)))
  await host.goto(`${BASE}/seminars/${SEM}`,{waitUntil:'domcontentloaded'}); await sleep(800)
  const removed = await host.evaluate(()=>{ const f=[...document.querySelectorAll('form')].find(f=>/p2p\/remove/.test(f.action)); if(!f) return false; window.confirm=()=>true; f.submit(); return true })
  step('the host has a Remove from room button', removed)
  await sleep(6000)
  const msg = await v1.evaluate(()=>document.querySelector('[data-status]').textContent)
  step('the removed viewer is told and disconnected', /removed you/i.test(msg), msg)
} catch(e){ step('script error', false, String(e).slice(0,300)) }
await browser.close(); console.log(JSON.stringify(report,null,1))
