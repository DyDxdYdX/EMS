# Technical Decisions

## Documentation Status

Updated September 2026. All listed ADRs are accepted architectural decisions. The stack has been updated to **Laravel 13 + Livewire 4 + Flux UI + Blade + Tailwind CSS 4 + Pest 5**. Progress has been reset for new development: base starter kit auth & settings are active, and farm domain implementations are planned.

Related: [SRS](SRS.md) · [Architecture](ARCHITECTURE.md) · [Business Rules](BUSINESS-RULES.md)

---

## ADR-001: Simplified Operational Profit/Loss Instead of Full Accounting

**Status:** Accepted (Planned for `ProfitLossService`).

### Context

Farm owners need to see whether the farm made or lost money. They do not need statutory accounts.

### Decision

```text
Total Revenue     = Sum of all recorded sale totals
Total Expenses    = Sum of all expenses
Net Profit/Loss   = Total Revenue − Total Expenses
```

Every recorded sale counts at its full total. There is no credit, amount-paid, or cash-received split.

Do not implement chart of accounts, general ledger, double-entry, journals, trial balance, balance sheet, tax reports, or accounts payable.

Support period views: today, this week, this month, custom range, in **Asia/Kuala_Lumpur**. Break down expenses by category and sales by egg grade.

### Reason

Matches how the farm already thinks: money from egg sales minus money spent.

### Consequences

The P&L is an operational summary only. It is not a replacement for accounting software. See [BR-009](BUSINESS-RULES.md) and [BR-010](BUSINESS-RULES.md).

---

## ADR-002: Single User Permission Level

**Status:** Accepted (Starter Kit / Planned).

### Context

Users are farm owners / family members on one farm.

### Decision

One user type. All authenticated users have the same access. No Superadmin, Admin, staff roles, permission hierarchy, public signup, or invite system.

Accounts are created manually or seeded. Required functions: login, logout, and change password. Optional name/email profile remains.

### Reason

A family-run farm does not need employee permission matrices.

### Consequences

Anyone with an account can see all farm data.

---

## ADR-003: Transaction-Derived Stock

**Status:** Accepted (Planned for `StockService`).

### Context

Stock must stay correct when grading, sales, and adjustments are edited or deleted.

### Decision

Do **not** maintain a manually editable stock balance as the source of truth.

Calculate per grade:

```text
Available eggs =
  graded eggs − sold normalized eggs + add adjustments − remove adjustments
```

No hidden reversal rows. Edits and deletes change the underlying transactions; stock is the sum of what remains.

### Reason

A stored balance is easy to desync. Recalculation from history matches the confirmed edit/delete rules.

### Consequences

