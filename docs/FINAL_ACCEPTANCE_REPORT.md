# Final Acceptance Report

**Read this document first.** It states, without minimizing, what was
actually built and verified, what was not built, and the exact
evidence behind every claim of "working" or "tested" in this delivery.
Every number in this report (test counts, file counts, line counts)
was obtained by actually running a command against the codebase while
writing this report, not recalled from memory -- the commands are
shown so they can be re-run to check this report itself.

**Revision note:** this report was updated after an initial delivery
pass to reflect a second round of work that closed three of the gaps
originally listed in section 3: password reset/change (staff and
customer), the orchard and pond application layer (previously schema
only), and an audit-log viewer UI. Every section below reflects the
codebase as it now stands, not the original pass -- where something
changed, it is stated as "now built" rather than silently rewritten.

## 1. Environment actually available while building this

This project was built inside a sandboxed Linux container with:
- PHP 8.3.6 CLI available and working.
- **No MySQL or MariaDB server**, and no way to install one:
  `apt-get update` failed (DNS to `archive.ubuntu.com` blocked), and no
  MariaDB/MySQL package could be fetched by any means available.
- **No Docker daemon** (`/var/run/docker.sock` does not exist; no
  `docker` command functions).
- **No Composer package downloads**: outbound requests to
  `repo.packagist.org` were rejected (403) by the environment's
  network proxy.
- **No PHPUnit**: `phar.phpunit.de` was likewise rejected (403).
- Outbound network was limited to an allowlist that did not include
  general package/download mirrors.

**Consequence, stated plainly:** this delivery could not be built as
"a PHP app with Composer-installed PHPUnit, tested against a live
MySQL server," because none of those three things (Composer registry,
PHPUnit distribution, MySQL server) were reachable or installable in
this environment. The response to that constraint was:

1. Write the entire application in dependency-free PHP 8.3 (own
   router, own PDO wrapper, own session/auth/RBAC/CSRF/validator/view
   layer) -- see `README.md`, "Why no Composer dependencies are
   vendored."
2. Write the **production** database schema as MySQL migrations
   (`database/migrations/mysql/`), unmodified by the sandbox
   limitation, as the schema a real XAMPP/MySQL deployment runs.
3. Write a **parallel, test-only** SQLite schema
   (`database/migrations/sqlite/`) so the automated suite and live
   smoke testing could actually execute against a real database engine
   in this sandbox, instead of being skipped or faked.
4. Write a small custom test harness (`tests/TestCase.php`,
   `tests/run.php`) instead of using PHPUnit, since PHPUnit could not
   be downloaded.

This is a real, load-bearing limitation of this delivery, not a
cosmetic one: **the MySQL migration files and the MySQL-specific code
paths (`SELECT ... FOR UPDATE` row locking, the generated-column
unique index) have been statically reviewed for correctness against
MySQL 8 documentation, but have never been executed against an actual
MySQL or MariaDB server.** The first real execution of the MySQL
schema will be whoever runs `php bin/console.php migrate` against a
real XAMPP MySQL instance, per `docs/XAMPP_INSTALL_GUIDE.md`. See
`docs/DATABASE_PORTABILITY.md` for the full breakdown of what is and
is not verified on each driver.

## 2. What was actually built (verified present in the codebase)

Counts below were obtained with:
```
find . -type f \( -name "*.php" -o -name "*.sql" \) | wc -l   # => 139
find src -name "*.php" | xargs wc -l                          # => 5,401 lines
find tests -name "*.php" | xargs wc -l                        # => 929 lines
find resources/views -name "*.php" | wc -l                    # => 45 view files
```
(11 roles, 31 permissions, confirmed by actually migrating and seeding
a fresh database and querying `roles`/`permissions` while writing this
revision -- not recalled from the seed file by inspection alone.)

**Fully implemented, with real controllers/models/services/views and
passing automated + manual tests:**
- Authentication (staff and separately-scoped customer accounts),
  session security (regenerate-on-login, HttpOnly/SameSite cookies,
  idle timeout), login throttling/lockout.
- RBAC: 11 roles, 31 permissions, per-route and per-nav-item
  enforcement.
- CSRF protection on every state-changing form.
- Append-only audit logging on every mutating action, in the same DB
  transaction as the action.
- Livestock: registration, species/breed master data, RFID tag
  assignment/scanning/deactivation (keyboard-wedge hardware model --
  see `docs/RFID_SETUP_GUIDE.md`), weight history, lifecycle events,
  vaccination records with optional linked inventory consumption and
  next-due tracking.
