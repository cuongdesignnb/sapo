<template>
  <div class="bg-white">
    <!-- Header -->
    <div class="p-6 border-b">
      <h1 class="text-2xl font-semibold text-gray-900">Danh sách đơn hàng</h1>
    </div>

    <!-- Action Buttons -->
    <div class="p-6 border-b bg-gray-50">
      <div class="flex items-center justify-between">
        <div class="flex items-center space-x-4">
          <button 
            class="flex items-center space-x-2 text-gray-600 hover:text-gray-800"
            @click="exportOrders"
            :disabled="loading"
          >
            <span>⬇</span>
            <span>Xuất file</span>
          </button>
          <button 
            class="flex items-center space-x-2 text-gray-600 hover:text-gray-800"
            @click="showImportModal = true"
          >
            <span>⬆</span>
            <span>Nhập file</span>
          </button>
          <button 
            v-if="selectedIds.length > 0"
            class="flex items-center space-x-2 text-red-600 hover:text-red-800"
            @click="bulkDelete"
            :disabled="deleting"
          >
            <span>🗑️</span>
            <span>Xóa đã chọn ({{ selectedIds.length }})</span>
          </button>
        </div>
        <button 
          class="bg-blue-500 text-white px-4 py-2 rounded flex items-center space-x-2 hover:bg-blue-600"
          @click="createOrder"
        >
          <span>+</span>
          <span>Tạo đơn hàng</span>
        </button>
      </div>
    </div>

    <!-- Filters -->
    <div class="p-6 border-b">
      <div class="grid grid-cols-12 gap-4 items-center">
        <!-- Search -->
        <div class="col-span-4">
          <div class="relative">
            <input
              type="text"
              placeholder="Tìm kiếm theo mã đơn hàng, đơn trả, tên, số điện thoại KH / người nhận hàng"
              class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md"
              v-model="searchQuery"
              @input="debouncedSearch"
            />
            <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400">🔍</span>
          </div>
        </div>
        
        <!-- Filters -->
        <div class="col-span-2">
          <select 
            class="w-full px-3 py-2 border border-gray-300 rounded-md"
            v-model="filters.date_range"
            @change="applyFilters"
          >
            <option value="90">90 ngày gần nhất</option>
            <option value="30">30 ngày gần nhất</option>
            <option value="7">7 ngày gần nhất</option>
            <option value="today">Hôm nay</option>
          </select>
        </div>
        
        <div class="col-span-2">
          <select 
            class="w-full px-3 py-2 border border-gray-300 rounded-md"
            v-model="filters.status"
            @change="applyFilters"
          >
            <option value="">Tất cả trạng thái</option>
            <option value="pending">Chờ duyệt</option>
            <option value="confirmed">Đã xác nhận</option>
            <option value="processing">Đang xử lý</option>
            <option value="shipping">Đang giao hàng</option>
            <option value="completed">Hoàn thành</option>
            <option value="cancelled">Đã hủy</option>
          </select>
        </div>
        
        <div class="col-span-2">
          <select 
            class="w-full px-3 py-2 border border-gray-300 rounded-md"
            v-model="filters.cashier_id"
            @change="applyFilters"
          >
            <option value="">Nhân viên phụ trách</option>
            <option v-for="cashier in cashiers" :key="cashier.id" :value="cashier.id">
              {{ cashier.name }}
            </option>
          </select>
        </div>
        
        <!-- Actions -->
        <div class="col-span-1">
          <button 
            class="w-full px-3 py-2 border border-gray-300 rounded-md text-gray-600 hover:bg-gray-50"
            @click="resetFilters"
          >
            🔄
          </button>
        </div>
        
        <div class="col-span-1">
          <button class="w-full px-3 py-2 text-blue-500 hover:bg-blue-50 rounded">
            ⚙️
          </button>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading && orders.length === 0" class="flex justify-center items-center py-12">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
      <span class="ml-2 text-gray-600">Đang tải dữ liệu...</span>
    </div>

    <!-- Table -->
    <div v-else class="overflow-x-auto">
      <table class="w-full">
        <thead class="bg-gray-50">
          <tr>
            <th class="w-12 p-4">
              <input 
                type="checkbox" 
                class="rounded"
                :checked="isAllSelected"
                @change="toggleSelectAll"
              />
            </th>
            <th class="text-left p-4 text-sm font-medium text-gray-600">Mã đơn hàng</th>
            <th class="text-left p-4 text-sm font-medium text-gray-600">Ngày tạo đơn</th>
            <th class="text-left p-4 text-sm font-medium text-gray-600">Ngày duyệt đơn</th>
            <th class="text-left p-4 text-sm font-medium text-gray-600">Ngày ghi nhận</th>
            <th class="text-left p-4 text-sm font-medium text-gray-600">Tên khách hàng</th>
            <th class="text-left p-4 text-sm font-medium text-gray-600">Trạng thái đơn hàng</th>
            <th class="text-left p-4 text-sm font-medium text-gray-600">Trạng thái Thanh toán</th>
            <th class="text-left p-4 text-sm font-medium text-gray-600">Trạng thái giao hàng</th>
            <th class="text-left p-4 text-sm font-medium text-gray-600">Khách phải trả</th>
            <th class="text-left p-4 text-sm font-medium text-gray-600">Thao tác</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <tr 
            v-for="order in orders" 
            :key="order.id"
            class="hover:bg-gray-50"
          >
            <td class="p-4">
              <input 
                type="checkbox" 
                class="rounded" 
                :checked="selectedIds.includes(order.id)"
                @click.stop="toggleSelection(order.id)"
              />
            </td>
            <td class="p-4">
              <a class="text-blue-500 hover:text-blue-700 font-medium" :href="`/orders/${order.id}`" title="Mở trang chi tiết">
                {{ order.code }}
              </a>
            </td>
            <td class="p-4 text-sm text-gray-600">
              {{ formatDate(order.created_at) }}
            </td>
            <td class="p-4 text-sm text-gray-600">
              {{ order.confirmed_at ? formatDate(order.confirmed_at) : '' }}
            </td>
            <td class="p-4 text-sm text-gray-600">
              {{ formatDate(order.ordered_at) }}
            </td>
            <td class="p-4 text-sm text-gray-600">
              {{ order.customer?.name || '' }}
            </td>
            <td class="p-4">
              <span 
                class="px-2 py-1 rounded-full text-xs font-medium"
                :class="getStatusClass(order.status)"
              >
                {{ getStatusText(order.status) }}
              </span>
            </td>
            <td class="p-4">
              <span class="flex items-center">
                <span class="w-2 h-2 rounded-full mr-2" :class="getPaymentStatusColor(order)"></span>
                {{ getPaymentStatus(order) }}
              </span>
            </td>
            <td class="p-4">
              <span 
                class="px-2 py-1 rounded-full text-xs font-medium"
                :class="getShippingStatusClass(order.shipping_status)"
              >
                {{ getShippingStatusText(order.shipping_status) }}
              </span>
            </td>
            <td class="p-4 text-sm text-gray-600">
              {{ formatCurrency(order.total) }}
            </td>
            <td class="p-4">
              <div class="flex space-x-2">
                <button 
                  @click="goToDetail(order)" 
                  class="text-blue-600 hover:text-blue-800 text-sm px-2 py-1 rounded" 
                  title="Mở trang chi tiết"
                >
                  👁️
                </button>
                <button 
  @click="printOrder(order.id)" 
  class="text-purple-600 hover:text-purple-800 text-sm px-2 py-1 rounded" 
  title="In đơn hàng"
