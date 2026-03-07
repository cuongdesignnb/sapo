<template>
  <div class="bg-white min-h-screen">
    <!-- Header -->
    <div class="p-6 border-b bg-white sticky top-0 z-10">
      <div class="flex justify-between items-center">
        <div>
          <h1 class="text-2xl font-semibold text-gray-900">Chỉnh sửa đơn trả hàng</h1>
          <div class="text-sm text-gray-500 mt-1">
            <span>Trang chủ</span> / <span>Đơn trả hàng</span> / <span class="text-gray-900">Chỉnh sửa</span>
          </div>
        </div>
        <div class="flex space-x-3">
          <button @click="goBack" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
            ← Quay lại
          </button>
          <button v-if="canEdit" @click="saveChanges" class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600" :disabled="loading">
            💾 Lưu thay đổi
          </button>
          <span v-else class="px-4 py-2 bg-gray-100 text-gray-500 rounded-md cursor-not-allowed">
            🔒 Không thể chỉnh sửa
          </span>
        </div>
      </div>
    </div>

    <div class="flex">
      <!-- Main Content -->
      <div class="flex-1 p-6">
        <!-- Loading State -->
        <div v-if="loading" class="text-center py-12">
          <div class="text-6xl mb-4">⏳</div>
          <div class="text-lg text-gray-600">Đang tải thông tin đơn trả hàng...</div>
        </div>

        <!-- Content -->
        <div v-else>
          <!-- Order Info -->
          <div class="bg-white border border-gray-200 rounded-lg mb-6">
            <div class="px-6 py-4 border-b border-gray-200">
              <h2 class="text-lg font-medium text-gray-900 flex items-center">
                📋 Thông tin đơn trả hàng
                <span class="ml-4 px-3 py-1 rounded-full text-sm font-medium" :class="getStatusClass(returnOrder?.status)">
                  {{ getStatusLabel(returnOrder?.status) }}
                </span>
              </h2>
            </div>
            <div class="p-6">
              <div class="grid grid-cols-3 gap-6">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Mã đơn trả hàng</label>
                  <div class="text-lg font-semibold text-blue-600">{{ returnOrder?.code }}</div>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Ngày tạo</label>
                  <div>{{ formatDate(returnOrder?.created_at) }}</div>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Người tạo</label>
                  <div>{{ returnOrder?.creator?.name }}</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Supplier Info -->
          <div class="bg-white border border-gray-200 rounded-lg mb-6">
            <div class="px-6 py-4 border-b border-gray-200">
              <h2 class="text-lg font-medium text-gray-900 flex items-center">
                🏢 Thông tin nhà cung cấp
              </h2>
            </div>
            <div class="p-6">
              <div class="grid grid-cols-2 gap-6">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">
                    Nhà cung cấp <span class="text-red-500">*</span>
                  </label>
                  <select v-model="form.supplier_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" :disabled="!canEdit" @change="onSupplierChange">
                    <option value="">Chọn nhà cung cấp</option>
                    <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">
                      {{ supplier.name }}
                    </option>
                  </select>
                  <div v-if="selectedSupplier" class="mt-2 text-sm text-gray-500">
                    📞 {{ selectedSupplier.phone }} | 📧 {{ selectedSupplier.email }}
                  </div>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">
                    Kho trả hàng <span class="text-red-500">*</span>
                  </label>
                  <select v-model="form.warehouse_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" :disabled="!canEdit" @change="onWarehouseChange">
                    <option value="">Chọn kho</option>
                    <option v-for="warehouse in warehouses" :key="warehouse.id" :value="warehouse.id">
                      {{ warehouse.name }}
                    </option>
                  </select>
                </div>
              </div>
            </div>
          </div>

          <!-- Order Details -->
          <div class="bg-white border border-gray-200 rounded-lg mb-6">
            <div class="px-6 py-4 border-b border-gray-200">
              <h2 class="text-lg font-medium text-gray-900 flex items-center">
                📝 Chi tiết đơn trả hàng
              </h2>
            </div>
            <div class="p-6">
              <div class="grid grid-cols-2 gap-6">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Ngày trả hàng</label>
                  <input v-model="form.returned_at" type="date" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" :disabled="!canEdit" :max="todayDate">
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Lý do trả hàng</label>
                  <select v-model="form.return_reason" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" :disabled="!canEdit">
                    <option value="">Chọn lý do</option>
                    <option value="damaged">Hàng bị hỏng</option>
                    <option value="expired">Hàng hết hạn</option>
                    <option value="wrong_item">Giao sai hàng</option>
                    <option value="excess">Giao thừa</option>
                    <option value="defective">Lỗi sản xuất</option>
                    <option value="other">Khác</option>
                  </select>
                </div>
              </div>
              <div class="mt-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Ghi chú</label>
                <textarea v-model="form.note" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" :disabled="!canEdit" placeholder="Ghi chú thêm về đơn trả hàng..."></textarea>
              </div>
            </div>
          </div>

          <!-- Products -->
          <div class="bg-white border border-gray-200 rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
              <h2 class="text-lg font-medium text-gray-900 flex items-center">
                📦 Sản phẩm trả hàng
              </h2>
              <button v-if="canEdit" @click="showReceiptModal = true" class="px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600" :disabled="!form.supplier_id">
                ➕ Chọn từ phiếu nhập
              </button>
            </div>
            <div class="p-6">
              <div v-if="form.items.length === 0" class="text-center py-12">
                <div class="text-6xl mb-4">📦</div>
                <h3 class="text-lg font-medium text-gray-900 mb-2">Chưa có sản phẩm nào</h3>
                <p class="text-gray-500 mb-4">Vui lòng chọn nhà cung cấp, sau đó chọn sản phẩm từ phiếu nhập để trả.</p>
              </div>

              <div v-else class="overflow-x-auto">
                <table class="w-full">
                  <thead class="bg-gray-50">
                    <tr>
                      <th class="text-left p-3 text-sm font-medium text-gray-600" style="width: 5%">STT</th>
                      <th class="text-left p-3 text-sm font-medium text-gray-600" style="width: 30%">Sản phẩm</th>
                      <th class="text-left p-3 text-sm font-medium text-gray-600" style="width: 15%">Phiếu nhập</th>
                      <th class="text-left p-3 text-sm font-medium text-gray-600" style="width: 10%">Có thể trả</th>
                      <th class="text-left p-3 text-sm font-medium text-gray-600" style="width: 10%">SL trả</th>
                      <th class="text-left p-3 text-sm font-medium text-gray-600" style="width: 15%">Đơn giá</th>
                      <th class="text-left p-3 text-sm font-medium text-gray-600" style="width: 15%">Thành tiền</th>
                      <th class="text-left p-3 text-sm font-medium text-gray-600" style="width: 5%">Thao tác</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-200">
                    <tr v-for="(item, index) in form.items" :key="index">
                      <td class="p-3 text-center text-sm">{{ index + 1 }}</td>
                      <td class="p-3">
                        <div class="font-medium">{{ item.product?.name }}</div>
                        <div class="text-sm text-gray-500">SKU: {{ item.product?.sku }}</div>
                        <div class="mt-1">
                          <select v-model="item.condition_status" class="text-xs border border-gray-300 rounded px-2 py-1" :disabled="!canEdit">
                            <option value="good">Tốt</option>
                            <option value="damaged">Hỏng</option>
                            <option value="expired">Hết hạn</option>
                            <option value="wrong_item">Sai hàng</option>
                            <option value="excess">Thừa</option>
                            <option value="defective">Lỗi sản xuất</option>
                          </select>
                        </div>
                      </td>
                      <td class="p-3 text-sm">
                        <div class="font-medium">{{ item.receipt_code }}</div>
                        <div class="text-gray-500">{{ formatDate(item.receipt_date) }}</div>
                      </td>
                      <td class="p-3 text-center">
                        <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded">
                          {{ item.max_returnable_quantity }}
                        </span>
                      </td>
                      <td class="p-3">
                        <input 
                          v-model.number="item.quantity" 
                          @input="calculateItemTotal(item)"
                          type="number" 
                          min="1" 
                          :max="item.max_returnable_quantity"
                          class="w-full px-2 py-1 border border-gray-300 rounded text-center focus:outline-none focus:ring-2 focus:ring-blue-500"
                          :class="{'border-red-500 bg-red-50': item.quantity > item.max_returnable_quantity}"
                          :disabled="!canEdit"
                        >
                        <div v-if="item.quantity > item.max_returnable_quantity" class="text-xs text-red-500 mt-1">
                          Vượt quá số lượng có thể trả!
                        </div>
                      </td>
                      <td class="p-3">
                        <input 
                          v-model.number="item.price" 
                          @input="calculateItemTotal(item)"
                          type="number" 
                          min="0" 
                          step="1000"
                          class="w-full px-2 py-1 border border-gray-300 rounded text-right focus:outline-none focus:ring-2 focus:ring-blue-500"
                          :disabled="!canEdit"
                        >
                      </td>
                      <td class="p-3 text-right font-medium">
                        {{ formatCurrency(item.total || 0) }}
                      </td>
                      <td class="p-3 text-center">
                        <button v-if="canEdit" @click="removeItem(index)" class="text-red-500 hover:text-red-700">
                          🗑️
                        </button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Summary Sidebar -->
      <div class="w-80 p-6 bg-gray-50 border-l">
        <div class="bg-white rounded-lg p-6 shadow-sm">
          <h3 class="text-lg font-medium text-gray-900 mb-4">📊 Tổng kết đơn hàng</h3>
          
          <div class="space-y-3">
            <div class="flex justify-between text-sm">
              <span class="text-gray-600">Tổng sản phẩm:</span>
              <span class="font-medium">{{ form.items.length }} loại</span>
            </div>
            <div class="flex justify-between text-sm">
              <span class="text-gray-600">Tổng số lượng:</span>
              <span class="font-medium">{{ totalQuantity }}</span>
            </div>
            <div class="border-t pt-3">
              <div class="flex justify-between text-lg font-medium">
                <span>Tổng tiền:</span>
                <span class="text-blue-600">{{ formatCurrency(totalAmount) }}</span>
              </div>
            </div>
          </div>

          <div class="mt-6 pt-6 border-t">
            <div class="text-sm text-gray-600 mb-2">Trạng thái validation:</div>
            <div class="space-y-2">
              <div class="flex items-center text-sm">
                <span class="w-3 h-3 rounded-full mr-2" :class="form.supplier_id ? 'bg-green-500' : 'bg-red-500'"></span>
                <span :class="form.supplier_id ? 'text-green-700' : 'text-red-700'">
                  {{ form.supplier_id ? 'Đã chọn nhà cung cấp' : 'Chưa chọn nhà cung cấp' }}
                </span>
              </div>
              <div class="flex items-center text-sm">
                <span class="w-3 h-3 rounded-full mr-2" :class="form.items.length > 0 ? 'bg-green-500' : 'bg-red-500'"></span>
                <span :class="form.items.length > 0 ? 'text-green-700' : 'text-red-700'">
                  {{ form.items.length > 0 ? 'Đã có sản phẩm' : 'Chưa có sản phẩm' }}
                </span>
              </div>
              <div class="flex items-center text-sm">
                <span class="w-3 h-3 rounded-full mr-2" :class="isValidQuantities ? 'bg-green-500' : 'bg-red-500'"></span>
                <span :class="isValidQuantities ? 'text-green-700' : 'text-red-700'">
                  {{ isValidQuantities ? 'Số lượng hợp lệ' : 'Số lượng không hợp lệ' }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Receipt Selection Modal (same as Create) -->
    <div v-if="showReceiptModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg max-w-6xl w-full max-h-[90vh] overflow-hidden">
        <div class="p-6 border-b border-gray-200">
          <div class="flex justify-between items-center">
            <h3 class="text-lg font-medium text-gray-900">Chọn phiếu nhập và sản phẩm trả hàng</h3>
            <button @click="showReceiptModal = false" class="text-gray-400 hover:text-gray-600">
              ✕
            </button>
          </div>
        </div>
        
        <div class="p-6 overflow-y-auto max-h-[calc(90vh-120px)]">
          <!-- Step 1: Select Receipt -->
          <div v-if="!selectedReceipt" class="space-y-4">
            <div class="text-sm text-gray-600 mb-4">
              <span class="font-medium">Bước 1:</span> Chọn phiếu nhập hàng từ nhà cung cấp <strong>{{ selectedSupplier?.name }}</strong>
            </div>
            
            <div v-if="availableReceipts.length === 0" class="text-center py-8">
              <div class="text-gray-500">Không có phiếu nhập nào có thể trả hàng từ nhà cung cấp này</div>
            </div>
            
            <div v-else class="grid gap-4">
              <div v-for="receipt in availableReceipts" :key="receipt.id" 
                   @click="selectReceipt(receipt)"
                   class="border border-gray-200 rounded-lg p-4 hover:border-blue-500 hover:bg-blue-50 cursor-pointer transition-colors">
                <div class="flex justify-between items-start">
                  <div>
                    <div class="font-medium">{{ receipt.code }}</div>
                    <div class="text-sm text-gray-600">Ngày nhập: {{ formatDate(receipt.received_at) }}</div>
                    <div class="text-sm text-gray-600">Kho: {{ receipt.warehouse?.name }}</div>
                  </div>
                  <div class="text-right">
                    <div class="text-sm text-blue-600">{{ receipt.items?.length || 0 }} sản phẩm</div>
                    <div class="text-xs text-gray-500">Có thể trả</div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Step 2: Select Items -->
          <div v-else class="space-y-4">
            <div class="flex items-center justify-between">
              <div class="text-sm text-gray-600">
                <span class="font-medium">Bước 2:</span> Chọn sản phẩm từ phiếu <strong>{{ selectedReceipt.code }}</strong>
              </div>
              <button @click="selectedReceipt = null" class="text-blue-600 hover:text-blue-800 text-sm">
                ← Chọn phiếu khác
              </button>
            </div>

            <div v-if="returnableItems.length === 0" class="text-center py-8">
              <div class="text-gray-500">Không có sản phẩm nào có thể trả từ phiếu này</div>
            </div>

            <div v-else class="overflow-x-auto">
              <table class="w-full border border-gray-200 rounded-lg">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="text-left p-3 text-sm font-medium text-gray-600">Chọn</th>
                    <th class="text-left p-3 text-sm font-medium text-gray-600">Sản phẩm</th>
                    <th class="text-left p-3 text-sm font-medium text-gray-600">Đã nhập</th>
                    <th class="text-left p-3 text-sm font-medium text-gray-600">Đã trả</th>
                    <th class="text-left p-3 text-sm font-medium text-gray-600">Có thể trả</th>
                    <th class="text-left p-3 text-sm font-medium text-gray-600">Đơn giá</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                  <tr v-for="item in returnableItems" :key="item.id" 
                      :class="{'bg-blue-50': isItemSelected(item.id)}"
                      class="hover:bg-gray-50">
                    <td class="p-3">
                      <input 
                        type="checkbox" 
                        :checked="isItemSelected(item.id)"
                        @change="toggleItemSelection(item)"
                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                      >
                    </td>
                    <td class="p-3">
                      <div class="font-medium">{{ item.product?.name }}</div>
                      <div class="text-sm text-gray-500">SKU: {{ item.product?.sku }}</div>
                    </td>
                    <td class="p-3 text-center">{{ item.quantity_received }}</td>
                    <td class="p-3 text-center">{{ item.returned_quantity }}</td>
                    <td class="p-3 text-center">
                      <span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded">
                        {{ item.returnable_quantity }}
                      </span>
                    </td>
                    <td class="p-3 text-right">{{ formatCurrency(item.unit_cost) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="flex justify-end space-x-3 pt-4 border-t">
              <button @click="showReceiptModal = false" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                Hủy
              </button>
              <button @click="addSelectedItems" class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600" :disabled="selectedItems.length === 0">
                Thêm {{ selectedItems.length }} sản phẩm
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Notification -->
    <div v-if="notification.show" class="fixed top-4 right-4 z-50">
      <div class="bg-white border border-gray-200 rounded-lg shadow-lg p-4 max-w-sm">
        <div class="flex items-center space-x-3">
          <span class="text-2xl">
            {{ notification.type === 'success' ? '✅' : notification.type === 'warning' ? '⚠️' : '❌' }}
          </span>
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
  name: 'PurchaseReturnOrderEdit',
  setup() {
    const loading = ref(true)
    const showReceiptModal = ref(false)
    const orderId = ref(null)
    
    const suppliers = ref([])
    const warehouses = ref([])
    const availableReceipts = ref([])
    const returnableItems = ref([])
    const selectedReceipt = ref(null)
    const selectedItems = ref([])
    const selectedSupplier = ref(null)
    const returnOrder = ref(null)

    // Form data
    const form = ref({
      supplier_id: '',
      warehouse_id: '',
      returned_at: '',
      return_reason: '',
      note: '',
      items: []
    })

    // Notification
    const notification = ref({
      show: false,
      type: 'success',
      message: ''
    })

    const todayDate = computed(() => {
      return new Date().toISOString().split('T')[0]
    })

    const totalQuantity = computed(() => {
      return form.value.items.reduce((sum, item) => sum + (item.quantity || 0), 0)
    })

    const totalAmount = computed(() => {
      return form.value.items.reduce((sum, item) => sum + (item.total || 0), 0)
    })

    const isValidQuantities = computed(() => {
      return form.value.items.every(item => 
        item.quantity > 0 && item.quantity <= item.max_returnable_quantity
      )
    })

    const canEdit = computed(() => {
      return returnOrder.value?.status === 'pending'
    })

    const showNotification = (message, type = 'success') => {
      notification.value = { show: true, type, message }
      setTimeout(() => {
        notification.value.show = false
      }, 3000)
    }

    const formatCurrency = (amount) => {
      return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND'
      }).format(amount)
    }

    const formatDate = (date) => {
      if (!date) return ''
      return new Date(date).toLocaleDateString('vi-VN')
    }

    const getStatusLabel = (status) => {
      const labels = {
        pending: 'Chờ duyệt',
        approved: 'Đã duyệt',
        returned: 'Đã trả hàng',
        completed: 'Hoàn thành',
        cancelled: 'Đã hủy'
      }
      return labels[status] || status
    }

    const getStatusClass = (status) => {
      const classes = {
        pending: 'bg-yellow-100 text-yellow-800',
        approved: 'bg-blue-100 text-blue-800',
        returned: 'bg-purple-100 text-purple-800',
        completed: 'bg-green-100 text-green-800',
        cancelled: 'bg-red-100 text-red-800'
      }
      return classes[status] || 'bg-gray-100 text-gray-800'
    }

    onMounted(() => {
      // Get order ID from URL
      const pathParts = window.location.pathname.split('/')
      orderId.value = pathParts[pathParts.length - 2] // Assuming URL like /orders/{id}/edit
      
      fetchInitialData()
    })

    const fetchInitialData = async () => {
      await Promise.all([
        fetchSuppliers(),
        fetchWarehouses(),
        fetchReturnOrder()
      ])
    }

    const fetchSuppliers = async () => {
      try {
        const response = await purchaseReturnOrderApi.getSuppliers()
        suppliers.value = response.data || response
      } catch (error) {
        console.error('Error fetching suppliers:', error)
      }
    }

    const fetchWarehouses = async () => {
      try {
        const response = await purchaseReturnOrderApi.getWarehouses()
        warehouses.value = response.data.data || response
      } catch (error) {
        console.error('Error fetching warehouses:', error)
      }
    }

    const fetchReturnOrder = async () => {
      if (!orderId.value) return
      
      try {
        loading.value = true
        const response = await purchaseReturnOrderApi.getPurchaseReturnOrder(orderId.value)
        returnOrder.value = response.data || response
        
        // Populate form
        form.value = {
          supplier_id: returnOrder.value.supplier_id,
          warehouse_id: returnOrder.value.warehouse_id,
          returned_at: returnOrder.value.returned_at ? returnOrder.value.returned_at.split(' ')[0] : '',
          return_reason: returnOrder.value.return_reason || '',
          note: returnOrder.value.note || '',
          items: (returnOrder.value.items || []).map(item => ({
            ...item,
            purchase_receipt_item_id: item.purchase_receipt_item_id,
            product_id: item.product_id,
            product: item.product,
            quantity: item.quantity,
            max_returnable_quantity: item.max_returnable_quantity,
            price: item.price,
            total: item.total,
            receipt_code: item.purchaseReceipt?.code || '',
            receipt_date: item.purchaseReceipt?.received_at || '',
            condition_status: item.condition_status || 'good',
            return_reason: item.return_reason || ''
          }))
        }

        // Set selected supplier
        selectedSupplier.value = suppliers.value.find(s => s.id == form.value.supplier_id)
        
      } catch (error) {
        console.error('Error fetching return order:', error)
        showNotification('Không thể tải thông tin đơn trả hàng', 'error')
      } finally {
        loading.value = false
      }
    }

    const onSupplierChange = async () => {
      selectedSupplier.value = suppliers.value.find(s => s.id == form.value.supplier_id)
      availableReceipts.value = []
      
      if (form.value.supplier_id) {
        await fetchReceiptsBySupplier()
      }
    }

    const onWarehouseChange = () => {
      // Can add logic here if needed when warehouse changes
    }

    const fetchReceiptsBySupplier = async () => {
      if (!form.value.supplier_id) return
      
      try {
        const response = await purchaseReturnOrderApi.getReceiptsBySupplier(form.value.supplier_id)
        availableReceipts.value = response.data || response
      } catch (error) {
        console.error('Error fetching receipts:', error)
        showNotification('Lỗi khi tải danh sách phiếu nhập', 'error')
      }
    }

    const selectReceipt = async (receipt) => {
      selectedReceipt.value = receipt
      selectedItems.value = []
      
      try {
        const response = await purchaseReturnOrderApi.getReturnableItems(receipt.id)
        returnableItems.value = response.data || response
      } catch (error) {
        console.error('Error fetching returnable items:', error)
        showNotification('Lỗi khi tải sản phẩm có thể trả', 'error')
      }
    }

    const isItemSelected = (itemId) => {
      return selectedItems.value.some(item => item.id === itemId)
    }

    const toggleItemSelection = (item) => {
      const index = selectedItems.value.findIndex(selected => selected.id === item.id)
      if (index >= 0) {
        selectedItems.value.splice(index, 1)
      } else {
        selectedItems.value.push(item)
      }
    }

    const addSelectedItems = () => {
      selectedItems.value.forEach(item => {
        const existingItem = form.value.items.find(formItem => formItem.purchase_receipt_item_id === item.id)
        
        if (!existingItem) {
          form.value.items.push({
            purchase_receipt_item_id: item.id,
            product_id: item.product_id,
            product: item.product,
            quantity: 1,
            max_returnable_quantity: item.returnable_quantity,
            price: item.unit_cost,
            total: item.unit_cost,
            receipt_code: selectedReceipt.value.code,
            receipt_date: selectedReceipt.value.received_at,
            condition_status: 'good',
            return_reason: ''
          })
        }
      })
      
      showReceiptModal.value = false
      selectedReceipt.value = null
      selectedItems.value = []
      showNotification(`Đã thêm ${selectedItems.value.length} sản phẩm`)
    }

    const removeItem = (index) => {
      form.value.items.splice(index, 1)
    }

    const calculateItemTotal = (item) => {
      item.total = (item.quantity || 0) * (item.price || 0)
    }

    const validateForm = () => {
      if (!form.value.supplier_id) {
        showNotification('Vui lòng chọn nhà cung cấp', 'error')
        return false
      }

      if (!form.value.warehouse_id) {
        showNotification('Vui lòng chọn kho trả hàng', 'error')
        return false
      }

      if (form.value.items.length === 0) {
        showNotification('Vui lòng thêm ít nhất một sản phẩm', 'error')
        return false
      }

      if (!isValidQuantities.value) {
        showNotification('Vui lòng kiểm tra lại số lượng các sản phẩm', 'error')
        return false
      }

      return true
    }

    const saveChanges = async () => {
      if (!validateForm()) return

      try {
        loading.value = true
        const response = await purchaseReturnOrderApi.updatePurchaseReturnOrder(orderId.value, form.value)

        if (response.success) {
          showNotification('Cập nhật đơn trả hàng thành công')
          setTimeout(() => {
            window.location.href = '/purchase-return-orders'
          }, 1000)
        }
      } catch (error) {
        console.error('Error updating order:', error)
        showNotification('Lỗi khi cập nhật đơn trả hàng', 'error')
      } finally {
        loading.value = false
      }
    }

    const goBack = () => {
      window.history.back()
    }

    return {
      loading,
      showReceiptModal,
      orderId,
      suppliers,
      warehouses,
      availableReceipts,
      returnableItems,
      selectedReceipt,
      selectedItems,
      selectedSupplier,
      returnOrder,
      form,
      notification,
      todayDate,
      totalQuantity,
      totalAmount,
      isValidQuantities,
      canEdit,
      showNotification,
      formatCurrency,
      formatDate,
      getStatusLabel,
      getStatusClass,
      fetchInitialData,
      fetchSuppliers,
      fetchWarehouses,
      fetchReturnOrder,
      onSupplierChange,
      onWarehouseChange,
      fetchReceiptsBySupplier,
      selectReceipt,
      isItemSelected,
      toggleItemSelection,
      addSelectedItems,
      removeItem,
      calculateItemTotal,
      validateForm,
      saveChanges,
      goBack
    }
  }
}
</script>

