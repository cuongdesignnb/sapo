<template>
  <div class="warehouse-product-table">
    <!-- Loading -->
    <div v-if="loading" class="text-center py-8">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500 mx-auto"></div>
      <p class="text-gray-600 mt-2">Đang tải danh sách sản phẩm...</p>
    </div>

    <!-- Table -->
    <div v-else-if="products.length > 0" class="overflow-x-auto">
      <table class="w-full">
        <thead class="bg-gray-50">
          <tr>
            <th class="text-left p-4 text-sm font-medium text-gray-600">Sản phẩm</th>
            <th class="text-center p-4 text-sm font-medium text-gray-600">Tồn kho</th>
            <th class="text-center p-4 text-sm font-medium text-gray-600">Dự trữ</th>
            <th class="text-center p-4 text-sm font-medium text-gray-600">Có thể bán</th>
            <th class="text-center p-4 text-sm font-medium text-gray-600">Trạng thái</th>
            <th class="text-center p-4 text-sm font-medium text-gray-600">Giá trị</th>
            <th class="text-center p-4 text-sm font-medium text-gray-600">Hoạt động cuối</th>
            <th class="text-center p-4 text-sm font-medium text-gray-600">Thao tác</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <tr v-for="item in products" :key="item.id" class="hover:bg-gray-50">
            <!-- Product Info -->
            <td class="p-4">
              <div class="flex items-center">
                <div>
                  <div class="font-semibold text-gray-900">{{ item.product.name }}</div>
                  <div class="text-sm text-gray-500">
                    SKU: {{ item.product.sku }}
                  </div>
                  <div class="text-xs text-gray-400">
                    {{ item.product.category_name }} - {{ item.product.brand_name }}
                  </div>
                </div>
              </div>
            </td>

            <!-- Stock Quantity -->
            <td class="text-center p-4">
              <div class="font-bold text-lg">{{ item.quantity }}</div>
              <div class="text-xs text-gray-500">
                Min: {{ item.min_stock }} | Max: {{ item.max_stock }}
              </div>
            </td>

            <!-- Reserved -->
            <td class="text-center p-4">
              <div class="font-bold">{{ item.reserved_quantity }}</div>
              <div class="text-xs text-gray-500">{{ item.reserved_percent }}%</div>
            </td>

            <!-- Available -->
            <td class="text-center p-4">
              <div class="font-bold text-green-600 text-lg">{{ item.available_stock }}</div>
            </td>

            <!-- Status -->
            <td class="text-center p-4">
              <span :class="getStatusBadgeClass(item.stock_status)">
                {{ item.stock_status_label }}
              </span>
            </td>

            <!-- Value -->
            <td class="text-center p-4">
              <div class="font-bold">{{ formatCurrency(item.total_value) }}</div>
              <div class="text-xs text-gray-500">{{ formatCurrency(item.cost) }}/đv</div>
            </td>

            <!-- Last Activity -->
            <td class="text-center p-4">
              <div v-if="item.last_import_date" class="text-xs text-green-600 mb-1">
                ↓ Nhập: {{ formatDate(item.last_import_date) }}
              </div>
              <div v-if="item.last_export_date" class="text-xs text-red-600">
                ↑ Xuất: {{ formatDate(item.last_export_date) }}
              </div>
              <div v-if="!item.last_import_date && !item.last_export_date" class="text-xs text-gray-400">
                Chưa có hoạt động
              </div>
            </td>

            <!-- Actions -->
            <td class="text-center p-4">
              <div class="flex justify-center space-x-1">
                <button 
                  class="bg-blue-500 text-white px-2 py-1 rounded text-xs hover:bg-blue-600"
                  @click="adjustStock(item)"
                  title="Điều chỉnh tồn kho"
                >
                  ✏️
                </button>
                <button 
                  class="bg-green-500 text-white px-2 py-1 rounded text-xs hover:bg-green-600"
                  @click="transferProduct(item)"
                  title="Chuyển kho"
                >
                  🔄
                </button>
                <button 
                  class="bg-purple-500 text-white px-2 py-1 rounded text-xs hover:bg-purple-600"
                  @click="viewHistory(item)"
                  title="Lịch sử"
                >
                  📊
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Empty State -->
    <div v-else class="text-center py-12">
      <div class="text-4xl mb-4">📦</div>
      <h6 class="text-lg font-medium text-gray-900 mb-2">Không có sản phẩm nào</h6>
      <p class="text-gray-500">{{ getEmptyMessage() }}</p>
    </div>

    <!-- Pagination -->
    <div v-if="pagination.total > 0" class="flex justify-between items-center mt-6 pt-4 border-t">
      <div class="text-sm text-gray-700">
        Hiển thị {{ pagination.from }}-{{ pagination.to }} của {{ pagination.total }} sản phẩm
      </div>
      <div class="flex space-x-2">
        <button 
          class="px-3 py-1 border rounded text-sm hover:bg-gray-100 disabled:opacity-50"
          :disabled="pagination.current_page === 1"
          @click="changePage(pagination.current_page - 1)"
        >
          Trước
        </button>
        
        <template v-for="page in getPageNumbers()" :key="page">
          <button 
            class="px-3 py-1 border rounded text-sm hover:bg-gray-100"
            :class="page === pagination.current_page ? 'bg-blue-500 text-white border-blue-500' : ''"
            @click="changePage(page)"
          >
            {{ page }}
          </button>
        </template>
        
        <button 
          class="px-3 py-1 border rounded text-sm hover:bg-gray-100 disabled:opacity-50"
          :disabled="pagination.current_page === pagination.last_page"
          @click="changePage(pagination.current_page + 1)"
        >
          Tiếp
        </button>
      </div>
    </div>

    <!-- Stock Adjust Modal -->
    <div v-if="showAdjustModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg p-6 w-full max-w-md">
        <h3 class="text-lg font-semibold mb-4">Điều chỉnh tồn kho</h3>
        <div v-if="selectedProduct">
          <div class="mb-4 p-4 bg-gray-50 rounded">
            <div class="font-medium">{{ selectedProduct.product.name }}</div>
            <div class="text-sm text-gray-500">Hiện tại: {{ selectedProduct.quantity }}</div>
          </div>
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium mb-1">Số lượng mới</label>
              <input 
                type="number" 
                v-model="adjustForm.quantity"
                class="w-full px-3 py-2 border rounded"
                min="0"
              >
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">Lý do</label>
              <textarea 
                v-model="adjustForm.reason"
                class="w-full px-3 py-2 border rounded"
                rows="2"
                placeholder="Lý do điều chỉnh..."
              ></textarea>
            </div>
          </div>
        </div>
        <div class="flex justify-end space-x-2 mt-6">
          <button @click="closeAdjustModal" class="px-4 py-2 text-gray-600 border rounded">Hủy</button>
          <button @click="handleAdjustStock" class="px-4 py-2 bg-blue-500 text-white rounded">Lưu</button>
        </div>
      </div>
    </div>

    <!-- Transfer Modal -->
    <div v-if="showTransferModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg p-6 w-full max-w-md">
        <h3 class="text-lg font-semibold mb-4">Chuyển sản phẩm</h3>
        <div class="text-center py-8">
          <p class="text-gray-500">Tính năng chuyển kho đang phát triển...</p>
        </div>
        <div class="flex justify-end">
          <button @click="showTransferModal = false" class="px-4 py-2 bg-gray-500 text-white rounded">Đóng</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import warehouseApi from '../api/warehouseApi.js'