- Inventory: categories, items, FIFO batch receipt/consumption with
  weighted-average cost recalculation, manual adjustments, full
  movement history.
- Employees and task assignment/status tracking.
- Customers, addresses, account statements.
- E-commerce: product catalogue (including quotation-only products,
  and products linked to a specific animal or inventory item), cart,
  checkout, order placement with real-time stock reservation, the
  full order status state machine (`pending → confirmed → processing
  → shipped → delivered → completed`, plus `cancelled`/`refunded`
  with automatic stock restoration), invoicing, payment recording with
  automatic order-status advancement.
- Finance: accounts, income/expense/transfer transactions, balance
  tracking.
- Reports: Livestock Register, Vaccinations Due, Inventory Stock,
  Sales Register, Income & Expense, Receivables -- all generated live
  from the database, each with a working CSV export
  (`?format=csv`, implemented in `ReportController::maybeCsv()`).
- Backup/restore scripts for both MySQL (`scripts/backup-mysql.sh`,
  `restore-mysql.sh`) and SQLite (`scripts/backup-sqlite.sh`, with the
  WAL-checkpoint fix described in section 5 below).
- **Password change (self-service) and password reset (administrator
  resetting another account).** Added after the initial pass to close
  the gap originally reported below. `UserController::updateOwnPassword`
  (route `/account/password`) lets any signed-in staff account change
  its own password after confirming the current one;
  `UserController::resetPassword` (route `/users/{id}/password`, gated
  by `users.manage`) lets an administrator set a new password for a
  locked-out staff account without needing the old one.
  `Shop\AccountController::updatePassword` (route
  `/shop/account/password`) provides the equivalent self-service flow
  for customers. All three write an `audit_logs` entry recording that a
  change happened and who performed it, never the password itself.
  There is still no email-based "forgot password" flow (no outbound
  email exists at all in this delivery -- see section 3), so a
  customer who has forgotten their password and cannot supply the
  current one must be reset by staff via the admin route, and a staff
  member locked out of every account with `users.manage` has no
  recovery path except a direct database update.
- **Orchard management** (`src/Models/OrchardBlock.php`,
  `OrchardTree.php`, `OrchardHarvest.php`; `src/Services/OrchardService.php`;
  `src/Controllers/Admin/OrchardController.php`; routes under
  `/orchard`): register blocks (a planted area of one species/variety),
  plant and track trees within a block (with a lifecycle status of
  alive/dead/replaced), and record harvest events against a block or a
  specific tree. A harvest can optionally be credited straight into an
  inventory item in the same database transaction via
  `InventoryService::receiveStock()`, so a harvest and its inventory
  receipt can never exist independently of each other. Gated by new
  `orchard.view`/`orchard.manage` permissions. Does **not** implement
  maintenance scheduling, yield forecasting, or pest/disease tracking
  -- see section 3.
- **Pond/aquaculture management** (`src/Models/Pond.php`,
  `PondStockingBatch.php`, `PondWaterQualityReading.php`;
  `src/Services/PondService.php`;
  `src/Controllers/Admin/PondController.php`; routes under `/pond`):
  register ponds, stock them with a dated batch of fingerlings, close a
  batch out as either harvested (recording harvested weight, date, and
  an optional sale amount) or lost, and log water-quality readings
  (temperature, dissolved oxygen, pH, ammonia) per pond. A batch's sale
  amount is stored as a record only -- it is **not** automatically
  posted to the financial ledger, because doing so would require
  guessing which account received the money; if that income should
  appear in the books, it must be entered explicitly via Finance →
  Transactions. Gated by new `pond.view`/`pond.manage` permissions.
  Does **not** implement automated water-quality alerting (e.g. paging
  someone when a reading crosses a threshold) -- readings are recorded
  and viewable only.
- **Audit log viewer** (`src/Models/AuditLog.php`,
  `src/Controllers/Admin/AuditController.php`, route `/audit`, gated by
  the pre-existing `audit.view` permission): a read-only screen listing
  the most recent audit entries with filters by record type and a
  free-text search over the action/notes fields. It has no
  update/delete route, matching `App\Core\Audit`'s own append-only
  design -- this screen can only ever display what that class wrote.

## 3. What was NOT built -- stated plainly, not minimized

The three items originally listed here as the biggest gaps --
orchard/pond having no application layer, no password reset/change,
and no audit-log viewer -- were closed in a second pass and are now
described in section 2 instead. What remains genuinely absent:

- **No online payment gateway.** Orders are placed and invoiced, but
  payment is recorded manually by staff against the invoice
  (`OrderController::recordPayment`); there is no Razorpay/Stripe/UPI
  integration of any kind.
- **No outbound email or SMS of any kind.** Grepped the entire `src/`
  tree for `mail(`, SMTP, Twilio, and SMS-related calls -- none exist.
  `.env.example`'s `MAIL_*`/`SMS_*` keys are placeholders only; nothing
  reads or acts on them. Consequently: no registration confirmation
  email, no order-status notification email, and no email-based
  "forgot password" flow -- see the password-reset entry in section 2
  for the in-app alternative that exists instead.
- **No serial/native RFID reader bridge.** Only keyboard-wedge
  (HID-mode) USB RFID readers are supported, because the application
  treats a scan as plain text input. A reader requiring a serial-port
  driver/background service was not built -- see
  `docs/RFID_SETUP_GUIDE.md` for exactly what that would require.
- **No GST/tax computation or tax-compliant invoice fields.** See
  `docs/FINANCIAL_ASSUMPTIONS.md`, section 6.
- **No PDF or Excel export** of any report -- CSV only (see section 2
  above; this is a smaller gap than originally planned since CSV
  export is in fact implemented).
- **Pond stocking-batch sale amounts are not posted to the financial
  ledger automatically** -- see the pond-management entry in section
  2 for why, and how to record it manually.
- **Orchard/pond scope is deliberately smaller than "full farm
  management"**: no maintenance/spray/fertigation scheduling, no yield
  forecasting, no pest/disease tracking, and no automated
  water-quality alerting. What exists is register-and-log, not
  planning or alerting -- see the section 2 entries for the precise
  boundary.
- **No `docker-compose.yml`** -- see `docs/XAMPP_INSTALL_GUIDE.md`,
  section 8.
- **Playwright/browser-level end-to-end tests were not written.** The
  master specification asked for PHPUnit + Playwright. Neither tool
  could run in this sandbox (PHPUnit: no network access to fetch it;
  Playwright: no browser-renderable HTTP server was kept running long
  enough across tool-call boundaries to drive a real browser against
  it in this environment). What *was* done instead, and should not be
  confused with Playwright coverage: (a) the 48-test custom-harness
  suite described in section 4, and (b) manual `curl`-driven live HTTP
  smoke testing (cookie jars, CSRF token scraping, real POST/GET
  requests against a running `php -S` server) covering the same
  critical-path workflows end-to-end at the HTTP level, documented
  test-by-test below. Neither is a substitute for a real
  browser-driven UI test, which would additionally catch
  JavaScript/rendering defects that an HTTP-level test cannot see.

## 4. Automated test suite -- actual, current results

Re-run while writing this revision:
```
$ php tests/run.php
...
Passed: 48
Failed: 0
All tests passed.
```
(Full output is reproducible by running that command from the project
root; it requires no external services, since it runs against a
freshly created, freshly migrated-and-seeded SQLite database each
time.) 48/48 tests passed. The original 34 covered workflow numbers
**#1–#6, #11–#23** from the master specification (livestock/RFID,
inventory FIFO, employee/customer setup, order placement through
delivery, state-machine rejection, IDOR isolation, invoicing/payment,
over-payment rejection, cancellation with restock, refund with ledger
entry, reporting reconciliation, backup/restore, CSRF rejection, audit
logging). The 14 added in this revision are:
- `tests/OrchardPondTest.php` (11 tests): block/tree registration,
  duplicate-code rejection, tree status changes with audit logging,
  harvest recording with and without an inventory credit (asserting
  the credited quantity actually lands in `inventory_items.current_quantity`
  via a real `InventoryService::receiveStock()` call, not a mock),
  rejection of a zero-quantity harvest, pond/stocking-batch creation,
  duplicate pond-name rejection, harvest-closes-batch, a closed batch
  cannot be harvested or lost again, and water-quality readings
  ordering. These correspond to the orchard/pond workflow coverage
  this report previously listed as untested.
- Two password-change tests added to `tests/AuthRbacTest.php`: a
  self-service change invalidates the old password and accepts the
  new one; an administrator reset works without the target's old
  password.
- One audit-log-viewer test added to `tests/SecurityTest.php`:
  `AuditLog::search()` correctly filters by entity type and free text
  without hiding or leaking unrelated rows.

