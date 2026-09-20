# Business Rules

## Documentation Status

Updated September 2026. Confirmed farm and auth rules below define the domain business specifications. Status is reset for new development: base starter kit auth & settings are active, and farm domain rules are **Accepted (Planned)** for implementation on the **Laravel 13 + Livewire 4 + Flux UI** stack.

Related: [SRS](SRS.md) · [Database](DATABASE.md) · [Decisions](DECISIONS.md)

Status labels:

- **Accepted (Planned)** — specified rule planned for implementation
- **Starter Kit** — enforced in base starter kit
- **Configured** — configured in framework setting
- **Removed** — starter-kit behaviour that is disabled or out of scope

---

## Authentication and users

| ID | Rule | Status |
| --- | --- | --- |
| BR-007 | One user type. All authenticated users have the same access. No Superadmin, Admin, staff roles, or permission hierarchy. | Starter Kit (no roles exist) |
| BR-011 | Login uses email + password. Passkeys are disabled. | Starter Kit |
| BR-012 | Login is rate-limited (5 attempts per minute per email+IP). | Starter Kit |
| BR-013 | Passwords are hashed. | Starter Kit |
| BR-014 | Changing password requires the current password. | Starter Kit |
| BR-015 | Account deletion is not offered. | Removed |
| BR-055 | Application business dates use **Asia/Kuala_Lumpur**. Today / this week / this month and daily farm records use Malaysia local time, not UTC grouping. | Configured |
| BR-016 | Email must be unique. | Starter Kit |
| BR-017 | Public registration is **disabled**. Accounts are created manually or seeded. No invite system. | Planned |
| BR-006 | Only logged-in users may use farm screens. | Planned |
| BR-038 | Required auth functions are login, logout, and change password. Optional name/email profile remains. | Starter Kit |

Email verification is **not** a confirmed farm requirement. `User` does not implement `MustVerifyEmail`.

---

## Egg grades

| ID | Rule | Status |
| --- | --- | --- |
| BR-034 | Default grades are Grade AA through Grade F, with editable weight ranges. | Accepted (Planned) |
| BR-035 | Grades are configurable: rename, add, edit, deactivate, reactivate. Do not hardcode A/B/C in application logic. | Accepted (Planned) |
| BR-036 | Historical grading, sales, and adjustments keep a reference to the grade used at the time (`egg_grade_id`). Deactivate instead of deleting a grade that has history. | Accepted (Planned) |
| BR-056 | Sales, grading, and reports display the **current** grade name via the foreign key. Grade names are not snapshotted onto each transaction. | Accepted (Planned) |

Do not use “Small”, “Cracked / damaged”, or other extra grades as built-in defaults. The user may add those later as custom grades.

---

## Production

Daily production records how many eggs were **collected**. It does **not** increase sellable stock.

| ID | Rule | Status |
| --- | --- | --- |
| BR-019 | Production date is required. | Accepted (Planned) |
| BR-020 | Total eggs collected is required. | Accepted (Planned) |
| BR-001 | Quantities cannot be negative. | Accepted (Planned) |
| BR-028 | Production does **not** add to sellable inventory. | Accepted (Planned) |
| BR-057 | One production record per calendar date. `production_date` is unique. Multiple collections in a day are entered as the combined daily total. Multiple batches per day are out of scope for v1. | Accepted (Planned) |

Fields: date, total eggs collected, damaged/rejected eggs, notes (optional).

Expected Gradable Eggs = total eggs collected − damaged/rejected eggs.

---

## Egg grading

Only grading creates sellable stock.

| ID | Rule | Status |
| --- | --- | --- |
| BR-029 | Sellable stock increases only from grading quantity (individual eggs). | Accepted (Planned) |
| BR-021 | Grading quantity cannot be negative. | Accepted (Planned) |
| BR-039 | Grading quantity is recorded in individual eggs. | Accepted (Planned) |
| BR-022 | Compare grading against **Expected Gradable Eggs** (`total collected − damaged/rejected`), not the raw collected total. If total graded ≠ expected gradable: **warn**, **allow save**, do not hard-block, and do not auto-fix either record. | Accepted (Planned) |

Example warning:

