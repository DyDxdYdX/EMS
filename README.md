# Chicken Egg Production and Sales Management System

A lightweight web app for a small chicken egg farm. It records daily production, grades eggs into sellable stock, records tray or individual-egg sales, tracks expenses, and shows an operational profit/loss summary.

Business dates and “today / this week / this month” use **Asia/Kuala_Lumpur**.

## Problem

Small farms often keep production and sales in notebooks or spreadsheets. That makes it hard to:

- record daily egg collection consistently
- know how many **sellable** eggs are on hand by grade
- record tray and individual-egg sales after WhatsApp or walk-in orders
- see farm expenses in one place
- tell whether the farm is making or losing money

This project covers those operational needs without becoming a full accounting or ERP system. WhatsApp stays outside the app.

## Features

- Login, logout, and change password (no public registration)
- Optional name/email profile and appearance settings
- Configurable egg grades (defaults Grade AA through F, with weight ranges)
- Daily production (one record per date)
- Egg grading in individual eggs (this is what increases stock)
- Transaction-derived stock by grade, with tray-equivalent display
- Manual stock adjustments (eggs, reason required)
- Optional simple customers
- Tray (default) and individual-egg sales, with stored `normalized_egg_quantity`
- Expenses with default categories
- Operational profit/loss (today, week, month, custom range)
- Dashboard summaries and on-screen reports (print / Save PDF from the browser)

## Core Workflow

```text
Production
   ↓
Egg Grading
   ↓
Stock (eggs)
   ↓
Sales (tray or egg)
   ↓
Revenue

Expenses
   ↓
Profit / Loss Summary
```

Production does not add inventory. Only grading does. Sales are completed totals (no credit). WhatsApp orders are typed in by the owner.

## Screenshots

Screenshots have not been captured yet. Add files under `docs/screenshots/` when you want them on GitHub.

### Dashboard

Placeholder: `docs/screenshots/dashboard.png`

### Production

Placeholder: `docs/screenshots/production.png`

### Sales

Placeholder: `docs/screenshots/sales.png`

### Expenses

Placeholder: `docs/screenshots/expenses.png`

### Profit / Loss

Placeholder: `docs/screenshots/profit-loss.png`

## Tech Stack

| Area | Choice |
| --- | --- |
| Backend | PHP 8.3+, Laravel 13 |
| Frontend | Livewire 4 (with Blaze), Flux UI 2, Blade |
| Styling | Tailwind CSS 4, Vite 8 (`vite-plus`, `@tailwindcss/vite`, `laravel-vite-plugin`) |
| Database | MySQL in `.env.example`; SQLite in-memory for tests |
| Auth | Laravel Fortify sessions + Flux UI views |
| PDF | Browser print dialog on report pages |
| Tests | Pest 5 (`pestphp/pest`) |
| Static analysis & Dev tools | Larastan / PHPStan, Laravel Pint, Laravel Boost, Pail, Pao |
| Timezone | `Asia/Kuala_Lumpur` |

## Project Structure

| Path | Purpose |
| --- | --- |
| `app/Models/` | User model and planned farm domain models |
| `app/Livewire/` | Livewire components and single-file pages |
| `app/Services/` | Planned `StockService`, `ProfitLossService` |
| `routes/web.php` | Application routes |
| `resources/views/` | Blade templates, layouts (`layouts/app`), and Flux UI components |
| `database/migrations/` | Database schema migrations |
| `database/seeders/` | User, farm settings, grades, and category seeders |
| `tests/` | Pest feature and unit tests |
| `docs/` | Project documentation and specifications |

## Installation

Requirements: PHP 8.3+ (PHP 8.5 supported), Composer, Node.js 20.19+ (or 22.12+ for Vite 8), and MySQL if you follow `.env.example` (or SQLite).

```bash
git clone <repository-url>
cd EMS
cp .env.example .env
```

Create a database named `ems` (or adjust `.env` to SQLite/MySQL credentials).

```bash
composer setup
```

Or step-by-step:

```bash
composer install
php artisan key:generate
php artisan migrate
npm install
npm run build
```

To run locally in development:

```bash
composer run dev
```

The repository is freshly set up on the Laravel Livewire starter kit. Farm domain features (production, grading, stock, sales, expenses, and P&L) are documented in `docs/` and ready for implementation.

## Testing

```bash
php artisan test
composer test
```

Details: [docs/TESTING.md](docs/TESTING.md).

## Documentation

- [Software Requirements](docs/SRS.md)
- [Architecture](docs/ARCHITECTURE.md)
- [Database](docs/DATABASE.md)
- [Business Rules](docs/BUSINESS-RULES.md)
- [Testing](docs/TESTING.md)
- [Deployment](docs/DEPLOYMENT.md)
- [Technical Decisions](docs/DECISIONS.md)

## Scope

This is a small farm operations tool, not ERP, accounting software, CRM, POS, or e-commerce.

## License

MIT License.

