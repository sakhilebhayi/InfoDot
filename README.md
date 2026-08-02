<div align="center">

<img src="docs/logo.svg" alt="InfoDot" width="320" />

<br /><br />

**Log in once — access every Dot platform seamlessly.**

<br />

![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?style=flat-square&logo=laravel&logoColor=white) ![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?style=flat-square&logo=php&logoColor=white) ![Livewire](https://img.shields.io/badge/Livewire-3-FB70A9?style=flat-square) ![PostgreSQL](https://img.shields.io/badge/PostgreSQL-16-336791?style=flat-square&logo=postgresql&logoColor=white)

<br /><br />

**Part of the [InfoDot Ecosystem](https://github.com/sakhilebhayi/InfoDot)** &nbsp;·&nbsp; `infodot.app`

</div>

---

## What is InfoDot?

InfoDot is the central identity hub for the Dot Ecosystem — a unified gateway giving users single sign-on access to every connected Dot platform. Authenticate once and move freely between them without re-entering credentials.

**Platform registry:** 27 entries in `config/ecosystem.php` — the 20 real, built platforms tracked by [Dot.Brain's `os/Appendix.md`](https://github.com/sakhilebhayi/Dot.Brain/blob/main/os/Appendix.md) (verified: each implements the identical `/auth/ecosystem` token contract this hub issues), plus 7 platforms from InfoDot's original, earlier plan (Dot.Files, Dot.Docs, Dot.Forms, Dot.Sheet, Dot.Engage, Dot.Press, Dot.Tutor) that haven't yet been verified against that same contract. See `CLAUDE.md` for the full reconciliation notes.

## Core Features

- Single Sign-On (SSO) via Sanctum handoff tokens (5-min TTL)
- Solutions hub — community knowledge base with polymorphic likes
- Q&A forum with threaded comments
- Team management — invitations, roles, and multi-tenancy
- Real-time notifications via Laravel Reverb
- Full-text search across solutions, questions, and users
- Social graph — follow users, build your network
- Ecosystem widget — one-click launch into any registered platform, grouped by category
- File uploads backed by AWS S3 or local disk

## Domain Models

- **User** — identity, profile, social graph
- **Team** — multi-tenant workspace with roles
- **Solution** — knowledge articles with tagging
- **Question / Answer** — threaded Q&A forum
- **Notification** — real-time alert feed
- **File** — attachment storage

## Tech Stack

| Layer | Technology |
|---|---|
| Framework | Laravel 12 |
| Language | PHP 8.4 |
| Frontend | Livewire 3 · Alpine.js 3 · Tailwind CSS |
| Database | PostgreSQL 16 (shared across ecosystem) |
| Realtime | Laravel Reverb |
| Auth | Laravel Sanctum (InfoDot SSO) |
| AI | Anthropic Claude (`claude-sonnet-4-6`) |
| Storage | AWS S3 / Local (Flysystem) |
| Search | Laravel Scout · Meilisearch |
| Queue | Redis · Laravel Horizon |

## Quick Start

```bash
git clone https://github.com/sakhilebhayi/InfoDot.git
cd InfoDot
cp .env.example .env
composer install
npm install && npm run build
php artisan key:generate
php artisan migrate
php artisan serve
```

> **Ecosystem SSO:** Set `DB_*` env vars to the shared InfoDot PostgreSQL instance and `APP_URL=https://infodot.app`. Users authenticated through InfoDot gain access automatically via Sanctum handoff tokens.

## Ecosystem

**InfoDot** is the hub connecting the Dot Ecosystem's 20 real platforms (plus 7 from an earlier, not-yet-reconciled plan — see above) via shared PostgreSQL and Sanctum SSO. Visit [Dot.Brain](https://github.com/sakhilebhayi/Dot.Brain) for the authoritative ecosystem map and engineering state.

## License

MIT © [SK Digital / BluPin Incorporated](https://github.com/sakhilebhayi)
