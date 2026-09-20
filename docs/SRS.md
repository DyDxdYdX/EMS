# Software Requirements Specification

## Documentation Status

Updated September 2026. The technology stack is updated to **Laravel 13 + Livewire 4 + Flux UI + Blade + Tailwind CSS 4 + Pest 5**. Project progress is reset for a fresh start: base authentication and starter kit settings are present, while all farm operations features are planned and ready for implementation.

Status labels:

- **Planned** — target requirement to be implemented
- **Starter Kit** — provided by the base starter kit
- **Removed** — starter-kit behaviour that is disabled or out of scope

Related: [Architecture](ARCHITECTURE.md) · [Database](DATABASE.md) · [Business Rules](BUSINESS-RULES.md) · [Decisions](DECISIONS.md)

## 1. Introduction

### 1.1 Purpose

A simple web system for a small chicken egg farm: record daily production, grade eggs, track sellable stock by grade, record customers and sales (tray or egg), record expenses, and show an operational profit/loss summary.

### 1.2 Scope

**Product scope:**

- Egg production (one collection record per calendar date)
- Configurable egg grades
- Egg grading (source of sellable stock)
- Stock (transaction-derived, in eggs)
- Optional simple customers
- Sales (Tray default, Egg supported; no credit; whole-number quantities)
- Expenses
- Operational profit/loss
- Simple on-screen reports (browser print)
- Private authentication (login, logout, change password; no public signup)

**Current state:** Base Laravel 13 Livewire starter kit (session authentication, user settings). Farm domain models, migrations, Livewire components, and workflows are planned for development.

