# FLOW 15 — KIOTVIET SALES ORDER / ORDER-TO-INVOICE TEST INSTRUCTION

## Objective
Validate that the application implements **Sales Order / Đặt hàng** behavior close to KiotViet Retail.
This flow covers:
- creating a sales order,
- recording customer deposit,
- editing the order before fulfillment,
- converting the order into a sales invoice,
- reflecting prior deposit during invoice payment,
- canceling / ending orders,
- merging compatible orders,
- preserving auditability and balances.

The agent must test **only this flow**. Do not test unrelated features unless they are directly required to complete this flow.

---

## Official KiotViet behavior baseline
Use the following as the reference behavior baseline:

1. KiotViet has a dedicated **Đặt hàng** flow under sales/order management.
2. Users can create an order, search/add products, select or add a customer, adjust line values, and enter a customer deposit during checkout.
3. Users can later **xử lý đặt hàng** and **tạo hóa đơn** from the order.
4. Existing customer deposit / prepayment on the order is shown during invoice payment when converting the order to an invoice.
5. Order list management supports searching/filtering and actions such as canceling, ending, exporting, printing, editing some common fields, and processing orders.
6. KiotViet supports **merging sales orders** only when orders are compatible, specifically same customer, same branch, and in allowed states such as draft / confirmed.

Reference docs:
- https://www.kiotviet.vn/huong-dan-su-dung-kiotviet/retail-dat-hang/dat-hang/
- https://www.kiotviet.vn/huong-dan-su-dung-kiotviet/ung-dung-tren-mobile-mobile-app/xu-ly-dat-hang/

---

## Required preconditions
This flow assumes the following are already working:
- Flow 01 foundation master data
- Flow 03 sales invoice basics
- Flow 04 customer receivables
- Flow 11 user permissions
- Flow 13 audit history
- Flow 14 lock period (if implemented)

If one of these foundations is broken, mark the case as **BLOCKED** and explain exactly why.

---

## Fixed test dataset
Use or seed the following stable dataset:

### Branch / warehouse
- Branch: `CN01`
- Sales warehouse: `KHO_BAN`

### Products
1. `SP001` — Water 500ml
   - track_inventory = true
   - sale_price = 7000
   - on_hand = 100
2. `SP002` — Biscuit Box
   - track_inventory = true
   - sale_price = 30000
   - on_hand = 50
3. `DV001` — Delivery Fee
   - track_inventory = false
   - sale_price = 15000

### Customers
1. `KH001` — Nguyen Van A — phone `0900000001`
2. `KH002` — Tran Thi B — phone `0900000002`

### Users
- `admin01`: full permission
- `sale01`: can create/edit/process orders and create invoices
- `viewer01`: read only, cannot create/edit/process/cancel orders

### Accounting assumptions for expected results
- Deposits collected on an order must be traceable.
- Converting order -> invoice must not lose the relationship to the original order.
- Payment screen for converted invoice must surface previously paid/deposited amount from the order.
- Inventory must not be deducted merely because an order was created, unless the app intentionally supports reservation and documents it clearly. If reservation exists, the agent must explicitly report the deviation.

---

## Agent operating rules

1. **Read source before changing anything**.
2. Prefer to prove failures using:
   - route/controller/service code,
   - DB writes,
   - UI behavior,
   - automated tests.
3. Fix only the smallest scope necessary.
4. Do not refactor unrelated modules.
5. After each fix, re-run only the failed case first, then run the whole Flow 15 suite.
6. If behavior differs from KiotViet but is clearly intentional in this app, classify as:
   - `PASS_WITH_DEVIATION`
   and explain the difference.

---

## What the agent must inspect in source
Before running tests, inspect and summarize:

- sales order entities/tables/models
- order line item persistence
- order status/state machine
- deposit/prepayment persistence for orders
- relation between order and invoice
- payment allocation logic during order -> invoice conversion
- cancel/end logic
- merge-order logic and compatibility checks
- permission gates for order actions
- audit/event log writes for create/edit/process/cancel/merge

Potential names may include:
- `sales_orders`
- `sale_orders`
- `orders`
- `order_items`
- `order_payments`
- `invoice_from_order`
- `convert_order_to_invoice`
- `merge_orders`
- `cancel_order`
- `end_order`

---

## State model expected by the agent
Map the app states to this conceptual model:
- `draft`
- `confirmed`
- `converted_to_invoice` or equivalent completed sales state
- `ended`
- `canceled`

If the app uses different names, produce a mapping table in the report.

---

## Test cases

