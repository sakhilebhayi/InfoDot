# Dot.Emall — E-Commerce and Digital Marketplace Platform

**Role:** The Commerce and Retail Layer of the Dot Ecosystem  
**URL:** `emall.infodot.app`  
**Tagline:** Sell anything. Reach everyone.

---

## Vision

Dot.Emall is not a Shopify clone. It is the **multi-vendor e-commerce and digital marketplace platform** native to the Dot ecosystem — where merchants, service providers, and digital creators sell products and services with the full intelligence of the ecosystem behind every transaction.

A merchant on Dot.Emall benefits from Dot.Engage for customer relationships, Dot.Finance for accounting, Dot.Analytics for sales intelligence, Dot.Agents for automated order processing, and InfoDot for identity. Every sale generates data. Every return triggers a workflow. Every customer interaction enriches the CRM.

The market it competes in includes Shopify, WooCommerce, Takealot, and Amazon — but Dot.Emall's differentiation is the ecosystem: no external app store required because the intelligence and automation are already built in.

---

## Architecture

```
Dot Ecosystem

├── Dot.Emall
│   ├── Storefront (merchant-branded)
│   ├── Marketplace (multi-vendor discovery)
│   ├── Product Catalogue
│   ├── Order Management
│   ├── Inventory Management
│   ├── Payment Processing
│   └── Seller Intelligence Dashboard
│
└── Shared: PostgreSQL · Redis · Reverb · S3 (media) · Stripe (Cashier)
```

---

## Marketplace Structure

Dot.Emall operates two layers simultaneously.

### 1. Individual storefronts
Each seller has a branded storefront at a subdomain or custom domain:
- `sellername.emall.infodot.app`
- `store.merchantdomain.com` (custom domain)

The storefront is built on Dot.Press blocks — merchants control layout, branding, and content without code.

### 2. Central marketplace
A unified discovery surface where buyers browse across all sellers:
- Category-based navigation
- Search powered by Meilisearch
- Featured and sponsored listings
- Vendor ratings and reviews
- Flash sales and limited-time deals
- Trending and recommended products

---

## Product Catalogue

### Product types

| Type | Description |
|---|---|
| Physical product | Tangible goods requiring shipping and inventory |
| Digital product | Downloadable files (PDF, ZIP, video, software) |
| Service | Bookable or quotation-based offering |
| Subscription product | Recurring physical or digital deliveries |
| Auction lot | Items listed for live or timed auction (via Dot.Auction) |
| Bundle | Combined products sold at a package price |

### Product record
- Title, description (rich text), category, tags
- Images and video (stored in Dot.Files / S3)
- Variants: size, colour, SKU-level pricing and stock
- Pricing: standard, sale, bulk, and tiered pricing
- Shipping dimensions and weight
- Digital download file attachment
- Custom fields per category (e.g. size guide, compatibility)

---

## Order Management

Full order lifecycle from placement to fulfilment.

### Order states
```
Placed → Payment confirmed → Processing → Packed → Shipped
      → Delivered → Completed
      → Cancelled / Refund requested → Refunded
```

### Order record
- Customer details and delivery address
- Line items with quantity, variant, and price at time of order
- Payment method and transaction reference
- Shipping carrier, tracking number, and estimated delivery
- Notes and communication thread
- Return and refund history

### Automation rules
- Auto-acknowledge order and send confirmation email
- Alert warehouse or supplier when stock drops below reorder level
- Trigger Dot.Agents workflow on high-value orders (e.g. fraud check, VIP treatment)
- Auto-generate invoice in Dot.Finance on payment confirmation

---

## Inventory Management

- Real-time stock levels per variant and warehouse location
- Low-stock alerts and automatic reorder triggers
- Batch stock adjustments with reason codes
- Purchase order creation to suppliers
- Barcode and QR code label printing
- Stock valuation: FIFO and weighted average methods
- Dot.Analytics integration: stock turnover, dead stock, forecasting

---

## Payment Processing

- Stripe integration via Laravel Cashier: cards, Apple Pay, Google Pay
- Local payment method support (configurable per region)
- Escrow for marketplace transactions: payment held until fulfilment confirmed
- Split payments: platform commission deducted before seller payout
- Payout schedule: daily, weekly, or monthly to seller bank account
- Dispute and chargeback management workflow

---

## Shipping and Logistics

- Configurable shipping zones, rates, and methods per seller
- Carrier integration: rate lookup and label generation
- Free shipping thresholds
- Click-and-collect configuration for physical stores
- International shipping with customs declaration support
- Third-party logistics (3PL) fulfilment API hook

---

## Seller Intelligence Dashboard

Each seller sees a personalised intelligence view powered by Dot.Analytics.

| Metric | Description |
|---|---|
| Revenue today / week / month | Live transaction total |
| Top selling products | Ranked by units and revenue |
| Conversion rate | Visitors to buyers |
| Average order value | Trend over time |
| Cart abandonment rate | And abandoned cart recovery rate |
| Return rate | By product and by customer |
| Customer lifetime value | Segmented by cohort |
| Inventory alerts | Low stock and overstock signals |

---

## Ecosystem Integration

| Platform | Integration |
|---|---|
| Dot.Finance | Orders create transactions; payouts reconcile automatically |
| Dot.Engage | Buyers become contacts; order history enriches CRM records |
| Dot.Agents | Order processing, reorder triggers, and fulfilment workflows automated |
| Dot.Analytics | Full e-commerce analytics fed to the intelligence platform |
| Dot.Press | Storefront built on Dot.Press blocks with SEO |
| Dot.Auction | Auction lots listed simultaneously on Dot.Auction |
| Dot.Files | Product media and digital downloads stored in S3 |
| InfoDot | Seller and buyer identity; team-based store management |

---

## Revenue Model

Dot.Emall earns through subscription tiers and transaction commissions.

| Plan | Commission | Monthly fee | Notes |
|---|---|---|---|
| Free seller | 5% per sale | None | Up to 10 products |
| Starter | 3% per sale | Low | Up to 100 products |
| Business | 1.5% per sale | Medium | Unlimited products, analytics |
| Enterprise | 0.5% per sale | Custom | White-label, dedicated support |
