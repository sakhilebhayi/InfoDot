# Dot.Press — Content Management and Publishing Platform

**Role:** The Publishing and Web Presence Layer of the Dot Ecosystem  
**URL:** `press.infodot.app`  
**Tagline:** Publish anything. Own your presence.

---

## Vision

Dot.Press is not a blog plugin. It is the **full-stack content and web presence platform** where organisations manage their public-facing websites, knowledge bases, help centres, marketing pages, and community content — all natively connected to the Dot ecosystem that powers their operations.

When a new product is released, Dot.Press publishes the announcement. When a question is asked on Dot.Pulse, Dot.Press can convert the answer into a help article. When Dot.Analytics identifies a content gap, Dot.Agents can draft and schedule a post. Dot.Press is the publishing surface; the rest of the ecosystem is the editorial intelligence behind it.

---

## Architecture

```
Dot Ecosystem

├── Dot.Press
│   ├── Site Builder (pages + blocks)
│   ├── CMS (posts, articles, docs)
│   ├── Multi-site Management
│   ├── Headless API (JSON)
│   ├── AI Content Assistant
│   └── SEO and Analytics Layer
│
└── Shared: PostgreSQL · Redis · S3 (media) · Meilisearch · Reverb
```

---

## Site Builder

A block-based visual builder for creating pages without code.

### Page block types

```
Layout            Content            Media              Interactive
──────            ───────            ─────              ───────────
Hero section      Heading            Image              Contact form
Two columns       Rich text          Video              Dot.Forms embed
Three columns     Quote              Gallery            Pricing table
Full-width        Code block         Background video   Newsletter signup
Card grid         Testimonial        Lottie animation   Live chat hook
Feature list      FAQ accordion      Dot.Sheet chart    Social feed
CTA banner        Timeline           Dot.Analytics widget Search bar
Footer            Team member grid   Map embed
```

### Theme system
- Pre-built themes with full colour, font, and spacing customisation
- Component library with branded variants
- Dark mode support with auto-detection and manual toggle
- Mobile-first responsive rendering

---

## CMS (Content Management System)

Structured content management for posts, articles, and documentation.

### Content types

| Type | Use case |
|---|---|
| Blog post | Company news, thought leadership, product updates |
| Help article | Support documentation, how-to guides, FAQs |
| Landing page | Campaign and product-specific pages |
| Knowledge base | Internal or public SOP and policy documentation |
| Changelog | Product version history and release notes |
| Case study | Customer success stories |
| Job listing | Careers page powered by CMS |

### Editorial workflow
- Draft → In Review → Approved → Scheduled → Published → Archived
- Reviewer assignments with comment threads
- Publish scheduling: date and time with timezone
- Content expiry: auto-unpublish or flag for review after a set date

### Content versioning
- Every edit creates a version
- Restore any previous version
- Compare two versions side by side

---

## Multi-site Management

Manage multiple websites from a single Dot.Press organisation.

```
Organisation
  ├── infodot.app          (main marketing site)
  ├── help.infodot.app     (help centre)
  ├── blog.infodot.app     (company blog)
  ├── clients.infodot.app  (client portal)
  └── [custom domain]      (white-label partner site)
```

- Each site has independent content, theme, and domain
- Shared media library across sites
- Cross-post: publish the same article to multiple sites
- Per-site analytics and SEO settings

---

## AI Content Assistant

| Feature | Description |
|---|---|
| Blog generator | Generate a full post draft from a title or topic prompt |
| SEO optimiser | Suggests title, meta description, keywords, and heading structure |
| Content repurposing | Converts a Dot.Pulse discussion thread into an article |
| Readability score | Grades content and suggests improvements |
| Translation | Translates posts to configured languages |
| Image alt text | Generates alt text for every uploaded image |
| Social snippets | Generates Twitter/LinkedIn post versions of an article |

---

## SEO and Performance

- Automatic sitemap.xml and robots.txt generation
- Structured data (JSON-LD) for articles, FAQs, products
- Open Graph and Twitter Card meta tags
- Canonical URL management
- Redirect manager: 301/302 from old URLs
- Core Web Vitals monitoring with per-page scores
- Image optimisation pipeline: WebP conversion, responsive srcset

---

## Headless CMS API

Dot.Press exposes a read API allowing content to be consumed by any frontend.

```
GET /api/v1/posts
GET /api/v1/posts/{slug}
GET /api/v1/pages/{slug}
GET /api/v1/categories
GET /api/v1/search?q=query
```

Authenticated write endpoints allow Dot.Agents to create and update posts programmatically.

---

## Ecosystem Integration

| Platform | Integration |
|---|---|
| Dot.Pulse | Community discussions converted into help articles |
| Dot.Agents | Agents draft, schedule, and publish content automatically |
| Dot.Analytics | Site traffic, page views, and conversion data feed EIP |
| Dot.Forms | Contact and newsletter forms embedded in pages |
| Dot.Engage | New subscribers from forms added as contacts |
| Dot.Files | Media library backed by S3 via Dot.Files |
| InfoDot | Team-based access; editors managed via InfoDot teams |

---

## Revenue Model

| Plan | Features | Pricing |
|---|---|---|
| Free | 1 site, 20 pages, 500 MB media | Included with InfoDot |
| Starter | 3 sites, unlimited pages, custom domain, AI assist | Per-seat monthly |
| Business | Multi-site, SEO suite, headless API, editorial workflow | Per-seat monthly |
| Enterprise | White-label, CDN, advanced redirects, SLA, SSO | Annual contract |