> Total graded eggs do not match expected gradable eggs (collected minus damaged/rejected). Please verify the quantities before continuing.

Mismatch can happen because of counting differences or other operational reasons. Damaged/rejected eggs are already excluded from the expected amount.

Fields: date, egg grade, quantity (eggs), notes (optional).

---

## Sales units and tray size

| ID | Rule | Status |
| --- | --- | --- |
| BR-032 | Supported sale units in v1: **Tray** (default on the sales form) and **Egg**. No carton or other units. | Accepted (Planned) |
| BR-031 | Eggs per tray is a system setting. Default **30**. Do not hardcode 30 in business logic. | Accepted (Planned) |
| BR-030 | Inventory always uses **individual eggs** as the normalized unit. | Accepted (Planned) |
| BR-033 | Each sale stores `normalized_egg_quantity` calculated at save time. Later tray-size changes must not alter historical stock impact. | Accepted (Planned) |
| BR-058 | Sale quantity must be a **positive integer** for both tray and egg units. Fractional trays such as 1.5 are not allowed. Record leftover eggs as a separate egg-unit sale. | Accepted (Planned) |

Conversion at sale time:

```text
If unit = Tray:  normalized_egg_quantity = quantity × eggs_per_tray (current setting)
If unit = Egg:   normalized_egg_quantity = quantity
```

Examples:

- 3 trays × 30 eggs/tray → 90 eggs stored
- 20 eggs → 20 eggs stored

Display of current stock may convert eggs → trays using the **current** tray size. That conversion is display-only.

---

## Sales

| ID | Rule | Status |
| --- | --- | --- |
| BR-040 | Every sale is linked to one egg grade. | Accepted (Planned) |
| BR-041 | Customer is optional. Walk-in / informal WhatsApp orders may be recorded with no customer. | Accepted (Planned) |
| BR-003 | Total amount = quantity × unit price. | Accepted (Planned) |
| BR-004 | Recording a sale deducts `normalized_egg_quantity` from that grade’s available stock. | Accepted (Planned) |
| BR-037 | No credit sales. No paid/unpaid/partial status, amount paid, balance, or receivables. Every recorded sale is completed. | Accepted (Planned) |
| BR-009 | Revenue uses the **full sale total** of every recorded sale. | Accepted (Planned) |

Fields: sale date, customer (optional), egg grade, unit (Tray or Egg), quantity, unit price, total amount, notes (optional). Also persist `normalized_egg_quantity`.

Examples:

- 3 trays × RM15.00 = RM45.00 (90 eggs if tray size is 30)
- 20 eggs × RM0.50 = RM10.00

---

## Customers and WhatsApp

| ID | Rule | Status |
| --- | --- | --- |
| BR-042 | Customer records are optional and minimal: name, phone (optional), notes (optional). | Accepted (Planned) |
| BR-043 | WhatsApp stays outside the system. The user takes the order on WhatsApp, then types the sale in the app. | Accepted (Planned) |
| BR-059 | Deleting a customer must not delete sales. `sales.customer_id` is nullable with **ON DELETE SET NULL**. | Accepted (Planned) |

Do not build CRM, online ordering, chat, WhatsApp API, receipts/invoices via WhatsApp, customer portal, or marketing.

Typical flow:

```text
WhatsApp order (outside the app)
  → User records the sale
  → Stock deducted (normalized eggs)
  → Full sale total counted as revenue
```

---

## Stock

Inventory is **transaction-derived** by grade. Do not keep a manually editable stock balance as the source of truth.

```text
Available Stock (grade, in eggs) =
    Total graded egg quantity for that grade
  − Total sold normalized egg quantity for that grade
  + Positive stock adjustments (eggs)
  − Negative stock adjustments (eggs)
```

| ID | Rule | Status |
| --- | --- | --- |
| BR-023 | Hard-block a sale whose normalized egg quantity exceeds available stock for that grade. | Accepted (Planned) |
| BR-024 | Negative stock is not allowed in v1. | Accepted (Planned) |
| BR-044 | Selling one grade must not change another grade’s stock. | Accepted (Planned) |
| BR-045 | If physical stock differs from system stock, the user must create a stock adjustment first. | Accepted (Planned) |
| BR-046 | Stock display may show eggs and equivalent trays (`full trays + leftover eggs`) using the **current** tray size. Movements always use stored normalized quantities. | Accepted (Planned) |