<style scoped>
/* Same CSS styles as Create component */
.border-red-500 {
  border-color: #ef4444;
}

.bg-red-50 {
  background-color: #fef2f2;
}

.text-red-500 {
  color: #ef4444;
}

.text-red-700 {
  color: #b91c1c;
}

.text-green-700 {
  color: #15803d;
}

.bg-green-500 {
  background-color: #22c55e;
}

.bg-blue-50 {
  background-color: #eff6ff;
}

.text-blue-600 {
  color: #2563eb;
}

.text-blue-800 {
  color: #1e40af;
}

.bg-yellow-100 {
  background-color: #fef3c7;
}

.text-yellow-800 {
  color: #92400e;
}

.bg-blue-100 {
  background-color: #dbeafe;
}

.bg-purple-100 {
  background-color: #ede9fe;
}

.text-purple-800 {
  color: #5b21b6;
}

.bg-green-100 {
  background-color: #dcfce7;
}

.text-green-800 {
  color: #166534;
}

.bg-red-100 {
  background-color: #fee2e2;
}

.text-red-800 {
  color: #991b1b;
}

.bg-gray-100 {
  background-color: #f3f4f6;
}

.text-gray-800 {
  color: #1f2937;
}

.hover\:text-blue-800:hover {
  color: #1e40af;
}

