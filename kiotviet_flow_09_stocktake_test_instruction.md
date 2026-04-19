# KiotViet Flow 09 — Inventory Count / Stocktake Test Instruction

## Purpose
This instruction tells the agent how to audit, test, and minimally fix the **Inventory Count / Stocktake** flow in the target source code so behavior matches KiotViet as closely as possible.

The agent must focus on **one flow only**:
- Create stocktake draft
- Add products into stocktake
- Enter actual quantities
- Complete stocktake to balance inventory
- Update/cancel/copy/search/export stocktake
- Merge multiple draft stocktakes when supported

Do not expand into unrelated flows unless they are strictly required to prove or fix this flow.

---

## Source-of-truth behavior to match from KiotViet
The target implementation should match these behavioral expectations:

1. A stocktake must be created **for a specific warehouse**.
2. Products can be added into the stocktake by:
   - search,
   - barcode scan,
   - group/category selection,
   - Excel import (if supported in the app).
3. The user enters **actual quantity** for each item.
4. If actual quantity equals system quantity, the item is considered **matched**.
5. Completing the stocktake must **balance inventory** so on-hand stock equals actual counted quantity.
6. A **draft stocktake** can be reopened and edited.
7. A **completed / balanced** stocktake can be **canceled**, and canceling must restore inventory to the state before the stocktake.
8. A stocktake can be **copied** to create a new stocktake with similar product lines.
9. Multiple **draft** stocktakes can be **merged** into a new draft when the feature exists.
10. Drafts from different warehouses must **not** be merged together.
11. The user can search/filter stocktakes by code, product, time, status.
12. Export must not mutate stock or status.

---

## Non-negotiable guardrails
The agent must obey all of the following:

1. **Read before changing**
   - Inspect routes, controllers, services, actions, repositories, DB schema, UI screens, policies/permissions, and automated tests related to stocktake / inventory count / balance inventory.
   - Build a short flow map before any code change.

2. **Do not refactor broadly**
   - Only fix what is needed for this flow.
   - Prefer the smallest safe fix.

3. **Prove the bug first**
   - Before patching, reproduce the defect with a manual step list and, where possible, with an automated test.

4. **Preserve accounting/inventory integrity**
   - Never patch UI only when the underlying stock ledger / stock movement / inventory balance is wrong.
   - Never silently overwrite stock without a traceable movement or balancing record.

5. **Retest after every fix**
   - Re-run the failing case.
   - Re-run related cases that might regress:
     - stock on hand,
     - warehouse-specific balance,
     - product search in stocktake,
     - cancel stocktake,
     - merge drafts.

---

## Expected project reconnaissance
Before testing, inspect and summarize these areas in the target source:

- Warehouse model/table
- Product model/table
- Inventory balance table(s)
- Inventory movement / stock ledger table(s)
- Stocktake / inventory count header table
- Stocktake line items table
- Status enum/constants for stocktake
- Services/actions for:
  - create draft,
  - add lines,
  - complete/balance,
  - cancel,
  - copy,
  - merge drafts,
  - export/search/filter
- UI screens / API endpoints for stocktake
- Permission rules for warehouse selection and stocktake operations

The agent must write a short note like this before testing:
- `stocktake header stored in ...`
- `stocktake lines stored in ...`
- `inventory balance updated by ...`
- `cancel logic implemented in ...`
- `warehouse-specific constraints implemented in ...`

---

## Fixed seed data for this flow
Use deterministic seed data. If data does not exist, create it through seeders/factories or controlled setup scripts.

### Warehouses
- `KHO_A` — Kho A
- `KHO_B` — Kho B

### Products
1. `SP001` — Nước suối 500ml
2. `SP002` — Bánh quy hộp
3. `SP003` — Sữa hộp 1L
4. `DV001` — Phí giao hàng (service / non-stock item)

### Initial balances
In `KHO_A`:
- `SP001`: 20
- `SP002`: 10
- `SP003`: 5

In `KHO_B`:
- `SP001`: 7
- `SP002`: 0
- `SP003`: 12

### Users
- `admin`: full stocktake access
- `kho01`: can create/edit/complete stocktake for assigned warehouse(s)
- `sale01`: cannot complete stocktake unless explicitly allowed by the target app rules

### Important rule for this flow
- `DV001` must not be included in stocktake if the system treats it as non-stock/service.

---

## Test methodology
For every case below, the agent must verify all 4 layers:

1. **UI/API behavior**
2. **Persisted transaction data**
3. **Inventory balance result**
4. **Inventory movement / balancing trace**

When a case says “check inventory”, verify both:
- current balance by warehouse,
- ledger/movement history produced by the stocktake.

---

## Core cases

### 09A — Create stocktake draft for a specific warehouse
**Goal**: prove the flow starts with warehouse selection and stores a draft.

**Steps**
1. Open stocktake screen.
2. Choose `KHO_A`.
3. Create a new stocktake.
4. Save as draft without completing.

