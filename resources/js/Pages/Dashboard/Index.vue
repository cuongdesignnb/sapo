<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, onMounted } from 'vue';

// Chart.js imports
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend,
  Filler
} from 'chart.js';
import { Line } from 'vue-chartjs';

ChartJS.register(
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend,
  Filler
);

const props = defineProps({
    todayRevenue: Number,
    todayOrders: Number,
    totalProductsInStock: Number,
    thisMonthRevenue: Number,
    chartData: Object,
    branches: Array,
});

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: false,
        },
        tooltip: {
            callbacks: {
                label: function(context) {
                    let label = context.dataset.label || '';
                    if (label) {
                        label += ': ';
                    }
                    if (context.parsed.y !== null) {
                        label += new Intl.NumberFormat('vi-VN').format(context.parsed.y) + ' đ';
                    }
                    return label;
                }
            }
        }
    },
    scales: {
        y: {
            beginAtZero: true,
            ticks: {
                callback: function(value, index, values) {
                    return new Intl.NumberFormat('vi-VN').format(value) + ' đ';
                }
            }
        }
    }
};

const chartDataConfig = {
    labels: props.chartData.labels,
    datasets: [
        {
            label: 'Doanh thu',
            backgroundColor: 'rgba(59, 130, 246, 0.2)', // blue-500 with opacity
            borderColor: 'rgb(59, 130, 246)',       // blue-500
            borderWidth: 2,
            pointBackgroundColor: 'rgb(59, 130, 246)',
            pointBorderColor: '#fff',
            pointHoverBackgroundColor: '#fff',
            pointHoverBorderColor: 'rgb(59, 130, 246)',
            fill: true,
            tension: 0.4,
            data: props.chartData.data
        }
    ]
};
</script>