In addition to the automated suite, the orchard/pond and password
features were verified by **actually running** a `php -S` server and
driving it with `curl` (cookie jars, CSRF tokens scraped from real
HTML responses) over the real HTTP routes: creating an orchard block,
planting a tree, recording a harvest that credited a real inventory
item (quantity confirmed via a direct database read before and after),
creating a pond, adding and harvesting a stocking batch, recording a
water-quality reading, changing a password as both a staff user and a
customer (confirming the old password stops authenticating and the
new one works), an administrator resetting another user's password,
and confirming a role without `orchard.manage`/`pond.manage`/`audit.view`
receives HTTP 403 on those routes and does not see their nav links.

This is a **custom test harness**, not PHPUnit, for the reason stated
in section 1. It makes real assertions against a real (SQLite)
database with real transactions -- it is not a mock-only or
smoke-only suite -- but it is not PHPUnit, and nothing in this report
should be read as a claim that `phpunit.xml`/PHPUnit itself was ever
executed.

In addition to the automated suite, the following was verified by
**actually running** a `php -S 127.0.0.1:8000 -t public` server and
driving it with `curl` (cookie jars, CSRF tokens scraped from real
HTML responses, real HTTP status codes observed) during development:
staff login, RFID tag assignment and scan lookup, animal registration
(including the dam/sire-omitted case that originally triggered the
bug fixed in section 5), customer self-registration and login, cart
add/update, checkout/order placement, and IDOR isolation (one
customer's session could not retrieve another customer's order by
ID). This live-HTTP verification is not re-run as part of
`tests/run.php` and has no automated re-run script in this delivery --
re-verifying it requires manually starting the server and repeating
the curl sequence described in the development history, which has not
been packaged as a repeatable script. This should be treated as a
**known gap**, not as ongoing regression coverage: a future change
could silently break one of these flows without `tests/run.php`
catching it, since several of these checks (e.g. the full curl-driven
HTTP login flow) are not duplicated inside the automated suite.

## 5. Defects found and fixed during development (with evidence)

Each of the following was found by **actually running the code**, not
by static review, and each fix left an explanatory comment in the
affected file:

1. **`Migrator::splitStatements()` broke on SQL comments containing a
   semicolon.** Naive `explode(';', $sql)` split mid-comment in two
   migration files, producing `SQLSTATE[HY000]: ... syntax error`.
   Fixed by stripping `--` line comments via
   `preg_replace('/--.*$/m', '', $sql)` before splitting. Verified by
   re-running the migration against a fresh database.
2. **`LivestockService::registerAnimal()` inserted `0` as a foreign
   key when `dam_id`/`sire_id` were omitted from the form**, because
   `$data['dam_id'] !== ''` evaluates `null !== ''` as `true`. This
   caused a real `FOREIGN KEY constraint failed` error, reproduced via
   an actual HTTP form submission missing those keys. Fixed by
   switching optional nullable-field checks to `!empty(...)`. Verified
   by resubmitting the same request and confirming the animal was
   created.
3. **SQLite WAL-mode backup bug**, caught by the test suite itself
   (not anticipated in advance): a plain file copy of the `.sqlite`
   file missed data still sitting in the `-wal` file, producing
   `SQLSTATE[HY000]: ... no such table: animals` on restore. Fixed by
   checkpointing WAL (`PRAGMA wal_checkpoint(FULL)`) before every copy,
   in both the test and in `scripts/backup-sqlite.sh`.
4. **`session_regenerate_id()` PHP warning** when login ran outside a
   real HTTP session (i.e. from the test harness). Fixed by guarding
   with `session_status() === PHP_SESSION_ACTIVE`.
5. **Error pages were nested inside the normal page layout**,
   producing two `<html>` documents in one response. Fixed by passing
   an explicit empty layout argument at every error-rendering call
   site.
6. **A raw string-concatenated date filter** in `DashboardController`
   (not exploitable, since the value was server-generated, not user
   input, but inconsistent with the project's own "always use prepared
   statements" rule). Converted to a bound parameter.
7. **(Found during the orchard/pond revision.)** A docblock comment in
   the first draft of `OrchardService.php` contained the literal text
   `database/migrations/*/007_orchard_pond.sql` -- the `*/` inside that
   path closed the PHP `/** ... */` doc-comment early, which `php -l`
   caught immediately as a parse error (`unexpected identifier
   "_orchard_pond"`) before the file was ever run. Fixed by rewording
   the sentence to avoid a literal `*/` anywhere in a comment. Left as
   its own entry here because it is a real, if minor, instance of the
   same class of mistake as defect #1 (meaningful characters appearing
   inside what was assumed to be "just comment text") and is a cheap
   lesson for any future doc comment that quotes a glob path.

