# Testing Strategy

## Documentation Status

Updated September 2026. The test framework is **Pest 5** (`pestphp/pest`). Progress is reset for a fresh start: starter-kit authentication and dashboard tests are active and passing, while all farm domain test suites are planned for implementation.

Related: [SRS](SRS.md) · [Business Rules](BUSINESS-RULES.md)

## Test Stack

| Tool | Role |
| --- | --- |
| Pest 5 | Unit and feature test runner |
| Laravel HTTP & Livewire assertions | Feature and component tests |
| `RefreshDatabase` | SQLite `:memory:` |
| Pint, Larastan (level 7+) | PHP code style & static analysis |
| Dev tools | Laravel Boost, Pail, Pao |

Browser tests (Dusk / Playwright) are not used in v1.

`phpunit.xml`: `DB_CONNECTION=sqlite`, `MAIL_MAILER=array`, `QUEUE_CONNECTION=sync`, `SESSION_DRIVER=array`.

## Running Tests

```bash
php artisan test
php artisan test --compact
php artisan test --filter=testName
composer test
```

`composer test` clears config, runs Pint in parallel test mode, PHPStan analysis, and `php artisan test`.

## Test Categories

### Unit tests

Placeholder tests in `tests/Unit/`. Domain calculation logic for `StockService` and `ProfitLossService` will have dedicated unit tests.

### Feature tests (Current starter kit)

| File | What it covers | Status |
| --- | --- | --- |
| `tests/Feature/ExampleTest.php` | Home page renders / redirects | Passing |
| `tests/Feature/DashboardTest.php` | Guest → login; auth user can open dashboard | Passing |
| `tests/Feature/Auth/AuthenticationTest.php` | Login, invalid password, logout | Passing |
| `tests/Feature/Auth/RegistrationTest.php` | Register screen rendering and account creation | Passing |
| `tests/Feature/Auth/PasswordResetTest.php` | Reset password screens and flow | Passing |
| `tests/Feature/Auth/PasswordConfirmationTest.php` | Password confirmation screen | Passing |
| `tests/Feature/Settings/ProfileUpdateTest.php` | Profile update | Passing |
| `tests/Feature/Settings/SecurityTest.php` | Security password update | Passing |
| `tests/Feature/Settings/AppearanceTest.php` | Appearance setting updates | Passing |

### Farm feature tests (Planned)

| Planned Suite | Target Coverage |
| --- | --- |
| `tests/Feature/Farm/ProductionTest.php` | One record per date, collected/damaged validation, no stock impact |
| `tests/Feature/Farm/GradingTest.php` | Graded eggs increase sellable stock, mismatch warning logic |
| `tests/Feature/Farm/StockTest.php` | Transaction-derived stock, adjustments, overselling prevention |
| `tests/Feature/Farm/SalesTest.php` | Tray vs egg units, price calculation, normalized egg persistence |
| `tests/Feature/Farm/ExpensesTest.php` | Categories, amount > 0 validation |
| `tests/Feature/Farm/ProfitLossTest.php` | Operational revenue − expenses calculation with date filtering |
| `tests/Feature/Farm/ReportsTest.php` | On-screen report generation |

## Critical Test Scenarios

### Authentication

| Scenario | Covered now? |
| --- | --- |
| Valid login | Yes |
| Invalid login | Yes |
| Logout | Yes |
| Guest redirected from dashboard | Yes |
| Disable public registration in Fortify | Planned |
| Disable password reset & 2FA in Fortify | Planned |

### Production

| Scenario | Covered now? |
| --- | --- |
| Create / edit / delete | Planned |
| Reject negative quantities | Planned |
| Unique production date | Planned |
| Production does not change stock | Planned |

### Grading

| Scenario | Covered now? |
| --- | --- |
| Create grading | Planned |
| Grade-specific inventory increase | Planned |
| Production/grading mismatch warning vs expected gradable eggs | Planned |
| Mismatch can still be saved | Planned |
| Deleting grading blocked if it would strand sales | Planned |

### Tray and egg sales

| Scenario | Covered now? |
| --- | --- |
| Tray quantity converts to `normalized_egg_quantity` | Planned |
| Individual egg sale works | Planned |
| Mixed tray and egg sales on the same grade | Planned |
| Fractional tray quantity rejected | Planned |
| Overselling rejected | Planned |
| Selling Grade A does not reduce Grade B | Planned |
| Edit/delete sale recalculates stock | Planned |

Do **not** add tests for payment status, amount paid, or balance — credit sales are out of scope.

### Stock adjustments

| Scenario | Covered now? |
| --- | --- |
| Positive adjustment increases stock | Planned |
| Negative adjustment decreases stock | Planned |
| Reason is required | Planned |
| Oversized negative adjustment rejected | Planned |

### Tray size

| Scenario | Covered now? |
| --- | --- |
| Changing tray size does not rewrite historical `normalized_egg_quantity` | Planned |

### Customers and grades

| Scenario | Covered now? |
| --- | --- |
| Deleting a customer sets `sales.customer_id` null | Planned |
| Renaming a grade updates the name on historical sales | Planned |

### Expenses and profit / loss

| Scenario | Covered now? |
| --- | --- |
| Expenses and P&L use full sale totals | Planned |

Unpaid/partial revenue tests must **not** be written.

### Timezone

| Scenario | Covered now? |
| --- | --- |
| Application timezone is `Asia/Kuala_Lumpur` | Planned |

### Reports

| Scenario | Covered now? |
| --- | --- |
| Dedicated report Livewire / HTTP tests | Planned |
| Browser print | No (manual) |

