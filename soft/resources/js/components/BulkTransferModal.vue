<template>
  <div v-if="open" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg w-full max-w-4xl max-h-[90vh] overflow-hidden">
      <!-- Header -->
      <div class="flex items-center justify-between p-6 border-b">
        <h3 class="text-lg font-semibold">🔄 Chuyển kho hàng loạt</h3>
        <button @click="$emit('close')" class="text-gray-500 hover:text-gray-700">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </button>
      </div>

      <!-- Warehouse Selection -->
      <div class="p-6 border-b bg-gray-50">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium mb-2">📍 Từ kho:</label>
            <div class="px-3 py-2 bg-gray-100 rounded font-medium">{{ fromWarehouse?.name }}</div>
          </div>
          <div>
            <label class="block text-sm font-medium mb-2">📍 Đến kho:</label>
            <select v-model="selectedToWarehouse" class="w-full px-3 py-2 border rounded">
              <option value="">Chọn kho đích</option>
              <option v-for="warehouse in warehouses" :key="warehouse.id" :value="warehouse.id">
                {{ warehouse.name }}
              </option>
            </select>
          </div>
        </div>
      </div>

      <!-- Filters -->
      <div class="p-6 border-b">
        <div class="grid grid-cols-4 gap-4">
          <div>
            <label class="block text-sm font-medium mb-1">🔍 Tìm kiếm:</label>
            <input v-model="filters.search" type="text" class="w-full px-3 py-2 border rounded" placeholder="Tên, mã, barcode...">
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Trạng thái tồn:</label>
            <select v-model="filters.stockStatus" class="w-full px-3 py-2 border rounded">
              <option value="">Tất cả</option>
              <option value="in_stock">Còn hàng</option>
              <option value="low_stock">Sắp hết</option>
              <option value="out_of_stock">Hết hàng</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">☑️ Chọn:</label>
            <button @click="toggleSelectAll" class="w-full px-3 py-2 bg-blue-100 text-blue-700 rounded hover:bg-blue-200">
              {{ allSelected ? 'Bỏ chọn tất cả' : 'Chọn tất cả' }}
            </button>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">📋 Đã chọn:</label>
            <div class="px-3 py-2 bg-green-100 text-green-700 rounded font-medium">
              {{ selectedCount }} sản phẩm
            </div>
          </div>
        </div>
      </div>

      <!-- Product List -->
      <div class="p-6 max-h-96 overflow-y-auto">
        <table class="w-full table-auto">
          <thead class="sticky top-0 bg-white">
            <tr class="border-b">
              <th class="px-2 py-2 text-left">☑️</th>
              <th class="px-2 py-2 text-left">Mã SP</th>
              <th class="px-2 py-2 text-left">Tên sản phẩm</th>
              <th class="px-2 py-2 text-center">Tồn kho</th>
              <th class="px-2 py-2 text-center">SL chuyển</th>
              <th class="px-2 py-2 text-right">Giá trị</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="product in filteredProducts" :key="product.id" class="border-b hover:bg-gray-50">
              <td class="px-2 py-2">
                <input 
                  type="checkbox" 
                  :checked="selectedProducts[product.id]?.selected" 
                  @change="toggleProduct(product)"
                  class="rounded"
                >
              </td>
              <td class="px-2 py-2">{{ product.product?.sku }}</td>
              <td class="px-2 py-2">{{ product.product?.name }}</td>
              <td class="px-2 py-2 text-center">{{ product.available_stock }}</td>
              <td class="px-2 py-2">
                <input 
                  v-if="selectedProducts[product.id]?.selected"
                  type="number" 
                  v-model="selectedProducts[product.id].quantity"
                  :max="product.available_stock"
                  min="1"
                  class="w-20 px-2 py-1 border rounded text-center"
                >
              </td>
              <td class="px-2 py-2 text-right">{{ formatCurrency(product.total_value) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Note -->
      <div class="p-6 border-t">
        <label class="block text-sm font-medium mb-2">📝 Ghi chú:</label>
        <textarea v-model="note" class="w-full px-3 py-2 border rounded" rows="2" placeholder="Ghi chú chuyển kho..."></textarea>
      </div>

      <!-- Footer -->
      <div class="flex items-center justify-between p-6 border-t bg-gray-50">
        <div class="text-sm text-gray-600">
          📋 Tóm tắt: {{ selectedCount }} sản phẩm | Tổng SL: {{ totalQuantity }}
        </div>
        <div class="space-x-2">
          <button @click="$emit('close')" class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600">
            Hủy
          </button>
          <button @click="handleTransfer" :disabled="!canTransfer" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 disabled:opacity-50">
            {{ loading ? 'Đang chuyển...' : 'Chuyển kho' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import warehouseApi from '../api/warehouseApi.js'

export default {
  name: 'BulkTransferModal',
  props: {
    open: Boolean,
    fromWarehouse: Object,
    warehouses: Array,
    products: Array
  },
  emits: ['close', 'transferred'],
  data() {
    return {
      selectedToWarehouse: '',
      selectedProducts: {},
      filters: {
        search: '',
        stockStatus: ''
      },
      note: '',
      loading: false
    }
  },
  computed: {
    filteredProducts() {
      let list = this.products || []
      
      if (this.filters.search) {
        const search = this.filters.search.toLowerCase()
        list = list.filter(p => 
          p.product?.sku?.toLowerCase().includes(search) ||
          p.product?.name?.toLowerCase().includes(search) ||
          p.product?.barcode?.toLowerCase().includes(search)
        )
      }
      
      if (this.filters.stockStatus) {
        if (this.filters.stockStatus === 'in_stock') {
          list = list.filter(p => p.available_stock > 5)
        } else if (this.filters.stockStatus === 'low_stock') {
          list = list.filter(p => p.available_stock > 0 && p.available_stock <= 5)
        } else if (this.filters.stockStatus === 'out_of_stock') {
          list = list.filter(p => p.available_stock === 0)
        }
      }
      
      return list
    },
    
    selectedCount() {
      return Object.values(this.selectedProducts).filter(p => p.selected).length
    },
    
    totalQuantity() {
      return Object.values(this.selectedProducts)
        .filter(p => p.selected)
        .reduce((sum, p) => sum + (parseInt(p.quantity) || 0), 0)
    },
    
    allSelected() {
      return this.filteredProducts.length > 0 && 
             this.filteredProducts.every(p => this.selectedProducts[p.id]?.selected)
    },
    
    canTransfer() {
      return !this.loading && 
             this.selectedToWarehouse && 
             this.selectedCount > 0 &&
             Object.values(this.selectedProducts).every(p => 
               !p.selected || (p.quantity > 0 && p.quantity <= p.maxQuantity)
             )
    }
  },
  methods: {
    toggleProduct(product) {
      const newSelectedProducts = { ...this.selectedProducts }
      
      if (newSelectedProducts[product.id]?.selected) {
        delete newSelectedProducts[product.id]
      } else {
        newSelectedProducts[product.id] = {
          selected: true,
          quantity: Math.min(product.available_stock, 1),
          maxQuantity: product.available_stock,
          product: product
        }
      }
      
      this.selectedProducts = newSelectedProducts
    },
    
    toggleSelectAll() {
      if (this.allSelected) {
        this.selectedProducts = {}
      } else {
        const newSelectedProducts = {}
        this.filteredProducts.forEach(product => {
          if (product.available_stock > 0) {
            newSelectedProducts[product.id] = {
              selected: true,
              quantity: Math.min(product.available_stock, 1),
              maxQuantity: product.available_stock,
              product: product
            }
          }
        })
        this.selectedProducts = newSelectedProducts
      }
    },
    
    async handleTransfer() {
      if (!this.canTransfer) return
      
      this.loading = true
      try {
        const items = Object.values(this.selectedProducts)
          .filter(p => p.selected)
          .map(p => ({
            product_id: p.product.product.id,
            quantity: parseInt(p.quantity),
            note: `${p.product.product.name}`
          }))

        const transferData = {
          from_warehouse_id: this.fromWarehouse.id,
          to_warehouse_id: this.selectedToWarehouse,
          note: this.note,
          items: items
        }

        await warehouseApi.bulkTransferProducts(transferData)
        
        this.$emit('transferred')
        this.resetForm()
        
      } catch (error) {
        console.error('Transfer error:', error)
        alert('Lỗi chuyển kho: ' + (error.message || 'Có lỗi xảy ra'))
      } finally {
        this.loading = false
      }
    },
    
    resetForm() {
      this.selectedToWarehouse = ''
      this.selectedProducts = {}
      this.filters.search = ''
      this.filters.stockStatus = ''
      this.note = ''
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