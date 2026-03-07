<!-- resources/js/components/SupplierPurchaseHistory.vue -->
<template>
  <div class="supplier-purchase-history">
    <!-- Filters -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Loại giao dịch:</label>
        <select 
          v-model="filters.type" 
          @change="fetchHistory" 
          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
          <option value="all">Tất cả</option>
          <option value="orders">Đơn đặt hàng</option>
          <option value="receipts">Phiếu nhập kho</option>
          <option value="returns">Đơn trả hàng</option>
        </select>
      </div>
      
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Từ ngày:</label>
        <input 
          v-model="filters.from_date" 
          @change="fetchHistory"
          type="date" 
          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
      </div>
      
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Đến ngày:</label>
        <input 
          v-model="filters.to_date" 
          @change="fetchHistory"
          type="date" 
          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
      </div>
      
      <div class="flex items-end space-x-2">
        <button 
          @click="exportHistory" 
          :disabled="loading"
          class="px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          📊 Xuất Excel
        </button>
        <button 
          @click="resetFilters" 
          class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600"
        >
          🔄 Reset
        </button>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center items-center py-12">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
      <span class="ml-3 text-gray-600">Đang tải...</span>
    </div>

    <!-- Error -->
    <div v-if="error" class="bg-red-50 border border-red-200 rounded-md p-4 mb-6" role="alert">
      <div class="flex items-center">
        <span class="text-red-500 mr-2">⚠️</span>
        <span class="text-red-700">{{ error }}</span>
      </div>
    </div>

    <!-- Data Table -->
    <div v-if="!loading && !error" class="bg-white shadow-sm rounded-lg overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mã phiếu</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Loại</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Trạng thái</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ngày</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kho</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sản phẩm</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tổng tiền</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ghi chú</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Thao tác</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-for="item in historyData" :key="`${item.type}-${item.id}`" class="hover:bg-gray-50">
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-gray-900">{{ item.code }}</div>
              </td>
              
              <td class="px-6 py-4 whitespace-nowrap">
                <span 
                  class="inline-flex px-2 py-1 text-xs font-semibold rounded-full"
                  :class="{
                    'bg-blue-100 text-blue-800': item.type === 'purchase_order',
                    'bg-green-100 text-green-800': item.type === 'purchase_receipt', 
                    'bg-yellow-100 text-yellow-800': item.type === 'purchase_return'
                  }"
                >
                  {{ item.type_text }}
                </span>
              </td>
              
              <td class="px-6 py-4 whitespace-nowrap">
                <span 
                  class="inline-flex px-2 py-1 text-xs font-semibold rounded-full"
                  :class="{
                    'bg-green-100 text-green-800': item.status_color === 'success',
                    'bg-blue-100 text-blue-800': item.status_color === 'info',
                    'bg-yellow-100 text-yellow-800': item.status_color === 'warning',
                    'bg-red-100 text-red-800': item.status_color === 'danger',
                    'bg-gray-100 text-gray-800': item.status_color === 'secondary'
                  }"
                >
                  {{ item.status_text }}
                </span>
              </td>
              
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                {{ item.formatted_date }}
              </td>
              
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                <span v-if="item.warehouse">{{ item.warehouse.name }}</span>
                <span v-else class="text-gray-400">-</span>
              </td>
              
              <td class="px-6 py-4">
                <div class="text-sm text-gray-900 max-w-xs truncate" :title="item.products_summary">
                  {{ item.products_summary }}
                </div>
                <div class="text-sm text-gray-500">{{ item.items_count }} sản phẩm</div>
              </td>
              
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                <div class="font-medium text-blue-600">
                  {{ formatCurrency(item.total) }}
                </div>
                <div v-if="item.type === 'purchase_order' && item.need_pay > 0" class="text-xs text-red-600">
                  Cần trả: {{ formatCurrency(item.need_pay) }}
                </div>
              </td>
              
              <td class="px-6 py-4">
                <div 
                  v-if="item.note" 
                  class="text-sm text-gray-500 max-w-xs truncate"
                  :title="item.note"
                >
                  {{ item.note }}
                </div>
                <span v-else class="text-gray-400">-</span>
              </td>
              
              <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                <button 
                  @click="viewDetail(item)"
                  class="text-blue-600 hover:text-blue-900"
                  :title="'Xem chi tiết ' + item.type_text"
                >
                  👁️ Xem
                </button>
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Empty State -->
        <div v-if="historyData.length === 0" class="text-center py-12">
          <div class="text-gray-400 text-6xl mb-4">📋</div>
          <h3 class="text-lg font-medium text-gray-900 mb-2">Không có lịch sử giao dịch</h3>
          <p class="text-gray-500">Chưa có giao dịch nào với nhà cung cấp này.</p>
        </div>
      </div>
    </div>

    <!-- Pagination -->
    <div v-if="pagination && pagination.total > 0" class="flex items-center justify-between mt-6">
      <div class="text-sm text-gray-700">
        Hiển thị {{ pagination.from }}-{{ pagination.to }} 
        trong tổng số {{ pagination.total }} bản ghi
      </div>
      
      <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
        <button 
          @click="changePage(pagination.current_page - 1)"
          :disabled="pagination.current_page <= 1"
          class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          ← Trước
        </button>
        
        <button 
          v-for="page in visiblePages" 
          :key="page"
          @click="changePage(page)"
          :class="[
            'relative inline-flex items-center px-4 py-2 border text-sm font-medium',
            page === pagination.current_page 
              ? 'z-10 bg-blue-50 border-blue-500 text-blue-600'
              : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'
          ]"
        >
          {{ page }}
        </button>
        
        <button 
          @click="changePage(pagination.current_page + 1)"
          :disabled="pagination.current_page >= pagination.last_page"
          class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          Sau →
        </button>
      </nav>
    </div>
  </div>
