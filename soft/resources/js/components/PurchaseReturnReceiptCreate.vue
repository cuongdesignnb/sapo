<template>
  <div class="bg-gray-50 min-h-screen">
    <!-- Header -->
    <div class="bg-white border-b border-gray-200 px-6 py-4">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-semibold text-gray-900">🧾 Tạo phiếu trả hàng cho nhà cung cấp</h1>
          <nav class="text-sm text-gray-500 mt-1">
            <a href="/" class="hover:text-gray-700">Trang chủ</a>
            <span class="mx-2">/</span>
            <a href="/purchase-return-receipts" class="hover:text-gray-700">Phiếu trả hàng</a>
            <span class="mx-2">/</span>
            <span class="text-gray-900">Tạo mới</span>
          </nav>
        </div>
        <div class="flex space-x-3">
          <button 
            @click="saveDraft" 
            :disabled="loading || !canSave"
            class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 disabled:opacity-50"
          >
            💾 Lưu nháp
          </button>
          <button 
            @click="saveAndSubmit" 
            :disabled="loading || !canSubmit"
            class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 disabled:opacity-50"
          >
            ✅ Lưu và gửi duyệt
          </button>
        </div>
      </div>
    </div>

    <div class="flex">
      <!-- Main Content -->
      <div class="flex-1 p-6">
        <!-- Basic Information -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6">
          <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-medium text-gray-900">📋 Thông tin cơ bản</h2>
          </div>
          <div class="p-6">
            <div class="grid grid-cols-2 gap-6">
              <!-- Return Order Selection -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Đơn trả hàng <span class="text-red-500">*</span>
                </label>
                <div class="relative">
                  <input
                    v-model="returnOrderSearch"
                    @focus="showReturnOrderDropdown = true"
                    @input="searchReturnOrders"
                    type="text"
                    placeholder="Tìm đơn trả hàng..."
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                  >
                  <div 
                    v-if="showReturnOrderDropdown && filteredReturnOrders.length > 0" 
                    class="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-y-auto"
                  >
                    <div 
                      v-for="order in filteredReturnOrders" 
                      :key="order.id"
                      @click="selectReturnOrder(order)"
                      class="px-3 py-2 hover:bg-gray-50 cursor-pointer border-b border-gray-100 last:border-b-0"
                    >
                      <div class="font-medium text-gray-900">{{ order.code }}</div>
                      <div class="text-sm text-gray-500">
                        {{ order.supplier?.name }} - {{ formatCurrency(order.total) }}
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Warehouse -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Kho trả hàng <span class="text-red-500">*</span>
                </label>
                <div class="relative">
                  <input
                    v-model="warehouseSearch"
                    @focus="showWarehouseDropdown = true"
                    @input="searchWarehouses"
                    type="text"
                    placeholder="Chọn kho..."
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                  >
                  <div 
                    v-if="showWarehouseDropdown && filteredWarehouses.length > 0" 
                    class="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-y-auto"
                  >
                    <div 
                      v-for="warehouse in filteredWarehouses" 
                      :key="warehouse.id"
                      @click="selectWarehouse(warehouse)"
                      class="px-3 py-2 hover:bg-gray-50 cursor-pointer border-b border-gray-100 last:border-b-0"
                    >
                      <div class="font-medium text-gray-900">{{ warehouse.name }}</div>
                      <div class="text-sm text-gray-500">{{ warehouse.address }}</div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Return Date -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Ngày trả hàng <span class="text-red-500">*</span>
                </label>
                <input
                  v-model="form.returned_at"
                  type="date"
                  :max="todayDate"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
              </div>

              <!-- Note -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Ghi chú
                </label>
                <textarea
                  v-model="form.note"
                  rows="3"
                  placeholder="Ghi chú về phiếu trả hàng..."
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                ></textarea>
              </div>
            </div>
          </div>
        </div>

        <!-- Selected Return Order Info -->
        <div v-if="selectedReturnOrder" class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6">
          <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-medium text-gray-900">📦 Thông tin đơn trả hàng</h2>
          </div>
          <div class="p-6">
            <div class="grid grid-cols-3 gap-6 mb-6">
              <div>
                <div class="text-sm text-gray-600">Mã đơn trả hàng</div>
                <div class="font-medium text-gray-900">{{ selectedReturnOrder.code }}</div>
              </div>
              <div>
                <div class="text-sm text-gray-600">Nhà cung cấp</div>
                <div class="font-medium text-gray-900">{{ selectedReturnOrder.supplier?.name }}</div>
              </div>
              <div>
                <div class="text-sm text-gray-600">Tổng tiền</div>
                <div class="font-medium text-blue-600">{{ formatCurrency(selectedReturnOrder.total) }}</div>
              </div>
            </div>

            <!-- Items List -->
            <div class="border border-gray-200 rounded-lg overflow-hidden">
              <table class="w-full">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="text-left px-4 py-3 text-sm font-medium text-gray-500">Sản phẩm</th>
                    <th class="text-center px-4 py-3 text-sm font-medium text-gray-500">SL trả</th>
                    <th class="text-center px-4 py-3 text-sm font-medium text-gray-500">Đơn giá</th>
                    <th class="text-right px-4 py-3 text-sm font-medium text-gray-500">Thành tiền</th>
                    <th class="text-center px-4 py-3 text-sm font-medium text-gray-500">Trạng thái</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                  <tr v-for="item in selectedReturnOrder.items" :key="item.id">
                    <td class="px-4 py-3">
                      <div class="font-medium text-gray-900">{{ item.product?.name }}</div>
                      <div class="text-sm text-gray-500">{{ item.product?.sku }}</div>
                    </td>
                    <td class="px-4 py-3 text-center">{{ item.quantity }}</td>
                    <td class="px-4 py-3 text-center">{{ formatCurrency(item.price) }}</td>
                    <td class="px-4 py-3 text-right font-medium">{{ formatCurrency(item.total) }}</td>
                    <td class="px-4 py-3 text-center">
                      <span class="px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800">
                        Sẵn sàng trả
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Empty State -->
        <div v-else class="bg-white rounded-lg shadow-sm border border-gray-200 p-12 text-center">
          <div class="text-6xl mb-4">📦</div>
          <h3 class="text-lg font-medium text-gray-900 mb-2">Chọn đơn trả hàng</h3>
          <p class="text-gray-500">Vui lòng chọn đơn trả hàng đã được duyệt để tạo phiếu trả hàng.</p>
        </div>
      </div>

      <!-- Sidebar -->
      <div class="w-80 p-6 bg-white border-l border-gray-200">
        <!-- Summary -->
        <div class="bg-gray-50 rounded-lg p-4 mb-6">
          <h3 class="text-sm font-medium text-gray-900 mb-3">📊 Tóm tắt phiếu trả</h3>
          
          <div class="space-y-2 text-sm">
            <div class="flex justify-between">
              <span class="text-gray-600">Số lượng sản phẩm:</span>
              <span class="font-medium">{{ totalItems }} mặt hàng</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-600">Tổng số lượng:</span>
              <span class="font-medium">{{ totalQuantity }}</span>
            </div>
            <div class="border-t pt-2 mt-2">
              <div class="flex justify-between text-base font-medium">
                <span>Tổng tiền trả:</span>
                <span class="text-red-600">{{ formatCurrency(totalAmount) }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Validation Status -->
        <div class="bg-white border border-gray-200 rounded-lg p-4 mb-6">
          <h3 class="text-sm font-medium text-gray-900 mb-3">✅ Trạng thái kiểm tra</h3>
          
          <div class="space-y-2">
            <div class="flex items-center text-sm">
              <div class="w-3 h-3 rounded-full mr-2" :class="form.purchase_return_order_id ? 'bg-green-500' : 'bg-red-500'"></div>
              <span :class="form.purchase_return_order_id ? 'text-green-700' : 'text-red-700'">
                {{ form.purchase_return_order_id ? 'Đã chọn đơn trả hàng' : 'Chưa chọn đơn trả hàng' }}
              </span>
            </div>
            <div class="flex items-center text-sm">
              <div class="w-3 h-3 rounded-full mr-2" :class="form.warehouse_id ? 'bg-green-500' : 'bg-red-500'"></div>
              <span :class="form.warehouse_id ? 'text-green-700' : 'text-red-700'">
                {{ form.warehouse_id ? 'Đã chọn kho' : 'Chưa chọn kho' }}
              </span>
            </div>
            <div class="flex items-center text-sm">
              <div class="w-3 h-3 rounded-full mr-2" :class="form.returned_at ? 'bg-green-500' : 'bg-red-500'"></div>
              <span :class="form.returned_at ? 'text-green-700' : 'text-red-700'">
                {{ form.returned_at ? 'Đã chọn ngày trả' : 'Chưa chọn ngày trả' }}
              </span>
            </div>
          </div>
        </div>

        <!-- Quick Actions -->
        <div class="bg-white border border-gray-200 rounded-lg p-4">
          <h3 class="text-sm font-medium text-gray-900 mb-3">⚡ Thao tác nhanh</h3>
          
          <div class="space-y-2">
            <button 
              @click="resetForm" 
              class="w-full px-3 py-2 text-sm text-gray-700 border border-gray-300 rounded hover:bg-gray-50"
            >
              🔄 Làm mới form
            </button>
            <button 
              @click="goBack" 
              class="w-full px-3 py-2 text-sm text-gray-700 border border-gray-300 rounded hover:bg-gray-50"
            >
              ← Quay lại danh sách
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
          'bg-red-100 border border-red-400 text-red-700': notification.type === 'error',
          'bg-yellow-100 border border-yellow-400 text-yellow-700': notification.type === 'warning'
        }"
      >
        <div class="flex items-center">
          <span class="mr-2">
            {{ notification.type === 'success' ? '✅' : notification.type === 'warning' ? '⚠️' : '❌' }}
          </span>
          <span>{{ notification.message }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue'
import { purchaseReturnReceiptApi } from '../api/purchaseReturnReceiptApi'
import purchaseReturnOrderApi from '../api/purchaseReturnOrderApi'
import warehouseApi from '../api/warehouseApi'

export default {
  name: 'PurchaseReturnReceiptCreate',
  setup() {
    // Reactive state
    const loading = ref(false)
    const returnOrders = ref([])
    const warehouses = ref([])
    const selectedReturnOrder = ref(null)
    
    // Search states
    const returnOrderSearch = ref('')
    const warehouseSearch = ref('')
    const showReturnOrderDropdown = ref(false)
    const showWarehouseDropdown = ref(false)

    // Form data
    const form = reactive({
      purchase_return_order_id: '',
      warehouse_id: '',
      supplier_id: '',
      returned_at: '',
      note: '',
      reason: '',
      items: []
    })

    // Notification
    const notification = ref({
      show: false,
      type: 'success',
      message: ''
    })

    // Computed properties
    const todayDate = computed(() => {
      return new Date().toISOString().split('T')[0]
    })

    const filteredReturnOrders = computed(() => {
      // Đảm bảo returnOrders.value là array
      const orders = Array.isArray(returnOrders.value) ? returnOrders.value : []
      
      if (!returnOrderSearch.value) return orders.slice(0, 10)
      
      const searchTerm = returnOrderSearch.value.toLowerCase()
      return orders.filter(order =>
        order.code?.toLowerCase().includes(searchTerm) ||
        order.supplier?.name?.toLowerCase().includes(searchTerm)
      ).slice(0, 10)
    })

    const filteredWarehouses = computed(() => {
      // Đảm bảo warehouses.value là array
      const warehouseList = Array.isArray(warehouses.value) ? warehouses.value : []
      
      if (!warehouseSearch.value) return warehouseList
      
      const searchTerm = warehouseSearch.value.toLowerCase()
      return warehouseList.filter(warehouse =>
        warehouse.name?.toLowerCase().includes(searchTerm) ||
        warehouse.code?.toLowerCase().includes(searchTerm)
      )
    })

    const totalItems = computed(() => {
      return selectedReturnOrder.value?.items?.length || 0
    })

    const totalQuantity = computed(() => {
      return selectedReturnOrder.value?.items?.reduce((sum, item) => sum + (item.quantity || 0), 0) || 0
    })

    const totalAmount = computed(() => {
      return selectedReturnOrder.value?.total || 0
    })

    const canSave = computed(() => {
      return form.purchase_return_order_id && form.warehouse_id && form.returned_at
    })

    const canSubmit = computed(() => {
      return canSave.value && selectedReturnOrder.value
    })

    // Helper methods
    const showNotification = (message, type = 'success') => {
      notification.value = { show: true, type, message }
      setTimeout(() => {
        notification.value.show = false
      }, 3000)
    }

    // API methods
    const fetchApprovedReturnOrders = async () => {
      try {
        console.log('🔍 Fetching return orders...')
        const response = await purchaseReturnOrderApi.getAll({ 
          status: 'approved',
          per_page: 100 
        })
        console.log('📋 Return orders response:', response)
        
        // Xử lý response structure
        let data = []
        if (response && response.success && response.data) {
          // Nếu có nested data
          data = response.data.data || response.data
        } else if (response && Array.isArray(response.data)) {
          // Nếu data là array trực tiếp
          data = response.data
        } else if (Array.isArray(response)) {
          // Nếu response là array trực tiếp
          data = response
        }
        
        returnOrders.value = Array.isArray(data) ? data : []
        console.log('✅ Return orders loaded:', returnOrders.value.length, 'items')
        
      } catch (error) {
        console.error('❌ Error fetching return orders:', error)
        returnOrders.value = [] // Đảm bảo luôn là array
        showNotification('Lỗi khi tải danh sách đơn trả hàng', 'error')
      }
    }

    const fetchWarehouses = async () => {
      try {
        console.log('🔍 Fetching warehouses...')
        const response = await warehouseApi.getWarehouses()
        console.log('🏭 Warehouses response:', response)
        
        // Xử lý response structure
        let data = []
        if (response && response.success && response.data) {
          data = response.data.data || response.data
        } else if (response && Array.isArray(response.data)) {
          data = response.data
        } else if (Array.isArray(response)) {
          data = response
        }
        
        warehouses.value = Array.isArray(data) ? data : []
        console.log('✅ Warehouses loaded:', warehouses.value.length, 'items')
        
      } catch (error) {
        console.error('❌ Error fetching warehouses:', error)
        warehouses.value = [] // Đảm bảo luôn là array
        showNotification('Lỗi khi tải danh sách kho', 'error')
      }
    }

    // Search methods
    const searchReturnOrders = () => {
      showReturnOrderDropdown.value = true
    }

    const searchWarehouses = () => {
      showWarehouseDropdown.value = true
    }

    const selectReturnOrder = async (order) => {
      selectedReturnOrder.value = order
      form.purchase_return_order_id = order.id
      form.supplier_id = order.supplier_id
      returnOrderSearch.value = `${order.code} - ${order.supplier?.name}`
      showReturnOrderDropdown.value = false
      
      // Load chi tiết đơn trả hàng để lấy items
      await loadReturnOrderDetails(order.id)
    }

    const selectWarehouse = (warehouse) => {
      form.warehouse_id = warehouse.id
      warehouseSearch.value = warehouse.name
      showWarehouseDropdown.value = false
    }

    const loadReturnOrderDetails = async (orderId) => {
      try {
        loading.value = true
        const response = await purchaseReturnOrderApi.show(orderId)
        
        if (response.success) {
          selectedReturnOrder.value = response.data
          
          // Map items cho form
          form.items = response.data.items?.map(item => ({
            purchase_return_order_item_id: item.id,
            product_id: item.product_id,
            product: item.product,
            quantity_returned: item.quantity,
            unit_cost: item.price,
            total_cost: item.total,
            condition_status: 'good',
            return_reason: item.return_reason || '',
            lot_number: item.lot_number || '',
            note: item.note || ''
          })) || []
        }
      } catch (error) {
        console.error('Error loading return order details:', error)
        showNotification('Lỗi khi tải chi tiết đơn trả hàng', 'error')
      } finally {
        loading.value = false
      }
    }

    // Form methods
    const saveDraft = async () => {
      if (!canSave.value) {
        showNotification('Vui lòng điền đầy đủ thông tin bắt buộc', 'warning')
        return
      }

      try {
        loading.value = true
        const data = {
          purchase_return_order_id: form.purchase_return_order_id,
          warehouse_id: form.warehouse_id,
          supplier_id: form.supplier_id,
          returned_at: form.returned_at,
          note: form.note,
          reason: form.reason,
          status: 'draft',
          items: form.items
        }
        
        console.log('📤 Saving draft:', data)
        const response = await purchaseReturnReceiptApi.createPurchaseReturnReceipt(data)
        
        if (response.success) {
          showNotification('Lưu nháp thành công!')
          setTimeout(() => {
            window.location.href = '/purchase-return-receipts'
          }, 1500)
        } else {
          showNotification(response.message || 'Có lỗi xảy ra', 'error')
        }
      } catch (error) {
        console.error('Error saving draft:', error)
        showNotification(error.message || 'Có lỗi xảy ra khi lưu nháp', 'error')
      } finally {
        loading.value = false
      }
    }

    const saveAndSubmit = async () => {
      if (!canSubmit.value) {
        showNotification('Vui lòng điền đầy đủ thông tin và chọn đơn trả hàng', 'warning')
        return
      }

      try {
        loading.value = true
        const data = {
          purchase_return_order_id: form.purchase_return_order_id,
          warehouse_id: form.warehouse_id,
          supplier_id: form.supplier_id,
          returned_at: form.returned_at,
          note: form.note,
          reason: form.reason,
          status: 'pending',
          items: form.items
        }
        
        console.log('📤 Creating receipt:', data)
        const response = await purchaseReturnReceiptApi.createPurchaseReturnReceipt(data)
        
        if (response.success) {
          showNotification('Tạo phiếu trả hàng thành công!')
          setTimeout(() => {
            window.location.href = `/purchase-return-receipts/${response.data.id}`
          }, 1500)
        } else {
          showNotification(response.message || 'Có lỗi xảy ra', 'error')
        }
      } catch (error) {
        console.error('Error creating receipt:', error)
        showNotification(error.message || 'Có lỗi xảy ra khi tạo phiếu', 'error')
      } finally {
        loading.value = false
      }
    }

    const resetForm = () => {
      Object.assign(form, {
        purchase_return_order_id: '',
        warehouse_id: '',
        supplier_id: '',
        returned_at: todayDate.value,
        note: '',
        reason: '',
        items: []
      })
      selectedReturnOrder.value = null
      returnOrderSearch.value = ''
      warehouseSearch.value = ''
      showNotification('Đã làm mới form', 'success')
    }

    const goBack = () => {
      if (form.purchase_return_order_id || form.note) {
        if (confirm('Bạn có thay đổi chưa lưu. Quay lại sẽ mất dữ liệu. Bạn có chắc chắn?')) {
          window.location.href = '/purchase-return-receipts'
        }
      } else {
        window.location.href = '/purchase-return-receipts'
      }
    }

    // Utility methods
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

    // Event handlers
    const handleClickOutside = (event) => {
      if (!event.target.closest('.relative')) {
        showReturnOrderDropdown.value = false
        showWarehouseDropdown.value = false
      }
    }

    const handleKeydown = (event) => {
      if (event.key === 'Escape') {
        showReturnOrderDropdown.value = false
        showWarehouseDropdown.value = false
      }
    }

    // Lifecycle hooks
    onMounted(async () => {
      // Set default date
      form.returned_at = todayDate.value
      
      // Load data
      await Promise.all([
        fetchApprovedReturnOrders(),
        fetchWarehouses()
      ])
      
      // Add event listeners
      document.addEventListener('click', handleClickOutside)
      document.addEventListener('keydown', handleKeydown)
    })

    onUnmounted(() => {
      document.removeEventListener('click', handleClickOutside)
      document.removeEventListener('keydown', handleKeydown)
    })

    // Return all reactive properties and methods
    return {
      // State
      loading,
      returnOrders,
      warehouses,
      selectedReturnOrder,
      returnOrderSearch,
      warehouseSearch,
      showReturnOrderDropdown,
      showWarehouseDropdown,
      form,
      notification,

      // Computed
      todayDate,
      filteredReturnOrders,
      filteredWarehouses,
      totalItems,
      totalQuantity,
      totalAmount,
      canSave,
      canSubmit,

      // Methods
      showNotification,
      searchReturnOrders,
      searchWarehouses,
      selectReturnOrder,
      selectWarehouse,
      loadReturnOrderDetails,
      saveDraft,
      saveAndSubmit,
      resetForm,
      goBack,
      formatCurrency,
      formatDate
    }
  }
}
</script>