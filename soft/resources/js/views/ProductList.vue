<template>
  <div class="bg-white">
    <!-- Header -->
    <div class="p-6 border-b">
      <h1 class="text-2xl font-semibold text-gray-900">Danh sách sản phẩm</h1>
       <!-- ← THÊM BLOCK NÀY -->
      <div v-if="warehouseContext" class="mt-2 p-2 bg-blue-50 border border-blue-200 rounded text-sm">
        <span class="font-medium">
          {{ warehouseContext.mode === 'global_view' ? '🌐 View All Warehouses' : `🏭 Kho: ${warehouseContext.warehouse_name}` }}
        </span>
      </div>
    </div>

    <!-- Action Buttons -->
    <div class="p-6 border-b bg-gray-50">
      <div class="flex items-center justify-between">
        <div class="flex items-center space-x-4">
          <button 
            class="flex items-center space-x-2 text-gray-600 hover:text-gray-800"
            @click="exportProducts"
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
          @click="createProduct"
        >
          <span>+</span>
          <span>Thêm sản phẩm</span>
        </button>
      </div>
    </div>

    <!-- Filters -->
    <div class="p-6 border-b">
      <div class="mb-4">
        <button class="text-blue-500 border-b-2 border-blue-500 pb-2">
          Tất cả sản phẩm ({{ pagination.total || 0 }})
        </button>
      </div>
      
      <div class="grid grid-cols-12 gap-4 items-center">
        <!-- Search -->
        <div class="col-span-4">
          <div class="relative">
            <input
              type="text"
              placeholder="Tìm kiếm theo mã sản phẩm, tên sản phẩm, barcode"
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
            v-model="filters.category_name"
            @change="applyFilters"
          >
            <option value="">Loại sản phẩm</option>
            <option v-for="category in categories" :key="category" :value="category">
              {{ category }}
            </option>
          </select>
        </div>
        
        <div class="col-span-2">
          <select 
            class="w-full px-3 py-2 border border-gray-300 rounded-md"
            v-model="filters.sort_field"
            @change="applyFilters"
          >
            <option value="created_at">Ngày tạo</option>
            <option value="name">Tên sản phẩm</option>
            <option value="retail_price">Giá bán</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading && products.length === 0" class="flex justify-center items-center py-12">
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
            <th class="text-left p-4 text-sm font-medium text-gray-600">Ảnh</th>
            <th class="text-left p-4 text-sm font-medium text-gray-600">Sản phẩm</th>
            <th class="text-left p-4 text-sm font-medium text-gray-600">Loại</th>
            <th class="text-left p-4 text-sm font-medium text-gray-600">Nhãn hiệu</th>
            <th class="text-left p-4 text-sm font-medium text-gray-600">Có thể bán</th>
            <th class="text-left p-4 text-sm font-medium text-gray-600">Tồn kho</th>
            <th class="text-left p-4 text-sm font-medium text-gray-600 cursor-pointer" @click="sortBy('created_at')">
              Ngày khởi tạo
              <span class="ml-1">↕</span>
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
  <tr 
    v-for="product in products" 
    :key="product.id"
    class="hover:bg-gray-50 cursor-pointer"
    @click="viewProduct(product)"
  >
    <!-- Checkbox -->
    <td class="p-4">
      <input 
        type="checkbox" 
        class="rounded" 
        :checked="selectedIds.includes(product.id)"
        @click.stop="toggleSelection(product.id)"
      />
    </td>
    
    <!-- Ảnh -->
    <td class="p-4">
      <div class="w-12 h-12 bg-gray-200 rounded flex items-center justify-center">
        📷
      </div>
    </td>
    
    <!-- Sản phẩm -->
    <td class="p-4">
      <div class="text-blue-500 hover:text-blue-700 font-medium">
        {{ product.name }}
      </div>
      <div class="text-xs text-gray-500">SKU: {{ product.sku }}</div>
    </td>
    
    <!-- Loại -->
    <td class="p-4 text-sm text-gray-600">
      {{ product.category_name || '' }}
    </td>
    
    <!-- Nhãn hiệu -->
    <td class="p-4 text-sm text-gray-600">
      {{ product.brand_name || '' }}
    </td>
    
    <!-- Tồn kho -->
    <td class="p-4 text-sm text-gray-600">
      <div v-if="warehouseContext?.mode === 'warehouse_mode'">
        {{ product.stock || 0 }}
        <div class="text-xs text-gray-400">Kho: {{ warehouseContext.warehouse_name }}</div>
      </div>
      <div v-else-if="warehouseContext?.mode === 'global_view'">
        {{ product.total_stock || 0 }}
        <div class="text-xs text-gray-400">Tổng tất cả kho</div>
      </div>
      <div v-else>
        {{ product.quantity || 0 }}
        <div class="text-xs text-gray-400">Tổng kho</div>
      </div>
    </td>
    
    <!-- Có thể bán -->
    <td class="p-4 text-sm text-gray-600">
      <div v-if="warehouseContext?.mode === 'warehouse_mode'">
        {{ (product.stock || 0) - (product.reserved_quantity || 0) }}
      </div>
      <div v-else-if="warehouseContext?.mode === 'global_view'">
        {{ (product.total_stock || 0) - (product.reserved_total || 0) }}
      </div>
      <div v-else>
        {{ (product.quantity || 0) - (product.reserved_quantity || 0) }}
      </div>
      <div class="text-xs text-gray-400">Có thể bán</div>
    </td>
    
    <!-- Ngày khởi tạo -->
    <td class="p-4 text-sm text-gray-600">
      {{ formatDate(product.created_at) }}
    </td>
  </tr>
  
  <!-- Empty State -->
  <tr v-if="products.length === 0 && !loading">
    <td colspan="8" class="text-center py-12 text-gray-500">
      <div class="text-4xl mb-4">📦</div>
      <div>Không có sản phẩm nào</div>
    </td>
  </tr>
</tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="pagination.total > 0" class="px-6 py-4 border-t flex justify-between items-center">
      <div class="text-sm text-gray-700">
        Hiển thị {{ pagination.from }}-{{ pagination.to }} của {{ pagination.total }} sản phẩm
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

    <!-- Product Detail Modal -->
    <ProductDetail 
  v-if="showDetail"
  :product="selectedProduct"
  @close="closeDetail"
  @save="saveProduct"
  @delete="deleteProduct"
  @edit="editFromDetail"
/>

    <!-- Product Form Modal -->
    <ProductForm
      v-if="showForm"
      :product="editingProduct"
      :categories="categories"
      :brands="brands"
      @close="closeForm"
      @save="saveProduct"
    />

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
    <!-- Import Modal -->
<div v-if="showImportModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50">
  <div class="bg-white rounded-lg p-6 w-full max-w-md">
    <div class="flex justify-between items-center mb-4">
      <h3 class="text-lg font-medium text-gray-900">Nhập file sản phẩm</h3>
      <button @click="closeImportModal" class="text-gray-400 hover:text-gray-600">×</button>
    </div>

    <div class="space-y-4">
      <div class="text-center">
        <button @click="downloadTemplate" class="text-blue-500 hover:text-blue-700 underline text-sm">
          📥 Tải file mẫu (.csv)
        </button>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Chọn file CSV</label>
        <input type="file" accept=".csv" @change="handleFileChange" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" />
      </div>
    </div>

    <div class="flex justify-end space-x-3 mt-6">
      <button @click="closeImportModal" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300">Hủy</button>
      <button @click="importProducts" :disabled="!importFile || importing" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 disabled:opacity-50">
        <span v-if="importing">⏳ Đang nhập...</span>
        <span v-else">📤 Nhập file</span>
      </button>
    </div>
  </div>
</div>
  </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue'
import ProductDetail from '../components/ProductDetail.vue'
import ProductForm from '../components/ProductForm.vue'
import { productApi, masterDataApi } from '../api/productApi'
import warehouseApi from '../api/warehouseApi'