**Expected**
- A stocktake document is created for `KHO_A`.
- Status is equivalent to `draft` / `phiếu tạm`.
- No inventory balance changes yet.
- No balancing movement should be posted yet.

**Fail if**
- warehouse is missing or optional when the app supports warehouse-managed stocktake,
- saving draft already changes inventory,
- wrong warehouse is attached.

---

### 09B — Add products into stocktake by search
**Goal**: ensure stock items can be added by search and non-stock items are handled correctly.

**Steps**
1. Open a draft stocktake for `KHO_A`.
2. Search and add `SP001`, `SP002`, `SP003`.
3. Try searching `DV001`.

**Expected**
- Stock items are added successfully.
- System quantity displayed for each item matches `KHO_A` balance only.
- `DV001` is either blocked or excluded from stocktake according to non-stock rules.

**Fail if**
- warehouse-specific quantity is wrong,
- search returns items from wrong warehouse context,
- non-stock service can be balanced as physical stock.

---

### 09C — Enter actual quantity: matched, shortage, overage
**Goal**: verify actual quantity entry and discrepancy calculation.

**Steps**
1. In draft stocktake for `KHO_A`, enter:
   - `SP001` actual = 20 (matched)
   - `SP002` actual = 8 (shortage of 2)
   - `SP003` actual = 7 (overage of 2)
2. Save as draft.

**Expected**
- Line-level discrepancy is computed correctly.
- Draft stores actual count values.
- Inventory balance is still unchanged while draft remains open.

**Fail if**
- discrepancy sign is reversed,
- saving draft already updates inventory,
- draft forgets actual counted quantity.

---

### 09D — Complete stocktake to balance inventory
**Goal**: verify completion changes inventory to actual counted quantity.

**Precondition**
Use the draft from 09C.

**Steps**
1. Complete the stocktake.

**Expected**
- Status changes to equivalent of `balanced` / `đã cân bằng kho` / `completed`.
- `KHO_A` balances become:
  - `SP001` = 20
  - `SP002` = 8
  - `SP003` = 7
- A traceable balancing movement is created for the delta only.
- Warehouse `KHO_B` remains unchanged.

**Fail if**
- completion does not affect stock,
- all lines are rewritten without movement trace,
- other warehouse stock changes,
- final stock differs from actual count.

---

### 09E — Cancel a completed stocktake
**Goal**: verify cancel restores inventory to the pre-stocktake state.

**Precondition**
Use the completed stocktake from 09D.

**Steps**
1. Cancel the completed stocktake.

**Expected**
- Stocktake status changes to canceled (or equivalent).
- `KHO_A` balances revert to the exact pre-09D state:
  - `SP001` = 20
  - `SP002` = 10
  - `SP003` = 5
- Reversal movement / restoration trace exists.
- No duplicate residual adjustment remains.

**Fail if**
- stock does not revert,
- stock reverts partially,
- cancel hard-deletes history,
- other warehouses are touched.

---

### 09F — Reopen and update draft stocktake
**Goal**: confirm only draft stocktakes are editable.

**Steps**
1. Create a new stocktake for `KHO_A`.
2. Add `SP001`, set actual = 19.
3. Save draft.
4. Reopen the draft.
5. Change actual to 18.
6. Save draft again.

**Expected**
- Draft can be reopened and edited.
- Updated actual quantity persists.
- Inventory remains unchanged until completion.

**Fail if**
- draft cannot be updated,
- edit creates duplicate lines unexpectedly,
- draft edit already changes stock.

---

### 09G — Copy stocktake
**Goal**: verify copy creates a new stocktake with similar lines without mutating the source.

**Steps**
1. Create or use an existing stocktake.
2. Trigger copy.

**Expected**
- A new stocktake is created.
- Source document remains unchanged.
- Copied lines and warehouse context are preserved appropriately.
- New document has a new code/id and starts in editable state.

**Fail if**
- copy mutates original,
- copied document starts in completed state unexpectedly,
- wrong warehouse is assigned.

---

### 09H — Merge multiple draft stocktakes from the same warehouse
**Goal**: verify same-warehouse drafts can merge into one draft.

**Setup**
Create 2 draft stocktakes for `KHO_A`:
- Draft 1: `SP001` actual = 20
- Draft 2: `SP002` actual = 9

**Steps**
1. Select both drafts.
2. Merge drafts.

**Expected**
- A new merged draft is created.
- It contains combined counted quantities from selected drafts.
- Original drafts are handled according to implementation rules, but the result must be deterministic and traceable.
- No inventory balance changes during merge.

**Fail if**
- merge completes or balances stock automatically,
- merged quantities are lost or duplicated,
- cross-warehouse contamination occurs.

---

### 09I — Prevent merging drafts from different warehouses
**Goal**: verify warehouse isolation.

**Setup**
Create:
- Draft A in `KHO_A`
- Draft B in `KHO_B`

**Steps**
1. Attempt merge.

**Expected**
- System blocks merge, or only allows same-warehouse drafts.
- No merged draft is created from mixed warehouses.
- No stock changes occur.

