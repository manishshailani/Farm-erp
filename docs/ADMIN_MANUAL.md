# Staff / Administrator Manual

This describes day-to-day use of the staff portal (`/login`, then the
various admin sections such as `/livestock`, `/inventory`, `/orchard`,
`/pond`, `/orders`, and so on -- there is no `/admin` URL prefix; these
are plain top-level routes gated by sign-in and permission checks).
What you see depends
on your assigned role -- the left-hand navigation only shows sections
you have permission for (`Permission::currentUserHas()` gates every
menu item and every route).

## Signing in

Go to `/login` and enter your email and password. After 5 failed
attempts from the same email+IP combination, further attempts are
locked out for 15 minutes (`src/Services/LoginThrottle.php`,
`AUTH_MAX_ATTEMPTS`/`AUTH_LOCKOUT_MINUTES` in `.env`). There is no
email-based "forgot password" flow in this delivery (no outbound email
exists at all -- see `docs/FINAL_ACCEPTANCE_REPORT.md`), but you can
change your own password any time from **My Account**
(`/account/password`, link in the sidebar) by entering your current
password once, and an administrator can reset another locked-out
staff account's password without needing the old one (see **Users &
Permissions** below).

## Dashboard

The landing page after login. Shows: animals due for vaccination in
the next 7 days, low-stock inventory items, open orders needing
action, and today's cash position across accounts. Each figure links
through to the relevant module.

## Livestock

- **Livestock → All Animals**: searchable/filterable list (by
  species, breed, status, active RFID tag). Click a row for the full
  record: weight history, lifecycle events (birth, purchase, sale,
  death, etc.), vaccination history, and the active tag.
- **Livestock → Register Animal**: add a new animal. Dam/sire are
  optional -- leave blank if unknown or not applicable.
- **Livestock → Scan Tag** (`/livestock-rfid/scan`): see
  `docs/RFID_SETUP_GUIDE.md`.
- From an animal's page: **Assign Tag**, **Record Weight**, **Record
  Lifecycle Event**, **Record Vaccination** (optionally consumes a
  dose from inventory in the same transaction, so stock and vet
  records always agree).

## Inventory

- **Inventory → Items**: every stocked item, current quantity on
  hand, weighted-average cost, and a low-stock flag (configurable
  reorder threshold per item).
- **Inventory → Item detail**: full batch list (FIFO order) and
  movement history (every receipt, consumption, and manual
  adjustment, each with who made it and why).
- Stock is also consumed automatically when: a vaccination records an
  inventory-linked vaccine/dose, or a customer order containing an
  inventory-linked product is placed/fulfilled, or an orchard harvest
  is credited into an inventory item (see **Orchard** below).

## Orchard

Covers planting, trees, and harvest logging -- it does not include
maintenance scheduling, yield forecasting, or pest/disease tracking
(not built in this delivery; see
`docs/FINAL_ACCEPTANCE_REPORT.md`).

- **Orchard** (`/orchard`): lists every orchard block (a planted area
  of one species/variety, e.g. "North Mango Block") with its tree
  count and total harvested-to-date. Use **+ New block** to register
  one.
- **Block detail** (`/orchard/{id}`): add trees to the block (with an
  optional row/position and planting date), change a tree's status
  (alive/dead/replaced -- the tree's history is kept either way, never
  deleted), and record a harvest. A harvest can be attributed to the
  whole block or a specific tree.
- **Crediting a harvest into inventory**: on the harvest form, pick an
  inventory item and the harvested quantity is received into that
  item's stock in the same step (at a unit cost you can set, default
  ₹0 since own-grown produce has no purchase cost). Leave it on "Do
  not credit to inventory" to just log the harvest without touching
  stock.

## Ponds

Covers pond records, fingerling stocking batches, and water-quality
logging -- it does not include feed-ration calculation or automated
alerts when a reading crosses a threshold (not built in this
delivery).

- **Ponds** (`/pond`): lists every pond with its area/depth and how
  many stocking batches are currently growing. Use **+ New pond** to
  register one.
- **Pond detail** (`/pond/{id}`): add a stocking batch (species,
  stocking date, fingerling quantity, optional average weight and
  purchase cost) -- it starts in **growing** status. From a growing
  batch you can either **Harvest** it (enter the harvest date,
  harvested weight, and optionally the sale amount realized) or
  **Mark lost**. Either action closes the batch out permanently --
  it cannot be harvested or marked lost a second time.
