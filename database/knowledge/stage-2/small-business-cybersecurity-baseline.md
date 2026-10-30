---
key: small-business-cybersecurity-baseline
title: The cybersecurity baseline every small business needs (and POPIA requires)
topics: Technology and tools | Legal and compliance
tags: cybersecurity, passwords, MFA, backups, phishing, POPIA, breach notification, payment fraud
duration: 2
duration_type: weeks
evidence: The UK National Cyber Security Centre's small-organisations guide and the US CISA small-business baseline, applied under South Africa's POPIA security and breach-notification duties, with banking-fraud patterns from SABRIC's 2025 crime statistics and bank advisories.
---
A small business does not need enterprise security tooling. It needs a short list of disciplined basics — the same list two national cyber agencies publish for small organisations — plus the South African overlay most guides skip: under POPIA, securing personal information is a legal duty, and a breach triggers a mandatory report to the Information Regulator. This entry is that baseline: what to do before anything happens, and exactly what to do after.

## The problem
Small businesses are attacked through the same few doors: email accounts with reused passwords, missing second factors on the accounts that matter, backups that were never tested (or sat connected when ransomware arrived), and payment instructions nobody verified. The UK's National Cyber Security Centre (NCSC) states on its small-organisations guide that 1 in 2 small businesses suffer a cyber incident every year and 85% of attacks on businesses start with a scam email (the NCSC's own figures). In South Africa the money side is stark: SABRIC's 2025 banking crime statistics report digital-banking fraud losses rising to R2.4 billion, and names supplier mandate fraud and business email compromise as material risks in business environments — criminals redirecting payments through fraudulent changes to banking details or compromised communications.

## Who this is for
Every business with an email address and a bank account. If you also hold customers' or employees' personal information — names, contact details, IDs, payslips — POPIA section 19 makes "appropriate, reasonable technical and organisational measures" a legal obligation, not a preference, and section 19(3) points you to generally accepted security practice, which is what follows.

## Step 1 — Lock down email and the accounts that move money
Email first — it resets every other password. Use passkeys where the provider supports them; otherwise a strong unique password plus two-step verification, because "any type of 2SV is better than none" (NCSC). Then the accounts the NCSC names as important: banking, payroll/HR, social media, cloud storage, your website and domain host, and the point of sale. On strength of second factor, CISA's ranking from strongest to weakest: a security key, an authenticator app with number matching, an authenticator one-time code, biometrics, and last — SMS or email codes, to be used only where nothing stronger exists.

## Step 2 — Give the whole team a password manager
Unique passwords per account are only realistic with a password manager (NCSC; CISA recommends a company-provided one, with passwords of at least 16 characters or a 5–7-word passphrase). The manager is also what makes passkeys workable.

## Step 3 — Updates on, old kit out
Turn on automatic updates for operating systems, apps and browsers; replace devices that no longer receive updates; install apps only from official stores; check antivirus and firewall are on (NCSC's device page). CISA's ordering: patch critical and internet-facing systems first, and retire end-of-life products.

## Step 4 — Backups that would actually save you
Back up everything the business needs to operate — invoicing, documents, contacts, customer information, the website (NCSC). Follow CISA's 3-2-1 rule: three copies, on two kinds of media, one off-site. Keep one copy OFFLINE — an external drive disconnected when not in use — because malware spreads to connected drives (NCSC), and protect the online copy with its own 2SV. Then the step almost everyone skips: do a restore test, so you know the backup works and how long restoring takes.

## Step 5 — Build the payment-verification habit
The single highest-value South African habit: when a supplier's banking details "change", or an unusual payment instruction arrives by email or WhatsApp, verify it with a person you know on a number you already hold — never a number in the message (SABRIC's warning sign is exactly this: an instruction "received through email, WhatsApp or telephone" that "cannot be independently confirmed"). Standard Bank's guidance adds: if you cannot reach the supplier, ask your bank for account validation. Nedbank warns that fraudsters attach forged bank "confirmation letters" as proof. Train everyone who pays invoices; this one habit defeats the fraud pattern SABRIC says remains a material business risk.

## Step 6 — Leavers lose access, admin stays special
Remove or disable accounts people no longer need the day they leave — stale accounts are likelier to have weak or leaked passwords — and review who has access to what every few months (NCSC). Do daily work in a standard user account, never the administrator account.

