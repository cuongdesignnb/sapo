<template>
  <div class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center">
  <div class="bg-white rounded-xl shadow-lg w-[98vw] max-w-screen-2xl overflow-y-auto max-h-[95vh] relative">

    <button 
      @click="$emit('close')" 
      class="absolute top-3 right-3 text-xl text-gray-400 hover:text-gray-700"
      title="Đóng"
    >✕</button>
    <!-- Loading -->
    <div v-if="loading" class="flex justify-center items-center py-12">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
      <span class="ml-2 text-gray-600">Đang tải dữ liệu...</span>
    </div>

    <!-- Content -->
    <div v-else-if="warehouse">
      <!-- Header -->
      <div class="p-6 border-b bg-gradient-to-r from-blue-50 to-indigo-50">
        <div class="flex justify-between items-start">
          <div>
            <nav class="text-sm text-gray-500 mb-2">
              <button @click="$emit('back-to-list')" class="hover:text-blue-600">← Danh sách kho</button>
              <span class="mx-2">/</span>
              <span>{{ warehouse.name }}</span>
            </nav>
            <div class="flex items-center space-x-4">
              <h1 class="text-3xl font-bold text-gray-900">{{ warehouse.name }}</h1>
              <span :class="getStatusClass(warehouse.status)">
                {{ getStatusText(warehouse.status) }}
              </span>
            </div>
            <p class="text-gray-600 mt-1">{{ warehouse.code }}</p>
          </div>
          <div class="flex space-x-3">
            <button 
              class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition-colors"
              @click="editWarehouse"
            >
              ✏️ Chỉnh sửa
            </button>
            <button 
              class="bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600 transition-colors"
              @click="openBulkTransferModal"
            >
              🔄 Chuyển kho hàng loạt
            </button>
          </div>
        </div>
      </div>

      <!-- Stats Cards -->
      <div class="p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
          <!-- Giá trị hiện tại -->
          <div class="bg-gradient-to-br from-blue-500 to-blue-600 text-white rounded-xl p-6">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-blue-100 text-sm">Giá trị hiện tại</p>
                <p class="text-2xl font-bold">{{ formatCurrency(warehouse.current_value) }}</p>
              </div>
              <div class="bg-blue-400 bg-opacity-30 rounded-lg p-3">
                <span class="text-2xl">💰</span>
              </div>
            </div>
          </div>

          <!-- Tỷ lệ sử dụng -->
          <div class="bg-gradient-to-br from-green-500 to-green-600 text-white rounded-xl p-6">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-green-100 text-sm">Tỷ lệ sử dụng</p>
                <p class="text-2xl font-bold">{{ warehouse.capacity_usage_percent || 0 }}%</p>
              </div>
              <div class="bg-green-400 bg-opacity-30 rounded-lg p-3">
                <span class="text-2xl">📊</span>
              </div>
            </div>
          </div>

          <!-- Loại sản phẩm -->
          <div class="bg-gradient-to-br from-purple-500 to-purple-600 text-white rounded-xl p-6">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-purple-100 text-sm">Loại sản phẩm</p>
                <p class="text-2xl font-bold">{{ warehouse.total_product_types || 0 }}</p>
              </div>
              <div class="bg-purple-400 bg-opacity-30 rounded-lg p-3">
                <span class="text-2xl">📦</span>
              </div>
            </div>
          </div>

          <!-- Tổng số lượng -->
          <div class="bg-gradient-to-br from-orange-500 to-orange-600 text-white rounded-xl p-6">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-orange-100 text-sm">Tổng số lượng</p>
                <p class="text-2xl font-bold">{{ warehouse.total_products || 0 }}</p>
              </div>
              <div class="bg-orange-400 bg-opacity-30 rounded-lg p-3">
                <span class="text-2xl">📋</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
          <!-- Capacity Chart -->
          <div class="lg:col-span-2">
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
              <div class="p-6 border-b">
                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                  <span class="mr-2">📈</span>
                  Công suất kho
                </h3>
              </div>
              <div class="p-6">
                <!-- Progress Bar -->
                <div class="mb-6">
                  <div class="flex justify-between text-sm text-gray-600 mb-2">
                    <span>Đã sử dụng: {{ formatCurrency(warehouse.current_value) }}</span>
                    <span>{{ warehouse.capacity_usage_percent || 0 }}%</span>
                  </div>
                  <div class="w-full bg-gray-200 rounded-full h-4">
                    <div 
                      class="h-4 rounded-full transition-all duration-500"
                      :class="getProgressColor(warehouse.capacity_usage_percent || 0)"
                      :style="{ width: (warehouse.capacity_usage_percent || 0) + '%' }"
                    ></div>
                  </div>
                  <div class="flex justify-between text-xs text-gray-500 mt-1">
                    <span>0</span>
                    <span>{{ formatCurrency(warehouse.capacity) }}</span>
                  </div>
                </div>

                <!-- Capacity Stats -->
                <div class="grid grid-cols-3 gap-4">
                  <div class="text-center p-4 bg-gray-50 rounded-lg">
                    <div class="text-sm text-gray-600">Còn lại</div>
                    <div class="text-lg font-bold text-green-600">
                      {{ formatCurrency((warehouse.capacity || 0) - (warehouse.current_value || 0)) }}
                    </div>
                  </div>
                  <div class="text-center p-4 bg-gray-50 rounded-lg">
                    <div class="text-sm text-gray-600">Tổng dung lượng</div>
                    <div class="text-lg font-bold text-blue-600">
                      {{ formatCurrency(warehouse.capacity) }}
                    </div>
                  </div>
                  <div class="text-center p-4 bg-gray-50 rounded-lg">
                    <div class="text-sm text-gray-600">Trạng thái</div>
                    <div :class="getCapacityStatusClass(warehouse.capacity_usage_percent || 0)">
                      {{ getCapacityStatusText(warehouse.capacity_usage_percent || 0) }}
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Warehouse Info -->
          <div class="lg:col-span-1">
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
              <div class="p-6 border-b">
                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                  <span class="mr-2">ℹ️</span>
                  Thông tin kho
                </h3>
              </div>
              <div class="p-6">
                <div class="space-y-4">
                  <div class="flex justify-between">
                    <span class="text-gray-600">Mã kho:</span>
                    <span class="font-medium">{{ warehouse.code }}</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-600">Quản lý:</span>
                    <span class="font-medium">{{ warehouse.manager_name || 'Chưa có' }}</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-600">Điện thoại:</span>
                    <span class="font-medium">{{ warehouse.phone || 'Chưa có' }}</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-600">Email:</span>
                    <span class="font-medium">{{ warehouse.email || 'Chưa có' }}</span>
                  </div>
                  <div class="pt-2 border-t">
                    <span class="text-gray-600">Địa chỉ:</span>
                    <p class="font-medium mt-1">{{ warehouse.address || 'Chưa có' }}</p>
                  </div>
                  <div v-if="warehouse.note" class="pt-2 border-t">
                    <span class="text-gray-600">Ghi chú:</span>
                    <p class="font-medium mt-1">{{ warehouse.note }}</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Products Table -->