- **Sale amount is a record only.** It is shown on the batch but is
  **not** automatically posted to Finance -- if that income needs to
  appear in the farm's books, record it explicitly under **Finance →
  Transactions**, noting which batch it was for.
- Below the stocking batches, record water-quality readings
  (temperature, dissolved oxygen, pH, ammonia) at any time; the most
  recent readings are listed first.

## Employees

- **Employees → All**: roster with contact info and role.
- **Employees → Tasks**: assign a task to an employee with a due date
  and status (open/in-progress/done); filter by employee or status.

## Customers

- **Customers → All**: every registered shop customer, their
  addresses, order history, and running account statement (invoices,
  payments, balance).
- Staff cannot edit a customer's password from here (there is no
  "login as customer" or password-reset-for-customer feature in this
  delivery -- see known limitations).

## Orders

- **Orders → All**: every order, filterable by status. Click a row
  for line items, status history, the linked invoice, and payment
  records.
- **Order status workflow** (enforced; invalid transitions are
  rejected by `OrderService::VALID_TRANSITIONS`):
  `pending → confirmed → processing → shipped → delivered → completed`,
  with `cancelled` and `refunded` reachable from the appropriate
  earlier states. Cancelling or refunding an order automatically
  restores any stock that was reserved/consumed for it.
- **Record a payment** against an order's invoice from the order
  detail page; this is the action that actually books income (see
  `docs/FINANCIAL_ASSUMPTIONS.md`) and will auto-advance the order
  status when fully paid.

## Finance

- **Finance → Accounts**: your cash/bank accounts and current
  balances.
- **Finance → Transactions**: record income, expense, or a transfer
  between two of your own accounts. See
  `docs/FINANCIAL_ASSUMPTIONS.md` for exactly what counts as what.
- **Finance → Reports** (also under **Reports**, see below).

## Users & Permissions (requires the relevant permission -- normally
Super Administrator / Farm Owner only)

- **Users → All**: create staff accounts, deactivate them (soft
  disable -- `is_active = 0` blocks login, the account and its audit
  trail are never deleted), and assign one or more roles.
- **Users → Roles**: see each role's permission set. The 11 seeded
  roles are: Super Administrator, Farm Owner, Farm Manager,
  Livestock/Vet Manager, Orchard/Crop Manager, Pond Manager,
  Inventory Manager, Accountant, Sales/Order Manager, and
  Employee/Field Worker (plus the non-staff **Customer** role, which
  has zero backend permissions and exists only to mark shop
  accounts).
- **Resetting a locked-out staff member's password**: next to their
  row, click **Reset password** to expand an inline form, enter and
  confirm a new password (minimum 10 characters), and submit. This
  does not require their old password -- it is exactly the recovery
  path for someone who has forgotten it. The action is written to the
  audit trail (who reset whose password, and when), but the new
  password itself is never logged anywhere.

## Reports

All under **Reports**: Livestock Register, Vaccinations Due,
Inventory Stock, Sales Register, Income & Expense, and Receivables
(outstanding customer balances). Each report is generated live from
the database (no scheduled/cached reports). Every report page supports
a plain **CSV download** by adding `?format=csv` to its URL (e.g.
`/reports/sales-register?format=csv`) -- this is implemented and
tested (`ReportController::maybeCsv()`). There is **no PDF or Excel
(.xlsx) export** in this delivery; CSV is the only machine-readable
export format currently available.

## Audit trail

Every mutating action (create/update/status-change/delete-equivalent)
writes an append-only row to `audit_logs` with the acting user, a
before/after JSON snapshot, and a timestamp, inside the same database
transaction as the action itself -- so an audit entry can never exist
for an action that didn't actually commit, and vice versa.

**Audit Log** (`/audit`, requires the `audit.view` permission --
by default only Super Administrator and Farm Owner) is a read-only
screen listing the most recent entries, newest first, with a filter by
record type and a free-text search over the action name and notes.
There is no edit or delete control anywhere on this page, matching the
append-only design of the underlying table -- a mistaken action is
corrected with a new, later entry (e.g. a reversing transaction), never
by rewriting history.
