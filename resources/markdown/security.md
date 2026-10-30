# Vulnerability Disclosure Policy

_Last updated: 30 October 2026_

InfoDot takes the security of its platform and its members' data seriously. If you believe you
have found a security vulnerability in InfoDot or another Dot ecosystem service, we want to hear
from you, and we will work with you in good faith.

## Scope

This policy covers:

- **infodot.co.za** and **www.infodot.co.za** (the InfoDot platform, including its sign-in and
  identity services)
- Dot ecosystem applications on subdomains of **infodot.co.za** (for example
  `central.infodot.co.za`, `doc.infodot.co.za`)

Out of scope:

- Denial-of-service testing, load testing, or any testing that degrades the service for others
- Social engineering, phishing, or physical attacks against InfoDot, its operator, or its members
- Spam, or testing of third-party services that InfoDot uses but does not operate (for example
  the hosting provider's own infrastructure, payment providers, or email delivery services)
- Automated scanning at a volume that resembles an attack

## How to report

Email **privacy@infodot.co.za** with:

- A description of the issue and where you found it (URL, endpoint, or page)
- Steps to reproduce it, or a proof of concept
- The potential impact, as you understand it
- A way for us to contact you for follow-up questions

Please do not include other people's personal data in your report. If demonstrating an issue
requires account data, use your own account.

## What to expect from us

- We aim to acknowledge your report within **7 days**.
- We will investigate every genuine report, keep you informed of material progress, and tell you
  when the issue is resolved.
- We will not take legal action against you for research carried out in line with this policy
  (see Safe harbor below).
- With your permission, we are happy to credit you once the issue is fixed. We do not currently
  operate a paid bounty programme.

## What we ask of you

- Give us a reasonable time to fix the issue before any public disclosure — we ask for **90
  days** from your report, or the date we tell you it is fixed, whichever is sooner.
- Only access, modify, or delete data that belongs to you. If you encounter someone else's data,
  stop, do not copy it, and report what happened.
- Do not use a vulnerability to pivot further into the system than is needed to demonstrate it.

## Safe harbor

We consider security research conducted in good faith and in line with this policy to be
authorised. We will not initiate or support legal action against you for such research, and if a
third party raises action against you for activity this policy authorises, we will make it known
that your research was conducted in accordance with this policy. This safe harbor does not apply
to research that breaks the rules above, and it cannot override laws we do not control.

## Machine-readable reference

This policy is referenced from
[`/.well-known/security.txt`](/.well-known/security.txt) (RFC 9116).