<div class="p-6">
  <div v-if="products.length" class="overflow-x-auto">
    <!-- BỘ LỌC & SEARCH -->
    <div class="flex flex-wrap gap-2 mb-3 items-center">
      <select class="px-3 py-1 border border-gray-300 rounded-md text-sm" v-model="stockFilter">
        <option value="">Tất cả trạng thái</option>
        <option value="in_stock">Bình thường</option>
        <option value="low_stock">Sắp hết</option>
        <option value="out_of_stock">Hết hàng</option>
        <option value="over_stock">Dư thừa</option>
      </select>
      <input
        type="text"
        class="px-3 py-1 border border-gray-300 rounded-md text-sm"
        placeholder="Tìm sản phẩm..."
        v-model="productSearch"
        style="width: 200px;"
      >
    </div>
    <!-- TABLE BẮT ĐẦU Ở ĐÂY -->
    <table class="w-full table-auto border border-gray-200">
      <thead>
        <tr>
          <th class="px-4 py-2 border">Mã SP</th>
          <th class="px-4 py-2 border">Tên sản phẩm</th>
          <th class="px-4 py-2 border">Barcode</th>
          <th class="px-4 py-2 border">Loại</th>
          <th class="px-4 py-2 border">SL Tồn</th>
          <th class="px-4 py-2 border">Trạng thái</th>
          <th class="px-4 py-2 border">Giá nhập</th>
          <th class="px-4 py-2 border">Tổng giá trị</th>
          <th class="px-4 py-2 border">Thao tác</th>
        </tr>
      </thead>
      <tbody>
  <tr v-for="p in filteredProducts" :key="p.id">
    <td class="px-4 py-2 border">{{ p.product?.sku }}</td>
    <td class="px-4 py-2 border">{{ p.product?.name }}</td>
    <td class="px-4 py-2 border">{{ p.product?.barcode }}</td>
    <td class="px-4 py-2 border">{{ p.product?.category_name }}</td>
    <td class="px-4 py-2 border text-center">{{ p.available_stock }}</td>
    <td class="px-4 py-2 border text-center">
      <span :class="{
        'text-green-600': p.stock_status_label === 'Bình thường',
        'text-yellow-600': p.stock_status_label === 'Sắp hết',
        'text-red-600': p.stock_status_label === 'Hết hàng'
      }">
        {{ p.stock_status_label }}
      </span>
    </td>
    <td class="px-4 py-2 border text-right">{{ formatCurrency(p.cost) }}</td>
    <td class="px-4 py-2 border text-right">{{ formatCurrency(p.total_value) }}</td>
    <!-- Cột Thao tác (nút chuyển kho) -->
    <td class="px-4 py-2 border text-center">
      <button
        class="px-3 py-1 bg-blue-500 text-white rounded hover:bg-blue-600"
        @click="openTransferModal(p)"
        title="Chuyển sản phẩm này sang kho khác"
      >
        Chuyển kho
      </button>
    </td>
  </tr>
