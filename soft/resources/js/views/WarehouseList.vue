<template>
  <div class="bg-white">
    <!-- Header -->
    <div class="p-6 border-b">
      <h1 class="text-2xl font-semibold text-gray-900">Quản lý kho hàng</h1>
    </div>

    <!-- Action Bar -->
    <div class="p-6 border-b bg-gray-50">
      <div class="flex items-center justify-between">
        <div class="flex items-center space-x-4">
          <button 
            class="flex items-center space-x-2 text-blue-600 hover:text-blue-800"
            @click="showStockModal"
          >
            <span>📦</span>
            <span>Tồn kho ({{ totalProducts }})</span>
          </button>
          <button 
            class="flex items-center space-x-2 text-yellow-600 hover:text-yellow-800"
            @click="showAlertsModal"
          >
            <span>⚠️</span>
            <span>Cảnh báo hết hàng</span>
          </button>
        </div>
        <button 
          class="bg-blue-500 text-white px-4 py-2 rounded flex items-center space-x-2 hover:bg-blue-600"
          @click="createWarehouse"
        >
          <span>+</span>
          <span>Thêm kho</span>
        </button>
      </div>
    </div>

    <!-- Search & Filter -->
    <div class="p-6 border-b">
      <div class="grid grid-cols-12 gap-4">
        <div class="col-span-4">
          <div class="relative">
            <input
              type="text"
              placeholder="Tìm kiếm tên kho, mã kho, địa chỉ..."
              class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
              v-model="filters.search"
              @input="debounceSearch"
            />
            <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400">🔍</span>
          </div>
        </div>
        <div class="col-span-3">
          <select 
            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500" 
            v-model="filters.status"
            @change="applyFilters"
          >
            <option value="">Tất cả trạng thái</option>
            <option value="active">Hoạt động</option>
            <option value="inactive">Ngưng hoạt động</option>
            <option value="maintenance">Bảo trì</option>
          </select>
        </div>
        <div class="col-span-3">
          <select 
            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500" 
            v-model="filters.sortBy"
            @change="applyFilters"
          >
            <option value="created_at">Ngày tạo</option>
            <option value="name">Tên kho</option>
            <option value="current_value">Giá trị hiện tại</option>
            <option value="capacity_usage_percent">Tỷ lệ sử dụng</option>
          </select>
        </div>
        <div class="col-span-2">
          <button 
            class="w-full px-3 py-2 border border-gray-300 rounded-md text-gray-600 hover:bg-gray-50"
            @click="resetFilters"
          >
            🔄 Reset
          </button>
        </div>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center items-center py-12">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
      <span class="ml-2 text-gray-600">Đang tải dữ liệu...</span>
    </div>

    <!-- Warehouse Grid -->
    <div v-else-if="warehouses.length > 0" class="p-6">
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <div 
          v-for="warehouse in warehouses" 
          :key="warehouse.id"
          class="border rounded-lg p-6 hover:shadow-lg transition-shadow bg-white"
        >
          <!-- Header -->
          <div class="flex justify-between items-start mb-4">
            <div>
              <h3 class="font-semibold text-gray-900 text-lg">{{ warehouse.name }}</h3>
              <p class="text-sm text-gray-500">{{ warehouse.code }}</p>
            </div>
            <span :class="getStatusClass(warehouse.status)">
              {{ getStatusText(warehouse.status) }}
            </span>
          </div>

          <!-- Basic Info -->
          <div class="space-y-2 text-sm mb-4">
            <div class="flex justify-between">
              <span class="text-gray-600">Quản lý:</span>
              <span class="font-medium">{{ warehouse.manager_name || 'Chưa có' }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-600">Điện thoại:</span>
              <span class="font-medium">{{ warehouse.phone || 'Chưa có' }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-600">Địa chỉ:</span>
              <span class="font-medium truncate">{{ warehouse.address || 'Chưa có' }}</span>
            </div>
          </div>

          <!-- Value Info -->
          <div class="bg-gray-50 rounded-lg p-4 mb-4">
            <div class="text-sm text-gray-600 mb-1">Giá trị hiện tại:</div>
            <div class="text-xl font-bold text-blue-600">{{ formatCurrency(warehouse.current_value) }}</div>
            <div class="text-xs text-gray-500 mt-1">
              Dung lượng: {{ formatCurrency(warehouse.capacity) }}
            </div>
          </div>

          <!-- Progress Bar -->
          <div class="mb-4">
            <div class="flex justify-between text-xs text-gray-500 mb-2">
              <span>Sử dụng</span>
              <span>{{ warehouse.capacity_usage_percent || 0 }}%</span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2">
              <div 
                class="h-2 rounded-full transition-all"
                :class="getProgressColor(warehouse.capacity_usage_percent || 0)"
                :style="{ width: (warehouse.capacity_usage_percent || 0) + '%' }"
              ></div>
            </div>
          </div>

          <!-- Stats -->
          <div class="grid grid-cols-2 gap-4 mb-4">
            <div class="bg-green-50 rounded-lg p-3 text-center">
              <div class="text-2xl font-bold text-green-600">{{ warehouse.total_product_types || 0 }}</div>
              <div class="text-xs text-gray-500">Loại sản phẩm</div>
            </div>
            <div class="bg-blue-50 rounded-lg p-3 text-center">
              <div class="text-2xl font-bold text-blue-600">{{ warehouse.total_products || 0 }}</div>
              <div class="text-xs text-gray-500">Tổng số lượng</div>
            </div>
          </div>

          <!-- Action Buttons -->
          <div class="grid grid-cols-2 gap-2 border-t pt-4">
            <button 
              class="bg-blue-500 text-white py-2 px-3 rounded text-sm hover:bg-blue-600 transition-colors"
              @click="viewWarehouseDetail(warehouse.id)"
            >
              👁️ Chi tiết
            </button>
            <button 
              class="bg-green-500 text-white py-2 px-3 rounded text-sm hover:bg-green-600 transition-colors"
              @click="quickStockAdjust(warehouse.id)"
            >
              📊 Điều chỉnh
            </button>
            <button 
              class="bg-yellow-500 text-white py-2 px-3 rounded text-sm hover:bg-yellow-600 transition-colors"
              @click="editWarehouse(warehouse)"
            >
              ✏️ Sửa
            </button>
            <button 
              class="bg-red-500 text-white py-2 px-3 rounded text-sm hover:bg-red-600 transition-colors"
              @click="deleteWarehouse(warehouse)"
            >
              🗑️ Xóa
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else class="text-center py-12">
      <div class="text-6xl mb-4">🏪</div>
      <h3 class="text-xl font-medium text-gray-900 mb-2">Chưa có kho hàng nào</h3>
      <p class="text-gray-500 mb-6">Hãy tạo kho hàng đầu tiên để bắt đầu quản lý tồn kho</p>
      <button 
        class="bg-blue-500 text-white px-6 py-3 rounded-lg hover:bg-blue-600 transition-colors"
        @click="createWarehouse"
      >
        + Thêm kho đầu tiên
      </button>
    </div>

    <!-- Pagination -->
    <div v-if="pagination.total > 0" class="px-6 py-4 border-t flex justify-between items-center bg-gray-50">
      <div class="text-sm text-gray-700">
        Hiển thị {{ pagination.from }}-{{ pagination.to }} của {{ pagination.total }} kho hàng
      </div>
      <div class="flex space-x-2">
        <button 
          class="px-3 py-1 border rounded text-sm hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed"
          :disabled="pagination.current_page <= 1"
          @click="changePage(pagination.current_page - 1)"
        >
          Trước
        </button>
        
        <button 
          v-for="page in visiblePages"
          :key="page"
          class="px-3 py-1 border rounded text-sm hover:bg-gray-100"
          :class="page === pagination.current_page ? 'bg-blue-500 text-white border-blue-500' : 'bg-white'"
          @click="changePage(page)"
        >
          {{ page }}
        </button>
        
        <button 
          class="px-3 py-1 border rounded text-sm hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed"
          :disabled="pagination.current_page >= pagination.last_page"
          @click="changePage(pagination.current_page + 1)"
        >
          Tiếp
        </button>
      </div>
    </div>

    <!-- Modals -->
    <WarehouseForm 
      v-if="showWarehouseForm"
      :warehouse="selectedWarehouse"
      @close="closeWarehouseForm"
      @saved="handleWarehouseSaved"
    />

    <WarehouseDetail
      v-if="showDetail"
      :warehouse-id="selectedWarehouseId"
      @close="closeWarehouseDetail"
      @back-to-list="closeWarehouseDetail"
    />

    <StockOverviewModal
      v-if="showStockOverview"
      @close="closeStockModal"
    />

    <AlertsModal
      v-if="showAlerts"
      @close="closeAlertsModal"
    />

    <!-- Stock Adjust Modal -->
    <StockAdjustModal
      v-if="showStockAdjustModal"
      :warehouses="warehouses"
      :preselected-warehouse-id="selectedWarehouseForAdjust"
      @close="closeStockAdjustModal"
      @updated="handleStockUpdated"
    />

    <!-- Toast Notification -->
    <div 
      v-if="toast.show"
      class="fixed top-4 right-4 z-50 px-4 py-2 rounded-lg text-white font-medium transform transition-transform duration-300"
      :class="[
        toast.type === 'success' ? 'bg-green-500' : 
        toast.type === 'error' ? 'bg-red-500' : 
        'bg-yellow-500'
      ]"
    >
      {{ toast.message }}
    </div>
  </div>
</template>

<script>
import { ref, computed, onMounted, watch } from 'vue'
import WarehouseForm from '../components/WarehouseForm.vue'
import WarehouseDetail from '../components/WarehouseDetail.vue'
import StockOverviewModal from '../components/StockOverviewModal.vue'
import AlertsModal from '../components/AlertsModal.vue'
import StockAdjustModal from '../components/StockAdjustModal.vue'
import warehouseApi from '../api/warehouseApi'

export default {
  name: 'WarehouseList',
  components: {
    WarehouseForm,
    WarehouseDetail,
    StockOverviewModal,
    AlertsModal,
    StockAdjustModal
  },
  setup() {
    // State
    const warehouses = ref([])
    const loading = ref(false)
    const selectedWarehouse = ref(null)
    const selectedWarehouseId = ref(null)
    const selectedWarehouseForAdjust = ref(null)
    const searchTimeout = ref(null)
    
    // Modals
    const showWarehouseForm = ref(false)
    const showDetail = ref(false)
    const showStockOverview = ref(false)
    const showAlerts = ref(false)
    const showStockAdjustModal = ref(false)
    
    // Filters
    const filters = ref({
      search: '',
      status: '',
      sortBy: 'created_at'
    })
    
    // Pagination
    const pagination = ref({
      current_page: 1,
      last_page: 1,
      per_page: 9,
      total: 0,
      from: 0,
      to: 0
    })
    
    // Toast
    const toast = ref({
      show: false,
      message: '',
      type: 'success'
    })

    // Computed
    const totalProducts = computed(() => {
      return warehouses.value.reduce((sum, w) => sum + (w.total_products || 0), 0)
    })

    const visiblePages = computed(() => {
      const pages = []
      const current = pagination.value.current_page
      const last = pagination.value.last_page
      
      for (let i = Math.max(1, current - 2); i <= Math.min(last, current + 2); i++) {
        pages.push(i)
      }
      
      return pages
    })

    // Methods
    const loadWarehouses = async (page = 1) => {
      loading.value = true
      
      try {
        const params = {
          page,
          per_page: pagination.value.per_page,
          search: filters.value.search,
          status: filters.value.status,
          sort_by: filters.value.sortBy
        }
        
        const response = await warehouseApi.getWarehouses(params)
        
        if (response.success) {
          warehouses.value = response.data.data || []
          pagination.value = response.data.pagination || pagination.value
        } else {
          showToast('Không thể tải danh sách kho hàng', 'error')
        }
      } catch (error) {
        console.error('Error loading warehouses:', error)
        showToast('Lỗi kết nối API', 'error')
      } finally {
        loading.value = false
      }
    }

    const debounceSearch = () => {
      clearTimeout(searchTimeout.value)
      searchTimeout.value = setTimeout(() => {
        applyFilters()
      }, 500)
    }

    const applyFilters = () => {
      pagination.value.current_page = 1
      loadWarehouses(1)
    }

    const resetFilters = () => {
      filters.value = {
        search: '',
        status: '',
        sortBy: 'created_at'
      }
      applyFilters()
    }

    const changePage = (page) => {
      if (page >= 1 && page <= pagination.value.last_page) {
        loadWarehouses(page)
      }
    }

    const createWarehouse = () => {
      selectedWarehouse.value = null
      showWarehouseForm.value = true
    }

    const editWarehouse = (warehouse) => {
      selectedWarehouse.value = warehouse
      showWarehouseForm.value = true
    }

    const closeWarehouseForm = () => {
      showWarehouseForm.value = false
      selectedWarehouse.value = null
    }

    const handleWarehouseSaved = () => {
      showToast(selectedWarehouse.value ? 'Cập nhật kho hàng thành công' : 'Tạo kho hàng thành công', 'success')
      closeWarehouseForm()
      loadWarehouses(pagination.value.current_page)
    }

    const viewWarehouseDetail = (id) => {
      selectedWarehouseId.value = id
      showDetail.value = true
    }

    const closeWarehouseDetail = () => {
      showDetail.value = false
      selectedWarehouseId.value = null
      loadWarehouses(pagination.value.current_page)
    }

    const deleteWarehouse = async (warehouse) => {
      if (!confirm(`Bạn có chắc muốn xóa kho "${warehouse.name}"?`)) return
      
      try {
        const response = await warehouseApi.deleteWarehouse(warehouse.id)
        
        if (response.success) {
          showToast('Xóa kho hàng thành công', 'success')
          loadWarehouses(pagination.value.current_page)
        } else {
          showToast(response.message || 'Không thể xóa kho hàng', 'error')
        }
      } catch (error) {
        console.error('Error deleting warehouse:', error)
        showToast('Lỗi khi xóa kho hàng', 'error')
      }
    }

    // Stock Adjust Modal Methods
    const quickStockAdjust = (warehouseId) => {
      selectedWarehouseForAdjust.value = warehouseId
      showStockAdjustModal.value = true
    }

    const closeStockAdjustModal = () => {
      showStockAdjustModal.value = false
      selectedWarehouseForAdjust.value = null
    }

    const handleStockUpdated = (updatedData) => {
      showToast('Cập nhật tồn kho thành công!', 'success')
      closeStockAdjustModal()
      loadWarehouses(pagination.value.current_page)
    }

    // Other Modal Methods
    const showStockModal = () => {
      showStockOverview.value = true
    }

    const closeStockModal = () => {
      showStockOverview.value = false
    }

    const showAlertsModal = () => {
      showAlerts.value = true
    }

    const closeAlertsModal = () => {
      showAlerts.value = false
    }

    const showToast = (message, type = 'success') => {
      toast.value = {
        show: true,
        message,
        type
      }
      
      setTimeout(() => {
        toast.value.show = false
      }, 3000)
    }

    // Helper methods
    const getStatusClass = (status) => {
      const classes = {
        active: 'px-2 py-1 text-xs bg-green-100 text-green-800 rounded-full font-medium',
        inactive: 'px-2 py-1 text-xs bg-red-100 text-red-800 rounded-full font-medium',
        maintenance: 'px-2 py-1 text-xs bg-yellow-100 text-yellow-800 rounded-full font-medium'
      }
      return classes[status] || 'px-2 py-1 text-xs bg-gray-100 text-gray-800 rounded-full font-medium'
    }

    const getStatusText = (status) => {
      return {
        active: 'Hoạt động',
        inactive: 'Ngưng hoạt động',
        maintenance: 'Bảo trì'
      }[status] || 'Không xác định'
    }

    const getProgressColor = (percent) => {
      if (percent >= 90) return 'bg-red-500'
      if (percent >= 75) return 'bg-yellow-500'
      return 'bg-green-500'
    }

    const formatCurrency = (value) => {
      if (!value) return '0 ₫'
      return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND'
      }).format(value)
    }

    // Lifecycle
    onMounted(() => {
      loadWarehouses()
    })

    return {
      // State
      warehouses,
      loading,
      selectedWarehouse,
      selectedWarehouseId,
      selectedWarehouseForAdjust,
      filters,
      pagination,
      toast,
      
      // Modals
      showWarehouseForm,
      showDetail,
      showStockOverview,
      showAlerts,
      showStockAdjustModal,
      
      // Computed
      totalProducts,
      visiblePages,
      
      // Methods
      loadWarehouses,
      debounceSearch,
      applyFilters,
      resetFilters,
      changePage,
      createWarehouse,
      editWarehouse,
      closeWarehouseForm,
      handleWarehouseSaved,
      viewWarehouseDetail,
      closeWarehouseDetail,
      deleteWarehouse,
      
      // Stock Adjust Methods
      quickStockAdjust,
      closeStockAdjustModal,
      handleStockUpdated,
      
      // Other Modal Methods
      showStockModal,
      closeStockModal,
      showAlertsModal,
      closeAlertsModal,
      showToast,
      
      // Helpers
      getStatusClass,
      getStatusText,
      getProgressColor,
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

.transition-all {
  transition: all 0.3s ease;
}

.transition-colors {
  transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out;
}

.transition-shadow {
  transition: box-shadow 0.15s ease-in-out;
}
</style>