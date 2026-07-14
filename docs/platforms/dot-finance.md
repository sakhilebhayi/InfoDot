# Dot.Finance — Business Finance and Accounting Platform

**Role:** The Financial Operations Layer of the Dot Ecosystem  
**URL:** `finance.infodot.app`  
**Tagline:** Every rand, every transaction, every decision — in one place.

---

## Vision

Dot.Finance is not accounting software bolted onto the Dot ecosystem. It is the **financial intelligence and operations platform** where every monetary event across the organisation — from a closed deal in Dot.Engage to an equipment purchase tracked in fleet — flows into a single financial truth.

Traditional accounting platforms like Xero, QuickBooks, and Sage require manual data entry from other systems or expensive integrations. Dot.Finance receives transaction data directly from every Dot platform that generates financial events. Invoices are raised automatically when a deal closes. Expenses are captured when an order is placed. Budget consumption is visible in real time against project budgets in Dot.Projects.

---

## Architecture

```
Dot Ecosystem

├── Dot.Finance
│   ├── Chart of Accounts
│   ├── Invoicing and Billing
│   ├── Expense Management
│   ├── Bank Reconciliation
│   ├── Payroll Integration
│   ├── Financial Reporting
│   └── Cashier / Stripe Integration
│
└── Shared: PostgreSQL · Redis · Stripe (Laravel Cashier) · S3 (documents)
```

---

## Chart of Accounts

A customisable double-entry bookkeeping foundation.

- Standard account types: Assets, Liabilities, Equity, Revenue, Expenses
- Sub-account hierarchy up to 4 levels deep
- Account codes aligned to standard reporting frameworks
- Opening balances import from CSV or migration tool
- Multi-currency: base currency with live exchange rates

---

## Invoicing and Billing

Professional invoicing with payment integration.

### Invoice creation
- Auto-generate invoices when a deal closes in Dot.Engage
- Manual invoice creation with line items, taxes, and discounts
- Recurring invoices on configurable schedules
- Invoice templates with brand colours and logo
- Multi-currency with automatic conversion at invoice date rate

### Invoice lifecycle
```
Draft → Sent → Viewed → Partial Payment → Paid → Archived
             ↘ Overdue → Reminder sent → Written off
```

### Payment collection
- Stripe payment link embedded in invoice email (via Laravel Cashier)
- Card, ACH/EFT, and bank transfer acceptance
- Auto-match payment to invoice on receipt
- Payment receipt emailed to customer automatically

### Statements
- Customer account statements showing all invoices and payments
- Ageing report: 30/60/90/120+ days overdue
- Automated overdue reminders at configurable intervals

---

## Expense Management

Capture, approve, and categorise all business spending.

### Expense record
- Amount, currency, date, vendor, category
- Receipt image upload (Dot.Files integration)
- Project or cost centre allocation
- Billable flag: include in next customer invoice
- Reimbursable flag: include in payroll run

### Expense capture
- Mobile receipt photo → AI extracts amount, vendor, date, and suggests category
- Email forwarding: send receipts to a dedicated inbox for auto-capture
- Corporate card feed: bank transaction import with auto-matching
- Employee submission portal with approval workflow

### Approval workflow
- Configurable approval chains by amount and department
- Approvals via InfoDot notifications
- Bulk approve for routine low-value items
- Policy violation flags: over-limit, unapproved vendor, missing receipt

---

## Bank Reconciliation

- Bank feed import: OFX/QIF, CSV, or open banking API
- Auto-matching: payment matched to invoice, expense to transaction
- Manual match for unrecognised transactions
- Rule-based categorisation: transactions from a recurring vendor auto-assigned to account
- Unreconciled items surfaced daily in reconciliation queue

---

## Financial Reporting

### Core reports
```
Profit & Loss (Income Statement)      Balance Sheet
Cash Flow Statement                   Trial Balance
VAT / GST Return                      Aged Debtors
Aged Creditors                        Budget vs Actual
Project Profitability                 Payroll Summary
```

### Custom reports
- Filter any report by date range, department, project, cost centre, or account
- Drill down from summary to transaction level
- Schedule reports to email on a recurring basis
- Export to PDF, XLSX, or CSV
- Live Dot.Sheet integration: reports rendered as live spreadsheets

### Budget management
- Annual budget set by account and cost centre
- Monthly allocation with seasonal weighting option
- Real-time budget vs actual in every relevant view
- Budget variance alerts: threshold notifications via InfoDot

---

## Subscription and Billing Management (Cashier)

For organisations selling subscription-based products or services.

- Stripe integration via Laravel Cashier
- Subscription plans: monthly, annual, usage-based
- Trial periods, proration, and mid-period upgrades
- Cancellation flows with dunning management
- Subscription analytics: MRR, churn, LTV, ARR

---

## Ecosystem Integration

| Platform | Integration |
|---|---|
| Dot.Engage | Closed deals trigger automatic invoice creation |
| Dot.Projects | Project budgets tracked against real transactions |
| Dot.Forms | Payment-enabled forms create transactions directly |
| Dot.Files | Invoices, receipts, and statements stored in S3 |
| Dot.Agents | Invoice reminders, expense coding, and reconciliation automated |
| Dot.Analytics | Revenue, cash flow, and expense data feeds EIP |
| Dot.Sheet | Live financial data available via DOT.FINANCE() formula |
| InfoDot | Team-based access; payment tokens via Sanctum |

---

## Revenue Model

Dot.Finance is offered as a platform tier, not per-transaction pricing.

| Plan | Features | Pricing |
|---|---|---|
| Free | 5 invoices/month, basic expenses, single user | Included with InfoDot |
| Starter | Unlimited invoices, bank reconciliation, reports | Per-seat monthly |
| Business | Multi-currency, project finance, subscription billing, AI capture | Per-seat monthly |
| Enterprise | Multi-entity, consolidation, payroll API, advanced audit, SLA | Annual contract |
