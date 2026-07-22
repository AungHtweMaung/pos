# Small Grocery Shop POS — Project Specification

## 1. Overview

A point-of-sale system for a small grocery shop. One shared dashboard is used
by both admin and cashier accounts; the UI is identical, and access to
specific actions is controlled by role.

- No customer records — sales are anonymous.
- No offline mode — the app is always online (see §3 hosting).
- UI supports both **light and dark mode**.

## 2. Tech Stack

| Layer       | Choice                                        |
|-------------|------------------------------------------------|
| Backend     | Laravel 12, PHP 8.2+                           |
| Database    | MySQL                                          |
| Frontend    | React via Inertia.js                           |
| Styling     | Bootstrap 5.3+ + Bootstrap Icons              |
| Theme       | Light + dark mode via `data-bs-theme` (see §7) |
| Auth        | Username + password (see §4)                   |
| Receipts    | On-screen + browser print (no ESC/POS)         |
| Hosting     | VPS, public internet — always online           |

## 3. Hosting Model

- The Laravel app runs on a **VPS on the public internet**. There is no
  local-network setup.
- Every client connects over the internet — the cashier's till in the shop
  and the admin from home/phone reach the same server the same way.
- Because the login page is internet-facing, authentication uses real
  passwords, served over **HTTPS only** (see §4). This is the security
  boundary — a short PIN would not be safe here.

## 4. Authentication

- Login form: **username + password** for every user (admin and cashier
  alike). No PIN.
- Passwords hashed at rest (`Hash::make`), never stored or logged in
  plaintext. Enforce a reasonable minimum length/complexity.
- **HTTPS/TLS required** — the app is only served over HTTPS in production.
- **Rate-limit** login attempts per username/IP (Laravel's `throttle`
  middleware) to blunt brute-forcing. Optionally add fail2ban at the VPS
  level.
- Session-based auth (Laravel default). One shared dashboard; role decides
  what each user can do (see §5).

### Cashier management (admin only)

Full CRUD on cashier/admin accounts:
- Create: name, username, password, role (cashier/admin)
- Edit: name, username, reset password, activate/deactivate
- Deactivate instead of hard-delete (recommended), so past sales still show
  who rang them up.

### Optional / future hardening (NOT in MVP)

- **Cashier login time-window** — restrict cashier (not admin) logins to
  configurable shop hours. Deferred: the password + rate-limiting already
  cover the security need, and a time-window adds edge cases (late-night
  reconciliation, holiday hours, timezone handling) for a small payoff. Left
  here as a toggle to add later if staff trust or suspicious logins ever make
  it worthwhile.

## 5. Roles & Permissions

Two roles, one shared dashboard. Permission checks via Laravel
Policies/Gates on the backend and conditional rendering on the React side
(role passed down as a shared Inertia prop).

| Action                                   | Cashier | Admin |
|-------------------------------------------|:-------:|:-----:|
| Ring up a sale                            | ✅      | ✅    |
| Apply a preset/per-item discount          | ✅      | ✅    |
| View/print receipts                       | ✅      | ✅    |
| Void or refund a completed sale           | ❌      | ✅    |
| Create/edit/delete products & variants    | ❌      | ✅    |
| Edit prices                               | ❌      | ✅    |
| Manual stock adjustment                   | ❌      | ✅    |
| Manage cashier accounts (CRUD)            | ❌      | ✅    |
| View sales/reports                        | ❌      | ✅    |
| Reconcile end-of-shift cash drawer         | ✅ (own)| ✅    |

## 6. Data Model

### `users`
| column | type | notes |
|---|---|---|
| id | bigint | |
| name | string | |
| username | string, unique | |
| password | string | hashed |
| role | enum(admin, cashier) | |
| is_active | boolean | default true |
| timestamps | | |

### `products`
| column | type | notes |
|---|---|---|
| id | bigint | |
| name | string | e.g. "Coca-Cola" |
| category | string, nullable | plain text for MVP; promote to a table later if needed |
| tax_rate | decimal | percentage |
| timestamps | | |

### `variants`
The stocked unit — e.g. a size. Stock quantity always lives here, in base
units (e.g. individual bottles), regardless of how it's sold.

| column | type | notes |
|---|---|---|
| id | bigint | |
| product_id | FK → products | |
| label | string | e.g. "250ml" |
| stock_qty | integer | base-unit stock count |
| low_stock_threshold | integer, nullable | for alerts |
| timestamps | | |

### `sale_units`
The sellable/scannable unit. A variant can have more than one — e.g. sold as
a single bottle or as a 6-pack — without splitting stock.

| column | type | notes |
|---|---|---|
| id | bigint | |
| variant_id | FK → variants | |
| label | string | e.g. "Single", "6-Pack" |
| barcode | string, unique | |
| pack_size | integer | base units consumed per sale (1 for a single) |
| price | decimal | |
| cost | decimal | for margin reporting |
| timestamps | | |

