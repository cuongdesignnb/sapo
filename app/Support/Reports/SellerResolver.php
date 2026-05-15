<?php

namespace App\Support\Reports;

use App\Models\Employee;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HOTFIX 24.26 — Centralised seller resolution for all report controllers.
 * HOTFIX 24.27 — Fix duplicate sellers and align invoice filter consistency.
 *
 * Seller keys use prefixed strings to avoid ambiguity between
 * employees.id and users.id:
 *
 *   employee:<id>   — invoice created by an employee
 *   user:<id>       — invoice created by a user (admin/staff without employee row)
 *   orphan:<name>   — created_by IS NULL, created_by_name doesn't match any user
 *   unknown         — no creator info at all
 *
 * HOTFIX 24.27 canonical merge rules:
 *   - If employee has user_id → user:<user_id> merges into employee:<employee_id>
 *   - If created_by matches a user who has an employee row → canonical = employee:<employee_id>
 *   - If created_by matches employee AND user with DIFFERENT names → use created_by_name to disambiguate
 *   - Orphan names matching exactly ONE employee → merge to employee:<id>
 *   - Orphan names matching multiple employees → stay orphan:<name>
 */
class SellerResolver
{
    /**
     * HOTFIX 24.27 — Build a map from user_id → employee for merge.
     * Returns: array<user_id, Employee>
     */
    private function buildUserToEmployeeMap(): array
    {
        return Employee::whereNotNull('user_id')
            ->get(['id', 'name', 'code', 'user_id'])
            ->keyBy('user_id')
            ->all();
    }

    // ═══════════════════════════════════════
    // Key generation
    // ═══════════════════════════════════════

    /**
     * Build a seller-key map for every invoice in $query.
     * Returns: array<invoice_id, seller_key>
     */
    public function invoiceSellerMap($query): array
    {
        // HOTFIX 24.27 — only select columns that exist
        $selectCols = ['id', 'created_by', 'created_by_name'];
        if (Schema::hasColumn('invoices', 'employee_id')) {
            $selectCols[] = 'employee_id';
        }

        $invoices = (clone $query)
            ->select($selectCols)
            ->get();

        if ($invoices->isEmpty()) return [];

        // Preload lookup tables
        $createdByIds = $invoices->pluck('created_by')->filter()->unique()->values()->all();
        $employeeIds  = Schema::hasColumn('invoices', 'employee_id')
            ? $invoices->pluck('employee_id')->filter()->unique()->values()->all()
            : [];
        $allIds       = array_values(array_unique(array_merge($createdByIds, $employeeIds)));

        $employeeIdSet = !empty($allIds)
            ? Employee::whereIn('id', $allIds)->pluck('name', 'id')->all()
            : [];
        $userIdSet = !empty($allIds)
            ? User::whereIn('id', $allIds)->whereNull('deleted_at')->pluck('name', 'id')->all()
            : [];

        // HOTFIX 24.27 — user_id → employee map for canonical merge
        $userToEmployee = $this->buildUserToEmployeeMap();

        $orphanNames = $invoices->whereNull('created_by')
            ->pluck('created_by_name')->filter()->unique()->values()->all();
        $userByName = !empty($orphanNames)
            ? User::whereIn('name', $orphanNames)->whereNull('deleted_at')->pluck('id', 'name')->all()
            : [];
        // HOTFIX 24.27 — orphan name → employee match (only if exactly one match)
        $employeeByName = [];
        if (!empty($orphanNames)) {
            $empsByName = Employee::whereIn('name', $orphanNames)->get(['id', 'name', 'code']);
            $grouped = $empsByName->groupBy('name');
            foreach ($grouped as $name => $emps) {
                if ($emps->count() === 1) {
                    $employeeByName[$name] = $emps->first();
                }
                // If multiple employees share the same name, do NOT merge — stay orphan
            }
        }

        $map = [];
        foreach ($invoices as $inv) {
            $map[$inv->id] = $this->resolveKey(
                $inv->created_by,
                $inv->created_by_name,
                $inv->employee_id ?? null,
                $employeeIdSet,
                $userIdSet,
                $userByName,
                $userToEmployee,
                $employeeByName
            );
        }
        return $map;
    }

