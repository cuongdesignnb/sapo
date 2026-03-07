<?php

namespace App\Http\Controllers;

use App\Models\CashFlow;
use App\Models\Invoice;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $today = Carbon::today();

        // 1. Doanh thu hôm nay
        $todayRevenue = CashFlow::where('type', 'receipt')
            ->whereDate('created_at', $today)
            ->sum('amount');

        // 2. Số lượng đơn hàng mới trong ngày
        $todayOrders = Invoice::whereDate('created_at', $today)->count();

        // 3. Tổng hàng hóa tồn kho (hoặc đếm số mặt hàng)
        $totalProductsInStock = Product::sum('stock_quantity');

        // 4. Doanh thu tháng này (để so sánh hoặc hiển thị)
        $thisMonthRevenue = CashFlow::where('type', 'receipt')
            ->whereMonth('created_at', $today->month)
            ->whereYear('created_at', $today->year)
            ->sum('amount');

        // 5. Biểu đồ doanh thu 7 ngày gần nhất
        $revenueLast7Days = [];
        $labels = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $labels[] = $date->format('d/m');

            $dayRevenue = CashFlow::where('type', 'receipt')
                ->whereDate('created_at', $date)
                ->sum('amount');

            $revenueLast7Days[] = $dayRevenue;
        }

        return Inertia::render('Dashboard/Index', [
            'todayRevenue' => $todayRevenue,
            'todayOrders' => $todayOrders,
            'totalProductsInStock' => $totalProductsInStock,
            'thisMonthRevenue' => $thisMonthRevenue,
            'chartData' => [
                'labels' => $labels,
                'data' => $revenueLast7Days
            ],
            'branches' => \App\Models\Branch::all(),
        ]);
    }
}