### `sales`
| column | type | notes |
|---|---|---|
| id | bigint | |
| cashier_id | FK → users | who rang it up |
| subtotal | decimal | |
| tax_total | decimal | |
| discount_total | decimal | |
| grand_total | decimal | |
| payment_method | enum(cash, card, qr) | |
| cash_tendered | decimal, nullable | cash only |
| change_due | decimal, nullable | cash only |
| qr_reference_note | string, nullable | QR transfer only — the note read out to the buyer |
| status | enum(completed, voided, refunded) | |
| voided_by | FK → users, nullable | admin who voided/refunded |
| voided_reason | string, nullable | |
| timestamps | | |

### `sale_items`
| column | type | notes |
|---|---|---|
| id | bigint | |
| sale_id | FK → sales | |
| sale_unit_id | FK → sale_units | |
| quantity | integer | |
| unit_price | decimal | price at time of sale (frozen, doesn't move if price changes later) |
| discount | decimal | |
| line_total | decimal | |

### `stock_adjustments`
| column | type | notes |
|---|---|---|
| id | bigint | |
| variant_id | FK → variants | |
| change_qty | integer | positive (restock) or negative (damage/correction) |
| reason | string | |
| adjusted_by | FK → users | |
| timestamps | | |

### `shifts`
| column | type | notes |
|---|---|---|
| id | bigint | |
| cashier_id | FK → users | |
| opened_at | datetime | |
| closed_at | datetime, nullable | |
| expected_cash | decimal, nullable | system-calculated |
| counted_cash | decimal, nullable | cashier-entered |
| difference | decimal, nullable | counted − expected |

## 7. UI & Theming

- Bootstrap 5.3+ with Bootstrap Icons.
- **Light and dark mode** using Bootstrap's native `data-bs-theme` attribute
  on `<html>` (`data-bs-theme="light"` / `"dark"`).
- A theme toggle in the header lets the user switch; the choice is persisted
  in `localStorage` and re-applied on load.
- Default to the OS preference on first visit
  (`prefers-color-scheme`), then respect the user's explicit toggle
  afterwards.
- Build components with Bootstrap theme variables so both modes stay legible
  (don't hardcode colors that only work in one mode).
- Single shared dashboard layout; role only changes which menu items and
  actions are visible.

## 8. Core Modules

### 8.1 Sales / POS (cashier + admin)
1. Sign in (username + password)
2. Scan or search for an item by `sale_unit` barcode/name
3. Adjust cart — quantity, remove line, per-item discount
4. Checkout — system computes subtotal, tax, grand total
5. Choose payment method:
   - **Cash** — enter amount tendered, system computes change
   - **QR transfer** — cashier reads out a short reference note; buyer
     transfers the total via their bank/wallet app including that note;
     cashier manually matches the incoming transfer to the note and confirms
     (no automatic gateway confirmation for MVP)
6. On confirmation: decrement `variants.stock_qty` by
   `sale_units.pack_size × quantity`, save the `sale` + `sale_items`, show
   the on-screen receipt (browser print / Ctrl+P)
7. On payment failure/cancellation: cart stays intact, cashier retries or
   switches method

### 8.2 Inventory (admin only)
- CRUD products, variants, and sale units
- Manual stock adjustment with a reason (restock, damage, correction)
- Low-stock list based on `low_stock_threshold`

### 8.3 Cashier Management (admin only)
- CRUD cashier/admin accounts (see §4)

### 8.4 End of Shift (cashier + admin)
- Cashier counts the drawer, enters `counted_cash`
- System shows `expected_cash` (opening float + cash sales − cash refunds)
  and the difference
- Admin can view shift history across all cashiers

### 8.5 Reporting (admin only)
- Daily sales summary — revenue, transaction count, by payment method
- Best-selling products/variants
- Shift/cash-reconciliation history
- Void/refund log

## 9. MVP Feature Checklist

- [x] Username + password login (admin + cashier), HTTPS + rate-limiting
- [x] Single shared dashboard, role-gated actions
- [x] Light + dark mode with persisted toggle
- [x] Product → variant → sale-unit catalog (packs supported)
- [x] Cart, checkout, cash/card/QR-transfer payment
- [x] Stock auto-decrement on sale, manual stock adjustment
- [x] Low-stock alerting
- [x] Void/refund (admin only)
- [x] On-screen receipt with browser print
- [x] Cashier account CRUD (admin only)
- [x] End-of-shift cash reconciliation
- [x] Daily sales report, best-sellers report

## 10. Explicitly Out of Scope for MVP

- Customer records, loyalty programs, store credit
- Multi-terminal / multi-store sync
- Offline mode
- Local-network / on-premise hosting
- PIN login and cashier login time-windows (see §4 — optional future)
- Thermal printer (ESC/POS) integration
- Automatic QR/card payment gateway confirmation (webhook-based)
- Supplier/purchase-order management
- Employee scheduling/payroll
- Promotions engine beyond flat per-item discounts

## 11. Open Assumptions (revisit if wrong)

- Single shop; one active shift per cashier at a time
- One currency, one tax rate scheme (flat `tax_rate` per product — no
  multi-tax-jurisdiction handling)
- "Category" is a free-text field on `products`, not a managed table, for
  MVP simplicity
- App timezone is set explicitly to the shop's local timezone in
  `config/app.php`
