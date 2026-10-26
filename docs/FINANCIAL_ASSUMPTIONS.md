# Financial Assumptions and Accounting Rules

This document states, explicitly, every accounting policy decision
baked into `src/Services/FinanceService.php`, `OrderService.php`, and
`PaymentService.php`, so that whoever reviews the farm's books
understands exactly what the numbers mean. **This is not tax or
accounting advice; it describes what the software does, not what the
farm is legally required to do.** Consult a qualified accountant for
GST/tax compliance, which is explicitly out of scope (see
`docs/FINAL_ACCEPTANCE_REPORT.md`).

## 1. Money is stored as DECIMAL, never FLOAT

Every monetary column in the schema (`unit_price`, `total_amount`,
`amount_paid`, `current_balance`, etc.) is `DECIMAL(12,2)` (MySQL) /
`NUMERIC` (SQLite, which stores it as text-backed exact decimal via
PHP's string-based arithmetic at the point of calculation). PHP floats
are never used for money math anywhere in `Services/`; monetary
arithmetic is done with integer paise/cents internally where
multiplication/division risk exists, or with `bcmath`-equivalent
care using strings, to avoid binary floating-point rounding errors
(e.g. `0.1 + 0.2 !== 0.3` in IEEE-754 double precision). Where a
calculation is a simple one-step sum of already-rounded DECIMAL
values read from the database, PHP's native arithmetic on the numeric
strings PDO returns is used directly, which is safe because no
intermediate value is a non-terminating binary fraction at the
precision stored.

## 2. Revenue recognition: cash basis, not accrual

**Assumption, stated plainly: income is recognized when payment is
received, not when the order is placed or the invoice is issued.**

Concretely:
- Placing an order creates the order and (if applicable) its invoice,
  but does **not** create an income `financial_transactions` row.
- `PaymentService::record()` is the only code path that inserts an
  **income** transaction, and it does so at the moment a payment is
  recorded against an invoice -- for the amount actually paid, not the
  invoice total.
- A partially paid invoice therefore shows exactly the income actually
  received so far in the Income & Expense report, with the unpaid
  remainder visible separately via the Receivables report
  (`Invoice::outstandingBalance()`-style query in
  `ReportController`).

This was chosen because: (a) a small farm's practical bookkeeping
need is almost always "how much cash has actually come in," and (b)
it avoids ever overstating income for an invoice that is later
cancelled before payment. The trade-off, stated explicitly: this is
**not** standard accrual-basis accounting, and if the farm's
accountant needs accrual figures (recognizing a sale at invoice time
regardless of payment), this software's Income & Expense report is
not that figure -- the **Sales Register** report
(`admin/reports/sales_register.php`), which lists orders/invoices by
order date regardless of payment status, is the closer accrual-style
view, and the two reports should not be assumed interchangeable.

## 3. Refunds

A refund (`OrderService::transition()` to the `refunded` status) posts
an **expense** `financial_transactions` row for the refunded amount.
It does not reverse/delete the original income row from when the
payment was received -- both rows exist, so the net effect on
Income & Expense across the two periods is correct (income in the
period paid, expense in the period refunded), but a single period's
report will show the gross income and the refund expense as two
separate lines, not a single netted "zero" line. This is intentional:
it preserves an audit trail of the fact that money changed hands
twice, which matches how a bank statement would show it.

## 4. Transfers are excluded from Income & Expense totals

`financial_transactions.type` can be `income`, `expense`, or
`transfer`. A transfer (e.g. moving cash from the till to the farm's
bank account) updates both accounts' `current_balance` via
`FinanceService::recordTransaction()`, but is explicitly excluded from
the SUM() in the Income & Expense report
(`FinancialTransaction::summary()`'s WHERE clause). **Reason:** a
transfer is not revenue or spending, it is money moving between two
of the farm's own accounts; including it in both income and expense
totals would double-count and distort the P&L. If a transfer were
accidentally typed as income or expense instead, the report would be
wrong -- staff should be trained on this distinction, since the
application does not attempt to infer intent from the amount or
accounts involved.

## 5. Stock valuation: FIFO, weighted-average reporting

`InventoryService` consumes stock FIFO (oldest batch first) for
`cost_price` purposes, but `inventory_items.current_avg_cost` is
recalculated as a weighted average across all remaining batches after
every receipt/consumption (`recalculateItemAggregates()`). This means:
- The **cost of goods sold** figure recorded against a specific
  consumption event reflects the actual FIFO batch(es) it drew from
  (accurate per-transaction cost).
- The **current valuation shown on the inventory dashboard**
  (`current_avg_cost x quantity_on_hand`) is a weighted average across
  remaining stock, which is the conventional small-business
  presentation for "what is my inventory worth right now" and is
  simpler to read than a full FIFO batch breakdown, at the cost of not
  exactly matching what the next unit sold will be costed at (it will
  be costed at its specific FIFO batch's price, which may differ
  slightly from the displayed average). Both figures are internally
  consistent with each other over time; this is a reporting
  presentation choice, not a bug.

## 6. No tax (GST) calculation

Product prices, invoices, and orders in this delivery store and
display a single `total_amount` with no tax line item, tax rate
field, or HSN/SAC code field. **GST registration, invoicing
requirements, and tax computation are out of scope for this
delivery** (see `docs/FINAL_ACCEPTANCE_REPORT.md`). If the farm is
GST-registered, invoices generated by this system in their current
form are very likely not compliant tax invoices, and this should be
treated as a required follow-up, not a cosmetic gap, before using this
system for GST-reportable sales.

## 7. Currency

All amounts are assumed to be Indian Rupees (INR); there is no
multi-currency support and no currency field on any monetary column.
`View::money()` (`src/Core/View.php`) formats with a hardcoded `₹`
symbol via PHP's `number_format($amount, 2)`, which uses
**Western 3-digit comma grouping** (e.g. `₹1,234,567.00`), not the
Indian lakh/crore grouping convention (`₹12,34,567.00`). This is a
cosmetic gap, listed in `docs/FINAL_ACCEPTANCE_REPORT.md`, not a
calculation error -- the underlying DECIMAL value is correct either
way.
