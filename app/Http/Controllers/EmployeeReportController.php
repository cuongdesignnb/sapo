<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\OrderReturn;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class EmployeeReportController extends Controller
{
    public function index(Request $request)
    {
        $concern      = $request->input('concern', 'sales');       // sales | profit | items
        $period       = $request->input('period', 'this_month');
        $dateFrom     = $request->input('date_from');
        $dateTo       = $request->input('date_to');
        $branchId     = $request->input('branch_id');
        $employeeId   = $request->input('employee_id');
        $salesChannel = $request->input('sales_channel');
        $viewMode     = $request->input('view', 'chart');

        [$startDate, $endDate, $periodLabel] = $this->resolvePeriod($period, $dateFrom, $dateTo);

        // Base invoice query
        $invoiceQ = Invoice::whereBetween('created_at', [$startDate, $endDate])
            ->where('status', '!=', 'Đã hủy');
        if ($branchId) $invoiceQ->where('branch_id', $branchId);
        if ($salesChannel) $invoiceQ->where('sales_channel', $salesChannel);

        // HOTFIX 24.22 — filter `employee_id` (the FE picker) needs to cover both
        // the legacy employee bucket (`created_by = employees.id`) AND admin /
        // user orphan invoices (`created_by IS NULL` but `created_by_name`
        // matches a real user's name). Otherwise picking "Admin" in the picker
        // would return an empty report.
        if ($employeeId) {
            $userName = User::where('id', $employeeId)->value('name');
            $invoiceQ->where(function ($q) use ($employeeId, $userName) {
                $q->where('created_by', $employeeId);
                if ($userName !== null && $userName !== '') {
                    $q->orWhere(function ($q2) use ($userName) {
                        $q2->whereNull('created_by')->where('created_by_name', $userName);
                    });
                }
            });
        }

        // Returns query
        $returnQ = OrderReturn::whereBetween('created_at', [$startDate, $endDate])
            ->where('status', '!=', 'Đã hủy');
        if ($branchId) $returnQ->where('branch_id', $branchId);

        // Build data
        switch ($concern) {
            case 'profit':
                $chartData  = $this->buildProfitData($invoiceQ, $returnQ);
                $reportRows = $this->buildProfitReportRows($invoiceQ, $returnQ);
                break;
            case 'items':
                $chartData  = $this->buildItemsData($invoiceQ);
                $reportRows = $this->buildItemsReportRows($invoiceQ);
                break;
            default: // sales
                $chartData  = $this->buildSalesData($invoiceQ, $returnQ);
                $reportRows = $this->buildSalesReportRows($invoiceQ, $returnQ);
        }

        // Summary
        $summary = $this->buildSummary($reportRows);

        // Filter options (dynamic)
        $branches      = Branch::orderBy('name')->get(['id', 'name']);
        $employees     = $this->buildSellerFilterOptions();
        $salesChannels = Invoice::whereNotNull('sales_channel')
            ->distinct()->pluck('sales_channel')->filter()->values();
        $branchName    = $branchId ? (Branch::find($branchId)?->name ?? 'N/A') : 'Tất cả chi nhánh';

        return Inertia::render('Reports/EmployeeReport', [
            'filters' => [
                'concern'       => $concern,
                'period'        => $period,
                'date_from'     => $startDate->format('Y-m-d'),
                'date_to'       => $endDate->format('Y-m-d'),
                'branch_id'     => $branchId,
                'employee_id'   => $employeeId,
                'sales_channel' => $salesChannel,
                'view'          => $viewMode,
            ],
            'periodLabel'      => $periodLabel,
            'chartData'        => $chartData,
            'reportRows'       => $reportRows,
            'summary'          => $summary,
            'branchName'       => $branchName,
            'branches'         => $branches,
            'employees'        => $employees,
            'salesChannels'    => $salesChannels,
            'dateFromDisplay'  => $startDate->format('d/m/Y'),
            'dateToDisplay'    => $endDate->format('d/m/Y'),
        ]);
    }

    // ═══════════════════════════════════════
    // HOTFIX 24.22 — seller resolution.
    //
    // `invoices.created_by` historically stores `employees.id`. When admin
    // (or any user without an Employee row) checks out a sale, the POS
    // controller leaves `created_by = NULL` and writes the admin's display
    // name into `created_by_name`. The previous report controller filtered
    // those rows out entirely (whereNotNull('created_by')) and only resolved
    // names against the `employees` table — so admin invoices were invisible.
    //
    // New strategy:
    //   1. Group `created_by IS NOT NULL` rows by employee id (legacy bucket).
    //   2. Group `created_by IS NULL` rows by `created_by_name`, resolve the
    //      name to a `users.id` if possible, and merge that bucket under the
    //      resolved user's id.
    //   3. `resolveSellerNames()` looks the integer id up in employees first,
    //      then users (with an explicit isAdmin label), then falls back.
    // ═══════════════════════════════════════

    /**
     * Resolve a list of seller ids to a labelled name/code/type record.
     *
     * @param  array<int,int>  $sellerIds
     * @return array<int,array{id:int,code:string,name:string,type:string}>
     */
    private function resolveSellerNames(array $sellerIds): array
    {
        $sellerIds = array_values(array_unique(array_filter($sellerIds, fn ($id) => $id !== null && $id !== '')));
        $sellerIds = array_map('intval', $sellerIds);

        if (empty($sellerIds)) return [];

        $employeesById = Employee::whereIn('id', $sellerIds)
            ->get(['id', 'name', 'code', 'user_id'])
            ->keyBy('id');

        $usersById = User::whereIn('id', $sellerIds)
            ->get(['id', 'name', 'email', 'role_id', 'status'])
            ->keyBy('id');

        // Employees who happen to have a user_id matching one of the seller
        // ids — used when the picker / report key is a `users.id` but a
        // matching employee row exists (admin who also has an Employee
        // profile shouldn't double-show).
        $employeeByUserId = Employee::whereIn('user_id', $sellerIds)
            ->get(['id', 'user_id', 'name', 'code'])
            ->keyBy('user_id');

        $names = [];
        foreach ($sellerIds as $id) {
            if (isset($employeesById[$id])) {
                $emp = $employeesById[$id];
                $names[$id] = [
                    'id'   => $id,
                    'code' => $emp->code ?: "NV{$id}",
                    'name' => $emp->name ?: "Nhân viên #{$id}",
                    'type' => 'employee',
                ];
                continue;
            }
            if (isset($employeeByUserId[$id])) {
                $emp = $employeeByUserId[$id];
                $names[$id] = [
                    'id'   => $id,
                    'code' => $emp->code ?: "U{$id}",
                    'name' => $emp->name ?: "User #{$id}",
                    'type' => 'employee_user',
                ];
                continue;
            }
            if (isset($usersById[$id])) {
                $user    = $usersById[$id];
                $isAdmin = method_exists($user, 'isAdmin') ? $user->isAdmin() : false;
                $names[$id] = [
                    'id'   => $id,
                    'code' => $isAdmin ? 'ADMIN' : "U{$id}",
                    'name' => $user->name ?: ($isAdmin ? 'Admin' : "User #{$id}"),
                    'type' => $isAdmin ? 'admin' : 'user',
                ];
                continue;
            }
            $names[$id] = [
                'id'   => $id,
                'code' => "SELLER{$id}",
                'name' => "Người bán #{$id}",
                'type' => 'unknown',
            ];
        }
        return $names;
    }

    /**
     * Seller filter options for the picker: every employee + every distinct
     * `users.id` that has actually shown up as a seller (via direct
     * `created_by` or via the orphan-name → user lookup).
     *
     * @return array<int,array{id:int,name:string,code:string,type:string}>
     */
    private function buildSellerFilterOptions(): array
    {
        $employees = Employee::orderBy('name')->get(['id', 'name', 'code', 'user_id']);

        // (a) sellers who have invoices with created_by set
        $directIds = Invoice::whereNotNull('created_by')
            ->distinct()
            ->pluck('created_by')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values()
            ->all();

        // (b) sellers exposed only through orphan invoices (admin etc.)
        $orphanUserIds = $this->resolveOrphanCreatorUserIds();

        $sellerMap = $this->resolveSellerNames(array_unique(array_merge($directIds, $orphanUserIds)));

        $options = [];
        $seen    = [];

        foreach ($employees as $emp) {
            $options[] = [
                'id'   => $emp->id,
                'name' => $emp->name,
                'code' => $emp->code,
                'type' => 'employee',
            ];
            $seen['employee:' . $emp->id]  = true;
            if ($emp->user_id) {
                $seen['user:' . $emp->user_id] = true;
            }
        }

        foreach ($sellerMap as $id => $seller) {
            $key = match ($seller['type']) {
                'employee', 'employee_user' => 'employee:' . $id,
                'admin', 'user'             => 'user:' . $id,
                default                     => 'unknown:' . $id,
            };
            if (isset($seen[$key])) {
                continue;
            }
            $options[] = [
                'id'   => $id,
                'name' => $seller['name'],
                'code' => $seller['code'],
                'type' => $seller['type'],
            ];
            $seen[$key] = true;
        }

        return collect($options)->sortBy('name')->values()->all();
    }

    /**
     * @return array<int,int>
     */
    private function resolveOrphanCreatorUserIds(): array
    {
        $names = Invoice::whereNull('created_by')
            ->whereNotNull('created_by_name')
            ->distinct()
            ->pluck('created_by_name')
            ->filter()
            ->values()
            ->all();
        if (empty($names)) return [];

        return User::whereIn('name', $names)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Group a per-invoice aggregate by `created_by`, then fold orphan
     * `created_by IS NULL` rows into the user whose name matches
     * `created_by_name`.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string                                 $valueColumn  raw SQL aggregate (e.g. 'SUM(total)')
     * @return array<int,float>  seller_id => aggregated value
     */
    private function aggregateInvoicesBySeller($query, string $valueColumn): array
    {
        $byCreatedBy = (clone $query)->whereNotNull('created_by')
            ->select('created_by as emp_id', DB::raw("$valueColumn as total"))
            ->groupBy('created_by')
            ->pluck('total', 'emp_id')
            ->map(fn ($v) => (float) $v)
            ->toArray();

        $byOrphanName = (clone $query)->whereNull('created_by')
            ->whereNotNull('created_by_name')
            ->select('created_by_name as creator_name', DB::raw("$valueColumn as total"))
            ->groupBy('created_by_name')
            ->pluck('total', 'creator_name')
            ->map(fn ($v) => (float) $v)
            ->toArray();

        if (!empty($byOrphanName)) {
            $userIdByName = User::whereIn('name', array_keys($byOrphanName))
                ->pluck('id', 'name')
                ->map(fn ($id) => (int) $id)
                ->toArray();

            foreach ($byOrphanName as $name => $value) {
                $userId = $userIdByName[$name] ?? null;
                if (!$userId) continue;
                $byCreatedBy[$userId] = ($byCreatedBy[$userId] ?? 0) + $value;
            }
        }

        return $byCreatedBy;
    }

    // ═══════════════════════════════════════
    // Sales: Top employees by net revenue
    // ═══════════════════════════════════════
    private function buildSalesData($invoiceQ, $returnQ)
    {
        $empRevenue = $this->getRevenueByEmployee(clone $invoiceQ);
        $empReturns = $this->getReturnsByEmployee(clone $returnQ);

        $merged = [];
        foreach ($empRevenue as $empId => $rev) {
            $ret = $empReturns[$empId] ?? 0;
            $merged[$empId] = $rev - $ret;
        }
        arsort($merged);
        $top = array_slice($merged, 0, 10, true);

        $sellerMap = $this->resolveSellerNames(array_keys($top));
        $labels    = [];
        $data      = [];
        foreach ($top as $empId => $net) {
            $labels[] = $sellerMap[$empId]['name'] ?? "Người bán #{$empId}";
            $data[]   = $net;
        }

        return [
            'title'    => 'Top 10 người bán nhiều nhất (đã trừ trả hàng)',
            'labels'   => $labels,
            'datasets' => [['label' => 'Doanh thu thuần', 'data' => $data]],
            'type'     => 'horizontal_bar',
        ];
    }

    private function buildSalesReportRows($invoiceQ, $returnQ)
    {
        $empRevenue = $this->getRevenueByEmployee(clone $invoiceQ);
        $empReturns = $this->getReturnsByEmployee(clone $returnQ);

        $allIds    = array_unique(array_merge(array_keys($empRevenue), array_keys($empReturns)));
        $sellerMap = $this->resolveSellerNames($allIds);

        $rows = [];
        foreach ($allIds as $empId) {
            $seller = $sellerMap[$empId] ?? null;
            $rev    = $empRevenue[$empId] ?? 0;
            $ret    = $empReturns[$empId] ?? 0;
            $rows[] = [
                'id'          => $empId,
                'code'        => $seller['code'] ?? "SELLER{$empId}",
                'name'        => $seller['name'] ?? "Người bán #{$empId}",
                'seller_type' => $seller['type'] ?? 'unknown',
                'revenue'     => $rev,
                'returns'     => $ret,
                'net'         => $rev - $ret,
            ];
        }
        usort($rows, fn ($a, $b) => $b['net'] <=> $a['net']);
        return $rows;
    }

    // ═══════════════════════════════════════
    // Profit: Revenue minus cost per employee
    // ═══════════════════════════════════════
    private function buildProfitData($invoiceQ, $returnQ)
    {
        $empRevenue = $this->getRevenueByEmployee(clone $invoiceQ);
        $empCosts   = $this->getCostByEmployee(clone $invoiceQ);
        $empReturns = $this->getReturnsByEmployee(clone $returnQ);

        $merged = [];
        $allIds = array_unique(array_merge(array_keys($empRevenue), array_keys($empCosts)));
        foreach ($allIds as $empId) {
            $rev  = $empRevenue[$empId] ?? 0;
            $cost = $empCosts[$empId] ?? 0;
            $ret  = $empReturns[$empId] ?? 0;
            $merged[$empId] = ($rev - $ret) - $cost;
        }
        arsort($merged);
        $top = array_slice($merged, 0, 10, true);

        $sellerMap = $this->resolveSellerNames(array_keys($top));
        $labels    = [];
        $data      = [];
        foreach ($top as $empId => $profit) {
            $labels[] = $sellerMap[$empId]['name'] ?? "Người bán #{$empId}";
            $data[]   = $profit;
        }

        return [
            'title'    => 'Top 10 nhân viên lợi nhuận cao nhất',
            'labels'   => $labels,
            'datasets' => [['label' => 'Lợi nhuận', 'data' => $data]],
            'type'     => 'horizontal_bar',
        ];
    }

    private function buildProfitReportRows($invoiceQ, $returnQ)
    {
        $empRevenue = $this->getRevenueByEmployee(clone $invoiceQ);
        $empCosts   = $this->getCostByEmployee(clone $invoiceQ);
        $empReturns = $this->getReturnsByEmployee(clone $returnQ);

        $allIds    = array_unique(array_merge(array_keys($empRevenue), array_keys($empCosts)));
        $sellerMap = $this->resolveSellerNames($allIds);

        $rows = [];
        foreach ($allIds as $empId) {
            $seller = $sellerMap[$empId] ?? null;
            $rev    = $empRevenue[$empId] ?? 0;
            $ret    = $empReturns[$empId] ?? 0;
            $cost   = $empCosts[$empId] ?? 0;
            $rows[] = [
                'id'          => $empId,
                'code'        => $seller['code'] ?? "SELLER{$empId}",
                'name'        => $seller['name'] ?? "Người bán #{$empId}",
                'seller_type' => $seller['type'] ?? 'unknown',
                'revenue'     => $rev - $ret,
                'returns'     => $cost,
                'net'         => ($rev - $ret) - $cost,
            ];
        }
        usort($rows, fn ($a, $b) => $b['net'] <=> $a['net']);
        return $rows;
    }

    // ═══════════════════════════════════════
    // Items sold per employee
    // ═══════════════════════════════════════
    private function buildItemsData($invoiceQ)
    {
        $byEmp = $this->getItemQtyByEmployee(clone $invoiceQ);
        arsort($byEmp);
        $top = array_slice($byEmp, 0, 10, true);

        $sellerMap = $this->resolveSellerNames(array_keys($top));
        $labels    = [];
        $data      = [];
        foreach ($top as $empId => $qty) {
            $labels[] = $sellerMap[$empId]['name'] ?? "Người bán #{$empId}";
            $data[]   = (int) $qty;
        }

        return [
            'title'    => 'Top 10 nhân viên bán nhiều sản phẩm nhất',
            'labels'   => $labels,
            'datasets' => [['label' => 'Số lượng', 'data' => $data]],
            'type'     => 'horizontal_bar',
        ];
    }

    private function buildItemsReportRows($invoiceQ)
    {
        $qtyByEmp   = $this->getItemQtyByEmployee(clone $invoiceQ);
        $valueByEmp = $this->getItemValueByEmployee(clone $invoiceQ);

        $allIds    = array_unique(array_merge(array_keys($qtyByEmp), array_keys($valueByEmp)));
        $sellerMap = $this->resolveSellerNames($allIds);

        $rows = [];
        foreach ($allIds as $empId) {
            $seller = $sellerMap[$empId] ?? null;
            $rows[] = [
                'id'          => $empId,
                'code'        => $seller['code'] ?? "SELLER{$empId}",
                'name'        => $seller['name'] ?? "Người bán #{$empId}",
                'seller_type' => $seller['type'] ?? 'unknown',
                'revenue'     => (float) ($valueByEmp[$empId] ?? 0),
                'returns'     => (int) ($qtyByEmp[$empId] ?? 0),
                'net'         => (float) ($valueByEmp[$empId] ?? 0),
            ];
        }
        usort($rows, fn ($a, $b) => $b['returns'] <=> $a['returns']);
        return $rows;
    }

    // ═══════════════════════════════════════
    // Summary builder
    // ═══════════════════════════════════════
    private function buildSummary(array $rows): array
    {
        return [
            'count'        => count($rows),
            'totalRevenue' => array_sum(array_column($rows, 'revenue')),
            'totalReturns' => array_sum(array_column($rows, 'returns')),
            'totalNet'     => array_sum(array_column($rows, 'net')),
        ];
    }

    // ═══════════════════════════════════════
    // Aggregation helpers — HOTFIX 24.22 splits orphans + employees.
    // ═══════════════════════════════════════
    private function getRevenueByEmployee($query): array
    {
        return $this->aggregateInvoicesBySeller($query, 'SUM(total)');
    }

    private function getItemQtyByEmployee($query): array
    {
        $invoiceIds = (clone $query)->pluck('id');
        if ($invoiceIds->isEmpty()) return [];

        // Direct created_by buckets.
        $direct = DB::table('invoice_items')
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->whereIn('invoice_items.invoice_id', $invoiceIds)
            ->whereNotNull('invoices.created_by')
            ->select('invoices.created_by as emp_id', DB::raw('SUM(invoice_items.quantity) as total_qty'))
            ->groupBy('emp_id')
            ->pluck('total_qty', 'emp_id')
            ->map(fn ($v) => (int) $v)
            ->toArray();

        // Orphan-name buckets.
        $orphan = DB::table('invoice_items')
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->whereIn('invoice_items.invoice_id', $invoiceIds)
            ->whereNull('invoices.created_by')
            ->whereNotNull('invoices.created_by_name')
            ->select('invoices.created_by_name as creator_name', DB::raw('SUM(invoice_items.quantity) as total_qty'))
            ->groupBy('creator_name')
            ->pluck('total_qty', 'creator_name')
            ->map(fn ($v) => (int) $v)
            ->toArray();

        if (!empty($orphan)) {
            $userIdByName = User::whereIn('name', array_keys($orphan))
                ->pluck('id', 'name')->map(fn ($id) => (int) $id)->toArray();
            foreach ($orphan as $name => $qty) {
                $userId = $userIdByName[$name] ?? null;
                if (!$userId) continue;
                $direct[$userId] = ($direct[$userId] ?? 0) + $qty;
            }
        }
        return $direct;
    }

    private function getItemValueByEmployee($query): array
    {
        $invoiceIds = (clone $query)->pluck('id');
        if ($invoiceIds->isEmpty()) return [];

        $direct = DB::table('invoice_items')
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->whereIn('invoice_items.invoice_id', $invoiceIds)
            ->whereNotNull('invoices.created_by')
            ->select('invoices.created_by as emp_id', DB::raw('SUM(invoice_items.quantity * invoice_items.price) as total_value'))
            ->groupBy('emp_id')
            ->pluck('total_value', 'emp_id')
            ->map(fn ($v) => (float) $v)
            ->toArray();

        $orphan = DB::table('invoice_items')
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->whereIn('invoice_items.invoice_id', $invoiceIds)
            ->whereNull('invoices.created_by')
            ->whereNotNull('invoices.created_by_name')
            ->select('invoices.created_by_name as creator_name', DB::raw('SUM(invoice_items.quantity * invoice_items.price) as total_value'))
            ->groupBy('creator_name')
            ->pluck('total_value', 'creator_name')
            ->map(fn ($v) => (float) $v)
            ->toArray();

        if (!empty($orphan)) {
            $userIdByName = User::whereIn('name', array_keys($orphan))
                ->pluck('id', 'name')->map(fn ($id) => (int) $id)->toArray();
            foreach ($orphan as $name => $value) {
                $userId = $userIdByName[$name] ?? null;
                if (!$userId) continue;
                $direct[$userId] = ($direct[$userId] ?? 0) + $value;
            }
        }
        return $direct;
    }

    private function getReturnsByEmployee($query): array
    {
        $hasCreatedBy = Schema::hasColumn('returns', 'created_by');
        if ($hasCreatedBy) {
            return $this->aggregateInvoicesBySeller($query, 'SUM(total)');
        }

        $returnIds = (clone $query)->pluck('id');
        if ($returnIds->isEmpty()) return [];

        $direct = DB::table('returns')
            ->join('invoices', 'returns.invoice_id', '=', 'invoices.id')
            ->whereIn('returns.id', $returnIds)
            ->whereNotNull('invoices.created_by')
            ->select('invoices.created_by as emp_id', DB::raw('SUM(returns.total) as total'))
            ->groupBy('emp_id')
            ->pluck('total', 'emp_id')
            ->map(fn ($v) => (float) $v)
            ->toArray();

        $orphan = DB::table('returns')
            ->join('invoices', 'returns.invoice_id', '=', 'invoices.id')
            ->whereIn('returns.id', $returnIds)
            ->whereNull('invoices.created_by')
            ->whereNotNull('invoices.created_by_name')
            ->select('invoices.created_by_name as creator_name', DB::raw('SUM(returns.total) as total'))
            ->groupBy('creator_name')
            ->pluck('total', 'creator_name')
            ->map(fn ($v) => (float) $v)
            ->toArray();

        if (!empty($orphan)) {
            $userIdByName = User::whereIn('name', array_keys($orphan))
                ->pluck('id', 'name')->map(fn ($id) => (int) $id)->toArray();
            foreach ($orphan as $name => $value) {
                $userId = $userIdByName[$name] ?? null;
                if (!$userId) continue;
                $direct[$userId] = ($direct[$userId] ?? 0) + $value;
            }
        }
        return $direct;
    }

    private function getCostByEmployee($query): array
    {
        $invoiceIds = (clone $query)->pluck('id');
        if ($invoiceIds->isEmpty()) return [];

        $hasItemCost = Schema::hasColumn('invoice_items', 'cost_price');
        $costExpr = $hasItemCost
            ? 'SUM(invoice_items.quantity * COALESCE(NULLIF(invoice_items.cost_price, 0), products.cost_price, 0))'
            : 'SUM(invoice_items.quantity * COALESCE(products.cost_price, 0))';

        $direct = DB::table('invoice_items')
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->join('products', 'invoice_items.product_id', '=', 'products.id')
            ->whereIn('invoice_items.invoice_id', $invoiceIds)
            ->whereNotNull('invoices.created_by')
            ->select('invoices.created_by as emp_id', DB::raw("$costExpr as total_cost"))
            ->groupBy('emp_id')
            ->pluck('total_cost', 'emp_id')
            ->map(fn ($v) => (float) $v)
            ->toArray();

        $orphan = DB::table('invoice_items')
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->join('products', 'invoice_items.product_id', '=', 'products.id')
            ->whereIn('invoice_items.invoice_id', $invoiceIds)
            ->whereNull('invoices.created_by')
            ->whereNotNull('invoices.created_by_name')
            ->select('invoices.created_by_name as creator_name', DB::raw("$costExpr as total_cost"))
            ->groupBy('creator_name')
            ->pluck('total_cost', 'creator_name')
            ->map(fn ($v) => (float) $v)
            ->toArray();

        if (!empty($orphan)) {
            $userIdByName = User::whereIn('name', array_keys($orphan))
                ->pluck('id', 'name')->map(fn ($id) => (int) $id)->toArray();
            foreach ($orphan as $name => $value) {
                $userId = $userIdByName[$name] ?? null;
                if (!$userId) continue;
                $direct[$userId] = ($direct[$userId] ?? 0) + $value;
            }
        }
        return $direct;
    }

    // ═══════════════════════════════════════
    // Period resolver
    // ═══════════════════════════════════════
    private function resolvePeriod(string $period, ?string $customFrom, ?string $customTo): array
    {
        switch ($period) {
            case 'this_week':
                return [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek(), 'Tuần này'];
            case 'this_month':
                return [Carbon::now()->startOfMonth(), Carbon::now()->endOfDay(), 'Tháng này'];
            case 'this_year':
                return [Carbon::now()->startOfYear(), Carbon::now()->endOfDay(), 'Năm nay'];
            case 'last_year':
                return [Carbon::now()->subYear()->startOfYear(), Carbon::now()->subYear()->endOfYear(), 'Năm trước'];
            case 'custom':
                $s = $customFrom ? Carbon::parse($customFrom)->startOfDay() : Carbon::now()->startOfMonth();
                $e = $customTo ? Carbon::parse($customTo)->endOfDay() : Carbon::now()->endOfDay();
                return [$s, $e, 'Tùy chỉnh'];
            default:
                return [Carbon::now()->startOfMonth(), Carbon::now()->endOfDay(), 'Tháng này'];
        }
    }
}