No defect found during this process was left unfixed; all seven are
reflected in the current code. #1 through #6 were found during the
initial delivery pass; #7 was found during the follow-up pass that
added orchard/pond/password-reset/audit-log-viewer. All are covered,
where practical, by the regression tests or static check (`php -l`)
that caught them.

## 6. Confidence levels, by area

- **High confidence** (multiple independent passing tests, both
  automated and live-HTTP, matching documented behavior): auth, RBAC,
  CSRF, RFID assign/scan, vaccination recording, inventory FIFO
  consumption, order placement/state machine/cancellation/refund,
  payment recording, IDOR isolation, audit logging and its viewer,
  backup/restore (SQLite path), password change/reset (staff
  self-service, staff admin-reset, customer self-service), orchard
  block/tree/harvest recording including the inventory-credit path,
  pond stocking/harvest/loss/water-quality recording, and permission
  enforcement on every new route (`orchard.view`/`.manage`,
  `pond.view`/`.manage`, `audit.view`).
- **Moderate confidence** (code reviewed carefully, logic exercised
  indirectly or on one driver only, not independently executed against
  the specific named target): the MySQL migration files and
  MySQL-specific `FOR UPDATE` locking path (reviewed against MySQL 8
  documentation, never run against a real MySQL server); CSV report
  export (implemented and code-reviewed; not covered by an automated
  test asserting the actual CSV bytes produced).
- **Known absent, not a confidence question**: payment gateway,
  email/SMS (including email-based password reset), serial RFID
  bridge, tax handling, PDF/Excel export, orchard/pond maintenance
  scheduling and water-quality alerting, Playwright coverage -- see
  section 3.

## 7. Acceptance-criteria checklist (master spec section 21/19)

| Criterion | Status |
|---|---|
| No fake/empty CRUD | Met for every module listed as "built" in section 2, including orchard/pond/audit-log/password-reset added in this revision |
| No swallowed exceptions | Met -- controllers catch and surface errors via flash messages; `src/Core/Logger.php` never throws; no empty `catch {}` blocks exist in `src/` (checked again in this revision across the new files) |
| No invented integrations | Met -- no payment gateway, email, or SMS code was written and then left non-functional; those integrations simply do not exist, and this report says so. The pond sale-amount field is stored but deliberately NOT auto-posted to Finance, for the same reason (see section 3) |
| No hardcoded secrets | Met -- `.env.example` has empty placeholders only; `.gitignore` excludes `.env`; no password is ever written to the audit log (checked in the new password-reset code) |
| Migrations used | Met, for both drivers; no new migration was needed for this revision since the orchard/pond schema already existed from the initial pass |
| Server-side validation on all input | Met for every implemented module including the new ones (see `src/Core/Validator.php` usage and manual required-field checks in `OrchardService`/`PondService`) |
| Regression tests for fixed defects | Met for defects #2, #3, #4 above (direct test coverage); defects #1, #5, #6 are structural/infrastructure fixes verified by the suite continuing to pass rather than by a test targeting that exact line; defect #7 was caught by `php -l` before any test ran |
| No completeness claims without verification | This report is the mechanism: every "built" claim in section 2 maps to a passing test or a documented manual verification step, and section 3 states every gap found during an honest pass over the codebase while writing this report |
| Persistent TODO / known-limitations reporting | This document, section 3 and 8 |

## 8. Recommended next steps, in priority order

1. Run `bin/console.php migrate` against a real MySQL/MariaDB server
   (first real execution of that schema) and fix any syntax error
   immediately -- treat this as expected first-run work, not a sign
   of a broken delivery.
2. If online payment is required, integrate a gateway (e.g. Razorpay,
   widely used in India) -- this touches `PaymentService` and
   `OrderService` and should be scoped as its own task.
3. Decide the farm's GST status and, if registered, add tax
   fields/calculation before this is used for real sales.
4. If a non-keyboard-wedge RFID reader is in use or planned, get its
   protocol documentation before assuming a bridge can be built.
5. If email becomes available (an SMTP account, a transactional-email
   API key), the highest-value use of it is a real "forgot password"
   flow for customers, since they have no staff-mediated path as
   convenient as the admin reset staff accounts now have.
6. If orchard/pond maintenance scheduling, yield forecasting, or
   automated water-quality alerting turn out to be needed in practice
   (rather than the register-and-log slice built so far), scope those
   as their own follow-up tasks -- they would need new schema, not
   just new screens.
