import puppeteer from 'puppeteer-core'
const BASE='http://127.0.0.1:8765', sleep=(ms)=>new Promise(r=>setTimeout(r,ms))
const report={steps:[],ok:true}; const step=(n,ok,d)=>{report.steps.push({name:n,ok,...(d!==undefined?{detail:d}:{})}); if(!ok) report.ok=false}
const browser = await puppeteer.launch({ executablePath:'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome', headless:'new', args:['--no-sandbox'] })
const open = async (email)=>{ const ctx=await browser.createBrowserContext(); const page=await ctx.newPage(); page.on('pageerror',e=>step('pageerror '+email,false,String(e).slice(0,200)));
  await page.goto(BASE+'/login',{waitUntil:'domcontentloaded'}); await page.type('input[name=email]',email); await page.type('input[name=password]','password-for-local-ui-only');
  await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}), page.click('button[type=submit]')]); return {ctx,page} }
const fill = (page, link, title='Link test seminar') => page.evaluate((link,title)=>{ const set=(id,v)=>{const e=document.getElementById(id); e.value=v; e.dispatchEvent(new Event('input',{bubbles:true}))};
  set('title',title); set('summary','Checking links.'); set('duration_minutes','45'); const d=new Date(Date.now()+3*86400000); const pad=n=>String(n).padStart(2,'0'); set('starts_at', `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`);
  document.querySelector('input[name=delivery][value=link]').click(); set('join_url', link) }, link, title)
const testBtn = async (page) => { await page.evaluate(()=>{ [...document.querySelectorAll('button')].find(b=>/Test this link/.test(b.textContent)).click() }); await sleep(2500); return page.evaluate(()=>[...document.querySelectorAll('p')].map(p=>p.innerText).find(t=>/not a valid|does not exist|looks well|could not open|opened|checking|private|Type or paste|starts with/i.test(t)&&t.length<300)||'') }
try {
  const {page: host} = await open('hana@example.test')
  await host.goto(BASE+'/seminars/new',{waitUntil:'domcontentloaded'}); await sleep(1200)
  await fill(host,'https://meet.google.com/this-is-fake')
  const m1 = await testBtn(host); step('Test this link: a made-up Google Meet link is called out', /not a valid Google Meet link/.test(m1), m1)
  await fill(host,'https://notarealdomain-xyz12345.com/room')
  const m2 = await testBtn(host); step('Test this link: an address that does not exist is called out', /does not exist/.test(m2), m2)
  await fill(host,'http://insecure.example.com')
  const m3 = await testBtn(host); step('Test this link: a non-https link is refused', /starts with https/.test(m3), m3)
  // publishing a broken link is blocked with the reason on the form
  await fill(host,'https://meet.google.com/this-is-fake')
  await Promise.all([host.waitForNavigation({waitUntil:'domcontentloaded'}), host.evaluate(()=>{ [...document.querySelectorAll('button[type=submit]')].find(b=>/Publish/.test(b.textContent)).click() })])
  const t = await host.evaluate(()=>document.body.innerText)
  step('publishing a made-up link is refused with the reason, and the form is kept', /not a valid Google Meet link/.test(t) && /Host a seminar|Edit your seminar/.test(t), (t.match(/That is not a valid[^\n]*/)||[''])[0].slice(0,140))
  // a well-formed link that cannot be confirmed (TLS bundle on this computer) is allowed
  await fill(host,'https://meet.google.com/abc-defg-hij','Publishable link seminar')
  await Promise.all([host.waitForNavigation({waitUntil:'domcontentloaded'}), host.evaluate(()=>{ [...document.querySelectorAll('button[type=submit]')].find(b=>/Publish/.test(b.textContent)).click() })])
  const url = host.url(); const t2 = await host.evaluate(()=>document.body.innerText)
  step('a well-formed link is published', /\/seminars\/\d+$/.test(url), url)
  const share = await host.evaluate(()=>document.getElementById('share-link')?.value)
  step('the host sees an InfoDot share link', /\/j\/[a-z0-9]{8}$/.test(share||''), share)
  // the share link opens for a signed-out visitor: it lands on sign in (and after sign in, the seminar)
  const guestCtx = await browser.createBrowserContext(); const guest = await guestCtx.newPage()
  const r = await guest.goto(share, {waitUntil:'domcontentloaded'}); const gu = guest.url()
  step('the share link opens for a signed-out visitor and asks them to sign in', /\/login/.test(gu), gu)
  await guest.type('input[name=email]','v1@example.test'); await guest.type('input[name=password]','password-for-local-ui-only')
  await Promise.all([guest.waitForNavigation({waitUntil:'domcontentloaded'}), guest.click('button[type=submit]')])
  step('after signing in they land on the seminar page', new RegExp('/seminars/'+url.split('/').pop()+'$').test(guest.url()), guest.url())
  const gt = await guest.evaluate(()=>document.body.innerText)
  step('they do not see the host meeting link', !/meet\.google\.com\/abc-defg-hij/.test(gt))
  step('a mistyped share link gives a clean not-found, not a crash', (await guest.goto(BASE+'/j/zzzzzzzz',{waitUntil:'domcontentloaded'})).status()===404)
} catch(e){ step('script error', false, String(e).slice(0,300)) }
await browser.close(); console.log(JSON.stringify(report,null,1))
