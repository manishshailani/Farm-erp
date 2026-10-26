# Database Portability: MySQL vs. SQLite

## Why two sets of migrations exist

`database/migrations/mysql/` is the **authoritative, production schema**.
It is what `docs/XAMPP_INSTALL_GUIDE.md` tells a real deployment to run,
and it is the schema this project was designed against (InnoDB,
`utf8mb4`, `AUTO_INCREMENT`, `JSON` columns, `ON UPDATE CURRENT_TIMESTAMP`).

`database/migrations/sqlite/` is a **test-only** mirror of the same 7
migrations, same table/column names, same constraints, written in
SQLite's dialect. It exists for one reason: the sandbox this project
was developed and tested in has no MySQL/MariaDB server available and
no network path to install one (see
`docs/FINAL_ACCEPTANCE_REPORT.md` for the exact errors). `tests/run.php`
and `tests/bootstrap.php` always use the SQLite driver so the automated
suite can run without external services.

**Both file sets are hand-maintained in parallel.** There is no
generator that produces one from the other. Any future schema change
must be applied to both files for the matching migration number, or the
test database will silently drift from the production schema. This is
the single biggest portability risk in this delivery and is called out
again in `docs/FINAL_ACCEPTANCE_REPORT.md`.

## `src/Core/Database.php` driver selection

`DB_CONNECTION` in `.env` selects the driver (`mysql` or `sqlite`).
`Database::connect()` builds the appropriate PDO DSN and always sets
`PDO::ATTR_ERRMODE = PDO::ERRMODE_EXCEPTION` and
`PDO::ATTR_EMULATE_PREPARES = false` for both drivers, so parameterized
queries behave the same way (no silent client-side emulation
differences).

## Dialect differences this codebase has to work around

| Concern | MySQL | SQLite | How the code handles it |
|---|---|---|---|
| Auto-increment PK | `INT AUTO_INCREMENT PRIMARY KEY` | `INTEGER PRIMARY KEY AUTOINCREMENT` | Separate migration files; `Model::create()` calls `Database::lastInsertId()`, which is driver-agnostic (`PDO::lastInsertId()` works the same way on both). |
| Row locking | `SELECT ... FOR UPDATE` inside a transaction | Not supported (SQLite locks at the database/file level) | `InventoryService::consumeStock()` and `OrderService::placeOrder()` only append `FOR UPDATE` when `Database::driver() === 'mysql'`. SQLite's own file-level locking inside `Database::transaction()` is relied on instead; this is adequate for the single-process test suite but is **not** a substitute for row-level locking under real concurrent load, which only the MySQL path is designed for. |
| JSON columns | Native `JSON` type | Stored as `TEXT`; app encodes/decodes with `json_encode`/`json_decode` | Columns like `animals.metadata` or `audit_logs.before_data`/`after_data` are always JSON-encoded in PHP before insert and decoded after select, so the PHP-side contract is identical regardless of driver. |
| Partial unique constraint (one active RFID tag per animal) | Generated column `active_tag_number` (`NULL` unless `is_active = 1`) + unique index on it | Same generated-column technique, SQLite syntax (`GENERATED ALWAYS AS (...) VIRTUAL`) | Both migration files implement the identical logic so `LivestockService::assignTag()` behaves the same on either driver; this was verified by the automated test `LivestockTest.php` (SQLite) and by manual curl testing against a live MySQL-less `php -S` + SQLite run — **the MySQL path for this specific constraint has not been executed against a real MySQL server in this environment**, since none was available; the SQL has been reviewed against MySQL 8 documentation for generated-column + index support but not run. |
| Case-insensitive text comparisons | `utf8mb4_unicode_ci` collation | SQLite default `BINARY` collation (case-sensitive) | Everywhere the app does a case-insensitive lookup (e.g. matching an email on login), the comparison is lowercased in PHP (`strtolower()`) before the query, so behavior does not depend on collation. |
| Statement termination in migration files | `;` | `;` | `Migrator::splitStatements()` strips `--` line comments before splitting on `;` (this is the exact fix for a real bug caught during development -- see the comment in `src/Core/Migrator.php` and `docs/FINAL_ACCEPTANCE_REPORT.md`). |

## What is and is not verified against real MySQL

**Verified only against SQLite in this environment:**
- All 34 automated tests (`tests/run.php`)
- The full live HTTP smoke test (login, RFID assign/scan, vaccination,
  inventory FIFO consumption, order placement → payment → fulfillment,
  cancellation/refund, IDOR isolation) via `php -S` + `curl`

**Not executed against a real MySQL/MariaDB server in this
environment** (none was installable -- see acceptance report):
- The MySQL migration files themselves
- `FOR UPDATE` row-locking behavior under concurrent load
- The generated-column unique-index technique on MySQL specifically
- `scripts/backup-mysql.sh` / `scripts/restore-mysql.sh`

These MySQL-specific pieces were **statically reviewed** against
MySQL 8.0/8.4 and MariaDB 10.11 documentation for syntax correctness,
but "statically reviewed" is not the same claim as "executed," and this
document says so plainly per the project's own evidence-quality rule.
Running `bin/console.php migrate` against a real MySQL instance on
first install (section 5 of `docs/XAMPP_INSTALL_GUIDE.md`) is the first
real execution of that schema, and any error at that step should be
reported with the exact MySQL error text so the specific migration
statement can be fixed.
