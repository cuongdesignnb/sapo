<template>
  <!-- Modal Overlay -->
  <div class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-start justify-center pt-4">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-6xl mx-4 max-h-screen overflow-y-auto">
      
      <!-- Header -->
      <div class="flex items-center justify-between p-6 border-b">
        <div class="flex items-center space-x-4">
          <button @click="$emit('close')" class="text-gray-500 hover:text-gray-700">
            ← Quay lại danh sách nhóm khách hàng
          </button>
        </div>
        <div class="text-center">
          <h2 class="text-xl font-semibold text-red-500">CHI TIẾT NHÓM KHÁCH HÀNG</h2>
        </div>
        <div class="flex items-center space-x-3">
          <button @click="$emit('close')" class="px-4 py-2 border border-gray-300 rounded text-gray-600 hover:bg-gray-50">
            Thoát
          </button>
          <button @click="$emit('delete', group.id)" class="px-4 py-2 border border-red-300 rounded text-red-600 hover:bg-red-50">
            Xoá
          </button>
          <button @click="editGroup" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">
            Sửa nhóm khách hàng
          </button>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="flex justify-center items-center py-12">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
        <span class="ml-2 text-gray-600">Đang tải dữ liệu...</span>
      </div>

      <!-- Content -->
      <div v-else-if="group" class="p-6">
        <!-- Tabs -->
        <div class="flex space-x-8 border-b mb-6">
          <button 
            :class="['pb-3', activeTab === 'info' ? 'border-b-2 border-blue-500 text-blue-500' : 'text-gray-600']"
            @click="activeTab = 'info'"
          >
            Thông tin nhóm
          </button>
          <button 
  :class="['pb-3', activeTab === 'customers' ? 'border-b-2 border-blue-500 text-blue-500' : 'text-gray-600']"
  @click="() => { activeTab = 'customers'; if (customers.length === 0) loadCustomers(); }"
>
  Danh sách khách hàng ({{ group.customers_count }})