</tbody>

    </table>
  </div>
  <div v-else class="text-center py-8 text-gray-500">
    <div class="text-4xl mb-2">📦</div>
    <p>Không có sản phẩm nào trong kho</p>
  </div>
</div>


      </div>
    </div>

    <!-- Error State -->
    <div v-else class="text-center py-12">
      <div class="text-4xl mb-4">⚠️</div>
      <h3 class="text-lg font-medium text-gray-900 mb-2">Không tìm thấy kho hàng</h3>
      <button 
        class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600"
        @click="$emit('back-to-list')"
      >
        ← Quay lại danh sách
      </button>
    </div>

    <!-- Edit Modal -->
    <WarehouseForm 
      v-if="showEditForm"
      :warehouse="warehouse"
      @close="closeEditForm"
      @saved="handleWarehouseSaved"
    />

    <!-- Transfer Modal -->
    <div v-if="showTransferModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg p-6 w-full max-w-md">
        <h3 class="text-lg font-semibold mb-4">Chuyển sản phẩm</h3>
        <div class="text-center py-8">
          <p class="text-gray-500">Tính năng chuyển kho đang phát triển...</p>
        </div>
        <div class="flex justify-end">
          <button 
            @click="showTransferModal = false" 
            class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600"
          >
            Đóng
          </button>
        </div>
      </div>
    </div>
    </div>
  </div>
  
  <!-- Individual Product Transfer Modal -->
  <ProductTransferModal
    v-if="showProductTransferModal"
    :open="showProductTransferModal"
    :product="selectedProductToTransfer"
    :fromWarehouse="warehouse"   
    :warehouses="otherWarehouses"
    @close="closeProductTransferModal"
    @transferred="handleProductTransferred"
  />

  <!-- Bulk Transfer Modal -->
  <BulkTransferModal
    v-if="showBulkTransferModal"
    :open="showBulkTransferModal"
    :fromWarehouse="warehouse"
    :warehouses="otherWarehouses"
    :products="products"
    @close="closeBulkTransferModal"
    @transferred="handleBulkTransferred"
  />

</template>

<script>
import WarehouseForm from './WarehouseForm.vue'
import warehouseApi from '../api/warehouseApi.js'
import ProductTransferModal from './ProductTransferModal.vue'
import BulkTransferModal from '../components/BulkTransferModal.vue'

