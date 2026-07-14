# Dot.Design — Visual Design and Creative Platform

**Role:** The Creative and Brand Production Layer of the Dot Ecosystem  
**URL:** `design.infodot.app`  
**Tagline:** Design anything. Brand everything. Create at ecosystem speed.

---

## Vision

Dot.Design is not Canva with a Dot logo. It is the **brand and creative production platform** that generates, manages, and distributes visual assets natively connected to every other Dot platform — so that the same design is published to Dot.Press, shared to Dot.Engage contacts, stored in Dot.Files, and measured in Dot.Analytics without leaving the ecosystem.

Where Canva, Figma, and Adobe Express are standalone tools, Dot.Design is embedded in the workflow. A social post designed here is scheduled directly through Dot.Press. A pitch deck created for a CRM deal links directly to the Dot.Engage opportunity. A product image exported here is synced to Dot.Emall's listing. Design is not a finishing step — it is part of every operational workflow.

---

## Architecture

```
Dot Ecosystem

├── Dot.Design
│   ├── Design Editor (canvas + layers)
│   ├── Brand Kit Manager
│   ├── Template Library
│   ├── Asset Library
│   ├── AI Creative Tools
│   └── Publishing and Export Hub
│
└── Shared: PostgreSQL · Redis · S3 (assets) · Reverb (collaboration)
```

---

## Design Editor

A web-based canvas editor capable of producing professional print and digital assets.

### Canvas types

| Format | Use case |
|---|---|
| Social post | Instagram, Facebook, LinkedIn, Twitter — all standard dimensions |
| Presentation | 16:9 slides with multi-page deck support |
| Document | A4/Letter portrait for reports, proposals, flyers |
| Banner | Web and print banners at custom dimensions |
| Email header | Pixel-perfect email header and footer |
| Business card | Standard card dimensions front and back |
| Certificate | Printable certificates linked to Dot.Tutor |
| Logo | Vector-capable artboard for logo work |
| Custom | Any width × height in px, mm, cm, or inches |

### Editor capabilities
- Drag-and-drop elements: shapes, text, images, icons, lines, tables
- Layer panel: show/hide, lock, reorder, rename layers
- Alignment and distribution tools
- Rulers, guides, and snapping grid
- Undo/redo with 100-step history
- Background remover (AI)
- Image adjustment: brightness, contrast, saturation, blur, filter
- Text: font library (Google Fonts + uploaded fonts), size, spacing, effects
- Vector shapes with path editing
- Opacity, blend modes, and shadow effects

---

## Brand Kit Manager

Organisations maintain a central brand kit shared across their team.

### Brand kit contents
- Primary and secondary colour palette (HEX / RGB / CMYK)
- Typography: heading font, body font, accent font with weights
- Logo variations: full colour, white, black, icon-only
- Brand guidelines document (linked from Dot.docs)
- Approved image library with usage rules
- Tone-of-voice keywords for AI-assisted content

### Brand enforcement
- Templates can be locked to brand kit colours and fonts
- Off-brand colour or font selections show a warning
- Administrators can restrict team members to approved colours only
- Brand score: AI-assessed consistency across recent designs

---

## Template Library

### Built-in template categories
```
Marketing           Social Media         Business
─────────           ────────────         ────────
Flyer               Instagram post        Business card
Brochure            LinkedIn banner       Letterhead
Event poster        Twitter header        Invoice template
Email newsletter    Story / Reel          Proposal cover
Product catalogue   YouTube thumbnail     Report cover
Ad banner           Facebook post         Certificate

Presentations       Education            Internal
─────────────       ─────────            ────────
Pitch deck          Course slide          Org chart
Company overview    Quiz card             Team introduction
Product demo        Flashcard             Meeting agenda
Sales deck          Lesson visual         Process diagram
```

Teams can save their own designs as templates and share within the organisation.

---

## Asset Library

A searchable library of design resources.

- 100,000+ stock photos and illustrations (royalty-free)
- 10,000+ icons in multiple styles
- Custom uploaded assets: brand photos, product images, logos
- Asset tagging and search
- AI-generated images (text-to-image generation)
- Animated elements and Lottie files
- Audio clips for video exports

Assets are stored in Dot.Files / S3 and indexed in Meilisearch.

---

## AI Creative Tools

| Tool | Description |
|---|---|
| Background remover | Remove image background in one click |
| Image generator | Generate visuals from text prompt |
| Magic resize | Instantly reformat a design for multiple dimensions |
| AI copywriter | Generate headline and body copy based on canvas context |
| Brand match | Recolour a template to match uploaded brand colours |
| Style transfer | Apply the visual style of one image to another |
| Smart layout | Suggest improved element arrangement on the canvas |
| Alt text generator | Auto-generate accessible alt text for exported images |

---

## Collaboration

- Real-time multi-user editing via Laravel Reverb
- Presence indicators: see which team member is on which element
- Comments on canvas elements with mention notifications
- Approval workflow: submit for review, approver signs off before export
- Version history per design

---

## Publishing and Export Hub

Finished designs flow directly to their destinations.

### Export formats
- PNG, JPEG, WebP (with configurable quality)
- PDF (print-ready with bleed and crop marks)
- SVG (scalable vector)
- MP4 / GIF (for animated designs)
- ZIP (all pages as individual files)

### Ecosystem publishing

| Destination | Action |
|---|---|
| Dot.Files | Save to team workspace automatically |
| Dot.Press | Publish directly as a page image or post visual |
| Dot.Emall | Upload as product image to a listing |
| Dot.Engage | Attach to a deal or email campaign |
| Dot.Tutor | Upload as course visual or certificate design |
| Social media | Schedule via Dot.Press social scheduling |

---

## Ecosystem Integration

| Platform | Integration |
|---|---|
| Dot.Files | All design assets stored in S3 via Dot.Files |
| Dot.Press | Designs published directly to website pages |
| Dot.Emall | Product and marketing images synced to listings |
| Dot.Analytics | Design usage, template popularity, and export stats feed EIP |
| Dot.Engage | Assets attached to deals, proposals, and campaigns |
| Dot.Tutor | Certificate and course slide templates managed here |
| InfoDot | Brand kit and team access via InfoDot team model |

---

## Revenue Model

| Plan | Features | Pricing |
|---|---|---|
| Free | 5 active designs, basic templates, 500 MB storage | Included with InfoDot |
| Starter | Unlimited designs, full template library, brand kit | Per-seat monthly |
| Business | AI tools, collaboration, approval workflow, ecosystem publishing | Per-seat monthly |
| Enterprise | Custom fonts, unlimited storage, white-label export, SLA | Annual contract |
