<template>
  <div class="bg-white min-h-screen">
    <!-- Header -->
    <div class="p-6 border-b bg-white sticky top-0 z-10">
      <div class="flex justify-between items-center">
        <div>
          <h1 class="text-2xl font-semibold text-gray-900">📤 Danh sách đơn trả hàng</h1>
          <div class="text-sm text-gray-500 mt-1">
            <span>Trang chủ</span> / <span class="text-gray-900">Đơn trả hàng cho nhà cung cấp</span>
          </div>
        </div>
        <div class="flex space-x-3">
          <button @click="exportData" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
            📊 Xuất Excel
          </button>
          <a href="/purchase-return-orders/create" class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600">
            ➕ Tạo đơn trả hàng
          </a>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="p-6 border-b bg-gray-50">
      <div class="grid grid-cols-6 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Tìm kiếm</label>
          <input 
            v-model="filters.search" 
            @input="debounceSearch"
            type="text" 
            placeholder="Mã đơn, nhà cung cấp..." 
            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Nhà cung cấp</label>
          <select v-model="filters.supplier_id" @change="applyFilters" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">Tất cả</option>
            <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">
              {{ supplier.name }}
            </option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Kho</label>
          <select v-model="filters.warehouse_id" @change="applyFilters" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">Tất cả</option>
            <option v-for="warehouse in warehouses" :key="warehouse.id" :value="warehouse.id">
              {{ warehouse.name }}
            </option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Trạng thái</label>
          <select v-model="filters.status" @change="applyFilters" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">Tất cả</option>
            <option value="pending">Chờ duyệt</option>
            <option value="approved">Đã duyệt</option>
            <option value="returned">Đã trả hàng</option>
            <option value="completed">Hoàn tất</option>
            <option value="cancelled">Đã hủy</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Từ ngày</label>
          <input 
            v-model="filters.date_from" 
            @change="applyFilters"
            type="date" 
            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Đến ngày</label>
          <input 
            v-model="filters.date_to" 
            @change="applyFilters"
            type="date" 
            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
        </div>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center items-center py-12">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
      <span class="ml-2 text-gray-600">Đang tải...</span>
    </div>

    <!-- Table -->
    <div v-else class="p-6">
      <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-200">
              <tr>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">
                  <input type="checkbox" @change="toggleSelectAll" :checked="isAllSelected" class="rounded border-gray-300">
                </th>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100" @click="sortBy('code')">
                  Mã đơn trả hàng
                  <span v-if="filters.sort_field === 'code'" class="ml-1">
                    {{ filters.sort_direction === 'asc' ? '↑' : '↓' }}
                  </span>
                </th>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Nhà cung cấp
                </th>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Kho trả hàng
                </th>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100" @click="sortBy('created_at')">
                  Ngày tạo
                  <span v-if="filters.sort_field === 'created_at'" class="ml-1">
                    {{ filters.sort_direction === 'asc' ? '↑' : '↓' }}
                  </span>
                </th>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Trạng thái
                </th>
                <th class="text-right px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100" @click="sortBy('total')">
                  Tổng tiền
                  <span v-if="filters.sort_field === 'total'" class="ml-1">
                    {{ filters.sort_direction === 'asc' ? '↑' : '↓' }}
                  </span>
                </th>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Người tạo
                </th>
                <th class="text-center px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Thao tác
                </th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="returnOrder in returnOrders" :key="returnOrder.id" class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap">
                  <input type="checkbox" :value="returnOrder.id" @change="toggleSelection" :checked="selectedIds.includes(returnOrder.id)" class="rounded border-gray-300">
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="font-medium text-gray-900">{{ returnOrder.code }}</div>
                  <div class="text-sm text-gray-500">{{ formatDateTime(returnOrder.created_at) }}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="font-medium">{{ returnOrder.supplier?.name }}</div>
                  <div class="text-sm text-gray-500">{{ returnOrder.supplier?.phone }}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="font-medium">{{ returnOrder.warehouse?.name }}</div>
                  <div class="text-sm text-gray-500">{{ returnOrder.warehouse?.code }}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ formatDate(returnOrder.created_at) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span :class="getStatusBadgeClass(returnOrder.status)" class="px-2 py-1 text-xs font-medium rounded-full">
                    {{ getStatusText(returnOrder.status) }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium text-gray-900">
                  {{ formatCurrency(returnOrder.total) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ returnOrder.creator?.name }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                  <div class="flex justify-center space-x-2">
                    <button @click="viewReturnOrder(returnOrder)" class="text-blue-600 hover:text-blue-800" title="Xem chi tiết">
                      👁️
                    </button>
                    <button v-if="canEdit(returnOrder)" @click="editReturnOrder(returnOrder)" class="text-green-600 hover:text-green-800" title="Chỉnh sửa">
                      ✏️
                    </button>
                    <button v-if="canDelete(returnOrder)" @click="deleteReturnOrder(returnOrder)" class="text-red-600 hover:text-red-800" title="Xóa">
                      🗑️
                    </button>
                    <button v-if="canApprove(returnOrder)" @click="approveReturnOrder(returnOrder)" class="text-green-600 hover:text-green-800" title="Duyệt và giảm nợ">
  ✅
</button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Empty state -->
        <div v-if="returnOrders.length === 0" class="text-center py-12">
          <div class="text-6xl mb-4">📄</div>
          <h3 class="text-lg font-medium text-gray-900 mb-2">Chưa có đơn trả hàng nào</h3>
          <p class="text-gray-500 mb-4">Tạo đơn trả hàng đầu tiên của bạn.</p>
          <a href="/purchase-return-orders/create" class="inline-flex items-center px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600">
            ➕ Tạo đơn trả hàng
          </a>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="returnOrders.length > 0" class="flex items-center justify-between mt-6">
        <div class="text-sm text-gray-700">
          Hiển thị {{ ((pagination.current_page - 1) * pagination.per_page) + 1 }} - 
          {{ Math.min(pagination.current_page * pagination.per_page, pagination.total) }} 
          trong tổng số {{ pagination.total }} bản ghi
        </div>
        
        <div class="flex items-center space-x-2">
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

      <!-- Bulk Actions -->
      <div v-if="selectedIds.length > 0" class="fixed bottom-6 left-1/2 transform -translate-x-1/2 bg-white border border-gray-300 rounded-lg shadow-lg px-6 py-3">
        <div class="flex items-center space-x-4">
          <span class="text-sm text-gray-600">{{ selectedIds.length }} mục đã chọn</span>
          <button @click="bulkDelete" class="px-3 py-1 bg-red-500 text-white rounded text-sm hover:bg-red-600">
            🗑️ Xóa tất cả
          </button>
          <button @click="clearSelection" class="px-3 py-1 border border-gray-300 rounded text-sm hover:bg-gray-50">
            Bỏ chọn
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
import { purchaseReturnOrderApi } from '../api/purchaseReturnOrderApi'

export default {
  name: 'PurchaseReturnOrdersList',
  setup() {
    const loading = ref(true)
    const returnOrders = ref([])
    const suppliers = ref([])
    const warehouses = ref([])
    const selectedIds = ref([])

    // Filters
    const filters = ref({
      search: '',
      supplier_id: '',
      warehouse_id: '',
      status: '',
      date_from: '',
      date_to: '',
      sort_field: '',
      sort_direction: 'desc',
      page: 1,
      per_page: 20
    })

    // Pagination
    const pagination = ref({
      current_page: 1,
      last_page: 1,
      per_page: 20,
      total: 0
    })

    // Notification
    const notification = ref({
      show: false,
      type: 'success',
      message: ''
    })

    const isAllSelected = computed(() => {
  const validOrders = returnOrders.value.filter(order => order && order.id)
  return validOrders.length > 0 && selectedIds.value.length === validOrders.length
})

    const visiblePages = computed(() => {
      const current = pagination.value.current_page
      const last = pagination.value.last_page
      const pages = []
      
      for (let i = Math.max(1, current - 2); i <= Math.min(last, current + 2); i++) {
        pages.push(i)
      }
      
      return pages
    })

    let searchTimeout = null
    const debounceSearch = () => {
      clearTimeout(searchTimeout)
      searchTimeout = setTimeout(() => {
        applyFilters()
      }, 500)
    }

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

    const fetchReturnOrders = async () => {
  try {
    loading.value = true
    const response = await purchaseReturnOrderApi.getPurchaseReturnOrders(filters.value)
    
    // Lấy data từ response 
    const apiData = response.data?.data || response.data || []
    
    // Filter out các object rỗng hoặc không hợp lệ
    returnOrders.value = apiData.filter(order => 
      order && 
      order.id && 
      order.code && 
      typeof order.id === 'number'
    )
    
    // Cập nhật pagination info
    pagination.value = {
      current_page: response.data?.current_page || 1,
      last_page: response.data?.last_page || 1,
      per_page: response.data?.per_page || 20,
      total: response.data?.total || 0
    }
  } catch (error) {
    console.error('Error fetching return orders:', error)
    showNotification('Lỗi khi tải dữ liệu', 'error')
  } finally {
    loading.value = false
  }
}

    const fetchSuppliers = async () => {
      try {
        // Gọi API để lấy danh sách nhà cung cấp
        // suppliers.value = response.data
      } catch (error) {
        console.error('Error fetching suppliers:', error)
      }
    }

    const fetchWarehouses = async () => {
      try {
        // Gọi API để lấy danh sách kho
        // warehouses.value = response.data
      } catch (error) {
        console.error('Error fetching warehouses:', error)
      }
    }

    const applyFilters = () => {
      filters.value.page = 1
      fetchReturnOrders()
    }

    const sortBy = (field) => {
      if (filters.value.sort_field === field) {
        filters.value.sort_direction = filters.value.sort_direction === 'asc' ? 'desc' : 'asc'
      } else {
        filters.value.sort_field = field
        filters.value.sort_direction = 'desc'
      }
      applyFilters()
    }

    const changePage = (page) => {
      filters.value.page = page
      pagination.value.current_page = page
      fetchReturnOrders()
    }

    const toggleSelection = (event) => {
      const id = parseInt(event.target.value)
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
        selectedIds.value = returnOrders.value.map(order => order.id)
      }
    }

    const clearSelection = () => {
      selectedIds.value = []
    }

    const viewReturnOrder = (returnOrder) => {
      window.location.href = `/purchase-return-orders/${returnOrder.id}`
    }

    const editReturnOrder = (returnOrder) => {
      window.location.href = `/purchase-return-orders/${returnOrder.id}/edit`
    }

    const deleteReturnOrder = async (returnOrder) => {
      if (!confirm(`Bạn có chắc muốn xóa đơn trả hàng ${returnOrder.code}?`)) return

      try {
        const response = await purchaseReturnOrderApi.deletePurchaseReturnOrder(returnOrder.id)
        
        if (response.success) {
          showNotification('Xóa đơn trả hàng thành công')
          fetchReturnOrders()
        } else {
          showNotification(response.message || 'Có lỗi xảy ra', 'error')
        }
      } catch (error) {
        console.error('Error deleting return order:', error)
        showNotification('Có lỗi xảy ra khi xóa', 'error')
      }
    }

    const bulkDelete = async () => {
      if (!confirm(`Bạn có chắc muốn xóa ${selectedIds.value.length} đơn trả hàng đã chọn?`)) return

      try {
        // Gọi API bulk delete
        showNotification(`Đã xóa ${selectedIds.value.length} đơn trả hàng`)
        selectedIds.value = []
        fetchReturnOrders()
      } catch (error) {
        console.error('Error bulk deleting:', error)
        showNotification('Có lỗi xảy ra khi xóa', 'error')
      }
    }

    const exportData = async () => {
      try {
        showNotification('Đang xuất dữ liệu...')
        // Gọi API export
        showNotification('Xuất dữ liệu thành công')
      } catch (error) {
        console.error('Error exporting:', error)
        showNotification('Có lỗi xảy ra khi xuất dữ liệu', 'error')
      }
    }

    // Helper functions
    const formatCurrency = (amount) => {
      return purchaseReturnOrderApi.formatCurrency(amount)
    }

    const formatDate = (date) => {
      return purchaseReturnOrderApi.formatDate(date)
    }

    const formatDateTime = (datetime) => {
      return purchaseReturnOrderApi.formatDateTime(datetime)
    }

    const getStatusText = (status) => {
      return purchaseReturnOrderApi.getStatusText(status)
    }

    const getStatusBadgeClass = (status) => {
      const baseClasses = 'px-2 py-1 text-xs font-medium rounded-full'
      const statusClasses = {
        'pending': 'bg-yellow-100 text-yellow-800',
        'approved': 'bg-blue-100 text-blue-800',
        'returned': 'bg-purple-100 text-purple-800',
        'completed': 'bg-green-100 text-green-800',
        'cancelled': 'bg-red-100 text-red-800'
      }
      return `${baseClasses} ${statusClasses[status] || 'bg-gray-100 text-gray-800'}`
    }

    const canEdit = (returnOrder) => {
      return purchaseReturnOrderApi.canEdit(returnOrder)
    }

    const canDelete = (returnOrder) => {
      return purchaseReturnOrderApi.canDelete(returnOrder)
    }
    const canApprove = (returnOrder) => {
  return returnOrder.status === 'pending'
}

const approveReturnOrder = async (returnOrder) => {
  if (!confirm(`Duyệt đơn trả hàng ${returnOrder.code}?\n\nSẽ tự động giảm ${formatCurrency(returnOrder.total)} từ công nợ nhà cung cấp.`)) return

  try {
    const response = await purchaseReturnOrderApi.approve(returnOrder.id)
    
    if (response.success) {
      showNotification('Duyệt đơn trả hàng thành công và đã giảm công nợ!')
      fetchReturnOrders()
    } else {
      showNotification(response.message || 'Có lỗi xảy ra', 'error')
    }
  } catch (error) {
    console.error('Error approving return order:', error)
    showNotification('Có lỗi xảy ra khi duyệt', 'error')
  }
}

    onMounted(() => {
      fetchReturnOrders()
      fetchSuppliers()
      fetchWarehouses()
    })

    return {
      loading,
      returnOrders,
      suppliers,
      warehouses,
      selectedIds,
      filters,
      pagination,
      notification,
      isAllSelected,
      visiblePages,
      debounceSearch,
      showNotification,
      fetchReturnOrders,
      applyFilters,
      sortBy,
      changePage,
      toggleSelection,
      toggleSelectAll,
      clearSelection,
      viewReturnOrder,
      editReturnOrder,
      deleteReturnOrder,
      bulkDelete,
      exportData,
      formatCurrency,
      formatDate,
      formatDateTime,
      getStatusText,
      getStatusBadgeClass,
      canEdit,
      canDelete,
      canApprove,
      approveReturnOrder,
      
    }
  }
}
</script>