**Fail if**
- different-warehouse drafts can merge,
- merged result loses warehouse correctness,
- stock balances become inconsistent.

---

### 09J — Search and filter stocktakes
**Goal**: verify operational usability without changing data.

**Steps**
1. Search by document code.
2. Search by product code/name.
3. Filter by time.
4. Filter by status.

**Expected**
- Results are correct and deterministic.
- Search/filter do not mutate data.

**Fail if**
- incorrect records appear,
- searching changes status accidentally,
- warehouse scoping is ignored where applicable.

---

### 09K — Export stocktake
**Goal**: verify export/read-only behavior.

**Steps**
1. Export one stocktake.
2. Export multiple stocktakes if supported.

**Expected**
- Export succeeds.
- Exported data matches current document lines and status.
- Export does not change stock, status, timestamps, or counts.

**Fail if**
- export mutates the document,
- export includes wrong warehouse/product quantities.

---

### 09L — Role/permission check for stocktake
**Goal**: verify access control for stocktake flow.

**Steps**
1. Login as `kho01` and try create/update/complete stocktake.
2. Login as `sale01` and try the same.

**Expected**
- Authorized warehouse/inventory users can perform allowed actions.
- Unauthorized users cannot complete or cancel stocktake if not permitted.

**Fail if**
- broad access is given by mistake,
- unauthorized user can balance inventory.

---

### 09M — Warehouse-specific quantity integrity
**Goal**: verify displayed and balanced stock are warehouse-specific.

**Steps**
1. Create stocktake in `KHO_B`.
2. Add `SP001`.
3. Observe system quantity before count.

**Expected**
- Quantity shown must be `7`, not total across all warehouses.
- Completing this stocktake must only affect `KHO_B`.

**Fail if**
- total global quantity is shown instead of warehouse quantity,
- completion changes `KHO_A` stock.

---

## Optional cases if the target app claims support
Only run these if the feature exists in the source/UI.

### 09N — Add products by category/group selection
- Verify selecting a category injects the correct product set into the stocktake.

### 09O — Add products by Excel import
- Verify imported lines map correctly to products and actual quantities.

### 09P — Barcode scanning mode
- Verify scan adds the correct product and updates counted quantity correctly.

### 09Q — Count input mode: overwrite vs add-on
If the target app supports different count modes:
- `overwrite`: replacing previously entered actual quantity
- `add-on`: incrementing counted quantity

The agent must verify both semantics are implemented correctly.

---

## Database-level invariants
For every completion/cancel scenario, verify these invariants:

1. **No negative corruption from stocktake**
   - Stocktake may produce any delta, but resulting inventory must equal actual count exactly.

2. **Warehouse isolation**
   - Only the selected warehouse balance is changed.

3. **Traceability**
   - There is a persistent document or movement trail proving the adjustment.

4. **Reversibility**
   - Canceling a completed stocktake restores the exact previous balance.

5. **Draft safety**
   - Draft operations never mutate inventory.

---

## Suggested automated test coverage
Create automated tests where possible.

Minimum recommended automated tests:
1. create stocktake draft does not change stock
2. complete stocktake balances inventory to actual quantity
3. cancel completed stocktake restores previous stock
4. cannot merge drafts from different warehouses
5. merge same-warehouse drafts produces new draft only
6. stock quantities shown are warehouse-specific
7. non-stock/service item cannot be stock-counted
8. unauthorized user cannot complete stocktake

---

## Debug checklist when behavior is wrong
If any case fails, inspect in this order:

1. warehouse scoping in queries
2. stock balance source of truth
3. stock movement creation on complete/cancel
4. status machine and allowed transitions
5. duplicate line insertion / update logic
6. service vs stock item classification
7. merge algorithm for draft stocktakes
8. policy/permission enforcement

---

## Fix strategy
If defects are found, the agent must:

1. describe the exact deviation from expected KiotViet-like behavior,
2. identify the root cause in source,
3. apply the smallest possible fix,
4. add or update automated tests,
5. rerun:
   - failed case,
   - related stock integrity cases,
   - warehouse isolation cases.

Do not rewrite unrelated modules.

---

## Final report format
At the end, produce a report with these sections:

### 1. Recon summary
- files inspected
- stocktake architecture summary
- inventory source-of-truth summary

### 2. Case results
For each case `09A ... 09M`, output:
- status: PASS / FAIL / NA / PASS WITH DEVIATION
- evidence: UI/API/DB observation
- impacted files if any

### 3. Defects found
For each defect:
- title
- severity
- reproduce steps
- expected
- actual
- root cause

### 4. Fixes applied
- files changed
- what changed
- why this is the minimum safe fix

### 5. Retest results
- which cases were rerun
- final status after fix

### 6. Remaining gaps
- unsupported KiotViet behaviors
- intentional deviations
- risky areas not yet covered

---

## Stop condition
Stop after completing **Flow 09 only**.
Do not continue to other flows automatically.
