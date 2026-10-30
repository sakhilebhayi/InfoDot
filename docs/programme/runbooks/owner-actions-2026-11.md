# Owner action sheet — November 2026 (R-22 Track 2)

Everything on this page needs you (registrar/cPanel/provider access). Each item: what, why,
access, time, risk, and how we verify. Values come from the R-18 register — nothing here is
guessed.

## 1. Renew infodot.co.za — DO FIRST

- **Registry facts (verified 2026-10-30 via ZACR whois):** expiry **2026-11-27 13:53 UTC**;
  registrar of record **Key-Systems GmbH (IANA 269)** — the wholesale registrar your hosting
  reseller uses; nameservers ns1–ns4.mysecurecloudhost.com (your cPanel host's).
- **What to do:** renew through the control panel where the domain was bought (your
  mysecurecloudhost/reseller account, domains section — the same place that manages the hosting).
  Renew for at least 1 year; enable auto-renew if offered; confirm the billing card on file.
  Do NOT change nameservers or ownership while renewing.
- **Access:** your reseller/billing login. **Time:** ~10 min. **Risk:** none if done before
  expiry; after expiry co.za domains suspend quickly and every platform + email goes down.
- **Verify (we can do this part):** whois shows a new Registry Expiry Date; say the word and the
  session re-runs the whois and records the evidence in the register.

## 2. DNS hardening (cPanel → Zone Editor) — exact staged records in R-18 register §B

1. Create a `dmarc@infodot.co.za` mailbox or forwarder (needed before the rua record).
2. Replace SPF TXT: `v=spf1 a mx ip4:198.38.92.233 include:spf.mysecurecloudhost.com -all`
3. Replace `_dmarc` TXT: `v=DMARC1; p=none; rua=mailto:dmarc@infodot.co.za; fo=1`
4. Add three CAA records: `0 issue "letsencrypt.org"`, `0 issue "sectigo.com"`,
   `0 iodef "mailto:privacy@infodot.co.za"`
5. After ~2 weeks of clean DMARC reports: `_dmarc` → `p=quarantine; pct=100`; later `p=reject`.
6. DNSSEC: Zone Editor → DNSSEC → generate keys → add the DS at the registrar (via the reseller
   panel). Off-hours; a wrong DS breaks all resolution. If the panel has no DNSSEC option, reply
   "provider-blocked" and it goes on the provider ticket instead.

**Time:** ~20 min (+the two staged follow-ups). **Risk:** mail keeps working at every step (the
host is the only sender); CAA must include both CAs listed or AutoSSL renewals could fail.
**Verify:** we re-run the DNS probes and a password-reset email test after each step.

## 3. Provider tickets (mysecurecloudhost support)

Ask in one ticket: (a) current OpenSSH version and whether vendor security patches are applied
on the shared host; (b) whether plain FTP can be disabled for the account (SFTP stays — deploys
use SSH); (c) whether DNSSEC signing is supported for customer zones. **Time:** 10 min to file.
**Verify:** paste their reply into the session; the register gets updated with evidence.

## 4. MySQL restore drill — runbook: `runbooks/mysql-restore-drill.md`

Host-side, ~30–45 min. The tooling already does the heavy lifting (`dot-backup restore-test`).

## 5. Live Paystack verification — runbook: `runbooks/paystack-live-verification.md`

Needs you because real money moves (small controlled amount) and Dot.Billing owns the keys.
Step 0 is confirming where Dot.Billing actually runs in production.

## 6. Start the SSO client session

The one remaining engineering piece (R-20 relying-party client + Dot.Central pilot) must run in
its own fresh Claude session — this programme session is barred from generating it. Open a new
session in the InfoDot repo and paste:

> Execute Priority 2 of docs/programme/requirements/dot-final-launch-execution.md: complete
> packages/dot-sso-client per its README, then the Dot.Central pilot per §2.3/§2.5. PRs only;
> merge/deploy on my approval.