export default {
  name: 'ProductList',
  components: {
    ProductDetail,
    ProductForm
  },
  setup() {
    // Reactive data
    const loading = ref(false)
    const deleting = ref(false)
    const products = ref([])
    const categories = ref([])
    const brands = ref([])
    const selectedIds = ref([])
    const searchQuery = ref('')
    const showDetail = ref(false)
    const showForm = ref(false)
    const showImportModal = ref(false)
    const importing = ref(false)
    const importFile = ref(null)
    const selectedProduct = ref(null)
    const editingProduct = ref(null)
    const currentWarehouse = ref(null)
    const warehouseContext = ref(null)

    // Notification
    const notification = ref({
      show: false,
      type: 'success',
      message: ''
    })

    // Filters
    const filters = ref({
      category_name: '',
      brand_name: '',
      sort_field: 'created_at',
      sort_direction: 'desc'
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
    const editFromDetail = (product) => {
  editingProduct.value = product
  showDetail.value = false
  showForm.value = true
}

    // Computed
    const isAllSelected = computed(() => {
      return products.value.length > 0 && 
             selectedIds.value.length === products.value.length
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
    const fetchProducts = async () => {
  loading.value = true
  
  try {
    const params = {
      ...filters.value,
      search: searchQuery.value,
      page: pagination.value.current_page,
      per_page: pagination.value.per_page
    }

    // ← THÊM: Gửi warehouse_id nếu có
    if (currentWarehouse.value?.id) {
      params.warehouse_id = currentWarehouse.value.id
    }

    const response = await productApi.getAll(params)

    if (response.success) {
      products.value = response.data || []
      pagination.value = response.pagination || {}
      warehouseContext.value = response.context || null  // ← THÊM DÒNG NÀY
    }
  } catch (error) {
    console.error('Error fetching products:', error)
    showNotification('Lỗi khi tải dữ liệu', 'error')
  } finally {
    loading.value = false
  }
}

    const loadMasterData = async () => {
      try {
        const [categoriesRes, brandsRes] = await Promise.all([
          masterDataApi.getCategories(),
          masterDataApi.getBrands()
        ])

    const getCurrentWarehouse = async () => {
  try {
    const response = await warehouseApi.getCurrentWarehouse()
    if (response.success) {
      currentWarehouse.value = response.data
    } else {
      currentWarehouse.value = null
    }
  } catch (error) {
    console.error('Error getting current warehouse:', error)
    currentWarehouse.value = null
  }
}    

        // Extract unique category names for dropdown
const categoryNames = (categoriesRes.data || [])
  .map(item => item.category_name || item.name)
  .filter(name => name && name.trim())
categories.value = [...new Set(categoryNames)]

// Extract unique brand names for dropdown  
const brandNames = (brandsRes.data || [])
  .map(item => item.brand_name || item.name)
  .filter(name => name && name.trim())
brands.value = [...new Set(brandNames)]
      } catch (error) {
        console.error('Error loading master data:', error)
      }
    }

    const applyFilters = async () => {
      pagination.value.current_page = 1
      await fetchProducts()
    }

    const debouncedSearch = debounce(() => {
      applyFilters()
    }, 300)

    const resetFilters = () => {
      searchQuery.value = ''
      filters.value = {
        category_name: '',
        brand_name: '',
        sort_field: 'created_at',
        sort_direction: 'desc'
      }
      applyFilters()
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
      pagination.value.current_page = page
      fetchProducts()
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
        selectedIds.value = products.value.map(p => p.id)
      }
    }

    const createProduct = () => {
      editingProduct.value = null
      showForm.value = true
    }

    const viewProduct = async (product) => {
  try {
    // Gọi API để lấy data đầy đủ với relationships
    const response = await productApi.getById(product.id)
    if (response.success) {
      selectedProduct.value = response.data
      showDetail.value = true
    } else {
      showNotification('Không thể tải chi tiết sản phẩm', 'error')
    }
  } catch (error) {
    console.error('Error loading product details:', error)
    showNotification('Lỗi khi tải chi tiết sản phẩm', 'error')
  }
}

    const closeDetail = () => {
      showDetail.value = false
      selectedProduct.value = null
    }

    const closeForm = () => {
      showForm.value = false
      editingProduct.value = null
    }

    const saveProduct = async (data) => {
      try {
        if (editingProduct.value?.id) {
          await productApi.update(editingProduct.value.id, data)
          showNotification('Cập nhật sản phẩm thành công')
        } else {
          await productApi.create(data)
          showNotification('Tạo sản phẩm thành công')
        }
        
        await fetchProducts()
        closeForm()
        closeDetail()
      } catch (error) {
        console.error('Error saving product:', error)
        showNotification('Lỗi khi lưu sản phẩm', 'error')
      }
    }

    const deleteProduct = async (id) => {
      if (confirm('Bạn có chắc muốn xóa sản phẩm này?')) {
        try {
          await productApi.delete(id)
          showNotification('Xóa sản phẩm thành công')
          await fetchProducts()
          closeDetail()
        } catch (error) {
          console.error('Error deleting product:', error)
          showNotification('Lỗi khi xóa sản phẩm', 'error')
        }
      }
    }

    const bulkDelete = async () => {
      if (confirm(`Bạn có chắc muốn xóa ${selectedIds.value.length} sản phẩm đã chọn?`)) {
        deleting.value = true
        try {
          const res = await productApi.bulkDelete(selectedIds.value)
          const msg = res.data?.message || `Đã xóa ${selectedIds.value.length} sản phẩm`
          showNotification(msg)
          selectedIds.value = []
          await fetchProducts()
        } catch (error) {
          console.error('Error bulk deleting:', error)
          const resData = error.response?.data
          if (resData?.data?.blocked_products?.length) {
            const lines = resData.data.blocked_products.map(
              p => `• ${p.name} (đang có trong: ${p.reasons.join(', ')})`
            )
            const detail = lines.slice(0, 10).join('\n')
            const extra = lines.length > 10 ? `\n...và ${lines.length - 10} sản phẩm khác` : ''
            alert(`Không thể xóa ${resData.data.blocked_count} sản phẩm vì đang được sử dụng:\n\n${detail}${extra}\n\nBạn cần xóa đơn hàng/phiếu mua liên quan trước.`)
          } else {
            showNotification(resData?.message || 'Lỗi khi xóa hàng loạt', 'error')
          }
        } finally {
          deleting.value = false
        }
      }
    }

    const exportProducts = async () => {
  try {
    loading.value = true
    showNotification('Đang xuất file...', 'info')
    
    const exportParams = {
      search: searchQuery.value,
      category_name: filters.value.category_name,
      brand_name: filters.value.brand_name
    }

    if (selectedIds.value.length > 0) {
      exportParams.selected_ids = selectedIds.value
    }

    await productApi.export(exportParams)
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

const importProducts = async () => {
  if (!importFile.value) {
    showNotification('Vui lòng chọn file để nhập', 'error')
    return
  }

  try {
    importing.value = true
    showNotification('Đang nhập dữ liệu...', 'info')

    const formData = new FormData()
    formData.append('file', importFile.value)

    const response = await productApi.import(importFile.value)

    if (response.data.success) {
      showNotification(response.data.message, 'success')
      await fetchProducts()
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
    await productApi.downloadTemplate()
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
    const formatDate = (dateString) => {
      return new Date(dateString).toLocaleDateString('vi-VN')
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
    // ✅ ĐÚNG - gọi trực tiếp API trong onMounted
onMounted(async () => {
  // Load current warehouse first
  try {
    const response = await warehouseApi.getCurrentWarehouse()
    if (response.success) {
      currentWarehouse.value = response.data
    } else {
      currentWarehouse.value = null
    }
  } catch (error) {
    console.error('Error getting current warehouse:', error)
    currentWarehouse.value = null
  }
  
  // Then load products and master data
  await Promise.all([
    fetchProducts(),
    loadMasterData()
  ])
})

    return {
      loading,
      deleting,
      products,
      categories,
      brands,
      selectedIds,
      searchQuery,
      showDetail,
      showForm,
      showImportModal,
      selectedProduct,
      editingProduct,
      notification,
      filters,
      pagination,
      isAllSelected,
      visiblePages,
      fetchProducts,
      loadMasterData,
      applyFilters,
      debouncedSearch,
      resetFilters,
      sortBy,
      changePage,
      toggleSelection,
      toggleSelectAll,
      createProduct,
      viewProduct,
      closeDetail,
      closeForm,
      saveProduct,
      deleteProduct,
      bulkDelete,
      exportProducts,
      formatDate,
      editFromDetail,
      importing,
      importFile,
      handleFileChange,
      importProducts,
      downloadTemplate,
      closeImportModal,
      currentWarehouse,        // ← THÊM
      warehouseContext,       // ← THÊM
      fetchProducts,
    }
  }
}
</script>