    /**
     * HOTFIX 24.27 — Determine seller key for a single invoice record.
     *
     * Resolution priority:
     * 1. If created_by present:
     *    a. Check if it's a user who has an employee row → canonical employee:<emp_id>
     *    b. If both employee and user exist with same ID, use created_by_name to disambiguate
     *    c. If only employee exists → employee:<id>
     *    d. If only user exists → check if user has employee row → merge or user:<id>
     *    e. Neither → orphan:User #<id>
     * 2. If created_by NULL + created_by_name present:
     *    a. Match user → check if user has employee row → merge or user:<id>
     *    b. Match exactly one employee → employee:<id>
     *    c. No match → orphan:<name>
     * 3. No info → unknown
     */
    private function resolveKey(
        $createdBy,
        $createdByName,
        $employeeId,
        array $empNameById,
        array $userNameById,
        array $userByName,
        array $userToEmployee,
        array $employeeByName
    ): string {
        // Priority 1: created_by present
        if ($createdBy !== null && $createdBy !== '') {
            $id = (int) $createdBy;
            $empExists  = array_key_exists($id, $empNameById);
            $userExists = array_key_exists($id, $userNameById);

            if ($empExists && $userExists) {
                // HOTFIX 24.27 — Both employee and user share the same numeric ID.
                // Use created_by_name to disambiguate.
                $empName  = $empNameById[$id];
                $userName = $userNameById[$id];

                if ($createdByName !== null && $createdByName !== '') {
                    if ($createdByName === $userName && $createdByName !== $empName) {
                        // Name matches user, not employee → this is a user invoice
                        // Check if user has an employee row for merge
                        if (isset($userToEmployee[$id])) {
                            return "employee:{$userToEmployee[$id]->id}";
                        }
                        return "user:{$id}";
                    }
                    if ($createdByName === $empName && $createdByName !== $userName) {
                        // Name matches employee → this is an employee invoice
                        return "employee:{$id}";
                    }
                }
                // Names are the same or can't disambiguate — prefer user (admin)
                // then check for employee merge
                if (isset($userToEmployee[$id])) {
                    return "employee:{$userToEmployee[$id]->id}";
                }
                return "user:{$id}";
            }

            if ($userExists) {
                // HOTFIX 24.27 — User exists; check if they have an employee row
                if (isset($userToEmployee[$id])) {
                    return "employee:{$userToEmployee[$id]->id}";
                }
                return "user:{$id}";
            }

            if ($empExists) {
                return "employee:{$id}";
            }

            return "orphan:User #{$id}";
        }

        // Priority 2: orphan with created_by_name
        if ($createdByName !== null && $createdByName !== '') {
            $userId = $userByName[$createdByName] ?? null;
            if ($userId) {
                // Check if this user has an employee row → merge
                if (isset($userToEmployee[$userId])) {
                    return "employee:{$userToEmployee[$userId]->id}";
                }
                return "user:{$userId}";
            }
            // HOTFIX 24.27 — Check if name matches exactly one employee
            if (isset($employeeByName[$createdByName])) {
                return "employee:{$employeeByName[$createdByName]->id}";
            }
            return "orphan:{$createdByName}";
        }

        return 'unknown';
    }

    // ═══════════════════════════════════════
    // Aggregation helpers
    // ═══════════════════════════════════════

