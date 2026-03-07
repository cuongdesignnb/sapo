<template>
  <div class="min-h-screen bg-gray-50">
    <!-- Header -->
    <div class="bg-white shadow">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center py-6">
          <div>
            <h1 class="text-3xl font-bold text-gray-900">Dashboard</h1>
            <p class="mt-1 text-sm text-gray-500">Trang quản lý hệ thống bán hàng</p>
          </div>
          
          <!-- Date Filter -->
          <div class="flex items-center space-x-4">
            <select v-model="selectedPeriod" @change="fetchData" class="border border-gray-300 rounded-md px-3 py-2">
              <option value="7">7 ngày qua</option>
              <option value="30">30 ngày qua</option>
              <option value="90">90 ngày qua</option>
            </select>
            
            <select v-model="selectedWarehouse" @change="fetchData" class="border border-gray-300 rounded-md px-3 py-2">
              <option value="">Tất cả kho</option>
              <option v-for="warehouse in warehouses" :key="warehouse.id" :value="warehouse.id">
                {{ warehouse.name }}
              </option>
            </select>

            <button
              @click="showResetModal = true"
              class="bg-red-500 text-white px-4 py-2 rounded-md hover:bg-red-600 text-sm font-medium"
              title="Xoá toàn bộ dữ liệu để làm lại từ đầu"
            >
              🗑️ Reset dữ liệu
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <!-- Loading State -->
      <div v-if="loading" class="flex justify-center items-center py-12">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500"></div>
        <span class="ml-3 text-lg text-gray-600">Đang tải dữ liệu...</span>
      </div>

      <div v-else class="space-y-8">
        <!-- Overview Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          <!-- Revenue Card -->
          <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-xl shadow-lg p-6 text-white">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-blue-100 text-sm font-medium">Doanh thu</p>
                <p class="text-3xl font-bold">{{ formatCurrency(overview.financial?.revenue || 0) }}</p>
                <p class="text-blue-100 text-xs mt-1">
                  Lợi nhuận: {{ overview.financial?.profit_margin || 0 }}%
                </p>
              </div>
              <div class="p-3 bg-blue-400 bg-opacity-30 rounded-lg">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                </svg>
              </div>
            </div>
          </div>

          <!-- Orders Card -->
          <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-xl shadow-lg p-6 text-white">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-green-100 text-sm font-medium">Đơn hàng</p>
                <p class="text-3xl font-bold">{{ overview.sales?.total_orders || 0 }}</p>
                <p class="text-green-100 text-xs mt-1">
                  Hoàn thành: {{ overview.sales?.completed_orders || 0 }}
                </p>
              </div>
              <div class="p-3 bg-green-400 bg-opacity-30 rounded-lg">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
              </div>
            </div>
          </div>

          <!-- Products Card -->
          <div class="bg-gradient-to-r from-purple-500 to-purple-600 rounded-xl shadow-lg p-6 text-white">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-purple-100 text-sm font-medium">Sản phẩm</p>
                <p class="text-3xl font-bold">{{ overview.inventory?.total_products || 0 }}</p>
                <p class="text-purple-100 text-xs mt-1">
                  Sắp hết: {{ overview.inventory?.low_stock_count || 0 }}
                </p>
              </div>
              <div class="p-3 bg-purple-400 bg-opacity-30 rounded-lg">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                </svg>
              </div>
            </div>
          </div>

          <!-- Customers Card -->
          <div class="bg-gradient-to-r from-orange-500 to-orange-600 rounded-xl shadow-lg p-6 text-white">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-orange-100 text-sm font-medium">Khách hàng</p>
                <p class="text-3xl font-bold">{{ overview.customers?.total_customers || 0 }}</p>
                <p class="text-orange-100 text-xs mt-1">
                  Hoạt động: {{ overview.customers?.active_customers || 0 }}
                </p>
              </div>
              <div class="p-3 bg-orange-400 bg-opacity-30 rounded-lg">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                </svg>
              </div>
            </div>
          </div>
        </div>

        <!-- Charts Row -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
          <!-- Sales Trend Chart -->
          <div class="bg-white rounded-xl shadow-lg p-6">
            <div class="flex items-center justify-between mb-6">
              <h3 class="text-lg font-semibold text-gray-900">Xu hướng bán hàng</h3>
              <div class="text-sm text-gray-500">{{ selectedPeriod }} ngày qua</div>
            </div>
            
            <div class="h-64 relative">
              <svg class="w-full h-full" v-if="salesTrend.length > 0">
                <!-- Chart Background -->
                <defs>
                  <linearGradient id="salesGradient" x1="0%" y1="0%" x2="0%" y2="100%">
                    <stop offset="0%" style="stop-color:#3B82F6;stop-opacity:0.3" />
                    <stop offset="100%" style="stop-color:#3B82F6;stop-opacity:0.1" />
                  </linearGradient>
                </defs>
                
                <!-- Sales Line -->
                <polyline
                  fill="none"
                  stroke="#3B82F6"
                  stroke-width="3"
                  :points="getSalesChartPoints"
                />
                
                <!-- Area Fill -->
                <polygon
                  fill="url(#salesGradient)"
                  :points="getSalesAreaPoints"
                />
                
                <!-- Data Points -->
                <circle
                  v-for="(point, index) in salesTrend"
                  :key="index"
                  :cx="getChartX(index)"
                  :cy="getChartY(point.revenue)"
                  r="4"
                  fill="#3B82F6"
                  class="hover:r-6 transition-all cursor-pointer"
                  @mouseover="showTooltip($event, point)"
                  @mouseout="hideTooltip"
                />
              </svg>
              
              <div v-else class="flex items-center justify-center h-full text-gray-500">
                Không có dữ liệu để hiển thị
              </div>
            </div>
          </div>

          <!-- Revenue vs Profit Chart -->
          <div class="bg-white rounded-xl shadow-lg p-6">
            <div class="flex items-center justify-between mb-6">
              <h3 class="text-lg font-semibold text-gray-900">Doanh thu & Lợi nhuận</h3>
              <div class="flex items-center space-x-4 text-sm">
                <div class="flex items-center">
                  <div class="w-3 h-3 bg-blue-500 rounded-full mr-2"></div>
                  <span>Doanh thu</span>
                </div>
                <div class="flex items-center">
                  <div class="w-3 h-3 bg-green-500 rounded-full mr-2"></div>
                  <span>Lợi nhuận</span>
                </div>
              </div>
            </div>
            
            <div class="space-y-4">
              <div v-for="(item, index) in revenueProfit" :key="index" class="flex items-center">
                <div class="w-16 text-sm text-gray-600">{{ item.month_name }}</div>
                <div class="flex-1 ml-4">
                  <!-- Revenue Bar -->
                  <div class="flex items-center space-x-2 mb-1">
                    <div class="flex-1 bg-gray-200 rounded-full h-3">
                      <div
                        class="bg-blue-500 h-3 rounded-full"
                        :style="{ width: getBarWidth(item.revenue, maxRevenue) + '%' }"
                      ></div>
                    </div>
                    <div class="w-20 text-xs text-gray-600">{{ formatCurrencyShort(item.revenue) }}</div>
                  </div>
                  
                  <!-- Profit Bar -->
                  <div class="flex items-center space-x-2">
                    <div class="flex-1 bg-gray-200 rounded-full h-3">
                      <div
                        class="bg-green-500 h-3 rounded-full"
                        :style="{ width: getBarWidth(item.profit, maxRevenue) + '%' }"
                      ></div>
                    </div>
                    <div class="w-20 text-xs text-gray-600">{{ formatCurrencyShort(item.profit) }}</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Bottom Row -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
          <!-- Top Products -->
          <div class="bg-white rounded-xl shadow-lg p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-6">Sản phẩm bán chạy</h3>
            
            <div class="space-y-4">
              <div v-for="(product, index) in topProducts" :key="product.product_id" class="flex items-center">
                <div class="flex-shrink-0 w-8 h-8 bg-gradient-to-r from-blue-500 to-purple-500 rounded-full flex items-center justify-center text-white text-sm font-bold">
                  {{ index + 1 }}
                </div>
                <div class="ml-4 flex-1">
                  <p class="text-sm font-medium text-gray-900">{{ product.product_name }}</p>
                  <p class="text-xs text-gray-500">{{ product.product_sku }}</p>
                </div>
                <div class="text-right">
                  <p class="text-sm font-semibold text-gray-900">{{ product.total_sold }}</p>
                  <p class="text-xs text-green-600">{{ formatCurrencyShort(product.total_revenue) }}</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Low Stock Alerts -->
          <div class="bg-white rounded-xl shadow-lg p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-6">Cảnh báo tồn kho</h3>
            
            <div class="space-y-4">
              <div v-for="alert in lowStockAlerts" :key="alert.product_id" class="flex items-center p-3 bg-red-50 border border-red-200 rounded-lg">
                <div class="flex-shrink-0">
                  <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                  </svg>
                </div>
                <div class="ml-3 flex-1">
                  <p class="text-sm font-medium text-gray-900">{{ alert.product_name }}</p>
                  <p class="text-xs text-gray-500">{{ alert.warehouse_name }}</p>
                </div>
                <div class="text-right">
                  <p class="text-sm font-semibold text-red-600">{{ alert.current_stock }}</p>
                  <p class="text-xs text-gray-500">Min: {{ alert.min_stock }}</p>
                </div>
              </div>
              
              <div v-if="lowStockAlerts.length === 0" class="text-center py-4 text-gray-500">
                <svg class="w-12 h-12 mx-auto text-green-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <p class="text-sm">Tất cả sản phẩm đều đủ tồn kho</p>
              </div>
            </div>
          </div>

          <!-- Top Customers -->
          <div class="bg-white rounded-xl shadow-lg p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-6">Khách hàng VIP</h3>
            
            <div class="space-y-4">
              <div v-for="(customer, index) in topCustomers" :key="index" class="flex items-center">
                <div class="flex-shrink-0 w-10 h-10 bg-gradient-to-r from-green-400 to-blue-500 rounded-full flex items-center justify-center text-white font-bold">
                  {{ customer.customer_name.charAt(0).toUpperCase() }}
                </div>
                <div class="ml-4 flex-1">
                  <p class="text-sm font-medium text-gray-900">{{ customer.customer_name }}</p>
                  <p class="text-xs text-gray-500">{{ customer.order_count }} đơn hàng</p>
                </div>
                <div class="text-right">
                  <p class="text-sm font-semibold text-gray-900">{{ formatCurrencyShort(customer.total_spent) }}</p>
                  <p class="text-xs text-gray-500">{{ formatCurrencyShort(customer.avg_order_value) }}/đơn</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Tooltip -->
    <div 
      v-if="tooltip.show" 
      class="fixed z-50 bg-gray-900 text-white px-3 py-2 rounded-lg text-sm pointer-events-none"
      :style="{ left: tooltip.x + 'px', top: tooltip.y + 'px' }"
    >
      <div>{{ tooltip.date }}</div>
      <div>Doanh thu: {{ formatCurrency(tooltip.revenue) }}</div>
      <div>Đơn hàng: {{ tooltip.orders }}</div>
    </div>

    <!-- Reset Data Modal -->
    <div v-if="showResetModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div class="bg-white rounded-xl shadow-2xl max-w-md w-full mx-4 overflow-hidden">
        <div class="bg-red-500 px-6 py-4">
          <h3 class="text-xl font-bold text-white">⚠️ Reset toàn bộ dữ liệu</h3>
        </div>
        <div class="p-6">
          <div class="mb-4 text-gray-700">
            <p class="font-semibold text-red-600 mb-2">Hành động này sẽ xoá vĩnh viễn:</p>
            <ul class="list-disc pl-5 space-y-1 text-sm">
              <li>Tất cả đơn hàng, thanh toán, vận chuyển</li>
              <li>Tất cả phiếu nhập hàng, đơn mua hàng</li>
              <li>Tất cả sản phẩm, tồn kho, serial</li>
              <li>Tất cả khách hàng, nhà cung cấp, công nợ</li>
              <li>Tất cả danh mục, đơn vị tính</li>
              <li>Tất cả thu chi</li>
            </ul>
            <p class="mt-3 text-sm text-gray-500">
              <strong>Giữ lại:</strong> Tài khoản người dùng, phân quyền, kho hàng, đối tác vận chuyển.
            </p>
          </div>
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">
              Nhập <code class="bg-gray-100 px-2 py-0.5 rounded text-red-600 font-bold">RESET_ALL_DATA</code> để xác nhận:
            </label>
            <input
              v-model="resetConfirmText"
              type="text"
              class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-red-500 focus:border-red-500"
              placeholder="Nhập RESET_ALL_DATA"
              :disabled="resetting"
            />
          </div>
          <div class="flex justify-end space-x-3">
            <button
              @click="showResetModal = false; resetConfirmText = ''"
              class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
              :disabled="resetting"
            >
              Huỷ
            </button>
            <button
              @click="resetAllData"
              :disabled="resetConfirmText !== 'RESET_ALL_DATA' || resetting"
              class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <span v-if="resetting">Đang xoá...</span>
              <span v-else>🗑️ Xoá toàn bộ</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, computed, onMounted, nextTick } from 'vue'
