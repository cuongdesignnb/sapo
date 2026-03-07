<template>
  <div class="bg-white min-h-screen">
    <!-- Header -->
    <div class="p-6 border-b bg-white sticky top-0 z-10">
      <div class="flex justify-between items-center">
        <div>
          <h1 class="text-2xl font-semibold text-gray-900">🧾 Danh sách phiếu trả hàng</h1>
          <div class="text-sm text-gray-500 mt-1">
            <span>Trang chủ</span> / <span class="text-gray-900">Phiếu trả hàng cho nhà cung cấp</span>
          </div>
        </div>
        <div class="flex space-x-3">
          <button @click="exportData" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
            📊 Xuất Excel
          </button>
          <a href="/purchase-return-receipts/create" class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600">
            ➕ Tạo phiếu trả hàng
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
            placeholder="Mã phiếu, nhà cung cấp..." 
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
                  Mã phiếu trả hàng
                  <span v-if="filters.sort_field === 'code'" class="ml-1">
                    {{ filters.sort_direction === 'asc' ? '↑' : '↓' }}
                  </span>
                </th>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Đơn trả hàng
                </th>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Nhà cung cấp
                </th>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Kho trả hàng
                </th>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100" @click="sortBy('returned_at')">
                  Ngày trả
                  <span v-if="filters.sort_field === 'returned_at'" class="ml-1">
                    {{ filters.sort_direction === 'asc' ? '↑' : '↓' }}
                  </span>
                </th>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Trạng thái
                </th>
                <th class="text-right px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100" @click="sortBy('total_amount')">
                  Tổng tiền
                  <span v-if="filters.sort_field === 'total_amount'" class="ml-1">
                    {{ filters.sort_direction === 'asc' ? '↑' : '↓' }}
                  </span>
                </th>
                <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Người trả
                </th>
                <th class="text-center px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Thao tác
                </th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="receipt in receipts" :key="receipt.id" class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap">
                  <input type="checkbox" :value="receipt.id" @change="toggleSelection" :checked="selectedIds.includes(receipt.id)" class="rounded border-gray-300">
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="font-medium text-gray-900">{{ receipt.code }}</div>
                  <div class="text-sm text-gray-500">{{ formatDateTime(receipt.created_at) }}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div v-if="receipt.purchase_return_order" class="font-medium text-blue-600">
                    {{ receipt.purchase_return_order.code }}
                  </div>
                  <div v-else class="text-sm text-gray-400">Không có</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="font-medium">{{ receipt.supplier?.name }}</div>
                  <div class="text-sm text-gray-500">{{ receipt.supplier?.phone }}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="font-medium">{{ receipt.warehouse?.name }}</div>
                  <div class="text-sm text-gray-500">{{ receipt.warehouse?.code }}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ formatDateTime(receipt.returned_at) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span :class="getStatusBadgeClass(receipt.status)" class="px-2 py-1 text-xs font-medium rounded-full">
                    {{ getStatusText(receipt.status) }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium text-gray-900">
                  {{ formatCurrency(receipt.total_amount) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ receipt.returned_by?.name }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                  <div class="flex justify-center space-x-2">
                    <button @click="viewReceipt(receipt)" class="text-blue-600 hover:text-blue-800" title="Xem chi tiết">
                      👁️
                    </button>
                    <button v-if="canApprove(receipt)" @click="approveReceiptWithDebt(receipt)" class="text-green-600 hover:text-green-800" title="Duyệt">
                      ✅
                    </button>
                    <button v-if="canCancel(receipt)" @click="cancelReceipt(receipt)" class="text-red-600 hover:text-red-800" title="Hủy">
                      ❌
                    </button>
                    <button @click="printReceipt(receipt)" class="text-gray-600 hover:text-gray-800" title="In phiếu">
                      🖨️
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Empty state -->
        <div v-if="receipts.length === 0" class="text-center py-12">
          <div class="text-6xl mb-4">🧾</div>
          <h3 class="text-lg font-medium text-gray-900 mb-2">Chưa có phiếu trả hàng nào</h3>
          <p class="text-gray-500 mb-4">Tạo phiếu trả hàng đầu tiên của bạn.</p>
          <a href="/purchase-return-receipts/create" class="inline-flex items-center px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600">
            ➕ Tạo phiếu trả hàng
          </a>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="receipts.length > 0" class="flex items-center justify-between mt-6">
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
          <button @click="bulkApprove" class="px-3 py-1 bg-green-500 text-white rounded text-sm hover:bg-green-600">
            ✅ Duyệt tất cả
          </button>
          <button @click="bulkCancel" class="px-3 py-1 bg-red-500 text-white rounded text-sm hover:bg-red-600">
            ❌ Hủy tất cả
          </button>
          <button @click="clearSelection" class="px-3 py-1 border border-gray-300 rounded text-sm hover:bg-gray-50">
            Bỏ chọn
          </button>
        </div>
      </div>
    </div>

    <!-- Status Update Modal -->
    <div v-if="showStatusModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg max-w-md w-full mx-4">
        <div class="px-6 py-4 border-b border-gray-200">
          <h3 class="text-lg font-medium text-gray-900">Cập nhật trạng thái</h3>
        </div>
        <div class="p-6">
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">Trạng thái mới</label>
            <select v-model="statusForm.status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
              <option value="pending">Chờ duyệt</option>
              <option value="approved">Đã duyệt</option>
              <option value="returned">Đã trả hàng</option>
              <option value="completed">Hoàn tất</option>
              <option value="cancelled">Đã hủy</option>
            </select>
          </div>
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">Lý do</label>
            <textarea v-model="statusForm.reason" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Ghi chú về việc thay đổi trạng thái..."></textarea>
          </div>
        </div>
        <div class="px-6 py-4 border-t border-gray-200 flex justify-end space-x-3">
          <button @click="showStatusModal = false" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
            Hủy
          </button>
          <button @click="updateStatus" class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600">
            Cập nhật
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
import { purchaseReturnReceiptApi } from '../api/purchaseReturnReceiptApi'

export default {
  name: 'PurchaseReturnReceiptsList',
  setup() {
    const loading = ref(true)
    const receipts = ref([])
    const suppliers = ref([])
    const warehouses = ref([])
    const selectedIds = ref([])
    const showStatusModal = ref(false)
    const currentReceipt = ref(null)

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
      total: 0,
      from: 0,
      to: 0
    })

    // Status form
    const statusForm = ref({
      status: '',
      reason: ''
    })

    // Notification
    const notification = ref({
      show: false,
      type: 'success',
      message: ''
    })

    const isAllSelected = computed(() => {
      return receipts.value.length > 0 && selectedIds.value.length === receipts.value.length
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

    // ✅ SỬA: API calls thực tế
    const fetchReceipts = async () => {
      try {
        loading.value = true
        console.log('🔍 Fetching receipts with filters:', filters.value)
        
        const response = await purchaseReturnReceiptApi.getPurchaseReturnReceipts(filters.value)
        console.log('📋 Receipts response:', response)
        
        if (response.success) {
          receipts.value = response.data || []
          pagination.value = response.pagination || {
            current_page: 1,
            last_page: 1,
            per_page: 20,
            total: 0,
            from: 0,
            to: 0
          }
        } else {
          receipts.value = []
          console.error('API error:', response.message)
        }
        
        console.log('✅ Receipts loaded:', receipts.value.length, 'items')
      } catch (error) {
        console.error('❌ Error fetching receipts:', error)
        receipts.value = []
        showNotification('Lỗi khi tải dữ liệu', 'error')
      } finally {
        loading.value = false
      }
    }

    const fetchSuppliers = async () => {
      try {
        console.log('🔍 Fetching suppliers...')
        const response = await purchaseReturnReceiptApi.getSuppliers()
        
        if (response.success) {
          suppliers.value = response.data?.data || response.data || []
        } else {
          suppliers.value = []
        }
        
        console.log('✅ Suppliers loaded:', suppliers.value.length, 'items')
      } catch (error) {
        console.error('❌ Error fetching suppliers:', error)
        suppliers.value = []
      }
    }

    const fetchWarehouses = async () => {
      try {
        console.log('🔍 Fetching warehouses...')
        const response = await purchaseReturnReceiptApi.getWarehouses()
        
        if (response.success) {
          warehouses.value = response.data?.data || response.data || []
        } else {
          warehouses.value = []
        }
        
        console.log('✅ Warehouses loaded:', warehouses.value.length, 'items')
      } catch (error) {
        console.error('❌ Error fetching warehouses:', error)
        warehouses.value = []
      }
    }

    const applyFilters = () => {
      console.log('🔍 Applying filters:', filters.value)
      filters.value.page = 1
      fetchReceipts()
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
      fetchReceipts()
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
        selectedIds.value = receipts.value.map(receipt => receipt.id)
      }
    }

    const clearSelection = () => {
      selectedIds.value = []
    }

    const viewReceipt = (receipt) => {
      window.location.href = `/purchase-return-receipts/${receipt.id}`
    }

    const approveReceiptWithDebt = async (receipt) => {
      if (!confirm(`Duyệt phiếu trả hàng ${receipt.code}?\n\nSẽ tự động giảm ${formatCurrency(receipt.total_amount)} từ công nợ nhà cung cấp.`)) return

      try {
        const response = await purchaseReturnReceiptApi.approve(receipt.id)
        
        if (response.success) {
          showNotification('Duyệt phiếu trả hàng thành công và đã giảm công nợ!')
          fetchReceipts()
        } else {
          showNotification(response.message || 'Có lỗi xảy ra', 'error')
        }
      } catch (error) {
        console.error('Error approving receipt:', error)
        showNotification('Có lỗi xảy ra khi duyệt', 'error')
      }
    }

    const cancelReceipt = async (receipt) => {
      if (!confirm(`Hủy phiếu trả hàng ${receipt.code}?`)) return

      try {
        const response = await purchaseReturnReceiptApi.cancel(receipt.id, {
          reason: 'Hủy từ danh sách'
        })
        
        if (response.success) {
          showNotification('Hủy phiếu trả hàng thành công!')
          fetchReceipts()
        } else {
          showNotification(response.message || 'Có lỗi xảy ra', 'error')
        }
      } catch (error) {
        console.error('Error cancelling receipt:', error)
        showNotification('Có lỗi xảy ra khi hủy', 'error')
      }
    }

    const printReceipt = (receipt) => {
      window.open(`/purchase-return-receipts/${receipt.id}/print`, '_blank')
    }

    const bulkApprove = async () => {
      if (!confirm(`Bạn có chắc muốn duyệt ${selectedIds.value.length} phiếu trả hàng đã chọn?`)) return

      try {
        for (const id of selectedIds.value) {
          await purchaseReturnReceiptApi.approve(id)
        }
        
        showNotification(`Đã duyệt ${selectedIds.value.length} phiếu trả hàng`)
        selectedIds.value = []
        fetchReceipts()
      } catch (error) {
        console.error('Error bulk approving:', error)
        showNotification('Có lỗi xảy ra khi duyệt', 'error')
      }
    }

    const bulkCancel = async () => {
      if (!confirm(`Bạn có chắc muốn hủy ${selectedIds.value.length} phiếu trả hàng đã chọn?`)) return

      try {
        for (const id of selectedIds.value) {
          await purchaseReturnReceiptApi.cancel(id, { reason: 'Bulk cancel' })
        }
        
        showNotification(`Đã hủy ${selectedIds.value.length} phiếu trả hàng`)
        selectedIds.value = []
        fetchReceipts()
      } catch (error) {
        console.error('Error bulk cancelling:', error)
        showNotification('Có lỗi xảy ra khi hủy', 'error')
      }
    }

    const exportData = async () => {
      try {
        showNotification('Đang xuất dữ liệu...')
        
        const response = await purchaseReturnReceiptApi.exportPurchaseReturnReceipts(filters.value)
        
        // Create download link
        const blob = new Blob([response.data], { 
          type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' 
        })
        const url = window.URL.createObjectURL(blob)
        const link = document.createElement('a')
        link.href = url
        link.setAttribute('download', `purchase-return-receipts-${new Date().toISOString().split('T')[0]}.xlsx`)
        document.body.appendChild(link)
        link.click()
        link.remove()
        window.URL.revokeObjectURL(url)
        
        showNotification('Xuất dữ liệu thành công')
      } catch (error) {
        console.error('Error exporting:', error)
        showNotification('Có lỗi xảy ra khi xuất dữ liệu', 'error')
      }
    }

    // Helper functions
    const formatCurrency = (amount) => {
      return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND'
      }).format(amount || 0)
    }

    const formatDate = (date) => {
      if (!date) return ''
      return new Date(date).toLocaleDateString('vi-VN')
    }

    const formatDateTime = (datetime) => {
      if (!datetime) return ''
      return new Date(datetime).toLocaleString('vi-VN')
    }

    const getStatusText = (status) => {
      const texts = {
        'draft': 'Nháp',
        'pending': 'Chờ duyệt',
        'approved': 'Đã duyệt',
        'returned': 'Đã trả hàng',
        'completed': 'Hoàn tất',
        'cancelled': 'Đã hủy'
      }
      return texts[status] || status
    }

    const getStatusBadgeClass = (status) => {
      const baseClasses = 'px-2 py-1 text-xs font-medium rounded-full'
      const statusClasses = {
        'draft': 'bg-gray-100 text-gray-800',
        'pending': 'bg-yellow-100 text-yellow-800',
        'approved': 'bg-blue-100 text-blue-800',
        'returned': 'bg-purple-100 text-purple-800',
        'completed': 'bg-green-100 text-green-800',
        'cancelled': 'bg-red-100 text-red-800'
      }
      return `${baseClasses} ${statusClasses[status] || 'bg-gray-100 text-gray-800'}`
    }

    const canApprove = (receipt) => {
      return receipt.status === 'pending'
    }

    const canCancel = (receipt) => {
      return ['pending', 'approved'].includes(receipt.status)
    }

    // ✅ SỬA: onMounted thực tế load data
    onMounted(async () => {
      console.log('🚀 Component mounted, loading data...')
      
      // Test API trực tiếp
      try {
        const testResponse = await fetch('/api/purchase-return-receipts', {
          headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            'Accept': 'application/json'
          }
        })
        const testData = await testResponse.json()
        console.log('🧪 Direct API test:', testData)
      } catch (error) {
        console.error('🧪 Direct API test failed:', error)
      }
      
      // Load data
      await Promise.all([
        fetchReceipts(),
        fetchSuppliers(),
        fetchWarehouses()
      ])
    })

    return {
      loading,
      receipts,
      suppliers,
      warehouses,
      selectedIds,
      showStatusModal,
      currentReceipt,
      filters,
      pagination,
      statusForm,
      notification,
      isAllSelected,
      visiblePages,
      debounceSearch,
      showNotification,
      fetchReceipts,
      applyFilters,
      sortBy,
      changePage,
      toggleSelection,
      toggleSelectAll,
      clearSelection,
      viewReceipt,
      cancelReceipt,
      printReceipt,
      bulkApprove,
      bulkCancel,
      exportData,
      formatCurrency,
      formatDate,
      formatDateTime,
      getStatusText,
      getStatusBadgeClass,
      canApprove,
      canCancel,
      approveReceiptWithDebt
    }
  }
}
</script>