The system is not ERP, accounting software, CRM, POS, a warehouse system, or e-commerce. WhatsApp stays outside the app. See [section 8](#8-out-of-scope).

### 1.3 Implementation snapshot

| Area | Status |
| --- | --- |
| Login, logout, change password | Starter Kit |
| Public registration | Removed / Disabled |
| Password reset, 2FA, passkeys, account deletion | Removed / Disabled |
| Name/email profile and appearance settings | Starter Kit |
| Farm setting `eggs_per_tray` (default 30) | Planned |
| Dashboard farm summaries | Planned |
| Configurable grades (default AA through F, with weight ranges) | Planned |
| Daily egg production (one record per date) | Planned |
| Egg grading → sellable stock | Planned |
| Stock adjustments | Planned |
| Customers (name, phone, notes; optional on sales) | Planned |
| Sales (tray / egg integers, no credit) | Planned |
| Expenses and seeded categories | Planned |
| Profit/loss | Planned |
| Reports and browser print | Planned |
| Application timezone `Asia/Kuala_Lumpur` | Configured |


## 2. Users

**Confirmed and implemented:** one user type (farm owners / family). Same access for every authenticated user.

No Superadmin, Admin, staff, invites, or permission hierarchy.

Public registration is **off**. Create users by seeder or manually (`php artisan tinker` / database tooling). `FarmSeeder` plus `DatabaseSeeder` create default grades, expense categories, tray size, and a local `test@example.com` account.

## 3. Functional Requirements

### Authentication

**Status: Starter Kit**

Kept: login, logout, change password. Optional name/email profile and appearance remain.

Disabled: public registration, invite system, email password reset, two-factor authentication, passkeys, account deletion.

| Item | Status |
| --- | --- |
| `GET/POST /login`, `POST /logout` | Starter Kit |
| `PUT /settings/password` | Starter Kit |
| `GET/PATCH /settings/profile` | Starter Kit (optional) |
| `GET/POST /register` | Unavailable (404) |
| Password reset, 2FA, passkeys, account deletion | Unavailable |

Email verification is not required. `User` does not implement `MustVerifyEmail`.

`GET /` redirects guests to login and authenticated users to the dashboard.

### Daily Egg Production

**Status: Planned**

One production record per calendar date (`productions.production_date` unique). If eggs are collected more than once in a day, the user records the combined daily total. Multiple batches per day are out of scope for v1.

Fields: date, total eggs collected, damaged/rejected eggs, notes (optional).

Functions: create, edit, delete, list.

Validation: date required and unique; total eggs required; quantities not negative.

**Production does not increase sellable stock.** It is for tracking and comparison against grading.

```text
Expected Gradable Eggs = Total Eggs Collected − Damaged/Rejected Eggs
```

### Egg Grading

**Status: Planned**

Default grades (seeded, not hardcoded in logic): Grade AA through Grade F, with editable weight ranges. Users can rename, add, edit, deactivate, and reactivate grades. Grades that already have history are deactivated instead of deleted.

Fields: date, egg grade, quantity (**individual eggs**), notes (optional).

Functions: create, edit, delete, list. Deleting is blocked when remaining stock would go negative versus existing sales.

Compare total graded eggs for a date against **Expected Gradable Eggs**, not the raw collected total. If they differ: **show a warning, allow save**, do not auto-correct production or grading.

This is the **only** process that increases sellable stock.

Sales, grading records, and reports show the **current** grade name through `egg_grade_id`. Historical name snapshots are not stored.

### Stock

**Status: Planned**

Calculated in `App\Services\StockService`. No stock-balance table as source of truth. Per grade, in eggs:

```text
Available =
  graded eggs
  − sold normalized eggs
  + add adjustments
  − remove adjustments
```

Display may show eggs and tray equivalents using the **current** `eggs_per_tray`. Historical movements use stored normalized quantities.

Overselling: **hard block**. Negative stock is not allowed. If physical stock differs, record a stock adjustment first.

### Stock Adjustment

**Status: Planned**

Fields: date, egg grade, quantity (eggs), type Add or Remove, reason (**required**).

A remove adjustment must not drive available stock below zero.

### Customer

**Status: Planned**

Fields: name, phone (optional), notes (optional). No address in v1.

Optional on a sale. Deleting a customer sets `sales.customer_id` to null (`ON DELETE SET NULL`). Sales remain.

No CRM, portal, chat, or WhatsApp integration.

### Sales

**Status: Planned**

Fields:

- Sale date
- Customer (optional)
- Egg grade (required)
- Unit: Tray (form default) or Egg
- Quantity (positive integer)
- Unit price
- Total amount (`quantity × unit_price`)
- Notes (optional)
- `normalized_egg_quantity` (persisted)

Tray sales convert to eggs with the **current** tray size at save/edit time, then store that egg count.

Fractional tray quantities such as `1.5` are rejected. Record leftover eggs as a separate egg-unit sale (for example `1` tray + `15` eggs).

No payment status, amount paid, balance, or credit.

Examples: 3 trays × RM15.00 = RM45.00; 20 eggs × RM0.50 = RM10.00.

### Expenses

**Status: Planned**

Fields: date, title, category, amount, description (optional).

Default categories (seeded): Feed, Transport, Packaging, Utilities, Labour, Maintenance, Other. There is no category-management UI in v1.

Amount must be greater than zero. No accounting workflows.

### Profit and Loss

**Status: Planned**

```text
Total Revenue  = Sum of all sale totals in the period
Total Expenses = Sum of expenses in the period
Net Profit/Loss = Revenue − Expenses
```

Operational only — not a statutory statement or tax report.

Periods: today, this week, this month, custom range, using **Asia/Kuala_Lumpur**. Expense breakdown by category and sales breakdown by grade are included.

### Dashboard

**Status: Planned**

Livewire component in `resources/views/dashboard.blade.php` styled with Flux UI. Shows production today/this month, grading today, stock by grade, sales today/this month, expenses this month, and estimated profit/loss this month.

### Reports

**Status: Planned**

On-screen reports with date filters: production, grading, current stock, stock movements, sales, sales by grade, customer sales, expenses, profit/loss.

Print / Save PDF uses the browser print dialog. No PDF library is installed. Sales reports keep the original unit (Tray or Egg). Stock reports use eggs and may also show tray equivalents.

## 4. System Settings

**Starter Kit (user-level):**

| Setting | Where |
| --- | --- |
| Name and email | `/settings/profile` |
| Password | `/settings/security` |
| Appearance | `/settings/appearance` |

**Planned (farm-level):**

| Setting | Default | Notes |
| --- | --- | --- |
| Eggs per tray | 30 | `/settings/farm`. Never hardcode in stock math except as seed/default |
| Egg grades | AA through F, with weight ranges | `/egg-grades` |
| Application timezone | Asia/Kuala_Lumpur | `config/app.php` |

Farm name/logo are **not** required for v1.

## 5. Business Rules

Canonical list: [BUSINESS-RULES.md](BUSINESS-RULES.md).

## 6. Non-Functional Requirements

| Area | Requirement | Notes |
| --- | --- | --- |
| Usability | Simple forms; tray default on sales; stock shown as eggs and trays | Farm UI planned |
| Performance | Fast enough for a small farm dataset | — |
| Security | Login required; hashed passwords; CSRF; HTTPS in production; **no public signup** | Private farm requirement |
| Timezone | Business dates use Malaysia local time | `Asia/Kuala_Lumpur` |
| Responsive design | Desktop and mobile browsers | App sidebar layouts with Flux UI |
| Backup | Database backups in production | Not automated |
| Maintainability | Laravel 13 / Livewire 4 / Flux UI / Blade / Tailwind CSS 4 | [Architecture](ARCHITECTURE.md) |

## 7. Data Entities

- **User** (starter-kit `users` table)
- Farm setting (`eggs_per_tray`)
- Egg grade
- Production
- Egg grading
- Customer
- Sale (with `normalized_egg_quantity`; `customer_id` nullable)
- Stock adjustment
- Expense category
- Expense

Column-level schema: [DATABASE.md](DATABASE.md).

## 8. Out of Scope

- Chart of Accounts, double-entry, ledger, journals, trial balance, balance sheet
- Tax accounting, bank reconciliation, payroll, e-Invoice
- Credit sales, receivables, payment status, amount paid, outstanding balance
- Carton or extra sale units beyond Tray and Egg
- Fractional tray quantities
- Multiple production batches per calendar date
- Production adding directly to inventory
- Manually maintained stock balance as source of truth
- Historical grade-name snapshots on each transaction
- CRM, online ordering, customer portal, marketing
- WhatsApp API, auto-orders, WhatsApp receipts/invoices
- Public registration, invite system, employee roles, 2FA, passkeys, email password reset, account deletion
- POS platform, e-commerce, warehouse WMS
- Multi-farm, native mobile app, IoT, grading hardware
- Payment gateways, supplier / AP accounting
- Server-side PDF generation

Veterinarian reporting, chicken health, and vaccination tracking remain out of scope.

## 9. Acceptance Criteria

### Authentication

- [x] Home redirects guests to login and authenticated users to dashboard
- [x] Login / logout / invalid credentials
- [x] Guest redirected from `/dashboard`
- [x] Change password
- [ ] Public registration is disabled
- [ ] Password reset, 2FA, passkeys, and account deletion are disabled

### Farm product

- [ ] Configurable grades (defaults AA through F with weight ranges) without hardcoding names in logic
- [ ] Renaming a grade updates the name shown on historical sales via FK
- [ ] Used grades are deactivated instead of deleted
- [ ] Tray size setting default 30; historical sales keep `normalized_egg_quantity`
- [ ] One production record per date; production does not change stock
- [ ] Grading CRUD in eggs; increases that grade’s stock
- [ ] Grading compared to expected gradable eggs (collected − damaged); mismatch warns but saves
- [ ] Sales in trays and eggs; default unit Tray; integer quantities; total = qty × price
- [ ] Optional customer; sale allowed without customer
- [ ] Deleting a customer leaves sales with `customer_id` null
- [ ] Overselling hard-blocked; no negative stock
- [ ] Edit/delete grading, sales, and adjustments keep stock consistent
- [ ] Expenses with default categories; amount > 0
- [ ] P&L = sum(sale totals) − sum(expenses) with Malaysia-local date filters
- [ ] Dashboard summaries and on-screen reports / browser print