export default {
  name: 'WarehouseProductTable',
  props: {
    warehouseId: {
      type: [String, Number],
      required: true
    },
    stockFilter: {
      type: String,
      default: ''
    },
    search: {
      type: String,
      default: ''
    }
  },
  emits: ['transfer', 'adjust'],
  data() {
    return {
      products: [],
      loading: false,
      showAdjustModal: false,
      showTransferModal: false,
      selectedProduct: null,
      adjustForm: {
        quantity: '',
        reason: ''
      },
      pagination: {
        current_page: 1,
        last_page: 1,
        per_page: 15,
        total: 0,
        from: 0,
        to: 0
      }
    }
  },
  created() {
    this.loadProducts()
  },
  watch: {
    warehouseId() {
      this.loadProducts()
    },
    stockFilter() {
      this.loadProducts()
    },
    search() {
      this.loadProducts()
    }
  },
  methods: {
    async loadProducts(page = 1) {
      this.loading = true
      try {
        const params = {
          page,
          per_page: this.pagination.per_page,
          stock_status: this.stockFilter,
          search: this.search
        }
        
        const response = await warehouseApi.getWarehouseProducts(this.warehouseId, params)
        
        if (response.success) {
          this.products = response.data.data || []
          this.pagination = response.data.pagination || this.pagination
        }
      } catch (error) {
        console.error('Error loading products:', error)
        
        // Mock data fallback
        this.products = [
          {
            id: 1,
            product: {
              name: 'Moet & Chandon Imperial Rose 750ml',
              sku: 'PVN2317',
              category_name: 'Champagne France',
              brand_name: 'Moet & Chandon'
            },
            quantity: 12,
            min_stock: 5,
            max_stock: 50,
            reserved_quantity: 2,
            available_stock: 10,
            stock_status: 'IN_STOCK',
            stock_status_label: 'Bình thường',
            cost: 1280000,
            total_value: 15360000,
            reserved_percent: 17,
            last_import_date: '2025-07-03T10:39:00',
            last_export_date: '2025-07-10T14:20:00'
          },
          {
            id: 2,
            product: {
              name: 'Dalmore King Alexander III (70cl)',
              sku: 'DAL001',
              category_name: 'Scotch Whisky',
              brand_name: 'Dalmore'
            },
            quantity: 3,
            min_stock: 5,
            max_stock: 20,
            reserved_quantity: 0,
            available_stock: 3,
            stock_status: 'LOW_STOCK',
            stock_status_label: 'Sắp hết',
            cost: 2500000,
            total_value: 7500000,
            reserved_percent: 0,
            last_import_date: '2025-07-02T15:20:00',
            last_export_date: null
          }
        ]
        
        this.pagination = {
          current_page: 1,
          last_page: 1,
          per_page: 15,
          total: 2,
          from: 1,
          to: 2
        }
      } finally {
        this.loading = false
      }
    },

    changePage(page) {
      if (page >= 1 && page <= this.pagination.last_page) {
        this.loadProducts(page)
      }
    },

    getPageNumbers() {
      const pages = []
      const current = this.pagination.current_page
      const last = this.pagination.last_page
      
      const start = Math.max(1, current - 2)
      const end = Math.min(last, current + 2)
      
      for (let i = start; i <= end; i++) {
        pages.push(i)
      }
      
      return pages
    },

    adjustStock(item) {
      this.selectedProduct = item
      this.adjustForm = {
        quantity: item.quantity,
        reason: ''
      }
      this.showAdjustModal = true
    },

    closeAdjustModal() {
      this.showAdjustModal = false
      this.selectedProduct = null
    },

    async handleAdjustStock() {
      try {
        // Call API to adjust stock
        console.log('Adjusting stock:', this.adjustForm)
        this.closeAdjustModal()
        this.loadProducts(this.pagination.current_page)
      } catch (error) {
        console.error('Error adjusting stock:', error)
      }
    },

    transferProduct(item) {
      this.showTransferModal = true
      this.$emit('transfer', item)
    },

    viewHistory(item) {
      console.log('View history for:', item.product.name)
      // TODO: Implement history modal
    },

    getStatusBadgeClass(status) {
      const classes = {
        'OUT_OF_STOCK': 'px-2 py-1 text-xs bg-red-100 text-red-800 rounded-full font-medium',
        'LOW_STOCK': 'px-2 py-1 text-xs bg-yellow-100 text-yellow-800 rounded-full font-medium',
        'OVER_STOCK': 'px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded-full font-medium',
        'IN_STOCK': 'px-2 py-1 text-xs bg-green-100 text-green-800 rounded-full font-medium'
      }
      return classes[status] || 'px-2 py-1 text-xs bg-gray-100 text-gray-800 rounded-full font-medium'
    },

    getEmptyMessage() {
      if (this.search) {
        return `Không tìm thấy sản phẩm nào với từ khóa "${this.search}"`
      }
      if (this.stockFilter) {
        const filterLabels = {
          'in_stock': 'bình thường',
          'low_stock': 'sắp hết',
          'out_of_stock': 'hết hàng',
          'over_stock': 'dư thừa'
        }
        return `Không có sản phẩm nào có trạng thái ${filterLabels[this.stockFilter]}`
      }
      return 'Kho này chưa có sản phẩm nào'
    },

    formatCurrency(value) {
      if (!value) return '0 ₫'
      return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND'
      }).format(value)
    },

    formatDate(dateString) {
      if (!dateString) return ''
      const date = new Date(dateString)
      return date.toLocaleDateString('vi-VN', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
      })
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

table th {
  font-weight: 600;
  font-size: 0.875rem;
  border-bottom: 2px solid #e5e7eb;
}

table td {
  vertical-align: middle;
}

.hover\:bg-gray-50:hover {
  background-color: #f9fafb;
}

@media (max-width: 768px) {
  table {
    font-size: 0.875rem;
  }
  
  .px-2 {
    padding-left: 0.25rem;
    padding-right: 0.25rem;
  }
}
</style>