import { dashboardApi, dashboardHelpers } from '../api/dashboardApi.js'

export default {
  name: 'Dashboard',
  setup() {
    // Reactive data
    const loading = ref(true)
    const selectedPeriod = ref(30)
    const selectedWarehouse = ref('')
    const warehouses = ref([])
    
    // Dashboard data
    const overview = ref({})
    const salesTrend = ref([])
    const revenueProfit = ref([])
    const topProducts = ref([])
    const lowStockAlerts = ref([])
    const topCustomers = ref([])
    
    // Tooltip
    const tooltip = ref({
      show: false,
      x: 0,
      y: 0,
      date: '',
      revenue: 0,
      orders: 0
    })

    // Reset data
    const showResetModal = ref(false)
    const resetConfirmText = ref('')
    const resetting = ref(false)

    // Computed
    const maxRevenue = computed(() => {
      if (revenueProfit.value.length === 0) return 1
      return Math.max(...revenueProfit.value.map(item => Math.max(item.revenue, item.profit)))
    })

    const getSalesChartPoints = computed(() => {
      if (salesTrend.value.length === 0) return ''
      return salesTrend.value.map((point, index) => {
        return `${getChartX(index)},${getChartY(point.revenue)}`
      }).join(' ')
    })

    const getSalesAreaPoints = computed(() => {
      if (salesTrend.value.length === 0) return ''
      const points = salesTrend.value.map((point, index) => {
        return `${getChartX(index)},${getChartY(point.revenue)}`
      }).join(' ')
      
      const lastIndex = salesTrend.value.length - 1
      return `${getChartX(0)},250 ${points} ${getChartX(lastIndex)},250`
    })

    // Methods
    const fetchData = async () => {
  loading.value = true
  try {
    await Promise.all([
      fetchOverview(),
      fetchSalesTrend(),
      fetchRevenueProfit(),
      fetchTopProducts(),
      fetchLowStockAlerts(),
      fetchTopCustomers(),
      fetchWarehouses()
    ])
  } catch (error) {
    // Silent error handling
  } finally {
    loading.value = false
  }
}

const fetchOverview = async () => {
  try {
    const params = {
      days: selectedPeriod.value,
      ...(selectedWarehouse.value && { warehouse_id: selectedWarehouse.value })
    }
    
    const response = await dashboardApi.getOverview(params)
    
    if (response.success) {
      overview.value = response.data
    }
  } catch (error) {
    // Silent error handling
  }
}

const fetchSalesTrend = async () => {
  try {
    const params = {
      days: selectedPeriod.value,
      ...(selectedWarehouse.value && { warehouse_id: selectedWarehouse.value })
    }
    
    const response = await dashboardApi.getSalesTrend(params)
    
    if (response.success) {
      salesTrend.value = response.data
    }
  } catch (error) {
    // Silent error handling
  }
}

const fetchRevenueProfit = async () => {
  try {
    const params = {
      months: 6,
      ...(selectedWarehouse.value && { warehouse_id: selectedWarehouse.value })
    }
    
    const response = await dashboardApi.getRevenueProfit(params)
    
    if (response.success) {
      revenueProfit.value = response.data
    }
  } catch (error) {
    // Silent error handling
  }
}

const fetchTopProducts = async () => {
  try {
    const params = {
      limit: 5,
      days: selectedPeriod.value,
      ...(selectedWarehouse.value && { warehouse_id: selectedWarehouse.value })
    }
    
    const response = await dashboardApi.getTopProducts(params)
    
    if (response.success) {
      topProducts.value = response.data
    }
  } catch (error) {
    // Silent error handling
  }
}

const fetchLowStockAlerts = async () => {
  try {
    const params = {
      ...(selectedWarehouse.value && { warehouse_id: selectedWarehouse.value })
    }
    
    const response = await dashboardApi.getLowStockAlerts(params)
    
    if (response.success) {
      lowStockAlerts.value = response.data.slice(0, 5)
    }
  } catch (error) {
    // Silent error handling
  }
}

const fetchTopCustomers = async () => {
  try {
    const params = {
      days: selectedPeriod.value,
      ...(selectedWarehouse.value && { warehouse_id: selectedWarehouse.value })
    }
    
    const response = await dashboardApi.getCustomerAnalysis(params)
    
    if (response.success) {
      topCustomers.value = response.data.slice(0, 5)
    }
  } catch (error) {
    // Silent error handling
  }
}

const fetchWarehouses = async () => {
  try {
    const response = await dashboardApi.getWarehouses()
    
    if (response.success) {
      warehouses.value = response.data.data || response.data || []
    }
  } catch (error) {
    // Silent error handling
  }
}

    // Chart helper methods
    const getChartX = (index) => {
      const width = 300 // SVG width
      const padding = 40
      const chartWidth = width - (padding * 2)
      const step = chartWidth / (salesTrend.value.length - 1 || 1)
      return padding + (index * step)
    }

    const getChartY = (value) => {
      const height = 250 // SVG height  
      const padding = 40
      const chartHeight = height - (padding * 2)
      
      if (salesTrend.value.length === 0) return height - padding
      
      const maxValue = Math.max(...salesTrend.value.map(item => item.revenue))
      const ratio = value / (maxValue || 1)
      
      return height - padding - (ratio * chartHeight)
    }

    const getBarWidth = (value, maxValue) => {
      return maxValue > 0 ? (value / maxValue) * 100 : 0
    }

    const showTooltip = (event, data) => {
      tooltip.value = {
        show: true,
        x: event.clientX + 10,
        y: event.clientY - 10,
        date: data.formatted_date,
        revenue: data.revenue,
        orders: data.orders
      }
    }

    const hideTooltip = () => {
      tooltip.value.show = false
    }

    // Reset all data
    const resetAllData = async () => {
      if (resetConfirmText.value !== 'RESET_ALL_DATA') return
      
      resetting.value = true
      try {
        const response = await dashboardApi.resetAllData('RESET_ALL_DATA')
        if (response.success) {
          alert('✅ ' + response.message)
          showResetModal.value = false
          resetConfirmText.value = ''
          await fetchData()
        }
      } catch (error) {
        alert('❌ Lỗi: ' + (error.message || 'Không thể reset dữ liệu'))
      } finally {
        resetting.value = false
      }
    }

    // Utility methods
    const formatCurrency = (amount) => {
      return dashboardHelpers.formatCurrency(amount)
    }

    const formatCurrencyShort = (amount) => {
      return dashboardHelpers.formatCurrencyShort(amount)
    }

    // Lifecycle
    onMounted(() => {
      fetchData()
    })

    return {
      // Reactive data
      loading,
      selectedPeriod,
      selectedWarehouse,
      warehouses,
      overview,
      salesTrend,
      revenueProfit,
      topProducts,
      lowStockAlerts,
      topCustomers,
      tooltip,
      showResetModal,
      resetConfirmText,
      resetting,
      
      // Computed
      maxRevenue,
      getSalesChartPoints,
      getSalesAreaPoints,
      
      // Methods
      fetchData,
      getChartX,
      getChartY,
      getBarWidth,
      showTooltip,
      hideTooltip,
      formatCurrency,
      formatCurrencyShort,
      resetAllData
    }
  }
}
</script>