>
  🖨️
</button>
                
                <button 
  v-if="canApproveOrder(order)" 
  @click="approveOrderDirect(order)" 
  class="text-green-600 hover:text-green-800 text-sm px-2 py-1 rounded" 
  title="Duyệt đơn hàng"
>
  ✅
</button>
                
                <button 
                  @click="editOrderDirect(order)" 
                  class="text-orange-600 hover:text-orange-800 text-sm px-2 py-1 rounded" 
                  title="Sửa đơn hàng"
                >
                  ✏️
                </button>
                <button 
                  v-if="canDeleteOrder(order)" 
                  @click="deleteOrder(order)" 
                  class="text-red-600 hover:text-red-800 text-sm px-2 py-1 rounded" 
                  title="Xóa đơn hàng"
                >
                  🗑️
                </button>
              </div>
            </td>
          </tr>
          
          <!-- Empty State -->
          <tr v-if="orders.length === 0 && !loading">
            <td colspan="11" class="text-center py-12 text-gray-500">
              <div class="text-4xl mb-4">📋</div>
              <div>Không có đơn hàng nào</div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="pagination.total > 0" class="px-6 py-4 border-t flex justify-between items-center">
      <div class="text-sm text-gray-700">
        Hiển thị {{ pagination.from }}-{{ pagination.to }} của {{ pagination.total }} đơn hàng
      </div>
      <div class="flex space-x-2">
        <button 
          class="px-3 py-1 border rounded text-sm"
          :disabled="pagination.current_page <= 1"
          @click="changePage(pagination.current_page - 1)"
        >
          Trước
        </button>
        
        <template v-for="page in visiblePages" :key="page">
          <button 
            class="px-3 py-1 border rounded text-sm"
            :class="page === pagination.current_page ? 'bg-blue-500 text-white' : 'bg-white'"
            @click="changePage(page)"
          >
            {{ page }}
          </button>
        </template>
        
        <button 
          class="px-3 py-1 border rounded text-sm"
          :disabled="pagination.current_page >= pagination.last_page"
          @click="changePage(pagination.current_page + 1)"
        >
          Tiếp
        </button>
      </div>
    </div>

  <!-- Detail modal removed: open full page instead -->

    <!-- Order Form Modal -->
    <OrderForm
      v-if="showForm"
      :order="editingOrder"
      @close="closeForm"
      @save="saveOrder"
    />

    <!-- Import Modal -->
    <div v-if="showImportModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg p-6 w-full max-w-md">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-lg font-medium text-gray-900">Nhập file đơn hàng</h3>
          <button @click="closeImportModal" class="text-gray-400 hover:text-gray-600">×</button>
        </div>

        <div class="space-y-4">
          <div class="text-center">
            <button @click="downloadTemplate" class="text-blue-500 hover:text-blue-700 underline text-sm">
              📥 Tải file mẫu (.xlsx)
            </button>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Chọn file Excel</label>
            <input 
              type="file" 
              accept=".xlsx,.xls" 
              @change="handleFileChange" 
              class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" 
            />
          </div>
        </div>

        <div class="flex justify-end space-x-3 mt-6">
          <button @click="closeImportModal" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300">Hủy</button>
          <button @click="importOrders" :disabled="!importFile || importing" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 disabled:opacity-50">
            <span v-if="importing">⏳ Đang nhập...</span>
            <span v-else">📤 Nhập file</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Toast Notification -->
    <div v-if="notification.show" class="fixed top-4 right-4 z-50">
      <div 
        class="p-4 rounded-lg shadow-lg max-w-sm"
        :class="{
          'bg-green-100 border border-green-400 text-green-700': notification.type === 'success',
          'bg-red-100 border border-red-400 text-red-700': notification.type === 'error'
        }"
      >
        <div class="flex items-center">
          <span class="mr-2">{{ notification.type === 'success' ? '✅' : '❌' }}</span>
          <span>{{ notification.message }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue'
import OrderForm from '../components/OrderForm.vue'
import { orderApi, orderHelpers } from '../api/orderApi'

export default {
  name: 'OrderList',
  components: {
    OrderForm
  },
  setup() {
    // Reactive data
    const loading = ref(false)
    const deleting = ref(false)
    const orders = ref([])
    const cashiers = ref([])
    const selectedIds = ref([])
    const searchQuery = ref('')
    const showDetail = ref(false)
    const showForm = ref(false)
    const showImportModal = ref(false)
    const importing = ref(false)
    const importFile = ref(null)
  const selectedOrder = ref(null) // still used for editFromDetail
    const editingOrder = ref(null)

    // Stats
    const stats = ref({
      pending_orders: 0,
      waiting_payment: 0,
      pending_pack: 0,
      shipping: 0,
      pickup: 0,
      redelivery: 0
    })

    // Notification
    const notification = ref({
      show: false,
      type: 'success',
      message: ''
    })

    // Filters
    const filters = ref({
      date_range: '90',
      status: '',
      cashier_id: '',
      warehouse_id: ''
    })

    // Pagination
    const pagination = ref({
      current_page: 1,
      last_page: 1,
      per_page: 20,
      total: 0,
      from: 0,
      to: 0
    })

    // Computed
    const isAllSelected = computed(() => {
      return orders.value.length > 0 && 
             selectedIds.value.length === orders.value.length
    })

    const visiblePages = computed(() => {
      const current = pagination.value.current_page || 1
      const last = pagination.value.last_page || 1
      const pages = []
      
      for (let i = Math.max(1, current - 2); i <= Math.min(last, current + 2); i++) {
        pages.push(i)
      }
      
      return pages
    })

    // Notification helper
    const showNotification = (message, type = 'success') => {
      notification.value = {
        show: true,
        type,
        message
      }
      
      setTimeout(() => {
        notification.value.show = false
      }, 3000)
    }

    // Methods
    const fetchOrders = async () => {
      loading.value = true
      
      try {
        const params = {
          ...filters.value,
          search: searchQuery.value,
          page: pagination.value.current_page,
          per_page: pagination.value.per_page
        }

        const response = await orderApi.getAll(params)

        if (response.success) {
          orders.value = response.data || []
          pagination.value = response.pagination || {}
        }
      } catch (error) {
        console.error('Error fetching orders:', error)
        showNotification('Lỗi khi tải dữ liệu', 'error')
      } finally {
        loading.value = false
      }
    }

    const fetchStats = async () => {
      try {
        const response = await orderApi.getStats()
        if (response.success) {
          stats.value = response.data || {}
        }
      } catch (error) {
        console.error('Error fetching stats:', error)
      }
    }

    const applyFilters = async () => {
      pagination.value.current_page = 1
      await fetchOrders()
    }

    const debouncedSearch = debounce(() => {
      applyFilters()
    }, 300)

    const resetFilters = () => {
      searchQuery.value = ''
      filters.value = {
        date_range: '90',
        status: '',
        cashier_id: '',
        warehouse_id: ''
      }
      applyFilters()
    }

    const changePage = (page) => {
      pagination.value.current_page = page
      fetchOrders()
    }

    const toggleSelection = (id) => {
      const index = selectedIds.value.indexOf(id)
      if (index > -1) {
        selectedIds.value.splice(index, 1)
      } else {
        selectedIds.value.push(id)
      }
    }

    const toggleSelectAll = () => {
      if (isAllSelected.value) {
        selectedIds.value = []
      } else {
        selectedIds.value = orders.value.map(o => o.id)
      }
    }

    const createOrder = () => {
      editingOrder.value = null
      showForm.value = true
    }

    const goToDetail = (order) => {
      window.location.href = `/orders/${order.id}`
    }
    const printOrder = async (orderId) => {
  console.log('🖨️ Print button clicked for order:', orderId)
  
  try {
    const response = await orderApi.print(orderId)
    console.log('✅ Print response:', response)
    
    if (response.success) {
      showNotification('Đang mở cửa sổ in...', 'success')
    }
  } catch (error) {
    console.error('💥 Print error:', error)
    showNotification('Lỗi khi in đơn hàng: ' + error.message, 'error')
  }
}
    const viewOrder = (order) => {
      window.location.href = `/orders/${order.id}`
    }

    const closeDetail = () => {
      showDetail.value = false
      selectedOrder.value = null
    }

    const closeForm = () => {
      showForm.value = false
      editingOrder.value = null
    }

    const editFromDetail = (order) => {
      editingOrder.value = order
  // navigate to edit page directly if needed? keep modal-less edit
      showForm.value = true
    }

    const saveOrder = async (data) => {
      try {
        if (editingOrder.value?.id) {
          await orderApi.update(editingOrder.value.id, data)
          showNotification('Cập nhật đơn hàng thành công')
        } else {
          await orderApi.create(data)
          showNotification('Tạo đơn hàng thành công')
        }
        
        await fetchOrders()
        closeForm()
        closeDetail()
      } catch (error) {
        console.error('Error saving order:', error)
        showNotification('Lỗi khi lưu đơn hàng', 'error')
      }
    }

    const updateOrderStatus = async (id, statusData) => {
      try {
        await orderApi.updateStatus(id, statusData)
        showNotification('Cập nhật trạng thái thành công')
        await fetchOrders()
      } catch (error) {
        console.error('Error updating status:', error)
        showNotification('Lỗi khi cập nhật trạng thái', 'error')
      }
    }

    const addPayment = async (id, paymentData) => {
      try {
        await orderApi.addPayment(id, paymentData)
        showNotification('Thêm thanh toán thành công')
        await fetchOrders()
      } catch (error) {
        console.error('Error adding payment:', error)
        showNotification('Lỗi khi thêm thanh toán', 'error')
      }
    }

    const bulkDelete = async () => {
      if (confirm(`Bạn có chắc muốn xóa ${selectedIds.value.length} đơn hàng đã chọn?`)) {
        deleting.value = true
        try {
          await orderApi.bulkDelete(selectedIds.value)
          showNotification(`Đã xóa ${selectedIds.value.length} đơn hàng`)
          selectedIds.value = []
          await fetchOrders()
        } catch (error) {
          console.error('Error bulk deleting:', error)
          showNotification('Lỗi khi xóa hàng loạt', 'error')
        } finally {
          deleting.value = false
        }
      }
    }

    const exportOrders = async () => {
      try {
        loading.value = true
        showNotification('Đang xuất file...', 'info')
        
        const exportParams = {
          search: searchQuery.value,
          status: filters.value.status,
          cashier_id: filters.value.cashier_id,
          date_range: filters.value.date_range
        }

        if (selectedIds.value.length > 0) {
          exportParams.selected_ids = selectedIds.value
        }

        await orderApi.export(exportParams)
        showNotification('Xuất file thành công!', 'success')
        
      } catch (error) {
        console.error('Export error:', error)
        showNotification(`Lỗi: ${error.message}`, 'error')
      } finally {
        loading.value = false
      }
    }

    const handleFileChange = (event) => {
      importFile.value = event.target.files[0]
    }

    const importOrders = async () => {
      if (!importFile.value) {
        showNotification('Vui lòng chọn file để nhập', 'error')
        return
      }

      try {
        importing.value = true
        showNotification('Đang nhập dữ liệu...', 'info')

        const response = await orderApi.import(importFile.value)

        if (response.success) {
          showNotification(response.message, 'success')
          await fetchOrders()
          closeImportModal()
        }
      } catch (error) {
        console.error('Import error:', error)
        showNotification('Lỗi khi nhập file', 'error')
      } finally {
        importing.value = false
      }
    }

    const downloadTemplate = async () => {
      try {
        await orderApi.downloadTemplate()
        showNotification('Tải template thành công!', 'success')
      } catch (error) {
        console.error('Download template error:', error)
        showNotification('Lỗi khi tải template', 'error')
      }
    }

    const closeImportModal = () => {
      showImportModal.value = false
      importFile.value = null
    }

    // Helper methods
    const formatDate = (dateString) => {
      return orderHelpers.formatDate(dateString)
    }

    const formatCurrency = (amount) => {
      return orderHelpers.formatCurrency(amount)
    }

    const getStatusText = (status) => {
      return orderHelpers.getStatusText(status)
    }

    const getStatusClass = (status) => {
      return orderHelpers.getStatusClass(status)
    }

    const getPaymentStatus = (order) => {
      if (order.paid >= order.total) return 'Đã thanh toán'
      if (order.paid > 0) return 'Thanh toán một phần'
      return 'Chưa thanh toán'
    }

    const getPaymentStatusColor = (order) => {
      if (order.paid >= order.total) return 'bg-green-500'
      if (order.paid > 0) return 'bg-yellow-500'
      return 'bg-red-500'
    }

    const getShippingStatusText = (status) => {
      const statusMap = {
        'pending': 'Chờ lấy hàng',
        'picked_up': 'Đã lấy hàng',
        'in_transit': 'Đang vận chuyển',
        'delivered': 'Đã giao hàng',
        'failed': 'Giao hàng thất bại'
      }
      return statusMap[status] || 'Chưa xác định'
    }

    const getShippingStatusClass = (status) => {
      const classMap = {
        'pending': 'bg-yellow-100 text-yellow-800',
        'picked_up': 'bg-blue-100 text-blue-800',
        'in_transit': 'bg-purple-100 text-purple-800',
        'delivered': 'bg-green-100 text-green-800',
        'failed': 'bg-red-100 text-red-800'
      }
      return classMap[status] || 'bg-gray-100 text-gray-800'
    }

    const showOrdersByDate = () => {
      // Toggle filter logic
    }
    // ✅ THÊM 3 FUNCTIONS NÀY vào setup() sau dòng 600
const canApproveOrder = (order) => {
  return order.status === 'pending'
}

const canDeleteOrder = (order) => {
  return true
}

const deleteOrder = async (order) => {
  const statusText = order.status === 'cancelled' ? 'đã hủy' : order.status === 'ordered' ? 'đã đặt' : 'chờ xử lý'
  if (!confirm(`Bạn có chắc chắn muốn xóa đơn hàng ${order.code} (${statusText})?\n\nLưu ý: Công nợ khách hàng và tồn kho sẽ được hoàn lại.`)) return

  try {
    loading.value = true
    const response = await orderApi.delete(order.id)
    if (response.success) {
      showNotification(response.message || 'Xóa đơn hàng thành công')
      await fetchOrders()
      await fetchStats()
    }
  } catch (error) {
    console.error('Error deleting order:', error)
    showNotification(error.message || 'Lỗi khi xóa đơn hàng', 'error')
  } finally {
    loading.value = false
  }
}

const approveOrderDirect = async (order) => {
  if (!confirm(`Bạn có chắc chắn muốn duyệt đơn hàng ${order.code}?`)) return

  try {
    loading.value = true
    
    const response = await orderApi.updateStatus(order.id, {
      status: 'confirmed',
      note: 'Đơn hàng đã được duyệt từ danh sách'
    })

    if (response.success) {
      showNotification('Duyệt đơn hàng thành công')
      await fetchOrders() // Refresh danh sách
    }
  } catch (error) {
    console.error('Error approving order:', error)
    showNotification('Lỗi khi duyệt đơn hàng', 'error')
  } finally {
    loading.value = false
  }
}

const editOrderDirect = (order) => {
  editingOrder.value = order
  showForm.value = true
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

    // Lifecycle
    onMounted(async () => {
      await Promise.all([
        fetchOrders(),
        fetchStats()
      ])
    })

    return {
      loading,
      deleting,
      orders,
      cashiers,
      selectedIds,
      searchQuery,
      showDetail,
      showForm,
      showImportModal,
      importing,
      importFile,
      selectedOrder,
      editingOrder,
      stats,
      notification,
      filters,
      pagination,
      isAllSelected,
      visiblePages,
      fetchOrders,
      fetchStats,
      applyFilters,
      debouncedSearch,
      resetFilters,
      changePage,
      toggleSelection,
      toggleSelectAll,
      createOrder,
      goToDetail,
      viewOrder,
      closeDetail,
      closeForm,
      editFromDetail,
      saveOrder,
      updateOrderStatus,
      addPayment,
      bulkDelete,
      exportOrders,
      handleFileChange,
      importOrders,
      downloadTemplate,
      closeImportModal,
      showOrdersByDate,
      formatDate,
      formatCurrency,
      getStatusText,
      getStatusClass,
      getPaymentStatus,
      getPaymentStatusColor,
      getShippingStatusText,
      getShippingStatusClass,
      canApproveOrder,      
      approveOrderDirect, 
      editOrderDirect, 
      canDeleteOrder,
      deleteOrder,
      printOrder,
    }
  }
}
</script>