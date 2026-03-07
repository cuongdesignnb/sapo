<template>
  <div class="bg-white min-h-screen">
    <!-- Header -->
    <div class="p-6 border-b">
      <div class="flex justify-between items-center">
        <div>
          <h1 class="text-2xl font-semibold text-gray-900">Danh sách phiếu nhập kho</h1>
          <nav class="mt-1">
            <ol class="flex items-center space-x-2 text-sm text-gray-500">
              <li><a href="/dashboard" class="hover:text-gray-700">Trang chủ</a></li>
              <li>•</li>
              <li class="text-gray-900">Phiếu nhập kho</li>
            </ol>
          </nav>
        </div>
        <div class="flex space-x-3">
          <button @click="exportData" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
            📥 Xuất file
          </button>
          <a href="/purchase-receipts/create" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
            ➕ Tạo phiếu nhập
          </a>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="p-6 border-b bg-gray-50">
      <div class="bg-white rounded-lg border border-gray-200 p-6">
        <div class="grid grid-cols-4 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Tìm kiếm</label>
            <input 
              v-model="filters.search" 
              type="text" 
              placeholder="Tìm mã phiếu, mã đơn hàng..."
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500"
              @input="debouncedSearch"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Kho</label>
            <select v-model="filters.warehouse_id" @change="applyFilters" 
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500">
              <option value="">Tất cả kho</option>
              <option v-for="warehouse in warehouses" :key="warehouse.id" :value="warehouse.id">
                {{ warehouse.name }}
              </option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Trạng thái</label>
            <select v-model="filters.status" @change="applyFilters"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500">
              <option value="">Tất cả trạng thái</option>
              <option value="pending">Chờ xử lý</option>
              <option value="partial">Nhập một phần</option>
              <option value="completed">Hoàn thành</option>
              <option value="cancelled">Đã hủy</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Ngày nhập</label>
            <div class="flex space-x-2">
              <input v-model="filters.date_from" type="date" @change="applyFilters"
                     class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500">
              <span class="flex items-center text-gray-500">đến</span>
              <input v-model="filters.date_to" type="date" @change="applyFilters"
                     class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500">
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Stats Cards -->
    <div class="p-6 border-b">
      <div class="grid grid-cols-4 gap-6">
        <div class="bg-blue-600 text-white rounded-lg p-6">
          <div class="flex justify-between items-center">
            <div>
              <div class="text-2xl font-bold">{{ stats.total_receipts || 0 }}</div>
              <div class="text-blue-100">Tổng phiếu nhập</div>
            </div>
            <div class="text-blue-200 text-4xl opacity-50">🧾</div>
          </div>
        </div>
        <div class="bg-green-600 text-white rounded-lg p-6">
          <div class="flex justify-between items-center">
            <div>
              <div class="text-2xl font-bold">{{ stats.completed_receipts || 0 }}</div>
              <div class="text-green-100">Đã hoàn thành</div>
            </div>
            <div class="text-green-200 text-4xl opacity-50">✅</div>
          </div>
        </div>
        <div class="bg-yellow-600 text-white rounded-lg p-6">
          <div class="flex justify-between items-center">
            <div>
              <div class="text-2xl font-bold">{{ stats.pending_receipts || 0 }}</div>
              <div class="text-yellow-100">Chờ xử lý</div>
            </div>
            <div class="text-yellow-200 text-4xl opacity-50">⏰</div>
          </div>
        </div>
        <div class="bg-purple-600 text-white rounded-lg p-6">
          <div class="flex justify-between items-center">
            <div>
              <div class="text-2xl font-bold">{{ formatCurrency(stats.total_amount || 0) }}</div>
              <div class="text-purple-100">Tổng giá trị</div>
            </div>
            <div class="text-purple-200 text-4xl opacity-50">💰</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Table -->
    <div class="p-6">
      <div class="bg-white rounded-lg border border-gray-200">
        <div v-if="loading" class="flex justify-center items-center py-12">
          <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
          <span class="ml-2 text-gray-600">Đang tải...</span>
        </div>

        <div v-else-if="receipts.length === 0" class="text-center py-12">
          <div class="text-6xl mb-4">📋</div>
          <h3 class="text-lg font-medium text-gray-900 mb-2">Không có phiếu nhập kho nào</h3>
          <p class="text-gray-500 mb-4">Hãy tạo phiếu nhập kho đầu tiên của bạn</p>
          <a href="/purchase-receipts/create" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
            ➕ Tạo phiếu nhập
          </a>
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mã phiếu nhập</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Đơn nhập hàng</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nhà cung cấp</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kho</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ngày nhập</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Trạng thái</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tổng tiền</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Người nhập</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-36">Thao tác</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="receipt in receipts" :key="receipt.id" class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="font-medium text-gray-900">{{ receipt.code }}</div>
                  <div class="text-sm text-gray-500">{{ formatDateTime(receipt.created_at) }}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="font-medium text-gray-900">{{ receipt.purchase_order?.code }}</div>
                  <div class="text-sm text-gray-500">{{ receipt.purchase_order?.supplier?.name }}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div>{{ receipt.purchase_order?.supplier?.name }}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div>{{ receipt.warehouse?.name }}</div>
                  <div class="text-sm text-gray-500">{{ receipt.warehouse?.code }}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div>{{ formatDateTime(receipt.received_at) }}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span :class="getStatusClass(receipt.status)" class="inline-block px-2 py-1 text-xs font-medium rounded-full">
                    {{ getStatusText(receipt.status) }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="font-medium">{{ formatCurrency(receipt.total_amount) }}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div>{{ receipt.receiver?.name }}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="flex space-x-2">
                    <button @click="viewReceipt(receipt.id)" 
                            class="p-2 text-blue-600 hover:bg-blue-50 rounded" title="Xem chi tiết">
                      👁️
                    </button>
                    <button @click="printReceipt(receipt.id)" 
                            class="p-2 text-gray-600 hover:bg-gray-50 rounded" title="In phiếu">
                      🖨️
                    </button>
                    <button v-if="canEdit(receipt)" @click="editReceipt(receipt.id)" 
                            class="p-2 text-green-600 hover:bg-green-50 rounded" title="Sửa">
                      ✏️
                    </button>
                    <button v-if="canDelete(receipt)" @click="deleteReceipt(receipt.id)" 
                            class="p-2 text-red-600 hover:bg-red-50 rounded" title="Xóa">
                      🗑️
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="pagination.total > 0" class="px-6 py-4 border-t flex justify-between items-center">
          <div class="text-sm text-gray-700">
            Hiển thị {{ pagination.from }} - {{ pagination.to }} của {{ pagination.total }} kết quả
          </div>
          <div class="flex space-x-1">
            <button @click="goToPage(pagination.current_page - 1)" 
                    :disabled="pagination.current_page <= 1"
                    class="px-3 py-1 border rounded text-sm hover:bg-gray-50 disabled:opacity-50">
              ◀
            </button>
            <button v-for="page in visiblePages" :key="page" @click="goToPage(page)"
                    :class="page === pagination.current_page ? 'bg-blue-500 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'"
                    class="px-3 py-1 border rounded text-sm">
              {{ page }}
            </button>
            <button @click="goToPage(pagination.current_page + 1)" 
                    :disabled="pagination.current_page >= pagination.last_page"
                    class="px-3 py-1 border rounded text-sm hover:bg-gray-50 disabled:opacity-50">
              ▶
            </button>
          </div>
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
import { ref, reactive, computed, onMounted } from 'vue'
import purchaseReceiptApi from '../api/purchaseReceiptApi'
import warehouseApi from '../api/warehouseApi'

// Debounce helper function
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

export default {
  name: 'PurchaseReceiptsList',
  setup() {
    const loading = ref(false)
    const receipts = ref([])
    const warehouses = ref([])
    const stats = ref({})
    
    const notification = ref({
      show: false,
      type: 'success',
      message: ''
    })
    
    const filters = reactive({
      search: '',
      warehouse_id: '',
      status: '',
      date_from: '',
      date_to: ''
    })

    const pagination = reactive({
      current_page: 1,
      last_page: 1,
      per_page: 20,
      total: 0,
      from: 0,
      to: 0
    })

    const visiblePages = computed(() => {
      const pages = []
      const start = Math.max(1, pagination.current_page - 2)
      const end = Math.min(pagination.last_page, pagination.current_page + 2)
      
      for (let i = start; i <= end; i++) {
        pages.push(i)
      }
      
      return pages
    })

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

    const fetchReceipts = async (page = 1) => {
      loading.value = true
      try {
        const params = {
          page: page,
          per_page: pagination.per_page,
          ...Object.fromEntries(Object.entries(filters).filter(([_, v]) => v !== ''))
        }

        const response = await purchaseReceiptApi.getAll(params)
        if (response.success) {
          receipts.value = response.data
          Object.assign(pagination, response.pagination)
        }
      } catch (error) {
        console.error('Error fetching receipts:', error)
        showNotification('Có lỗi xảy ra khi tải dữ liệu', 'error')
      } finally {
        loading.value = false
      }
    }

    const fetchWarehouses = async () => {
      try {
        const response = await warehouseApi.getWarehouses()
        if (response.success) {
          warehouses.value = response.data
        }
      } catch (error) {
        console.error('Error fetching warehouses:', error)
      }
    }

    const fetchStats = async () => {
      try {
        //const response = await purchaseReceiptApi.getStats()
        //if (response.success) {
          //stats.value = response.data
        //}
         stats.value = {}
      } catch (error) {
        console.error('Error fetching stats:', error)
      }
    }

    const debouncedSearch = debounce(() => {
      fetchReceipts(1)
    }, 500)

    const applyFilters = () => {
      fetchReceipts(1)
    }

    const goToPage = (page) => {
      if (page >= 1 && page <= pagination.last_page) {
        fetchReceipts(page)
      }
    }

    const viewReceipt = (id) => {
      window.location.href = `/purchase-receipts/${id}`
    }

    const editReceipt = (id) => {
      window.location.href = `/purchase-receipts/${id}/edit`
    }

    const printReceipt = (id) => {
      window.open(`/purchase-receipts/${id}/print`, '_blank')
    }

    const deleteReceipt = async (id) => {
      if (!confirm('Bạn có chắc chắn muốn xóa phiếu nhập này?')) return

      try {
        const response = await purchaseReceiptApi.delete(id)
        if (response.success) {
          showNotification('Xóa phiếu nhập thành công')
          fetchReceipts(pagination.current_page)
          fetchStats()
        }
      } catch (error) {
        console.error('Error deleting receipt:', error)
        showNotification('Có lỗi xảy ra khi xóa phiếu nhập', 'error')
      }
    }

    const exportData = async () => {
      try {
        const params = Object.fromEntries(Object.entries(filters).filter(([_, v]) => v !== ''))
        await purchaseReceiptApi.export(params)
        showNotification('Xuất dữ liệu thành công')
      } catch (error) {
        console.error('Error exporting data:', error)
        showNotification('Có lỗi xảy ra khi xuất dữ liệu', 'error')
      }
    }

    const canEdit = (receipt) => {
      return ['pending', 'partial'].includes(receipt.status)
    }

    const canDelete = (receipt) => {
      return ['pending', 'cancelled'].includes(receipt.status)
    }

    const getStatusClass = (status) => {
      const classes = {
        'pending': 'bg-yellow-100 text-yellow-800',
        'partial': 'bg-blue-100 text-blue-800',
        'completed': 'bg-green-100 text-green-800',
        'cancelled': 'bg-red-100 text-red-800'
      }
      return classes[status] || 'bg-gray-100 text-gray-800'
    }

    const getStatusText = (status) => {
      const texts = {
        'pending': 'Chờ xử lý',
        'partial': 'Nhập một phần',
        'completed': 'Hoàn thành',
        'cancelled': 'Đã hủy'
      }
      return texts[status] || status
    }

    const formatDateTime = (date) => {
      if (!date) return ''
      return new Date(date).toLocaleDateString('vi-VN', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
      })
    }

    const formatCurrency = (amount) => {
      if (!amount) return '0 ₫'
      return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND'
      }).format(amount)
    }

    onMounted(() => {
      fetchReceipts()
      fetchWarehouses()
      //fetchStats()
    })

    return {
      loading,
      receipts,
      warehouses,
      stats,
      filters,
      pagination,
      notification,
      visiblePages,
      debouncedSearch,
      applyFilters,
      goToPage,
      viewReceipt,
      editReceipt,
      printReceipt,
      deleteReceipt,
      exportData,
      canEdit,
      canDelete,
      getStatusClass,
      getStatusText,
      formatDateTime,
      formatCurrency
    }
  }
}
</script>