</template>

<script>
import supplierApi from '../api/supplierApi.js';

export default {
  name: 'SupplierPurchaseHistory',
  props: {
    supplierId: {
      type: [Number, String],
      required: true
    }
  },
  
  data() {
    return {
      loading: false,
      error: null,
      historyData: [],
      pagination: null,
      filters: {
        type: 'all',
        from_date: '',
        to_date: '',
        page: 1,
        per_page: 15
      }
    };
  },
  
  computed: {
    visiblePages() {
      if (!this.pagination) return [];
      
      const current = this.pagination.current_page;
      const last = this.pagination.last_page;
      const delta = 2;
      
      let pages = [];
      
      // Thêm trang đầu
      if (current - delta > 1) {
        pages.push(1);
        if (current - delta > 2) pages.push('...');
      }
      
      // Thêm các trang xung quanh trang hiện tại
      for (let i = Math.max(1, current - delta); i <= Math.min(last, current + delta); i++) {
        pages.push(i);
      }
      
      // Thêm trang cuối
      if (current + delta < last) {
        if (current + delta < last - 1) pages.push('...');
        pages.push(last);
      }
      
      return pages.filter(page => page !== '...' || pages.indexOf(page) % 2 === 1);
    }
  },
  
  mounted() {
    this.fetchHistory();
  },
  
  methods: {
    async fetchHistory() {
      this.loading = true;
      this.error = null;
      
      try {
        const response = await supplierApi.getPurchaseHistory(this.supplierId, this.filters);
        this.historyData = response.data.data;
        this.pagination = response.data.pagination;
      } catch (error) {
        this.error = error.message || 'Có lỗi xảy ra khi tải dữ liệu';
        console.error('Error fetching purchase history:', error);
      } finally {
        this.loading = false;
      }
    },
    
    async exportHistory() {
      try {
        this.loading = true;
        await supplierApi.exportPurchaseHistory(this.supplierId, this.filters);
        
        // Show success message (simple alert, you can replace with toast)
        alert('Đã bắt đầu tải file Excel!');
      } catch (error) {
        this.error = 'Lỗi khi xuất file: ' + error.message;
      } finally {
        this.loading = false;
      }
    },
    
    resetFilters() {
      this.filters = {
        type: 'all',
        from_date: '',
        to_date: '',
        page: 1,
        per_page: 15
      };
      this.fetchHistory();
    },
    
    changePage(page) {
      if (page < 1 || page > this.pagination.last_page) return;
      this.filters.page = page;
      this.fetchHistory();
    },
    
    viewDetail(item) {
      // Redirect hoặc emit event để xem chi tiết
      const routeMap = {
        'purchase_order': '/purchase-orders/',
        'purchase_receipt': '/purchase-receipts/',
        'purchase_return': '/purchase-return-orders/'
      };
      
      const route = routeMap[item.type];
      if (route) {
        window.open(route + item.id, '_blank');
      }
    },
    
    formatCurrency(amount) {
      return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND'
      }).format(amount || 0);
    }
  }
};
</script>