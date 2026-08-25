# Fix UI Price Discrepancies & Rounding Inconsistencies

This plan addresses the user's concerns regarding the confusing price display in the POS transaction screen (as pointed out with "fokus kesini").

## Problem Summary
1. **Decimal vs Rounded Confusion:** The cart table displays exact, unrounded decimal unit prices (e.g., `Rp 25.591,04`), but the row total displays a heavily rounded final price (e.g., `Rp 27.000`). This makes it look like the math is broken (`1 x 25.591,04 != 27.000`).
2. **`Jumlah` vs `Total Beli` Discrepancy:** The `payment_total` (Jumlah) sometimes diverges wildly from `cartTotalInput` (Total Beli). This is because `Jumlah` uses a legacy `SUM(total_price) + SUM(embalase)` calculation which tracks the un-discounted subtotal, while `Total Beli` uses `SUM(final_price)`.

## Proposed Changes

### 1. Unify Cart Totals (Fix `Jumlah` vs `Total Beli`)
We will unify the total variables so both "Jumlah" and "Total Beli" reflect the exact same source of truth (`SUM(final_price)`).
#### [MODIFY] `app/Http/Controllers/SalesController.php`
- In `index()`, `addToCart()`, `updateCart()`, and `removeItem()`, change the `$totalbought` calculation to simply use `$total_transaction` (`SUM(final_price)`). This ensures the frontend's `totalbought` and `totaltransaction` never diverge.

### 2. Fix Decimal Display in Cart Table
We will remove the confusing decimals and show the "Effective Unit Price" or a rounded base price so the math makes visual sense to the cashier.
#### [MODIFY] `resources/views/kasir/transaction.blade.php`
- Change the Blade template for the cart table:
  - Instead of `number_format($cart->item_price, 2, ',', '.')` (which shows decimals), we will format it without decimals: `number_format($cart->item_price, 0, ',', '.')`.
  - Alternatively, if the user prefers, we can display the "Harga Jual" that aligns with the subtotal.
- In the JS `addToCart` success callback that dynamically appends rows, ensure the appended row also formats the unit price without decimals.

## Open Questions

> [!IMPORTANT]
> **To the User:** For the unit price in the cart table, currently it shows exact decimals (e.g., `Rp 25.591,04`) while the total is rounded (e.g., `Rp 27.000`). To fix the visual confusion, I can either:
> 1. Hide the decimals and just show `Rp 25.591`.
> 2. Show the **Effective Unit Price** (Total / Qty), which would be `Rp 27.000` for qty 1, making the math perfectly clear (`1 x 27.000 = 27.000`).
> 
> Which approach do you prefer for the Unit Price column?

## Verification Plan
- Add items to the cart and verify that the unit price matches the chosen rounding method.
- Verify that "Jumlah" and "Total Beli" at the top right of the POS remain perfectly in sync.
