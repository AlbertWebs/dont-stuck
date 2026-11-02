# Don't Stuck Solutions — Corporate Website & Loan Management System

Laravel 13 application for the Don't Stuck Solutions public website and internal loan operations. The public site captures applications into the same database used by the staff portal. Application documents are stored on Laravel's private local disk and are served only after staff authorization.

## Requirements

- PHP 8.3 or later with Laravel's standard extensions
- Composer 2
- Node.js 20.19+ or 22.12+ with npm
- SQLite for local development, or a supported production database

## Local setup

```powershell
composer install
npm install
Copy-Item .env.example .env
php artisan key:generate
```

Set `DB_CONNECTION=sqlite` in `.env`, then create the local database file if it is missing:

```powershell
New-Item -ItemType File -Path database/database.sqlite -Force
php artisan migrate --seed
npm run build
php artisan serve
```

The public website is available at `http://127.0.0.1:8000`. Development seeding adds the two DSS loan products with financial terms intentionally unset. No staff login is seeded. Create the first administrator from the terminal; the command prompts for a password of at least 12 characters:

```powershell
php artisan lms:create-administrator admin@example.com "System Administrator"
```

Sign in at `/login`. An administrator can create and manage staff roles under Users & Roles.

## Financial rules and controls

The source application forms mention 20% monthly interest, a 3% processing fee, a KSh 200 commissioning fee and KSh 200/day late penalty. The forms do not define calculation and allocation rules. For that reason, product rates and fee rules are stored as unset and disbursement is blocked until an administrator configures them.

The product settings include:

- Monthly rate and interest basis: flat monthly on original principal with equal principal installments, or reducing monthly on the remaining principal. No interest compounding is applied.
- Processing fee rate and fixed commissioning fee.
- Fee collection: deducted from the confirmed payout or due with the first installment.
- Late fee per day, the date penalty accrual begins (due date or the following day), allocation priority and optional default threshold. No default classification is applied when the threshold is blank.

Disbursement confirms a manual payout, snapshots the then-current product rules onto a loan account, records withheld or scheduled charges and creates monthly installments. M-Pesa is a method/reference placeholder only: the application makes no Daraja requests or simulated payouts. Repayment posting uses integer cents, records allocation rows, and rejects duplicate external references. Corrections use reversals that preserve the original transaction. The daily status refresh accrues configured calendar-day penalties and updates overdue/default labels. A backdated payment cannot precede penalties that have already accrued.

## Roles

- **Administrator:** staff accounts and roles, product terms, application oversight, portfolio data and reports.
- **Loan officer:** assigned applications and related borrower/loan information.
- **Finance officer:** finance workflow, disbursements, receipts and account schedules.
- **Management:** application decisions and portfolio oversight.
- **Client:** email-code access to their own dashboard, applications, loans, documents and profile.

Backend middleware and controller checks enforce role access. The first administrator command is the only initial account bootstrap.

Borrowers with an email address on an application can request a one-time sign-in code at `/client/login`. Codes are single-use, expire after 10 minutes and are stored hashed. Configure a real outbound mail transport before enabling client sign-in in production; the local default mailer is `log` and does not deliver to inboxes.

## Scheduled status refresh

The command recalculates overdue installment, overdue account and configured default statuses:

```powershell
php artisan lms:refresh-loan-statuses
```

Laravel schedules it daily at 00:05. For local live scheduling, run `php artisan schedule:work`. In production, configure the platform scheduler to invoke `php artisan schedule:run` once per minute. Keep the scheduler active so dashboard status labels stay current.

## Build, tests and code style

```powershell
npm run build
php artisan test
vendor/bin/pint --format agent
```

Tests use the in-memory SQLite database configured by `phpunit.xml` and do not require production data.

## Production deployment notes

1. Configure a supported database, `APP_KEY`, HTTPS, mail, cache and session drivers in the deployment environment. Do not commit production secrets.
2. Run `composer install --no-dev --optimize-autoloader`, `npm ci`, and `npm run build` during release preparation.
3. Run `php artisan migrate --force`; do not seed production with development products or staff.
4. Create the first administrator with `php artisan lms:create-administrator ...` through a protected deployment console.
5. Ensure `storage/` and `bootstrap/cache/` are writable by the application process. Keep application documents on private storage and serve them through the authorized controller, never a public symlink.
6. Configure the daily scheduler, database backups, log rotation, monitoring and an HTTPS reverse proxy before accepting real applications.

## Current reporting and configuration boundaries

The client portal provides an account dashboard, application tracking, a private document vault with secure upload/download, loan schedules, read-only payment history and profile details. Payment initiation/autopay, tax forms, special offers and secure messaging are not connected to the current LMS. The staff portal provides database-backed application, loan, repayment and overdue lists; dashboard summaries; configurable reports for applications, disbursements, repayments, outstanding balances, overdue accounts and borrower statements; Excel-compatible `.xls` and downloadable PDF exports; and the existing CSV application export. Report filters include dates, product, officer, borrower and status. The on-screen report table shows up to 100 rows; exports include the full filtered result. Administrators can edit the fixed Home, About and Contact page fields, publication status and SEO metadata from Website Content. Loan terms are configurable and unset by default; an authorized administrator must set the institution's rules before disbursement.