.hover\:border-blue-500:hover {
  border-color: #3b82f6;
}

.hover\:bg-blue-50:hover {
  background-color: #eff6ff;
}

.hover\:bg-blue-600:hover {
  background-color: #2563eb;
}

.hover\:bg-green-600:hover {
  background-color: #16a34a;
}

.hover\:bg-gray-50:hover {
  background-color: #f9fafb;
}

.hover\:text-red-700:hover {
  color: #b91c1c;
}

.hover\:text-gray-600:hover {
  color: #4b5563;
}

.focus\:ring-2:focus {
  --tw-ring-offset-shadow: var(--tw-ring-inset) 0 0 0 var(--tw-ring-offset-width) var(--tw-ring-offset-color);
  --tw-ring-shadow: var(--tw-ring-inset) 0 0 0 calc(2px + var(--tw-ring-offset-width)) var(--tw-ring-color);
  box-shadow: var(--tw-ring-offset-shadow), var(--tw-ring-shadow), var(--tw-shadow, 0 0 #0000);
}

.focus\:ring-blue-500:focus {
  --tw-ring-opacity: 1;
  --tw-ring-color: rgb(59 130 246 / var(--tw-ring-opacity));
}

.transition-colors {
  transition-property: color, background-color, border-color, text-decoration-color, fill, stroke;
  transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
  transition-duration: 150ms;
}

.cursor-not-allowed {
  cursor: not-allowed;
}

.disabled\:opacity-50:disabled {
  opacity: 0.5;
}

.disabled\:cursor-not-allowed:disabled {
  cursor: not-allowed;
}

/* Hide spinner for number inputs */
input[type="number"]::-webkit-outer-spin-button,
input[type="number"]::-webkit-inner-spin-button {
  -webkit-appearance: none;
  margin: 0;
}

input[type="number"] {
  -moz-appearance: textfield;
}

/* Loading animation */
@keyframes spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}

.animate-spin {
  animation: spin 1s linear infinite;
}
</style>