export default {
  name: 'WarehouseDetail',
  components: {
    WarehouseForm,
    ProductTransferModal,
    BulkTransferModal
  },
  props: {
    warehouseId: {
      type: [String, Number],
      required: true
    }
  },
  emits: ['close', 'back-to-list'],
  data() {
    return {
      warehouse: null,
      loading: false,
      showEditForm: false,
      showTransferModal: false,
      selectedProductToTransfer: null,
      showProductTransferModal: false,
      otherWarehouses: [],
      stockFilter: '',
      productSearch: '',
      products: [],
      showBulkTransferModal: false,
    }
  },
  created() {
    this.loadWarehouse()
  },
  watch: {
    warehouseId: {
      handler(newId) {
        if (newId) {
          this.loadWarehouse()
        }
      },
      immediate: true
    }
  },
  computed: {
    filteredProducts() {
      let list = this.products
      if (this.stockFilter === 'in_stock') {
        list = list.filter(p => p.available_stock > 5)
      } else if (this.stockFilter === 'low_stock') {
        list = list.filter(p => p.available_stock > 0 && p.available_stock <= 5)
      } else if (this.stockFilter === 'out_of_stock') {
        list = list.filter(p => p.available_stock === 0)
      } else if (this.stockFilter === 'over_stock') {
        list = list.filter(p => p.available_stock > 100)
      }
      if (this.productSearch) {
        const kw = this.productSearch.toLowerCase()
        list = list.filter(
          p =>
            (p.product?.sku && p.product.sku.toLowerCase().includes(kw)) ||
            (p.product?.name && p.product.name.toLowerCase().includes(kw)) ||
            (p.product?.barcode && p.product.barcode.toLowerCase().includes(kw))
        )
      }
      return list
    }
  },
  methods: {
    async loadWarehouse() {
      this.loading = true
      try {
        const response = await warehouseApi.getWarehouse(this.warehouseId)
        if (response.success) {
          this.warehouse = response.data.warehouse
          await this.loadWarehouseProducts()
          await this.loadOtherWarehouses()
        }
      } catch (error) {
        // fallback demo data
        this.warehouse = {
          id: this.warehouseId,
          code: 'WH001',
          name: 'Kho Hà Nội',
          status: 'active',
          manager_name: 'Nguyễn Văn A',
          phone: '0243856789',
          email: 'hanoi@warehouse.com',
          address: '123 Đường Láng, Đống Đa, Hà Nội',
          capacity: 200000000,
          current_value: 150000000,
          capacity_usage_percent: 75,
          total_product_types: 15,
          total_products: 150,
          note: 'Kho chính miền Bắc'
        }
        this.products = []
        this.otherWarehouses = []
      } finally {
        this.loading = false
      }
    },

    async loadWarehouseProducts() {
      try {
        const response = await warehouseApi.getWarehouseProducts(this.warehouseId)
        if (response.success && Array.isArray(response.data.data)) {
          this.products = response.data.data
        } else {
          this.products = []
        }
      } catch (error) {
        this.products = []
      }
    },

    async loadOtherWarehouses() {
      try {
        const res = await warehouseApi.getWarehouses()
        // Đúng chuẩn API trả về object {data: Array, ...} nên lấy .data.data
        if (res.success && Array.isArray(res.data.data)) {
          this.otherWarehouses = res.data.data.filter(
            w => String(w.id) !== String(this.warehouse.id)
          )
        } else {
          this.otherWarehouses = []
        }
      } catch (error) {
        this.otherWarehouses = []
      }
    },

    openTransferModal(product) {
      this.selectedProductToTransfer = product
      this.showProductTransferModal = true
      this.loadOtherWarehouses()
    },

    closeProductTransferModal() {
      this.showProductTransferModal = false
      this.selectedProductToTransfer = null
    },

    async handleProductTransferred() {
      this.showProductTransferModal = false
      this.selectedProductToTransfer = null
      await this.loadWarehouseProducts()
    },

    openBulkTransferModal() {
      this.showBulkTransferModal = true
      this.loadOtherWarehouses()
    },

    closeBulkTransferModal() {
      this.showBulkTransferModal = false
    },

    async handleBulkTransferred() {
      this.showBulkTransferModal = false
      await this.loadWarehouseProducts()
    },

    editWarehouse() {
      this.showEditForm = true
    },

    closeEditForm() {
      this.showEditForm = false
    },

    handleWarehouseSaved(updatedWarehouse) {
      this.warehouse = { ...this.warehouse, ...updatedWarehouse }
      this.closeEditForm()
    },

    getStatusClass(status) {
      const classes = {
        active: 'px-3 py-1 text-sm bg-green-100 text-green-800 rounded-full font-medium',
        inactive: 'px-3 py-1 text-sm bg-red-100 text-red-800 rounded-full font-medium',
        maintenance: 'px-3 py-1 text-sm bg-yellow-100 text-yellow-800 rounded-full font-medium'
      }
      return classes[status] || 'px-3 py-1 text-sm bg-gray-100 text-gray-800 rounded-full font-medium'
    },

    getStatusText(status) {
      return {
        active: 'Hoạt động',
        inactive: 'Ngưng hoạt động',
        maintenance: 'Bảo trì'
      }[status] || 'Không xác định'
    },

    getProgressColor(percent) {
      if (percent >= 90) return 'bg-red-500'
      if (percent >= 75) return 'bg-yellow-500'
      return 'bg-green-500'
    },

    getCapacityStatusClass(percent) {
      if (percent >= 90) return 'text-lg font-bold text-red-600'
      if (percent >= 75) return 'text-lg font-bold text-yellow-600'
      return 'text-lg font-bold text-green-600'
    },

    getCapacityStatusText(percent) {
      if (percent >= 90) return 'Gần đầy'
      if (percent >= 75) return 'Cảnh báo'
      return 'Bình thường'
    },

    formatCurrency(amount) {
      if (!amount) return '0 ₫'
      return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND'
      }).format(amount)
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

.transition-all {
  transition: all 0.3s ease;
}
</style>