    /**
     * Aggregate a per-invoice expression grouped by seller key.
     * Returns: array<seller_key, float>
     */
    public function aggregateBySeller($invoiceQuery, string $valueExpr): array
    {
        $sellerMap = $this->invoiceSellerMap($invoiceQuery);
        if (empty($sellerMap)) return [];

        $invoiceIds = array_keys($sellerMap);
        $rows = DB::table('invoices')
            ->whereIn('id', $invoiceIds)
            ->select('id', DB::raw("({$valueExpr}) as val"))
            ->groupBy('id')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $key = $sellerMap[$row->id] ?? 'unknown';
            $result[$key] = ($result[$key] ?? 0) + (float) $row->val;
        }
        return $result;
    }

    /**
     * Aggregate invoice_items expression grouped by seller key.
     */
    public function aggregateItemsBySeller($invoiceQuery, string $itemExpr): array
    {
        $sellerMap = $this->invoiceSellerMap($invoiceQuery);
        if (empty($sellerMap)) return [];

        $invoiceIds = array_keys($sellerMap);
        $rows = DB::table('invoice_items')
            ->whereIn('invoice_id', $invoiceIds)
            ->select('invoice_id', DB::raw("({$itemExpr}) as val"))
            ->groupBy('invoice_id')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $key = $sellerMap[$row->invoice_id] ?? 'unknown';
            $result[$key] = ($result[$key] ?? 0) + (float) $row->val;
        }
        return $result;
    }

    /**
     * Aggregate return-level expression grouped by original invoice's seller.
     */
    public function aggregateReturnsBySeller($returnQuery, string $valueExpr): array
    {
        $returnRows = (clone $returnQuery)->select('id', 'invoice_id')->get();
        if ($returnRows->isEmpty()) return [];

        $invoiceIds = $returnRows->pluck('invoice_id')->filter()->unique()->values()->all();
        if (empty($invoiceIds)) return [];

        // Build seller map for the original invoices
        $invoiceSellerMap = $this->invoiceSellerMap(
            Invoice::whereIn('id', $invoiceIds)
        );

        // Map return_id → seller_key via invoice_id
        $returnSellerMap = [];
        foreach ($returnRows as $ret) {
            $returnSellerMap[$ret->id] = $invoiceSellerMap[$ret->invoice_id] ?? 'unknown';
        }

        $rows = DB::table('returns')
            ->whereIn('id', array_keys($returnSellerMap))
            ->select('id', DB::raw("({$valueExpr}) as val"))
            ->groupBy('id')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $key = $returnSellerMap[$row->id] ?? 'unknown';
            $result[$key] = ($result[$key] ?? 0) + (float) $row->val;
        }
        return $result;
    }

    /**
     * Aggregate return_items expression grouped by original invoice's seller.
     */
    public function aggregateReturnItemsBySeller($returnQuery, string $itemExpr): array
    {
        $returnRows = (clone $returnQuery)->select('id', 'invoice_id')->get();
        if ($returnRows->isEmpty()) return [];

        $invoiceIds = $returnRows->pluck('invoice_id')->filter()->unique()->values()->all();
        if (empty($invoiceIds)) return [];

        $invoiceSellerMap = $this->invoiceSellerMap(
            Invoice::whereIn('id', $invoiceIds)
        );

        $returnSellerMap = [];
        foreach ($returnRows as $ret) {
            $returnSellerMap[$ret->id] = $invoiceSellerMap[$ret->invoice_id] ?? 'unknown';
        }

        $returnIds = array_keys($returnSellerMap);
        $rows = DB::table('return_items')
            ->whereIn('return_id', $returnIds)
            ->select('return_id', DB::raw("({$itemExpr}) as val"))
            ->groupBy('return_id')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $key = $returnSellerMap[$row->return_id] ?? 'unknown';
            $result[$key] = ($result[$key] ?? 0) + (float) $row->val;
        }
        return $result;
    }

    /**
     * COGS sold by seller (invoice_items.cost_price or fallback products.cost_price).
     */
    public function cogsSoldBySeller($invoiceQuery): array
    {
        $sellerMap = $this->invoiceSellerMap($invoiceQuery);
        if (empty($sellerMap)) return [];

        $invoiceIds = array_keys($sellerMap);
        $hasItemCost = Schema::hasColumn('invoice_items', 'cost_price');
        $costExpr = $hasItemCost
            ? 'invoice_items.quantity * COALESCE(NULLIF(invoice_items.cost_price, 0), products.cost_price, 0)'
            : 'invoice_items.quantity * COALESCE(products.cost_price, 0)';

        $rows = DB::table('invoice_items')
            ->join('products', 'invoice_items.product_id', '=', 'products.id')
            ->whereIn('invoice_items.invoice_id', $invoiceIds)
            ->select('invoice_items.invoice_id', DB::raw("SUM({$costExpr}) as val"))
            ->groupBy('invoice_items.invoice_id')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $key = $sellerMap[$row->invoice_id] ?? 'unknown';
            $result[$key] = ($result[$key] ?? 0) + (float) $row->val;
        }
        return $result;
    }

    /**
     * COGS returned by seller (return_items.cost_price or import_price).
     */
    public function cogsReturnedBySeller($returnQuery): array
    {
        $hasCostPrice = Schema::hasColumn('return_items', 'cost_price');
        $costExpr = $hasCostPrice
            ? 'SUM(return_items.quantity * COALESCE(NULLIF(return_items.cost_price, 0), return_items.import_price, 0))'
            : 'SUM(return_items.quantity * COALESCE(return_items.import_price, 0))';

        return $this->aggregateReturnItemsBySeller($returnQuery, $costExpr);
    }

    // ═══════════════════════════════════════
    // Seller meta resolution
    // ═══════════════════════════════════════

    /**
     * Resolve seller keys to display meta.
     * HOTFIX 24.27 — adds seller_key, seller_type, seller_code fields
     * and disambiguates duplicate display names with code/type suffix.
     *
     * Returns: array<seller_key, {id, key, raw_id, name, code, type, display_name}>
     */
    public function sellerMeta(array $sellerKeys): array
    {
        $sellerKeys = array_values(array_unique(array_filter($sellerKeys)));
        if (empty($sellerKeys)) return [];

        // Extract numeric ids by prefix
        $empIds = [];
        $userIds = [];
        $orphanNames = [];

        foreach ($sellerKeys as $key) {
            if (str_starts_with($key, 'employee:')) {
                $empIds[] = (int) substr($key, 9);
            } elseif (str_starts_with($key, 'user:')) {
                $userIds[] = (int) substr($key, 5);
            } elseif (str_starts_with($key, 'orphan:')) {
                $orphanNames[] = substr($key, 7);
            }
        }

        $employees = !empty($empIds)
            ? Employee::whereIn('id', $empIds)->get(['id', 'name', 'code'])->keyBy('id')
            : collect();
        $users = !empty($userIds)
            ? User::whereIn('id', $userIds)->get(['id', 'name', 'email', 'role_id'])->keyBy('id')
            : collect();

        // HOTFIX 24.27 — First pass: build raw meta
        $meta = [];
        foreach ($sellerKeys as $key) {
            if (str_starts_with($key, 'employee:')) {
                $id  = (int) substr($key, 9);
                $emp = $employees[$id] ?? null;
                $meta[$key] = [
                    'id'     => $key,
                    'key'    => $key,
                    'raw_id' => $id,
                    'name'   => $emp->name ?? "Nhân viên #{$id}",
                    'code'   => $emp->code ?? "NV{$id}",
                    'type'   => 'employee',
                ];
            } elseif (str_starts_with($key, 'user:')) {
                $id   = (int) substr($key, 5);
                $user = $users[$id] ?? null;
                $isAdmin = $user && method_exists($user, 'isAdmin') ? $user->isAdmin() : false;
                $meta[$key] = [
                    'id'     => $key,
                    'key'    => $key,
                    'raw_id' => $id,
                    'name'   => $user->name ?? ($isAdmin ? 'Admin' : "User #{$id}"),
                    'code'   => $isAdmin ? 'ADMIN' : "U{$id}",
                    'type'   => $isAdmin ? 'admin' : 'user',
                ];
            } elseif (str_starts_with($key, 'orphan:')) {
                $name = substr($key, 7);
                $meta[$key] = [
                    'id'     => $key,
                    'key'    => $key,
                    'raw_id' => null,
                    'name'   => $name,
                    'code'   => 'ORPHAN',
                    'type'   => 'orphan',
                ];
            } else {
                $meta[$key] = [
                    'id'     => $key,
                    'key'    => $key,
                    'raw_id' => null,
                    'name'   => 'Không xác định',
                    'code'   => 'UNK',
                    'type'   => 'unknown',
                ];
            }
        }

        // HOTFIX 24.27 — Second pass: disambiguate duplicate display names
        $nameCount = [];
        foreach ($meta as $m) {
            $nameCount[$m['name']] = ($nameCount[$m['name']] ?? 0) + 1;
        }
        foreach ($meta as $key => &$m) {
            if (($nameCount[$m['name']] ?? 0) > 1) {
                // Append code/type to disambiguate
                $suffix = $m['code'] ?? strtoupper($m['type']);
                $m['display_name'] = "{$m['name']} — {$suffix}";
            } else {
                $m['display_name'] = $m['name'];
            }
        }
        unset($m);

        return $meta;
    }

    // ═══════════════════════════════════════
    // Filter options
    // ═══════════════════════════════════════

    /**
     * HOTFIX 24.27 — Build seller filter options using canonical keys.
     * Merges employee+user when employee.user_id links to a user.
     * Only includes sellers who actually have invoices, plus all active employees.
     */
    public function buildSellerFilterOptions(): array
    {
        $userToEmployee = $this->buildUserToEmployeeMap();

        // All active employees
        $employees = Employee::orderBy('name')->get(['id', 'name', 'code', 'user_id']);

        // Sellers from invoice data
        $directIds = Invoice::whereNotNull('created_by')
            ->distinct()->pluck('created_by')
            ->map(fn ($id) => (int) $id)->filter()->values()->all();

        $orphanNames = Invoice::whereNull('created_by')
            ->whereNotNull('created_by_name')
            ->distinct()->pluck('created_by_name')
            ->filter()->values()->all();

        $userByName = !empty($orphanNames)
            ? User::whereIn('name', $orphanNames)->whereNull('deleted_at')->pluck('id', 'name')->all()
            : [];

        $empIdSet  = Employee::pluck('id')->flip()->all();
        $userIdSet = User::whereNull('deleted_at')->pluck('id')->flip()->all();

        $options = [];
        $seen    = [];

        // Add employees (canonical keys)
        foreach ($employees as $emp) {
            $key = "employee:{$emp->id}";
            $options[] = [
                'id'   => $key,
                'key'  => $key,
                'name' => $emp->name,
                'code' => $emp->code ?: "NV{$emp->id}",
                'type' => 'employee',
            ];
            $seen[$key] = true;
            // HOTFIX 24.27 — Mark the user key as seen too (merged)
            if ($emp->user_id) {
                $seen["user:{$emp->user_id}"] = true;
            }
        }

        // Add direct sellers not already represented
        foreach ($directIds as $id) {
            // If this ID is a user who has an employee row → already covered
            if (isset($userIdSet[$id]) && isset($userToEmployee[$id])) {
                continue; // merged into employee:<emp_id>
            }
            if (isset($empIdSet[$id]) && isset($seen["employee:{$id}"])) continue;
            if (isset($userIdSet[$id])) {
                $key = "user:{$id}";
                if (isset($seen[$key])) continue;
                $user = User::find($id);
                $isAdmin = $user && method_exists($user, 'isAdmin') ? $user->isAdmin() : false;
                $options[] = [
                    'id'   => $key,
                    'key'  => $key,
                    'name' => $user->name ?? "User #{$id}",
                    'code' => $isAdmin ? 'ADMIN' : "U{$id}",
                    'type' => $isAdmin ? 'admin' : 'user',
                ];
                $seen[$key] = true;
            }
        }

        // Add orphan names
        foreach ($orphanNames as $name) {
            $userId = $userByName[$name] ?? null;
            if ($userId) {
                // HOTFIX 24.27 — Check if user has employee row → already merged
                if (isset($userToEmployee[$userId])) {
                    continue; // merged into employee:<emp_id>
                }
                if (isset($seen["user:{$userId}"])) continue;
                $key = "user:{$userId}";
                $user = User::find($userId);
                $isAdmin = $user && method_exists($user, 'isAdmin') ? $user->isAdmin() : false;
                $options[] = [
                    'id'   => $key,
                    'key'  => $key,
                    'name' => $user->name ?? $name,
                    'code' => $isAdmin ? 'ADMIN' : "U{$userId}",
                    'type' => $isAdmin ? 'admin' : 'user',
                ];
                $seen[$key] = true;
            } else {
                // HOTFIX 24.27 — Check if orphan name matches exactly one employee
                $matchingEmps = Employee::where('name', $name)->get(['id', 'name', 'code']);
                if ($matchingEmps->count() === 1) {
                    $empKey = "employee:{$matchingEmps->first()->id}";
                    if (isset($seen[$empKey])) continue;
                    // Already in employee list, skip
                    continue;
                }
                $key = "orphan:{$name}";
                if (isset($seen[$key])) continue;
                $options[] = [
                    'id'   => $key,
                    'key'  => $key,
                    'name' => $name,
                    'code' => 'ORPHAN',
                    'type' => 'orphan',
                ];
                $seen[$key] = true;
            }
        }

        // HOTFIX 24.27 — Disambiguate duplicate display names
        $nameCount = [];
        foreach ($options as $opt) {
            $nameCount[$opt['name']] = ($nameCount[$opt['name']] ?? 0) + 1;
        }
        foreach ($options as &$opt) {
            if (($nameCount[$opt['name']] ?? 0) > 1) {
                $suffix = $opt['code'] ?? strtoupper($opt['type']);
                $opt['display_name'] = "{$opt['name']} — {$suffix}";
            } else {
                $opt['display_name'] = $opt['name'];
            }
        }
        unset($opt);

        return collect($options)->sortBy('name')->values()->all();
    }

    /**
     * Normalize a seller filter value from the frontend request.
     * Supports new prefixed keys and legacy numeric ids.
     * Returns array of seller keys to match against.
     */
    public function normalizeRequestedSellerKey($value): array
    {
        if (!$value) return [];

        $value = (string) $value;

        // Already prefixed
        if (str_starts_with($value, 'employee:') ||
            str_starts_with($value, 'user:') ||
            str_starts_with($value, 'orphan:')) {
            return [$value];
        }

        // Legacy numeric id — match both employee and user
        if (ctype_digit($value)) {
            return ["employee:{$value}", "user:{$value}"];
        }

        return [];
    }

    /**
     * Filter an invoice query to only include invoices from a specific seller.
     */
    public function filterBySeller($invoiceQuery, string $employeeIdParam)
    {
        $matchKeys = $this->normalizeRequestedSellerKey($employeeIdParam);
        if (empty($matchKeys)) return $invoiceQuery;

        $sellerMap = $this->invoiceSellerMap($invoiceQuery);
        $matchingIds = [];
        foreach ($sellerMap as $invoiceId => $key) {
            if (in_array($key, $matchKeys)) {
                $matchingIds[] = $invoiceId;
            }
        }

        return $invoiceQuery->whereIn('id', $matchingIds);
    }

    /**
     * HOTFIX 24.27 — Filter for invoice list page (Cách A).
     * Accepts seller_key and returns invoice IDs matching that seller.
     * This allows the invoice page to use the same SellerResolver logic.
     */
    public function getInvoiceIdsForSeller($invoiceQuery, string $sellerKey): array
    {
        $matchKeys = $this->normalizeRequestedSellerKey($sellerKey);
        if (empty($matchKeys)) return [];

        $sellerMap = $this->invoiceSellerMap($invoiceQuery);
        $ids = [];
        foreach ($sellerMap as $invoiceId => $key) {
            if (in_array($key, $matchKeys)) {
                $ids[] = $invoiceId;
            }
        }
        return $ids;
    }
}
