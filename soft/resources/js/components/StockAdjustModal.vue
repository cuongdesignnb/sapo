<template>
  <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg w-full max-w-2xl max-h-screen overflow-y-auto">
      <div class="p-6 border-b">
        <div class="flex justify-between items-center">
          <h3 class="text-xl font-semibold text-gray-900">Điều chỉnh tồn kho</h3>
          <button @click="$emit('close')" class="text-gray-400 hover:text-gray-600 text-2xl">✕</button>
        </div>
      </div>
      
      <form @submit.prevent="handleSubmit">
        <div class="p-6">
          <!-- Warehouse & Product Selection -->
          <div class="space-y-4 mb-6">
            <!-- Warehouse Selection -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Chọn kho <span class="text-red-500">*</span>
              </label>
              <select 
                v-model="form.warehouse_id"
                @change="loadWarehouseProducts"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                required
              >
                <option value="">Chọn kho hàng</option>
                <option v-for="warehouse in warehouses" :key="warehouse.id" :value="warehouse.id">
                  {{ warehouse.name }} ({{ warehouse.code }})
                </option>
              </select>
            </div>

            <!-- Product Selection -->
            <div v-if="warehouseProducts.length > 0">
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Chọn sản phẩm <span class="text-red-500">*</span>
              </label>
              <select 
                v-model="form.product_id"
                @change="onProductSelect"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                required
              >
                <option value="">Chọn sản phẩm</option>
                <option v-for="product in warehouseProducts" :key="product.id" :value="product.product_id">
                  {{ product.product.name }} ({{ product.product.sku }}) - Hiện tại: {{ product.quantity }}
                </option>
              </select>
            </div>
          </div>

          <!-- Product Info -->
          <div v-if="selectedProduct" class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg p-6 mb-6">
            <h4 class="font-semibold text-gray-900 mb-4 flex items-center">
              <span class="mr-2">📦</span>
              Thông tin sản phẩm
            </h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <div class="font-semibold text-lg text-gray-900">{{ selectedProduct.product.name }}</div>
                <div class="text-sm text-gray-600 mt-1">
                  SKU: {{ selectedProduct.product.sku }} | 
                  {{ selectedProduct.product.category_name }} - {{ selectedProduct.product.brand_name }}
                </div>
              </div>
              <div class="text-right">
                <span :class="getStatusBadgeClass(selectedProduct.stock_status)">
                  {{ selectedProduct.stock_status_label }}
                </span>
              </div>
            </div>
            
            <div class="grid grid-cols-4 gap-4 mt-4 pt-4 border-t border-blue-200">
              <div class="text-center">
                <div class="text-2xl font-bold text-blue-600">{{ selectedProduct.quantity }}</div>
                <div class="text-xs text-gray-500">Hiện tại</div>
              </div>
              <div class="text-center">
                <div class="text-2xl font-bold text-yellow-600">{{ selectedProduct.reserved_quantity }}</div>
                <div class="text-xs text-gray-500">Dự trữ</div>
              </div>
              <div class="text-center">
                <div class="text-2xl font-bold text-green-600">{{ selectedProduct.available_stock }}</div>
                <div class="text-xs text-gray-500">Có thể bán</div>
              </div>
              <div class="text-center">
                <div class="text-2xl font-bold text-purple-600">{{ formatCurrency(selectedProduct.total_value) }}</div>
                <div class="text-xs text-gray-500">Giá trị</div>
              </div>
            </div>
          </div>

          <!-- Adjustment Form -->
          <div v-if="selectedProduct" class="space-y-6">
            <!-- Stock Adjustment Section -->
            <div class="bg-gray-50 rounded-lg p-6">
              <h4 class="font-semibold text-gray-900 mb-4 flex items-center">
                <span class="mr-2">📊</span>
                Điều chỉnh số lượng
              </h4>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- New Quantity -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">
                    Số lượng mới <span class="text-red-500">*</span>
                  </label>
                  <input 
                    type="number" 
                    v-model="form.quantity"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    min="0"
                    placeholder="Nhập số lượng mới"
                    required
                    @input="calculateAvailableStock"
                  />
                </div>

                <!-- Cost -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Giá vốn mới</label>
                  <input 
                    type="number" 
                    v-model="form.cost"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    min="0"
                    placeholder="Để trống nếu không thay đổi"
                  />
                </div>
              </div>
            </div>

            <!-- Stock Limits Section -->
            <div class="bg-yellow-50 rounded-lg p-6">
              <h4 class="font-semibold text-gray-900 mb-4 flex items-center">
                <span class="mr-2">⚠️</span>
                Giới hạn tồn kho
              </h4>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Min Stock -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Tồn kho tối thiểu</label>
                  <input 
                    type="number" 
                    v-model="form.min_stock"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    min="0"
                    placeholder="Cảnh báo khi dưới mức này"
                  />
                </div>

                <!-- Max Stock -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Tồn kho tối đa</label>
                  <input 
                    type="number" 
                    v-model="form.max_stock"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    min="0"
                    placeholder="Cảnh báo khi vượt mức này"
                  />
                </div>
              </div>
            </div>

            <!-- Reserved & Available Section -->
            <div class="bg-green-50 rounded-lg p-6">
              <h4 class="font-semibold text-gray-900 mb-4 flex items-center">
                <span class="mr-2">🔒</span>
                Quản lý dự trữ
              </h4>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Reserved Quantity -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Số lượng dự trữ</label>
                  <input 
                    type="number" 
                    v-model="form.reserved_quantity"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    min="0"
                    placeholder="Số lượng không thể bán"
                    @input="calculateAvailableStock"
                  />
                </div>

                <!-- Available Stock (Calculated) -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Có thể bán</label>
                  <div class="w-full px-3 py-2 bg-gray-100 border border-gray-300 rounded-md">
                    <span class="text-2xl font-bold text-green-600">{{ calculatedAvailable }}</span>
                  </div>
                  <p class="text-sm text-gray-500 mt-1">Tự động tính = Tồn kho - Dự trữ</p>
                </div>
              </div>
            </div>

            <!-- Reason Section -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Lý do điều chỉnh</label>
              <textarea 
                v-model="form.reason"
                rows="3"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                placeholder="Kiểm kê, hư hỏng, mất mát, điều chỉnh giá, nhập thêm hàng..."
              ></textarea>
            </div>

            <!-- Change Summary -->
            <div v-if="form.quantity && selectedProduct" class="border rounded-lg p-4 bg-blue-50 border-blue-200">
              <h4 class="font-semibold mb-3 flex items-center">
                <span class="mr-2">📋</span>
                Tóm tắt thay đổi
              </h4>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-2">
                  <div class="flex justify-between">
                    <span class="text-gray-600">Số lượng:</span>
                    <div>
                      <span class="font-medium">{{ selectedProduct.quantity }} → {{ form.quantity }}</span>
                      <span class="ml-2 text-gray-500">
                        ({{ quantityDifference > 0 ? '+' : '' }}{{ quantityDifference }})
                      </span>
                    </div>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-600">Có thể bán:</span>
                    <span class="font-medium">{{ selectedProduct.available_stock }} → {{ calculatedAvailable }}</span>
                  </div>
                </div>
                <div class="space-y-2">
                  <div class="flex justify-between">
                    <span class="text-gray-600">Giá trị cũ:</span>
                    <span class="font-medium">{{ formatCurrency(selectedProduct.total_value) }}</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-600">Giá trị mới:</span>
                    <span class="font-bold text-blue-600">{{ formatCurrency(newValue) }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex justify-end space-x-3 p-6 border-t bg-gray-50">
          <button 
            type="button"
            @click="$emit('close')"
            class="px-6 py-2 text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
            :disabled="loading"
          >
            Hủy bỏ
          </button>
          <button 
            type="submit"
            :disabled="loading || !selectedProduct"
            class="px-6 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors flex items-center space-x-2"
          >
            <div v-if="loading" class="animate-spin rounded-full h-4 w-4 border-b-2 border-white"></div>
            <span>{{ loading ? 'Đang cập nhật...' : 'Cập nhật tồn kho' }}</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script>
import { ref, computed, onMounted, watch } from 'vue'
import warehouseApi from '../api/warehouseApi'

export default {
  name: 'StockAdjustModal',
  props: {
    warehouses: {
      type: Array,
      default: () => []
    },
    preselectedWarehouseId: {
      type: [String, Number],
      default: null
    }
  },
  emits: ['close', 'updated'],
  setup(props, { emit }) {
    // State
    const loading = ref(false)
    const warehouseProducts = ref([])
    const selectedProduct = ref(null)
    
    // Form data
    const form = ref({
      warehouse_id: '',
      product_id: '',
      quantity: '',
      cost: '',
      min_stock: '',
      max_stock: '',
      reserved_quantity: '',
      reason: ''
    })

    // Computed
    const calculatedAvailable = computed(() => {
      const quantity = parseInt(form.value.quantity) || 0
      const reserved = parseInt(form.value.reserved_quantity) || 0
      return Math.max(0, quantity - reserved)
    })

    const quantityDifference = computed(() => {
      if (!selectedProduct.value || !form.value.quantity) return 0
      return parseInt(form.value.quantity) - selectedProduct.value.quantity
    })

    const newValue = computed(() => {
      if (!form.value.quantity) return 0
      const cost = parseFloat(form.value.cost) || selectedProduct.value?.cost || 0
      return parseInt(form.value.quantity) * cost
    })

    // Methods
    const loadWarehouseProducts = async () => {
      if (!form.value.warehouse_id) {
        warehouseProducts.value = []
        return
      }

      try {
        const response = await warehouseApi.getWarehouseProducts(form.value.warehouse_id, {
          per_page: 100
        })
        
        if (response.success) {
          warehouseProducts.value = response.data.data || []
        }
      } catch (error) {
        console.error('Error loading warehouse products:', error)
        showToast('Không thể tải danh sách sản phẩm', 'error')
      }
    }

    const onProductSelect = () => {
      if (!form.value.product_id) {
        selectedProduct.value = null
        return
      }
      
      selectedProduct.value = warehouseProducts.value.find(p => 
        p.product_id == form.value.product_id
      )
      
      if (selectedProduct.value) {
        // Fill form with current values
        form.value.quantity = selectedProduct.value.quantity
        form.value.cost = selectedProduct.value.cost
        form.value.min_stock = selectedProduct.value.min_stock
        form.value.max_stock = selectedProduct.value.max_stock
        form.value.reserved_quantity = selectedProduct.value.reserved_quantity
      }
    }

    const calculateAvailableStock = () => {
      // This is handled by computed property
    }

    const handleSubmit = async () => {
      if (!validateForm()) return

      loading.value = true
      try {
        const response = await warehouseApi.updateWarehouseProduct(selectedProduct.value.id, {
          quantity: parseInt(form.value.quantity),
          cost: parseFloat(form.value.cost) || selectedProduct.value.cost,
          min_stock: parseInt(form.value.min_stock) || selectedProduct.value.min_stock,
          max_stock: parseInt(form.value.max_stock) || selectedProduct.value.max_stock,
          reserved_quantity: parseInt(form.value.reserved_quantity) || 0,
          reason: form.value.reason
        })

        if (response.success) {
          showToast('Cập nhật tồn kho thành công!', 'success')
          emit('updated', response.data)
          emit('close')
        } else {
          showToast(response.message || 'Có lỗi xảy ra', 'error')
        }
      } catch (error) {
        console.error('Error adjusting stock:', error)
        showToast('Lỗi khi cập nhật tồn kho', 'error')
      } finally {
        loading.value = false
      }
    }

    const validateForm = () => {
      if (!form.value.warehouse_id) {
        showToast('Vui lòng chọn kho hàng', 'error')
        return false
      }
      if (!form.value.product_id) {
        showToast('Vui lòng chọn sản phẩm', 'error')
        return false
      }
      if (!form.value.quantity || form.value.quantity < 0) {
        showToast('Số lượng không được để trống và phải >= 0', 'error')
        return false
      }
      if (form.value.reserved_quantity && parseInt(form.value.reserved_quantity) > parseInt(form.value.quantity)) {
        showToast('Số lượng dự trữ không được lớn hơn tổng số lượng', 'error')
        return false
      }
      return true
    }

    const getStatusBadgeClass = (status) => {
      const classes = {
        'OUT_OF_STOCK': 'px-3 py-1 text-sm bg-red-100 text-red-800 rounded-full font-medium',
        'LOW_STOCK': 'px-3 py-1 text-sm bg-yellow-100 text-yellow-800 rounded-full font-medium',
        'OVER_STOCK': 'px-3 py-1 text-sm bg-blue-100 text-blue-800 rounded-full font-medium',
        'IN_STOCK': 'px-3 py-1 text-sm bg-green-100 text-green-800 rounded-full font-medium'
      }
      return classes[status] || 'px-3 py-1 text-sm bg-gray-100 text-gray-800 rounded-full font-medium'
    }

    const formatCurrency = (value) => {
      if (!value) return '0 ₫'
      return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND'
      }).format(value)
    }

    const showToast = (message, type = 'success') => {
      // Simple toast implementation - you can enhance this
      alert(message)
    }

    // Lifecycle
    onMounted(() => {
      if (props.preselectedWarehouseId) {
        form.value.warehouse_id = props.preselectedWarehouseId
        loadWarehouseProducts()
      }
    })

    return {
      // State
      loading,
      warehouseProducts,
      selectedProduct,
      form,
      
      // Computed
      calculatedAvailable,
      quantityDifference,
      newValue,
      
      // Methods
      loadWarehouseProducts,
      onProductSelect,
      calculateAvailableStock,
      handleSubmit,
      validateForm,
      getStatusBadgeClass,
      formatCurrency
    }
  }
}
</script>

<style scoped>
.animate-spin {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

.transition-colors {
  transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out;
}

.max-h-screen {
  max-height: 90vh;
}

input:focus, select:focus, textarea:focus {
  outline: none;
}
</style>