Example:

- Graded Grade A: 300 eggs
- Sale: 2 trays (tray size 30) → 60 eggs → stock 240
- Sale: 10 eggs → stock 230
- Display at tray size 30: 230 eggs = 7 trays + 20 eggs

Overselling example (50 eggs available, tray size 30):

- Allowed: 1 tray; 50 eggs; or 1 tray and 20 eggs as separate sales
- Not allowed: 2 trays; 51 eggs

### Stock adjustments

| ID | Rule | Status |
| --- | --- | --- |
| BR-005 | Adjustment reason is required. | Accepted (Planned) |
| BR-047 | Adjustment quantity is in individual eggs. Type is Add or Remove. | Accepted (Planned) |
| BR-048 | A negative adjustment must not drive available stock below zero. | Accepted (Planned) |

Example reasons: broken eggs, damaged eggs, missing stock, counting correction, manual correction, other.

### Edit and delete (stock must stay correct)

Preferred design: **recalculate from transaction history**. Do not insert hidden reversal rows unless the architecture requires it.

| Event | Confirmed behaviour |
| --- | --- |
| Production create/edit/delete | Does not change stock. May change the grading-mismatch warning for that date. |
| Grading created | Increases that grade’s stock by the egg quantity. |
| Grading edited | Stock uses the new quantity. |
| Grading deleted | Its quantity leaves the calculation. **Block delete** if remaining stock would be insufficient for existing sales (or otherwise keep stock non-negative). |
| Sale created | Deduct `normalized_egg_quantity` after the overselling check. |
| Sale edited | Ignore the old normalized quantity, apply the new one, and re-run the overselling check (using stock as if the sale were not already deducted). Recalculate `normalized_egg_quantity` from the **current** tray size if the unit is Tray. |
| Sale deleted | Previously deducted eggs become available again. |
| Adjustment created/edited/deleted | Recalculate from the remaining adjustment rows. Block a change that would make available stock negative. |

Stock is calculated in `App\Services\StockService`. There is no stored running balance.

---

## Expenses

| ID | Rule | Status |
| --- | --- | --- |
| BR-002 | Amount must be greater than zero. | Accepted (Planned) |
| BR-027 | Date, title, category, and amount are required. Description is optional. | Accepted (Planned) |
| BR-049 | Default categories: Feed, Transport, Packaging, Utilities, Labour, Maintenance, Other. Seeded in `expense_categories`. There is no category-management UI in v1. | Accepted (Planned) |

No chart of accounts, journals, ledger, suppliers, or accounts payable.

---

## Profit / loss

| ID | Rule | Status |
| --- | --- | --- |
| BR-009 | Total Revenue = sum of all recorded sale **totals** in the period. | Accepted (Planned) |
| BR-050 | Total Expenses = sum of expense amounts in the period. | Accepted (Planned) |
| BR-010 | Net Profit/Loss = Total Revenue − Total Expenses. Operational summary only — not a statutory P&L, tax report, or accounting package. | Accepted (Planned) |
| BR-051 | Support views for today, this week, this month, and a custom date range. Where practical also show expense breakdown by category and sales breakdown by egg grade. | Accepted (Planned) |

Do not use cash-received or collection logic. There is no credit tracking.

---

## Reports

| ID | Rule | Status |
| --- | --- | --- |
| BR-052 | Simple on-screen reports with date filters, print, and PDF where relevant. | Accepted (Planned) |
| BR-053 | Sales reports keep the original unit (Tray or Egg), quantity, unit price, and total. | Accepted (Planned) |
| BR-054 | Stock reports use eggs as the source quantity and may also show tray equivalents. | Accepted (Planned) |

On-screen reports: production, grading, current stock, stock movements, sales, sales by grade, customer sales, expenses, profit/loss. Print / Save PDF uses the browser print dialog. No server-side PDF library is installed.

---

## UI confirmation

BR-008 (confirm before delete) is **Planned** for production, grading, sales, expenses, customers, grades, and stock adjustments using Flux UI confirmation dialogs or modals. Account deletion is not offered.