## Step 7 — Appoint and register your Information Officer
Under POPIA, every private body has an Information Officer by default — the head of the business, or someone they authorise (Information Regulator's guidance note) — and Information Officers must register with the Regulator before taking up their duties. This is the person who will handle Step 9 if it ever comes. Registering is a once-off piece of admin; do it before you need it.

## Step 8 — Write the one-page incident plan
CISA's incident-response basics for small organisations: a short written plan, approved by the owner, naming who manages the incident, who handles the technical side, who communicates — and a PRINTED contact list (bank fraud line, IT help, the Regulator, key suppliers), because email may be down. NCSC adds the attack warning signs to watch: unusual sent mail, login alerts you don't recognise, unauthorised payments.

## Step 9 — Know the after-a-breach drill (POPIA makes part of it law)
If an account or system is compromised: contain first — from a clean device, change passwords, log out all sessions, and check for attacker-added email forwarding rules (NCSC's hacked-account steps). If money moved, phone your bank's fraud line immediately using the number from its official site. Then the legal part: where there are reasonable grounds to believe personal information was accessed by an unauthorised person, POPIA section 22 requires notifying the Information Regulator AND the affected people, in writing, as soon as reasonably possible. The Regulator's 2025 fact sheet is explicit: every security compromise must be reported "irrespective of the deemed level of risk", you must not wait for the investigation to finish (report what you know and update), and since 1 April 2025 reports go through the Regulator's eServices portal (eservices.inforegulator.org.za), not email. A compromised vendor holding your data must tell you immediately (section 21(2)) — but the notification duty stays yours. Afterwards, hold a blameless review and fix the weakness (CISA).

## Tools and resources
The NCSC small-organisations guide (free, six short pages) and CISA's Secure Your Business pages are the baseline references. The Information Regulator: inforegulator.org.za, helpdesk@inforegulator.org.za, 010 023 5200. SABRIC's how-to-stay-safe pages for current SA fraud patterns. Your own bank's fraud line — find it on the bank's official site today and put it on the printed contact list (Nedbank's published line is 0800 110 929).

## What to expect and how to measure it
Measure coverage, not feelings: % of important accounts with 2SV or passkeys (target 100%), password-manager adoption, days since the last successful restore test, leavers' accounts removed within a day, and payment-detail changes verified by phone (target: every one). The POPIA penalties context, without drama: the Regulator's enforcement can carry administrative fines up to a ceiling of R10 million — but the practical reason to do this is in SABRIC's numbers, not the statute.

## Common mistakes
Reusing passwords, especially on email; SMS codes as the only second factor where an app or key is possible; backups never restore-tested or left permanently connected; ex-staff accounts left active; working as administrator; calling the number inside the suspicious message; treating a "small" breach as exempt from reporting (no risk threshold exists); and waiting for the full investigation before telling the Regulator. One claim to ignore: a general "report to police within 72 hours" duty — the Cybercrimes Act's section 54 reporting duty targets electronic communications service providers and financial institutions, not ordinary small businesses, and the 72-hour breach clock is a UK/EU GDPR idea; POPIA's standard is "as soon as reasonably possible".

## Sources and further reading
Small organisations guide to cyber security — UK National Cyber Security Centre — https://www.ncsc.gov.uk/collection/small-organisations-guide-to-cyber-security — published 9 April 2026, reviewed 21 July 2026 (sub-pages: secure-your-email, secure-your-important-online-accounts, protecting-your-devices, backing-up-your-data, spotting-cyber-attacks).
Secure Your Business — US CISA — https://www.cisa.gov/audiences/small-and-medium-businesses/secure-your-business — undated.
Require multifactor authentication — US CISA — https://www.cisa.gov/audiences/small-and-medium-businesses/secure-your-business/require-multifactor-authentication — undated.
Back Up Business Data — US CISA — https://www.cisa.gov/back-business-data — undated.
Incident Response Plan Basics — US CISA — https://www.cisa.gov/sites/default/files/2026-01/Incident-Response-Plan-Basics_508c.pdf — published January 2026 (path date).
Protection of Personal Information Act 4 of 2013 (sections 19, 21, 22, 55, 109) — Government Gazette 37067 — https://www.gov.za/sites/default/files/gcis_document/201409/3706726-11act4of2013protectionofpersonalinforcorrect.pdf — 26 November 2013.
Fact Sheet: Handling of Security Compromises — Information Regulator (South Africa) — https://inforegulator.org.za/2025/08/19/fact-sheet-handling-of-security-compromises/ — 19 August 2025.
Media statement: reporting security compromises through the eServices portal — Information Regulator — https://inforegulator.org.za/wp-content/uploads/2025/04/MEDIA-STATEMENT-INVITATION-TO-REPORT-SECURITY-COMPROMISES-THROUGH-THE-eSERVICES-PORTAL-.pdf — 7 April 2025.
Guidance Note on Information Officers — Information Regulator — https://inforegulator.org.za/wp-content/uploads/2020/07/InfoRegSA-GuidanceNote-IO-DIO-20210401.pdf — 1 April 2021.
Annual Crime Statistics 2025 — SABRIC — https://www.sabric.co.za/wp-content/uploads/2026/08/SABRIC-Annual-Crime-Statistics-Report-2025.pdf — released 4 August 2026.
It takes a phone call to avoid this scam — Standard Bank BizConnect — https://www.standardbank.co.za/southafrica/business/bizconnect/help-me-manage-my-business/guides/It-takes-a-phone-call-to-avoid-this-scam — undated.
Schemes and scams — Nedbank — https://personal.nedbank.co.za/home/fraud-awareness/schemes-and-scams.html — undated.
Responding to a cyber incident (small organisations) — UK NCSC — https://www.ncsc.gov.uk/section/respond-recover/sole-small — undated.
Cybercrimes Act 19 of 2020, section 54 — Acts Online — https://api.acts.co.za/cybercrimes-act-2020/54__obligations_of_electronic_.php — undated.
Last reviewed: 2026-10-31.