Queries aggregate movements. Overselling and unsafe deletes are validated against that aggregate. See [ADR-011](#adr-011-grading-as-the-source-of-sellable-inventory).

---

## ADR-004: Optional Minimal Customer Records

**Status:** Accepted (Planned). Delete behaviour is [ADR-018](#adr-018-customer-delete-sets-sales-customer_id-null).

### Context

Most orders arrive on WhatsApp and are written down by the owner. Many sales have no formal customer.

### Decision

Optional customer: name, optional phone, optional notes. A sale may omit `customer_id`.

Do not build CRM, pipelines, address books beyond these fields, customer portals, or marketing.

### Reason

The farm needs an optional name on a sale, not a CRM.

### Consequences

Customer sales reports only include sales that have a customer. Walk-in / informal sales still count in revenue and stock.

---

## ADR-005: Simple Expense Tracking

**Status:** Accepted (Planned).

### Context

Expenses exist only so profit/loss can subtract costs from sales.

### Decision

Dated expenses with title, required category, amount (> 0), and optional description.

Default categories: Feed, Transport, Packaging, Utilities, Labour, Maintenance, Other. Seeded; no category-management UI in v1.

No ledger, suppliers, or accounts payable.

### Reason

Labels are enough for an operational expense breakdown.

### Consequences

Category totals on the P&L are groupings of these rows, not accounting accounts.

---

## ADR-006: Laravel + Livewire + Flux UI Monolith (Supersedes Vue/Inertia)

**Status:** Accepted.

### Context

The application repository was updated from the Laravel Vue/Inertia starter kit to the Laravel Livewire starter kit (Livewire 4 with Blaze, Flux UI 2, Blade templates, Tailwind CSS 4).

### Decision

Build a unified Laravel 13 full-stack monolith using Livewire 4, Flux UI, and Blade. Do not use Vue 3, Inertia.js, or separate client-side JS SPAs. No public REST API (`routes/api.php` is absent).

### Reason

Livewire 4 paired with Flux UI provides clean, server-driven reactive interfaces, robust form validation, and modern UI components without the overhead and synchronization complexity of a decoupled frontend framework.

### Consequences

All UI state and validation remain server-side in PHP/Blade. Toast alerts, modals, and reactive updates leverage Flux UI and Alpine.js. WhatsApp stays a manual side channel ([ADR-014](#adr-014-whatsapp-remains-outside-the-application)).

---

## ADR-007: Fortify for Session Login Only

**Status:** Accepted (Starter Kit / Planned for auth streamlining).

### Context

The starter kit enabled Fortify registration, reset passwords, 2FA, and passkeys.

### Decision

Keep Fortify for login, logout, and change password. Disable every other Fortify feature (`config/fortify.php` `features` empty or tailored). See [ADR-008](#adr-008-disable-public-registration) and [ADR-020](#adr-020-simplified-authentication-scope).

### Reason

Reuse proven session auth without shipping unused starter-kit account features.

### Consequences

Unused Fortify routes/views are disabled.

---

## ADR-008: Disable Public Registration

**Status:** Accepted (Planned to disable registration in `config/fortify.php`).

### Context

Confirmed rule: the system is only for farm owners / family; no public signup. Combined with ADR-002, any registrant would have full access.

### Decision

Disable Fortify registration. Create users with a seeder or artisan. No invite flow.

### Reason

The farm is a private tool, not a multi-tenant signup product.

### Consequences

Login has no Sign up link. Tests assert registration is unavailable.

---

## ADR-009: Reports Use On-Screen Views and Browser Print

**Status:** Accepted (Planned).

### Context

Confirmed reports include production, grading, stock, sales (preserving original unit), expenses, and P&L, with date filters and print/PDF.

### Decision

Ship on-screen report pages. Print / Save PDF uses the browser print dialog. Do not add a PDF library in v1.

### Reason

Avoid unused dependencies for a small farm tool.

### Consequences

There is no server-generated PDF file. Users print or save from the browser.

---

## ADR-010: Eggs as the Normalized Inventory Unit

**Status:** Accepted (Planned).

### Context

Sales may be in trays or individual eggs. Tray size can change.

### Decision

All stock math uses individual eggs. Tray size is a setting (default 30), never hardcoded in business logic.

### Reason

One unit avoids mixing trays and eggs in the stock formula.

### Consequences

The UI may show tray equivalents for display. Internal checks always use eggs. See [ADR-013](#adr-013-store-normalized-egg-quantity-on-sales).

---

## ADR-011: Grading as the Source of Sellable Inventory

**Status:** Accepted (Planned).

### Context

Production is a collection count. Not every collected egg is sellable as a given grade.

### Decision

```text
Production → Grading → Sellable stock → Sales
```

Production does **not** increase inventory. Only grading quantity (in eggs) does.

### Reason

Grade A/B/C stock only exists after eggs are graded.

### Consequences

A day can have production without stock change until grading is entered. Comparison uses expected gradable eggs ([ADR-015](#adr-015-compare-grading-to-expected-gradable-eggs)).

---

## ADR-012: Tray Default, Egg Also Supported

**Status:** Accepted (Planned).

### Context

The farm usually sells by tray, but sometimes sells individual eggs. Cartons are not needed in v1.

### Decision

Sale units: **Tray** (form default) and **Egg**. No carton or other units.

Quantities are positive integers only ([ADR-017](#adr-017-integer-tray-and-egg-quantities)).

### Reason

Matches how orders are quoted, without warehouse unit complexity.

### Consequences

The sales form converts trays to eggs for stock using the current tray size, then persists the normalized quantity.

---

## ADR-013: Store Normalized Egg Quantity on Historical Sales

**Status:** Accepted (Planned).

### Context

If tray size later changes from 30 to 24, old “3 trays” must still mean 90 eggs for stock, not 72.

### Decision

Each sale stores at least:

- `unit` (`tray` or `egg`)
- `quantity` (whole trays or eggs as sold)
- `unit_price`
- `total_amount`
- `normalized_egg_quantity` (eggs at save time)

Historical inventory uses `normalized_egg_quantity` only. Current tray size is for **new** tray sales and for **display** of remaining stock.

### Reason

Keeps past stock movements stable when the setting changes.

### Consequences

Editing a **tray** sale recalculates normalized eggs from the tray size **at edit time**. That is a new transaction state, not a rewrite of other historical rows.

---

## ADR-014: WhatsApp Remains Outside the Application

**Status:** Accepted.

### Context

Orders typically arrive on WhatsApp.

### Decision

No WhatsApp API, inbound messages, auto-created orders, or outbound receipts/invoices.

Workflow: customer messages on WhatsApp → owner records the sale in the app → stock and revenue update.

### Reason

Integration would turn a small farm tool into a messaging/POS platform.

### Consequences

The app will never “know” about an order until a user types it. That is intentional.

---

## ADR-015: Compare Grading to Expected Gradable Eggs

**Status:** Accepted (Planned).

### Context

Collected eggs include damaged/rejected eggs that should not be treated as missing from grading.

### Decision

```text
Expected Gradable Eggs = Total Eggs Collected − Damaged/Rejected Eggs
```

Compare total graded eggs for a date against that expected amount, **not** the raw collected total.

If `Total Graded ≠ Expected Gradable Eggs`: show a warning, **allow save**, and do **not** automatically alter production or grading data.

### Reason

Damaged eggs are already excluded from what should be graded. A hard block would stop legitimate counting differences.

### Consequences

The warning is a flash toast after save. Production uniqueness and grading rows stay independent.

---

## ADR-016: One Production Record Per Calendar Date

**Status:** Accepted (Planned).

### Context

Eggs may be collected more than once in a day. Multiple batch rows would complicate the grading comparison.

### Decision

One production record per calendar date. `production_date` is unique.

Fields: date, total eggs collected, damaged/rejected eggs, notes.

If eggs are collected multiple times, the user records the combined daily total. Multiple production batches per day are out of scope for v1.

### Reason

Keeps the daily production vs grading comparison simple.

### Consequences

There is no batch or shift entity. Edit the same daily row if a later collection happens.

---

## ADR-017: Integer Tray and Egg Quantities

**Status:** Accepted (Planned).

### Context

Individual egg sales already exist, so fractional trays are unnecessary.

### Decision

If unit = tray, quantity must be a positive integer. If unit = egg, quantity must be a positive integer.

Do not allow values such as `1.5` trays. Record `1` tray plus `15` eggs instead.

### Reason

Avoids half-tray stock math while still supporting leftover eggs.

### Consequences

`sales.quantity` is an unsigned integer column.

---

## ADR-018: Customer Delete Sets Sales `customer_id` Null

**Status:** Accepted (Planned).

### Context

Customer records are optional. Historical sales must remain if a name is later removed.

### Decision

`sales.customer_id` is nullable. Foreign key: **ON DELETE SET NULL**. Do not cascade-delete sales.

### Reason

Sales are financial/stock history. They must not disappear with the optional customer record.

### Consequences

Customer sales reports omit rows whose customer was deleted. Revenue and stock still include those sales.

---

## ADR-019: Grade Names Live on the Grade Record

**Status:** Accepted (Planned).

### Context

Grades can be renamed. Historical sales and reports need a display name.

### Decision

Sales, grading records, and reports reference the grade through `egg_grade_id` only. Do not store a snapshot label on each transaction.

If a grade is renamed, historical records show the **current** name. Prefer deactivation instead of deletion for grades that have already been used.

### Reason

Snapshots are extra complexity not required for v1. The farm wants the latest grade name on history.

### Consequences

Renames rewrite the apparent history of labels. That is accepted. Used grades cannot be hard-deleted while related rows exist (`restrictOnDelete` plus application deactivation).

---

## ADR-020: Simplified Authentication Scope

**Status:** Accepted (Planned to streamline Fortify).

### Context

The starter kit shipped registration, invites-adjacent flows, email password reset, 2FA, passkeys, account deletion, and a complex security page.

### Decision

Keep only login, logout, and change password. Optional name/email profile and appearance remain.

Remove/disable public registration, invite system, email password reset, two-factor authentication, passkeys, account deletion, and extra profile features.

Users are created manually or via seed/database tooling. All authenticated users have the same access level.

### Reason

A private family farm tool does not need self-service account lifecycle features.

### Consequences

`config/fortify.php` features array is tailored to disable unused features. Auth tests assert the unused routes are unavailable.

---

## ADR-021: Application Timezone Asia/Kuala_Lumpur

**Status:** Accepted and configured.

### Context

Daily production, sales, expenses, and “today / this week / this month” must match Malaysia local calendar dates, not UTC day boundaries.

### Decision

Set `config/app.php` timezone to `Asia/Kuala_Lumpur`. Do not use UTC for application-level date grouping.

`App\Support\BusinessDate` computes today, week, and month in that timezone. Database timestamps may follow framework conventions, but business-date calculations use Malaysia local time.

### Reason

The farm operates in Malaysia.

### Consequences

Dashboard, P&L, reports, and unique production dates all use the same local calendar.