<template>
    <Head title="Tổng quan - KiotViet Clone" />
    <AppLayout>
        <!-- Mini Sidebar / Additional Info can go here if needed, but Dashboard often spans full -->
        <template #sidebar>
            <div class="px-4 py-3 font-semibold text-gray-800 border-b border-gray-200 uppercase text-xs tracking-wider">Hoạt động hôm nay</div>
            <div class="px-4 py-4 space-y-4">
               <div>
                   <div class="text-sm text-gray-500 mb-1">Tiền bán hàng</div>
                   <div class="font-bold text-lg text-blue-600 font-mono">{{ Number(props.todayRevenue).toLocaleString() }} ₫</div>
               </div>
               <div class="border-t border-gray-100 pt-3">
                   <div class="text-sm text-gray-500 mb-1">Số đơn hàng</div>
                   <div class="font-bold text-lg text-gray-800">{{ props.todayOrders }}</div>
               </div>
               <div class="border-t border-gray-100 pt-3">
                   <div class="text-sm text-gray-500 mb-1">Hàng hóa trong kho</div>
                   <div class="font-bold text-lg text-gray-800">{{ props.totalProductsInStock }}</div>
               </div>
            </div>

            <div class="px-4 py-3 font-semibold text-gray-800 border-b border-t border-gray-200 uppercase text-xs tracking-wider bg-gray-50/50">Chi nhánh</div>
            <div class="px-4 py-4">
                <select class="w-full border border-gray-300 rounded p-1.5 text-[13px] outline-none text-gray-700 font-medium">
                    <option v-for="branch in branches" :key="branch.id">{{ branch.name }}</option>
                </select>
            </div>
            
            <div class="px-4 py-3 font-semibold text-gray-800 border-b border-t border-gray-200 uppercase text-xs tracking-wider mt-4 bg-gray-50/50">Tháng này</div>
            <div class="px-4 py-4">
                 <div class="text-sm text-gray-500 mb-1">Doanh thu tạm tính</div>
                 <div class="font-bold text-xl text-blue-700 font-mono">{{ Number(props.thisMonthRevenue).toLocaleString() }} ₫</div>
            </div>
        </template>

        <div class="p-6">
            <h1 class="text-2xl font-bold text-gray-800 mb-6">Tổng quan</h1>
            
            <!-- Key Metrics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Card 1 -->
                <div class="bg-white rounded shadow-sm border border-gray-200 p-5 hover:shadow-md transition cursor-pointer">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-gray-500 text-sm font-medium mb-1">DOANH THU HÔM NAY</p>
                            <h3 class="text-2xl font-bold text-blue-600 font-mono">{{ Number(props.todayRevenue).toLocaleString() }} <span class="text-sm text-gray-500">₫</span></h3>
                        </div>
                        <div class="p-2 bg-blue-50 text-blue-600 rounded">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        </div>
                    </div>
                </div>

                <!-- Card 2 -->
                <div class="bg-white rounded shadow-sm border border-gray-200 p-5 hover:shadow-md transition cursor-pointer">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-gray-500 text-sm font-medium mb-1">ĐƠN HÀNG MỚI</p>
                            <h3 class="text-2xl font-bold text-gray-800">{{ props.todayOrders }}</h3>
                        </div>
                        <div class="p-2 bg-green-50 text-green-600 rounded">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
                        </div>
                    </div>
                </div>

                <!-- Card 3 -->
                <div class="bg-white rounded shadow-sm border border-gray-200 p-5 hover:shadow-md transition cursor-pointer">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-gray-500 text-sm font-medium mb-1">DOANH THU THÁNG</p>
                            <h3 class="text-2xl font-bold text-indigo-600 font-mono">{{ Number(props.thisMonthRevenue).toLocaleString() }} <span class="text-sm text-gray-500">₫</span></h3>
                        </div>
                        <div class="p-2 bg-indigo-50 text-indigo-600 rounded">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"></path></svg>
                        </div>
                    </div>
                </div>

                <!-- Card 4 -->
                <div class="bg-white rounded shadow-sm border border-gray-200 p-5 hover:shadow-md transition cursor-pointer">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-gray-500 text-sm font-medium mb-1">TỔNG TỒN KHO</p>
                            <h3 class="text-2xl font-bold text-orange-600">{{ props.totalProductsInStock }}</h3>
                        </div>
                        <div class="p-2 bg-orange-50 text-orange-600 rounded">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="bg-white rounded shadow-sm border border-gray-200 lg:col-span-2">
                    <div class="p-5 border-b border-gray-100 flex justify-between items-center">
                        <h2 class="font-bold text-gray-800">Doanh thu 7 ngày qua</h2>
                        <!-- A small dropdown mock -->
                        <button class="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1 border rounded px-2 py-1">
                            Hôm nay
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </button>
                    </div>
                    <div class="p-5" style="height: 350px;">
                        <Line :data="chartDataConfig" :options="chartOptions" />
                    </div>
                </div>

                <div class="bg-white rounded shadow-sm border border-gray-200">
                    <div class="p-5 border-b border-gray-100">
                        <h2 class="font-bold text-gray-800">Hoạt động gần đây</h2>
                    </div>
                    <div class="p-5">
                        <ul class="space-y-4">
                            <li class="flex gap-4">
                                <div class="w-2 h-2 mt-2 rounded-full bg-blue-500"></div>
                                <div>
                                    <p class="text-sm font-medium text-gray-800">Đơn hàng mới tạo thành công</p>
                                    <p class="text-xs text-gray-500">Vài phút trước</p>
                                </div>
                            </li>
                            <li class="flex gap-4">
                                <div class="w-2 h-2 mt-2 rounded-full bg-green-500"></div>
                                <div>
                                    <p class="text-sm font-medium text-gray-800">Đã thu tiền mặt 58.000.000 đ</p>
                                    <p class="text-xs text-gray-500">Vài phút trước</p>
                                </div>
                            </li>
                             <li class="flex gap-4">
                                <div class="w-2 h-2 mt-2 rounded-full bg-gray-300"></div>
                                <div>
                                    <p class="text-sm font-medium text-gray-500">Hệ thống sẵn sàng hoạt động</p>
                                    <p class="text-xs text-gray-500">1 giờ trước</p>
                                </div>
                            </li>
                        </ul>
                        
                        <div class="mt-6 pt-4 border-t border-gray-100 text-center">
                            <Link href="/invoices" class="text-sm text-blue-600 font-medium hover:underline">Xem tất cả hoạt động</Link>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </AppLayout>
</template>
