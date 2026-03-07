<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Customer;
use App\Models\Warehouse;
use App\Models\WarehouseProduct;
use App\Models\CustomerDebt;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReceipt;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Get dashboard overview statistics
     */
    public function overview(Request $request)
    {
        $dateFrom = $request->get('date_from', Carbon::now()->startOfMonth());
        $dateTo = $request->get('date_to', Carbon::now()->endOfMonth());
        $warehouseId = $request->get('warehouse_id');

        // 💰 FINANCIAL METRICS
        $revenue = $this->getRevenue($dateFrom, $dateTo, $warehouseId);
        $profit = $this->getProfit($dateFrom, $dateTo, $warehouseId);
        $expenses = $this->getExpenses($dateFrom, $dateTo, $warehouseId);
        $netProfit = $profit - $expenses;
        
        // 📈 SALES METRICS  
        $totalOrders = $this->getTotalOrders($dateFrom, $dateTo, $warehouseId);
        $avgOrderValue = $totalOrders > 0 ? $revenue / $totalOrders : 0;
        $completedOrders = $this->getCompletedOrders($dateFrom, $dateTo, $warehouseId);
        $conversionRate = $totalOrders > 0 ? ($completedOrders / $totalOrders) * 100 : 0;

        // 📦 INVENTORY METRICS
        $totalProducts = Product::count();
        $totalInventoryValue = $this->getInventoryValue($warehouseId);
        $lowStockCount = $this->getLowStockCount($warehouseId);
        $outOfStockCount = $this->getOutOfStockCount($warehouseId);

        // 👥 CUSTOMER METRICS
        $totalCustomers = Customer::count();
        $newCustomers = $this->getNewCustomers($dateFrom, $dateTo);
        $totalDebt = $this->getTotalCustomerDebt();
        $activeCustomers = $this->getActiveCustomers($dateFrom, $dateTo, $warehouseId);

        // 🏪 WAREHOUSE METRICS
        $totalWarehouses = Warehouse::where('status', 'active')->count();
        $warehouseUtilization = $this->getWarehouseUtilization($warehouseId);

        return response()->json([
            'success' => true,
            'data' => [
                // Financial Overview
                'financial' => [
                    'revenue' => round($revenue, 2),
                    'profit' => round($profit, 2),
                    'expenses' => round($expenses, 2),
                    'net_profit' => round($netProfit, 2),
                    'profit_margin' => $revenue > 0 ? round(($profit / $revenue) * 100, 2) : 0,
                ],
                
                // Sales Overview
                'sales' => [
                    'total_orders' => $totalOrders,
                    'completed_orders' => $completedOrders,
                    'avg_order_value' => round($avgOrderValue, 2),
                    'conversion_rate' => round($conversionRate, 2),
                ],
                
                // Inventory Overview
                'inventory' => [
                    'total_products' => $totalProducts,
                    'total_value' => round($totalInventoryValue, 2),
                    'low_stock_count' => $lowStockCount,
                    'out_of_stock_count' => $outOfStockCount,
                    'stock_health' => $this->getStockHealthPercentage($totalProducts, $lowStockCount, $outOfStockCount),
                ],
                
                // Customer Overview
                'customers' => [
                    'total_customers' => $totalCustomers,
                    'new_customers' => $newCustomers,
                    'active_customers' => $activeCustomers,
                    'total_debt' => round($totalDebt, 2),
                    'customer_retention' => $this->getCustomerRetention($dateFrom, $dateTo),
                ],
                
                // Warehouse Overview
                'warehouses' => [
                    'total_warehouses' => $totalWarehouses,
                    'utilization_rate' => round($warehouseUtilization, 2),
                ],
                
                // Period Info
                'period' => [
                    'from' => $dateFrom,
                    'to' => $dateTo,
                    'warehouse_id' => $warehouseId,
                ]
            ]
        ]);
    }

    /**
     * Get sales trend chart data
     */
    public function salesTrend(Request $request)
    {
        $days = $request->get('days', 30);
        $warehouseId = $request->get('warehouse_id');
        $dateFrom = Carbon::now()->subDays($days);
        $dateTo = Carbon::now();

        $query = Order::whereBetween('created_at', [$dateFrom, $dateTo]);
        
        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        $salesData = $query
            ->selectRaw('DATE(created_at) as date, COUNT(*) as orders, SUM(total) as revenue, SUM(paid) as paid')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Fill missing dates with zeros
        $chartData = [];
        $currentDate = $dateFrom->copy();
        
        while ($currentDate <= $dateTo) {
            $dateStr = $currentDate->format('Y-m-d');
            $dayData = $salesData->firstWhere('date', $dateStr);
            
            $chartData[] = [
                'date' => $dateStr,
                'formatted_date' => $currentDate->format('d/m'),
                'orders' => $dayData->orders ?? 0,
                'revenue' => $dayData->revenue ?? 0,
                'paid' => $dayData->paid ?? 0,
            ];
            
            $currentDate->addDay();
        }

        return response()->json([
            'success' => true,
            'data' => $chartData
        ]);
    }

    /**
     * Get top selling products
     */
    public function topProducts(Request $request)
    {
        $limit = $request->get('limit', 10);
        $days = $request->get('days', 30);
        $warehouseId = $request->get('warehouse_id');
        
        $dateFrom = Carbon::now()->subDays($days);
        
        $query = OrderItem::with(['product', 'order'])
            ->whereHas('order', function($q) use ($dateFrom, $warehouseId) {
                $q->where('created_at', '>=', $dateFrom);
                if ($warehouseId) {
                    $q->where('warehouse_id', $warehouseId);
                }
            });

        $topProducts = $query
            ->select('product_id')
            ->selectRaw('SUM(quantity) as total_sold')
            ->selectRaw('SUM(quantity * price) as total_revenue') 
            ->selectRaw('SUM(profit) as total_profit')
            ->groupBy('product_id')
            ->orderBy('total_sold', 'desc')
            ->limit($limit)
            ->get()
            ->map(function($item) {
                return [
                    'product_id' => $item->product_id,
                    'product_name' => $item->product->name,
                    'product_sku' => $item->product->sku,
                    'total_sold' => $item->total_sold,
                    'total_revenue' => round($item->total_revenue, 2),
                    'total_profit' => round($item->total_profit, 2),
                    'profit_margin' => $item->total_revenue > 0 ? round(($item->total_profit / $item->total_revenue) * 100, 2) : 0,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $topProducts
        ]);
    }

    /**
     * Get revenue vs profit comparison
     */
    public function revenueProfit(Request $request)
    {
        $months = $request->get('months', 6);
        $warehouseId = $request->get('warehouse_id');

        $data = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $month = Carbon::now()->subMonths($i);
            $startOfMonth = $month->copy()->startOfMonth();
            $endOfMonth = $month->copy()->endOfMonth();

            $query = Order::whereBetween('created_at', [$startOfMonth, $endOfMonth]);
            if ($warehouseId) {
                $query->where('warehouse_id', $warehouseId);
            }

            $revenue = $query->sum('total');
            
            $profitQuery = OrderItem::whereHas('order', function($q) use ($startOfMonth, $endOfMonth, $warehouseId) {
                $q->whereBetween('created_at', [$startOfMonth, $endOfMonth]);
                if ($warehouseId) {
                    $q->where('warehouse_id', $warehouseId);
                }
            });
            
            $profit = $profitQuery->sum('profit');

            $data[] = [
                'month' => $month->format('Y-m'),
                'month_name' => $month->format('M Y'),
                'revenue' => round($revenue, 2),
                'profit' => round($profit, 2),
                'profit_margin' => $revenue > 0 ? round(($profit / $revenue) * 100, 2) : 0,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Get low stock alerts
     */
    public function lowStockAlerts(Request $request)
    {
        $warehouseId = $request->get('warehouse_id');
        
        $query = WarehouseProduct::with(['product', 'warehouse'])
            ->whereRaw('quantity <= min_stock AND min_stock > 0');
            
        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        $alerts = $query->orderBy('quantity', 'asc')
            ->limit(20)
            ->get()
            ->map(function($item) {
                return [
                    'product_id' => $item->product_id,
                    'product_name' => $item->product->name,
                    'product_sku' => $item->product->sku,
                    'warehouse_name' => $item->warehouse->name,
                    'current_stock' => $item->quantity,
                    'min_stock' => $item->min_stock,
                    'shortage' => $item->min_stock - $item->quantity,
                    'status' => $item->quantity <= 0 ? 'out_of_stock' : 'low_stock',
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $alerts
        ]);
    }

    /**
     * Get customer analysis
     */
    public function customerAnalysis(Request $request)
    {
        $days = $request->get('days', 30);
        $warehouseId = $request->get('warehouse_id');
        $dateFrom = Carbon::now()->subDays($days);

        // Customer segments by order count
        $segments = DB::table('orders')
            ->join('customers', 'orders.customer_id', '=', 'customers.id')
            ->where('orders.created_at', '>=', $dateFrom)
            ->when($warehouseId, function($q) use ($warehouseId) {
                return $q->where('orders.warehouse_id', $warehouseId);
            })
            ->select('customers.id', 'customers.name')
            ->selectRaw('COUNT(orders.id) as order_count')
            ->selectRaw('SUM(orders.total) as total_spent')
            ->groupBy('customers.id', 'customers.name')
            ->orderBy('total_spent', 'desc')
            ->limit(10)
            ->get();

        $topCustomers = $segments->map(function($customer) {
            return [
                'customer_name' => $customer->name,
                'order_count' => $customer->order_count,
                'total_spent' => round($customer->total_spent, 2),
                'avg_order_value' => round($customer->total_spent / $customer->order_count, 2),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $topCustomers
        ]);
    }

    // ===============================
    // PRIVATE HELPER METHODS
    // ===============================

    private function getRevenue($dateFrom, $dateTo, $warehouseId = null)
    {
        $query = Order::whereBetween('created_at', [$dateFrom, $dateTo]);
        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }
        return $query->sum('total') ?? 0;
    }

    private function getProfit($dateFrom, $dateTo, $warehouseId = null)
    {
        $query = OrderItem::whereHas('order', function($q) use ($dateFrom, $dateTo, $warehouseId) {
            $q->whereBetween('created_at', [$dateFrom, $dateTo]);
            if ($warehouseId) {
                $q->where('warehouse_id', $warehouseId);
            }
        });
        return $query->sum('profit') ?? 0;
    }

    private function getExpenses($dateFrom, $dateTo, $warehouseId = null)
    {
        $query = PurchaseReceipt::whereBetween('created_at', [$dateFrom, $dateTo]);
        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }
        return $query->sum('total_amount') ?? 0;
    }

    private function getTotalOrders($dateFrom, $dateTo, $warehouseId = null)
    {
        $query = Order::whereBetween('created_at', [$dateFrom, $dateTo]);
        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }
        return $query->count();
    }

    private function getCompletedOrders($dateFrom, $dateTo, $warehouseId = null)
    {
        $query = Order::whereBetween('created_at', [$dateFrom, $dateTo])
                      ->where('status', 'completed');
        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }
        return $query->count();
    }

    private function getInventoryValue($warehouseId = null)
    {
        $query = WarehouseProduct::selectRaw('SUM(quantity * cost) as total_value');
        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }
        return $query->value('total_value') ?? 0;
    }

    private function getLowStockCount($warehouseId = null)
    {
        $query = WarehouseProduct::whereRaw('quantity <= min_stock AND min_stock > 0');
        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }
        return $query->count();
    }

    private function getOutOfStockCount($warehouseId = null)
    {
        $query = WarehouseProduct::where('quantity', '<=', 0);
        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }
        return $query->count();
    }

    private function getNewCustomers($dateFrom, $dateTo)
    {
        return Customer::whereBetween('created_at', [$dateFrom, $dateTo])->count();
    }

    private function getTotalCustomerDebt()
{
    return DB::table('customer_debts')
        ->select('customer_id', 'debt_total')
        ->whereIn('id', function($query) {
            $query->select(DB::raw('MAX(id)'))
                  ->from('customer_debts')
                  ->groupBy('customer_id');
        })
        ->sum('debt_total') ?? 0;
}

    private function getActiveCustomers($dateFrom, $dateTo, $warehouseId = null)
    {
        $query = Order::whereBetween('created_at', [$dateFrom, $dateTo]);
        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }
        return $query->distinct('customer_id')->count('customer_id');
    }

    private function getWarehouseUtilization($warehouseId = null)
    {
        // Simple calculation: (products with stock / total products) * 100
        $query = WarehouseProduct::where('quantity', '>', 0);
        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }
        
        $productsWithStock = $query->count();
        $totalProducts = Product::count();
        
        return $totalProducts > 0 ? ($productsWithStock / $totalProducts) * 100 : 0;
    }

    private function getStockHealthPercentage($totalProducts, $lowStock, $outOfStock)
    {
        if ($totalProducts == 0) return 100;
        
        $healthyStock = $totalProducts - $lowStock - $outOfStock;
        return ($healthyStock / $totalProducts) * 100;
    }

    private function getCustomerRetention($dateFrom, $dateTo)
    {
        // Customers who made orders in both current and previous period
        $previousPeriod = Carbon::parse($dateFrom)->subDays(Carbon::parse($dateTo)->diffInDays(Carbon::parse($dateFrom)));
        
        $currentCustomers = Order::whereBetween('created_at', [$dateFrom, $dateTo])
                                 ->distinct('customer_id')
                                 ->pluck('customer_id');
        
        $previousCustomers = Order::whereBetween('created_at', [$previousPeriod, $dateFrom])
                                  ->distinct('customer_id')
                                  ->pluck('customer_id');
        
        $returningCustomers = $currentCustomers->intersect($previousCustomers)->count();
        
        return $previousCustomers->count() > 0 ? ($returningCustomers / $previousCustomers->count()) * 100 : 0;
    }
}