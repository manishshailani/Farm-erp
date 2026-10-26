# Customer / Shop Manual

This describes the public storefront at `/` (no login required to
browse) and the customer account area (requires a free account).

## Browsing

- **Home / Catalogue** (`/shop` -- the site root `/` redirects here):
  active products the farm has published
  for sale. Each product shows its name, description, price, and
  availability. A product can be marked **quotation-only** (price
  shown as "Contact for price" rather than an Add to Cart button) --
  used for items like live animals where a fixed online price isn't
  appropriate.
- **Product detail**: full description and quantity selector.

## Creating an account

Click **Register** (`/shop/register`), provide your name, email, and
a password (minimum 10 characters). You will be signed in immediately
-- there is no email verification step in this delivery (no outbound
email is configured; see `docs/FINAL_ACCEPTANCE_REPORT.md`). Returning
customers sign in at `/shop/login` -- this is a separate account system
from staff sign-in (`/login`); a staff login does not work on the shop
and vice versa.

## Cart and checkout

1. Add items to your cart from the catalogue or product page.
2. **Cart** (`/shop/cart`): review quantities, remove items, see the
   running total.
3. **Checkout** (`/shop/checkout`): choose or add a delivery address, then
   place the order. Placing an order reserves/decrements stock for the
   items in it immediately (so two customers cannot both check out the
   last unit of something that's out of stock) and generates an
   invoice.
4. **No online payment gateway is integrated in this delivery.**
   Placing an order records it as awaiting payment; payment is
   collected and recorded by farm staff (cash, bank transfer, etc.)
   against your invoice. This is a known, explicitly stated gap -- see
   `docs/FINAL_ACCEPTANCE_REPORT.md` for what a real gateway
   integration would require.

## Your account area

- **My Orders** (`/shop/account/orders`): every order you've placed,
  its current status, and its invoice/payment history.
- **My Addresses** (`/shop/account/addresses`): manage delivery
  addresses.
- **Account statement**: total invoiced, total paid, and outstanding
  balance across all your orders.

Your account only ever shows **your own** data -- order and address
lookups are always scoped to your own customer record on the server
side, regardless of what ID appears in the page URL, specifically to
prevent one customer from viewing another's information by guessing or
editing a URL (see the IDOR-prevention test in
`tests/SecurityTest.php`).

## Order status meanings

| Status | Meaning |
|---|---|
| Pending | Placed, awaiting confirmation/payment from the farm |
| Confirmed | Farm has confirmed the order |
| Processing | Farm is preparing your order |
| Shipped | Order has left the farm |
| Delivered | Order has arrived |
| Completed | Order fully closed out |
| Cancelled | Order was cancelled; any reserved stock was released |
| Refunded | Payment was refunded; any reserved/consumed stock was released |

If you have questions about an order, contact the farm directly --
there is no in-app messaging/support ticket feature in this delivery.