### 15A — Create sales order without deposit
**Steps**
1. Login as `sale01`.
2. Open Sales -> Orders / Đặt hàng.
3. Create new order for `KH001`.
4. Add `SP001 x 2` and `SP002 x 1`.
5. Do not enter deposit.
6. Save/complete order.

**Expected**
- Order is created successfully.
- Order total = `2*7000 + 1*30000 = 44000`.
- Customer deposit = `0`.
- Remaining amount = `44000`.
- Order appears in order list and detail page.
- Inventory is **not** permanently deducted merely by creating the order, unless the app explicitly has stock reservation.
- Audit log/event trail exists for order creation.

**Fail if**
- order cannot be created,
- totals are wrong,
- deposit is incorrectly posted,
- stock is deducted with no documented reservation logic,
- no order record or no audit record exists.

---

### 15B — Create sales order with deposit
**Steps**
1. Create new order for `KH001`.
2. Add `SP001 x 5`.
3. Enter deposit = `20000`.
4. Save/complete order.

**Expected**
- Order total = `35000`.
- Deposit recorded = `20000`.
- Outstanding on order = `15000`.
- A receipt / payment record or equivalent deposit ledger is created and traceable.
- Customer debt/receivable presentation reflects the order deposit logic consistently.

**Fail if**
- deposit is lost,
- deposit is stored on screen only but not persisted,
- deposit is persisted but not linked to the order/customer,
- customer receivable summary becomes inconsistent.

---

### 15C — Search and reopen existing order
**Steps**
1. Search order by order code.
2. Search by customer name `Nguyen Van A`.
3. Search by phone `0900000001`.
4. Open the existing order.

**Expected**
- Search can find the order by at least order code and customer dimensions.
- Opening order detail shows lines, total, deposit, notes, customer, status.

**Fail if**
- order list cannot find order by normal business keys,
- detail view misses critical fields.

---

### 15D — Edit order before conversion
**Steps**
1. Reopen a not-yet-converted order.
2. Change quantity of `SP001` from `5` to `4`.
3. Add `DV001 x 1`.
4. Save.

**Expected**
- Order remains editable before conversion if state permits.
- New total = `4*7000 + 15000 = 43000` if only those lines remain.
- Existing deposit remains intact unless explicitly changed.
- Outstanding amount recalculates correctly.
- Audit trail shows update event.

**Fail if**
- editing a valid order is impossible,
- edited totals do not recalculate,
- deposit disappears or duplicates,
- line edits do not persist.

---

### 15E — Convert order to invoice
**Steps**
1. Open a confirmed order with deposit.
2. Trigger **Tạo hóa đơn** / convert to invoice.
3. Complete invoice payment flow.

**Expected**
- Invoice is created from the order.
- Order and invoice remain linked.
- Prior deposit from the order is visible during payment or already applied as a credit in a traceable way.
- Remaining amount due on invoice reflects the prior deposit correctly.
- Inventory is deducted at invoice/posting stage if that is the app model.
- Order status changes to a processed/converted state and cannot be incorrectly re-converted.
- Audit trail exists for conversion.

**Fail if**
- conversion duplicates or ignores the prior deposit,
- invoice total is wrong,
- order can be converted repeatedly without control,
- invoice is created but relation to order is lost.

---

### 15F — Partial payment at conversion after prior deposit
**Steps**
1. Use an order total `70000` with prior deposit `20000`.
2. Convert to invoice.
3. Pay only `30000` more during invoice creation.

**Expected**
- Total settled = `50000`.
- Remaining receivable = `20000`.
- Deposit is not counted twice.
- Customer debt reflects exactly the unpaid remainder.
- Receipt/payment history shows both deposit and invoice-time payment clearly.

**Fail if**
- remaining debt is miscomputed,
- deposit is double-counted,
- payment history is ambiguous or missing.

---

### 15G — Cancel order before conversion
**Steps**
1. Create a new order that has not yet been converted.
2. Cancel the order.

**Expected**
- Order state becomes `canceled`.
- If there was no deposit, outstanding becomes inactive/closed with no receivable leak.
- If there was a deposit, the app must have a clear treatment: refund, credit, or retained deposit according to implemented business rules. The agent must report exactly what the app does.
- Canceled order should not be processable into invoice.
- Audit trail records cancellation.

**Fail if**
- canceled order can still be converted,
- deposit/cash effect is orphaned,
- order disappears entirely with no historical trace.

---

### 15H — End order when not fully fulfilled
**Steps**
1. Take an order in a state where the app allows ending/closing without full fulfillment.
2. Trigger **Kết thúc** / end order.

**Expected**
- Ended order becomes closed for further processing.
- Reason/notes behavior is clear if required.
- Remaining quantity/value is no longer accidentally convertible.
- Audit trail records end action.

