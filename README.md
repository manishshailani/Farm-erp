# Farm ERP, Livestock, Orchard & E-Commerce Platform

An integrated farm-management system (livestock/RFID, veterinary records,
inventory, employees/tasks, accounts, orchard & pond master data) plus a
public e-commerce storefront and customer portal, sharing one database so
customer orders update stock and the financial ledger automatically.

**Read `docs/FINAL_ACCEPTANCE_REPORT.md` first.** It states plainly what
was built, what was tested where and how, and what is explicitly out of
scope for this delivery (a payment gateway, outbound email/SMS, tax/GST
handling, a real RFID serial-reader bridge, and orchard/pond maintenance
scheduling or automated water-quality alerting -- orchard and pond
register-and-log workflows themselves are built). This README is the
"how to run it" document; that report is the "what you actually got"
document.

## Quick start (XAMPP on Windows)

Full step-by-step instructions, including screenshots-free explanations
for a non-expert, are in `docs/XAMPP_INSTALL_GUIDE.md`. In short:

1. Install XAMPP (PHP 8.3+, MySQL/MariaDB, Apache).
2. Copy this `farm-erp` folder into `C:\xampp\htdocs\farm-erp`.
3. Create a MySQL database named `farm_erp` (phpMyAdmin or the `mysql` CLI).
4. Copy `.env.example` to `.env` and fill in your database credentials.
5. Open a terminal in the project folder and run:
   ```
   php bin/console.php migrate
   php bin/console.php seed
   php bin/console.php create-admin
   ```
6. Browse to `http://localhost/farm-erp/public/` (public shop) or
   `http://localhost/farm-erp/public/login` (staff sign-in).

## Project layout

```
farm-erp/
  public/            Web server document root (front controller + .htaccess)
  src/
    Core/             Framework-level code: router, DB, auth, RBAC, audit, CSRF...
    Controllers/       Admin/* (staff portal) and Shop/* (public site)
    Models/            Thin query helpers, one per table/aggregate
    Services/          All business logic and DB transactions live here
  resources/views/     Plain-PHP templates (no templating engine dependency)
  database/
    migrations/mysql   Authoritative schema for production (XAMPP/MySQL)
    migrations/sqlite  Same schema, SQLite dialect, used by the test suite
    seeds/             Idempotent roles/permissions/reference-data seeders
  bin/console.php     migrate / seed / create-admin / reset-demo-data
  tests/              Automated test suite (run with `php tests/run.php`;
                       see "Running the test suite" below)
  scripts/            Backup/restore shell scripts
  docs/               Everything else: install guide, manuals, data model,
                       RFID setup, financial assumptions, acceptance report
```

## Why no Composer dependencies are vendored

This project was first built and tested inside a sandboxed environment
with no outbound network access to Packagist or to phar.phpunit.de (see
`docs/FINAL_ACCEPTANCE_REPORT.md` for the exact errors). Rather than
leave placeholder `vendor/` code or claim a `composer install` that was
never actually run, every piece of this application is plain PHP 8.3
with no third-party runtime dependency, and the automated tests use a
small hand-written harness instead of PHPUnit. A `composer.json` is
still included, documenting the dependencies a normal-internet
environment would add (PHPUnit for testing, phpdotenv as an optional
drop-in replacement for `src/Core/Env.php`); none of them are required
for the application to run.

## Default accounts

There is no default/seeded password for any account -- `php bin/console.php
create-admin` prompts you to set the first administrator's password
yourself. This is deliberate: the engineering rules this project was
built under explicitly forbid shipping default production credentials.

## Running the test suite

```
php tests/run.php
```

This runs 48 automated tests against a fresh SQLite database (migrated
and seeded exactly as a real install would be), covering the workflow
list in the project brief: RBAC, livestock/RFID/vaccination, inventory
FIFO consumption, order placement/stock locking/cancellation/refund,
IDOR protection, CSRF, backup/restore, orchard/pond recording
(including the harvest-to-inventory credit path), and password
change/reset. See `docs/FINAL_ACCEPTANCE_REPORT.md` for the actual,
current pass/fail output and what is and is not covered.