</button>
          <button 
            :class="['pb-3', activeTab === 'statistics' ? 'border-b-2 border-blue-500 text-blue-500' : 'text-gray-600']"
            @click="activeTab = 'statistics'"
          >
            Thống kê
          </button>
        </div>

        <!-- Tab Content -->
        <div v-if="activeTab === 'info'" class="grid grid-cols-1 lg:grid-cols-3 gap-8">
          
          <!-- Left Column - Group Info -->
          <div class="lg:col-span-2 space-y-6">
            <!-- Basic Info -->
            <div class="grid grid-cols-2 gap-x-8 gap-y-4 text-sm">
              <div class="flex">
                <span class="w-32 text-gray-600">Mã nhóm</span>
                <span class="text-gray-900">: {{ group.code }}</span>
              </div>
              <div class="flex">
                <span class="w-32 text-gray-600">Loại nhóm</span>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" :class="group.type_color_class">
                  : {{ group.type_text }}
                </span>
              </div>
              
              <div class="flex">
                <span class="w-32 text-gray-600">Chiết khấu</span>
                <span class="text-gray-900">: {{ group.formatted_discount }}</span>
              </div>
              <div class="flex">
                <span class="w-32 text-gray-600">Điều kiện thanh toán</span>
                <span class="text-gray-900">: {{ formatPaymentTerms(group.payment_terms) }}</span>
              </div>
              
              <div class="flex">
                <span class="w-32 text-gray-600">Số khách hàng</span>
                <span class="text-gray-900 font-medium">: {{ group.customers_count }}</span>
              </div>
              <div class="flex">
                <span class="w-32 text-gray-600">Trạng thái</span>
                <span class="text-gray-900">: {{ group.customers_count > 0 ? 'Đang sử dụng' : 'Chưa có khách hàng' }}</span>
              </div>
              
              <div class="flex">
                <span class="w-32 text-gray-600">Ngày tạo</span>
                <span class="text-gray-900">: {{ group.created_at }}</span>
              </div>
              <div class="flex">
                <span class="w-32 text-gray-600">Cập nhật cuối</span>
                <span class="text-gray-900">: {{ group.updated_at }}</span>
              </div>
            </div>

            <!-- Description -->
            <div class="border-t pt-6">
              <h3 class="text-lg font-semibold mb-4">Mô tả</h3>
              <div class="text-sm text-gray-900">
                {{ group.description || 'Chưa có mô tả' }}
              </div>
            </div>
          </div>

          <!-- Right Column - Stats & Actions -->
          <div class="space-y-6">
            <!-- Quick Stats -->
            <div class="border rounded-lg p-6">
              <h4 class="font-semibold mb-4">Thống kê nhanh</h4>
              <div class="space-y-4">
                <div class="flex justify-between">
                  <span class="text-gray-600">Tổng khách hàng</span>
                  <span class="font-medium">{{ group.statistics?.total_customers || 0 }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-600">Khách hàng hoạt động</span>
                  <span class="font-medium">{{ group.statistics?.active_customers || 0 }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-600">Tổng doanh thu</span>
                  <span class="font-medium">{{ formatCurrency(group.statistics?.total_revenue) }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-600">Tổng đơn hàng</span>
                  <span class="font-medium">{{ group.statistics?.total_orders || 0 }}</span>
                </div>
              </div>
            </div>

            <!-- Group Benefits -->
            <div class="border rounded-lg p-6">
              <h4 class="font-semibold mb-4">Ưu đãi nhóm</h4>
              <div class="space-y-3">
                <div v-if="group.discount_percent > 0" class="flex items-center space-x-2">
                  <span class="text-green-500">💰</span>
                  <span class="text-sm">Chiết khấu {{ group.formatted_discount }}</span>
                </div>
                <div v-if="group.payment_terms > 0" class="flex items-center space-x-2">
                  <span class="text-blue-500">📅</span>
                  <span class="text-sm">Thanh toán {{ formatPaymentTerms(group.payment_terms) }}</span>
                </div>
                <div v-if="group.type === 'vip'" class="flex items-center space-x-2">
                  <span class="text-purple-500">👑</span>
                  <span class="text-sm">Ưu tiên VIP</span>
                </div>
                <div v-if="group.discount_percent === 0 && group.payment_terms === 0">
                  <span class="text-gray-500 text-sm">Chưa có ưu đãi đặc biệt</span>
                </div>
              </div>
            </div>

            <!-- Quick Actions -->
            <div class="border rounded-lg p-6">
              <h4 class="font-semibold mb-4">Thao tác nhanh</h4>
              <div class="space-y-3">
                <button 
                  @click="activeTab = 'customers'"
                  class="w-full px-4 py-2 text-left border rounded hover:bg-gray-50"
                >
                  👥 Xem danh sách khách hàng
                </button>
                <button 
                  @click="exportGroupCustomers"
                  class="w-full px-4 py-2 text-left border rounded hover:bg-gray-50"
                  :disabled="group.customers_count === 0"
                >
                  📊 Xuất danh sách khách hàng
                </button>
                <button 
                  @click="activeTab = 'statistics'"
                  class="w-full px-4 py-2 text-left border rounded hover:bg-gray-50"
                >
                  📈 Xem thống kê chi tiết
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Customers Tab -->
        <div v-else-if="activeTab === 'customers'">
          <!-- Search for customers -->
          <div class="mb-4">
            <div class="flex items-center space-x-4">
              <div class="flex-1">
                <input
                  type="text"
                  placeholder="Tìm kiếm khách hàng..."
                  class="w-full px-3 py-2 border border-gray-300 rounded-md"
                  v-model="customerSearch"
                  @input="debouncedCustomerSearch"
                />
              </div>
              <button 
                @click="loadCustomers"
                class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600"
                :disabled="loadingCustomers"
              >
                🔄 Tải lại
              </button>
            </div>
          </div>

          <!-- Loading customers -->
          <div v-if="loadingCustomers" class="flex justify-center items-center py-8">
            <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-500"></div>
            <span class="ml-2 text-gray-600">Đang tải khách hàng...</span>
          </div>

          <!-- Customers table -->
          <div v-else-if="customers.length > 0" class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="bg-gray-50">
                <tr>
                  <th class="text-left p-3 text-gray-600">Mã khách hàng</th>
                  <th class="text-left p-3 text-gray-600">Tên khách hàng</th>
                  <th class="text-left p-3 text-gray-600">Email</th>
                  <th class="text-left p-3 text-gray-600">Số điện thoại</th>
                  <th class="text-left p-3 text-gray-600">Trạng thái</th>
                  <th class="text-right p-3 text-gray-600">Tổng chi tiêu</th>
                  <th class="text-right p-3 text-gray-600">Số đơn hàng</th>
                  <th class="text-left p-3 text-gray-600">Ngày tham gia</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200">
                <tr v-for="customer in customers" :key="customer.id" class="hover:bg-gray-50">
                  <td class="p-3">
                    <span class="text-blue-500 hover:underline cursor-pointer">{{ customer.code }}</span>
                  </td>
                  <td class="p-3 font-medium">{{ customer.name }}</td>
                  <td class="p-3 text-gray-600">{{ customer.email || '---' }}</td>
                  <td class="p-3 text-gray-600">{{ customer.phone || '---' }}</td>
                  <td class="p-3">
                    <span 
                      class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                      :class="customer.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'"
                    >
                      {{ customer.status === 'active' ? 'Hoạt động' : 'Không hoạt động' }}
                    </span>
                  </td>
                  <td class="p-3 text-right font-medium">{{ formatCurrency(customer.total_spend) }}</td>
                  <td class="p-3 text-right">{{ customer.total_orders }}</td>
                  <td class="p-3 text-gray-600">{{ customer.created_at }}</td>
                </tr>
              </tbody>
            </table>

            <!-- Customer pagination -->
            <div v-if="customerPagination.total > customerPagination.per_page" class="mt-4 flex justify-between items-center">
              <div class="text-sm text-gray-700">
                {{ customerPagination.from }}-{{ customerPagination.to }} của {{ customerPagination.total }} khách hàng
              </div>
              <div class="flex space-x-2">
                <button 
                  @click="changeCustomerPage(customerPagination.current_page - 1)"
                  :disabled="customerPagination.current_page <= 1"
                  class="px-3 py-1 border rounded text-sm disabled:opacity-50"
                >
                  Trước
                </button>
                <button 
                  @click="changeCustomerPage(customerPagination.current_page + 1)"
                  :disabled="customerPagination.current_page >= customerPagination.last_page"
                  class="px-3 py-1 border rounded text-sm disabled:opacity-50"
                >
                  Tiếp
                </button>
              </div>
            </div>
          </div>
          
          <!-- Empty customers state -->
          <div v-else class="text-center py-12 text-gray-500">
            <div class="text-4xl mb-4">👥</div>
            <p>Nhóm này chưa có khách hàng nào</p>
          </div>
        </div>

        <!-- Statistics Tab -->
        <div v-else-if="activeTab === 'statistics'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <!-- Customer Stats -->
          <div class="border rounded-lg p-6">
            <h4 class="font-semibold mb-4 text-blue-600">👥 Khách hàng</h4>
            <div class="space-y-3">
              <div class="flex justify-between">
                <span class="text-gray-600">Tổng số</span>
                <span class="font-medium">{{ group.statistics?.total_customers || 0 }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-600">Đang hoạt động</span>
                <span class="font-medium">{{ group.statistics?.active_customers || 0 }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-600">Tỷ lệ hoạt động</span>
                <span class="font-medium">{{ getActivityRate() }}%</span>
              </div>
            </div>
          </div>

          <!-- Revenue Stats -->
          <div class="border rounded-lg p-6">
            <h4 class="font-semibold mb-4 text-green-600">💰 Doanh thu</h4>
            <div class="space-y-3">
              <div class="flex justify-between">
                <span class="text-gray-600">Tổng doanh thu</span>
                <span class="font-medium">{{ formatCurrency(group.statistics?.total_revenue) }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-600">Trung bình/KH</span>
                <span class="font-medium">{{ formatCurrency(group.statistics?.avg_order_value) }}</span>
              </div>
            </div>
          </div>

          <!-- Order Stats -->
          <div class="border rounded-lg p-6">
            <h4 class="font-semibold mb-4 text-purple-600">📦 Đơn hàng</h4>
            <div class="space-y-3">
              <div class="flex justify-between">
                <span class="text-gray-600">Tổng đơn hàng</span>
                <span class="font-medium">{{ group.statistics?.total_orders || 0 }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-600">TB đơn/KH</span>
                <span class="font-medium">{{ getAvgOrdersPerCustomer() }}</span>
              </div>
            </div>
          </div>

          <!-- Discount Stats -->
          <div class="border rounded-lg p-6">
            <h4 class="font-semibold mb-4 text-orange-600">🎯 Ưu đãi</h4>
            <div class="space-y-3">
              <div class="flex justify-between">
                <span class="text-gray-600">Chiết khấu</span>
                <span class="font-medium">{{ group.formatted_discount }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-600">Điều kiện TT</span>
                <span class="font-medium">{{ formatPaymentTerms(group.payment_terms) }}</span>
              </div>
            </div>
          </div>

          <!-- Group Type Stats -->
          <div class="border rounded-lg p-6">
            <h4 class="font-semibold mb-4 text-red-600">🏷️ Phân loại</h4>
            <div class="space-y-3">
              <div class="flex justify-between">
                <span class="text-gray-600">Loại nhóm</span>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" :class="group.type_color_class">
                  {{ group.type_text }}
                </span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-600">Mã nhóm</span>
                <span class="font-medium">{{ group.code }}</span>
              </div>
            </div>
          </div>

          <!-- Time Stats -->
          <div class="border rounded-lg p-6">
            <h4 class="font-semibold mb-4 text-indigo-600">⏰ Thời gian</h4>
            <div class="space-y-3">
              <div class="flex justify-between">
                <span class="text-gray-600">Ngày tạo</span>
                <span class="font-medium">{{ group.created_at }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-600">Cập nhật cuối</span>
                <span class="font-medium">{{ group.updated_at }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, onMounted, watch } from 'vue'
import customerGroupApi from '../api/customerGroupApi.js'

export default {
  name: 'CustomerGroupDetail',
  props: {
    group: {
      type: Object,
      default: null
    },
    groupId: {
      type: [String, Number],
      default: null
    }
  },
  emits: ['close', 'edit', 'delete'],
  setup(props, { emit }) {
    const loading = ref(false)
    const loadingCustomers = ref(false)
    const activeTab = ref('info')
    const customers = ref([])
    const customerSearch = ref('')
    
    const customerPagination = ref({
      current_page: 1,
      last_page: 1,
      per_page: 15,
      total: 0,
      from: 0,
      to: 0
    })
    watch(() => activeTab.value, (newTab) => {
      if (newTab === 'customers' && customers.value.length === 0) {
        loadCustomers()
      }
    })
    const editGroup = () => {
      emit('edit', props.group)
    }

    const loadGroupDetail = async () => {
      if (!props.groupId) return
      
      try {
        loading.value = true
        const response = await customerGroupApi.getCustomerGroup(props.groupId)
        if (response.data.success) {
          Object.assign(props.group, response.data.data)
        }
      } catch (error) {
        console.error('Error loading group detail:', error)
      } finally {
        loading.value = false
      }
    }

    const loadCustomers = async () => {
      if (!props.group?.id) return
      
      try {
        loadingCustomers.value = true
        const params = {
          page: customerPagination.value.current_page,
          per_page: customerPagination.value.per_page,
          search: customerSearch.value
        }
        
        const response = await customerGroupApi.getGroupCustomers(props.group.id, params)
        if (response.data.success) {
          customers.value = response.data.data
          customerPagination.value = response.data.pagination
        }
      } catch (error) {
        console.error('Error loading customers:', error)
      } finally {
        loadingCustomers.value = false
      }
    }

    const changeCustomerPage = (page) => {
      customerPagination.value.current_page = page
      loadCustomers()
    }

    const debouncedCustomerSearch = debounce(() => {
      customerPagination.value.current_page = 1
      loadCustomers()
    }, 300)

    const exportGroupCustomers = () => {
      if (props.group?.customers_count > 0) {
        window.open(`/api/customers/export?group_id=${props.group.id}`, '_blank')
      }
    }

    const formatCurrency = (amount) => {
      return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND'
      }).format(amount || 0)
    }

    const formatPaymentTerms = (days) => {
      return days === 0 ? 'Thanh toán ngay' : `${days} ngày`
    }

    const getActivityRate = () => {
      const stats = props.group?.statistics
      if (!stats || stats.total_customers === 0) return 0
      return Math.round((stats.active_customers / stats.total_customers) * 100)
    }

    const getAvgOrdersPerCustomer = () => {
      const stats = props.group?.statistics
      if (!stats || stats.total_customers === 0) return '0'
      return Math.round(stats.total_orders / stats.total_customers * 10) / 10
    }

    // Debounce helper
    function debounce(func, wait) {
      let timeout
      return function executedFunction(...args) {
        const later = () => {
          clearTimeout(timeout)
          func(...args)
        }
        clearTimeout(timeout)
        timeout = setTimeout(later, wait)
      }
    }

    // Watch for tab changes to load customers when needed
    const handleTabChange = () => {
      if (activeTab.value === 'customers' && customers.value.length === 0) {
        loadCustomers()
      }
    }

    onMounted(() => {
      if (props.groupId && !props.group?.statistics) {
        loadGroupDetail()
      }
    })

    return {
      loading,
      loadingCustomers,
      activeTab,
      customers,
      customerSearch,
      customerPagination,
      loadGroupDetail,
      loadCustomers,
      changeCustomerPage,
      debouncedCustomerSearch,
      exportGroupCustomers,
      editGroup,
      formatCurrency,
      formatPaymentTerms,
      getActivityRate,
      getAvgOrdersPerCustomer,
      handleTabChange
    }
  }
}
</script>