**Fail if**
- ended order can still be processed as normal,
- state is unclear or inconsistent.

---

### 15I — Prevent incompatible merge
**Steps**
1. Create two orders with different customers.
2. Try to merge them.
3. Create two orders same customer but different branches if the app supports branches.
4. Try to merge them.

**Expected**
- Merge is rejected for incompatible orders.
- Validation error explains why.

**Fail if**
- incompatible orders are merged,
- merged result corrupts customer/branch ownership.

---

### 15J — Merge compatible orders
**Steps**
1. Create 2 compatible orders:
   - same customer,
   - same branch,
   - valid merge states.
2. Merge them.

**Expected**
- System creates a merged order or equivalent final order as implemented.
- Legacy orders are canceled/closed/linked according to app design.
- No line item is lost.
- Totals and deposits remain mathematically correct and traceable.
- Audit trail records merge operation.

**Fail if**
- lines disappear,
- deposits are lost or doubled,
- old orders remain processable in a dangerous way after merge.

---

### 15K — Permission checks
**Steps**
1. Login as `viewer01`.
2. Attempt to create order.
3. Attempt to edit existing order.
4. Attempt to cancel/process/merge order.

**Expected**
- Viewer cannot create/edit/process/cancel/merge orders.
- UI hides forbidden actions or API rejects with authorization error.
- No unauthorized DB write occurs.

**Fail if**
- forbidden actions succeed,
- UI blocks but API still allows,
- partial writes occur before rejection.

---

### 15L — Locked period protection (if feature exists)
**Steps**
1. Use an accounting-locked date range.
2. Attempt to create/edit/cancel an order dated within the locked period.
3. Attempt order -> invoice conversion on a locked date.

**Expected**
- Operations violating locked period are blocked consistently.
- No hidden API or background path bypasses the lock.

**Fail if**
- UI blocks but API bypasses,
- conversion still posts accounting/inventory in locked period.

---

## Required DB / ledger checks
For each case, inspect or assert the following where applicable:
- sales order header row
- sales order item rows
- deposit/payment rows
- invoice row created from order
- linkage between order and invoice
- receivable/customer ledger rows
- inventory movement rows
- audit/history rows

If your app uses different table names, document the mapping.

---

## Allowed deviation handling
If the app intentionally differs from KiotViet, the agent must classify as:

### PASS_WITH_DEVIATION
Use only when all of the following are true:
1. the behavior is internally consistent,
2. there is no data corruption,
3. the difference is explainable,
4. the app documents or clearly signals that behavior.

Example possible deviation:
- app reserves stock at order creation, while KiotViet documentation for order flow does not explicitly state permanent deduction at that step.

If such a deviation exists, agent must explain:
- what KiotViet does or implies,
- what the app does,
- whether the deviation is safe,
- whether a UX note or warning is needed.

---

## Defect classification
When defects are found, classify each as one of:
- `ORDER_STATE_BUG`
- `TOTAL_CALCULATION_BUG`
- `DEPOSIT_PERSISTENCE_BUG`
- `ORDER_TO_INVOICE_CONVERSION_BUG`
- `PAYMENT_ALLOCATION_BUG`
- `MERGE_VALIDATION_BUG`
- `PERMISSION_BUG`
- `LOCK_PERIOD_BUG`
- `AUDIT_LOG_BUG`
- `UI_ONLY_BUG`

---

## Fix rules
If a defect is confirmed:
1. identify exact failing layer: UI / API / service / DB / policy
2. patch minimal scope
3. add or update automated tests for the exact defect
4. rerun failed test
5. rerun all Flow 15 tests
6. include changed files list

Do not change data model broadly unless absolutely required.

---

## Deliverables required from the agent
At the end, produce a report with these sections:

### 1. Source understanding summary
- modules/files inspected
- state mapping
- order->invoice flow mapping

### 2. Test execution matrix
For each case `15A` to `15L`, output:
- status: `PASS | FAIL | BLOCKED | PASS_WITH_DEVIATION`
- evidence
- root cause if failed

### 3. Defects found
For each defect:
- defect id
- classification
- user-visible symptom
- technical root cause
- changed files

### 4. Retest results
- failed cases rerun result
- full Flow 15 rerun result

### 5. Remaining risks
- anything not confidently verified
- any deviation from KiotViet

---

## Final instruction to the agent
Work only on **Flow 15: Sales Order / Order-to-Invoice**.
Do not continue to any next flow.
Do not silently change business rules.
When uncertain, prefer evidence from code + DB + repeatable test over assumption.
