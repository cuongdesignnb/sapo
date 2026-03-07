<!-- resources/js/components/SupplierDebtHistory.vue -->
<template>
  <div class="supplier-debt-history">
    <!-- Filters -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Loại giao dịch:</label>
        <select 
          v-model="filters.type" 
          @change="fetchDebtHistory" 
          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
          <option value="">Tất cả</option>
          <option value="purchase">Mua hàng</option>
          <option value="payment">Thanh toán</option>
          <option value="adjustment">Điều chỉnh</option>
        </select>
      </div>
      
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Từ ngày:</label>
        <input 
          v-model="filters.from_date" 
          @change="fetchDebtHistory"
          type="date" 
          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
      </div>
      
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Đến ngày:</label>
        <input 
          v-model="filters.to_date" 
          @change="fetchDebtHistory"
          type="date" 
          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
      </div>
      
      <div class="flex items-end space-x-2">
        <select 
          v-model="filters.sort_by" 
          @change="fetchDebtHistory"
          class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
          <option value="recorded_at|desc">Mới nhất trước</option>
          <option value="recorded_at|asc">Cũ nhất trước</option>
          <option value="amount|desc">Số tiền giảm dần</option>
          <option value="amount|asc">Số tiền tăng dần</option>
        </select>
        
        <button 
          @click="exportDebtHistory" 
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

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
      <div class="bg-white p-4 rounded-lg shadow-sm border">
        <div class="text-sm text-gray-600">Tổng nợ hiện tại</div>
        <div class="text-2xl font-bold text-red-600">{{ formatCurrency(supplier?.total_debt || 0) }}</div>
      </div>
      
      <div class="bg-white p-4 rounded-lg shadow-sm border">
        <div class="text-sm text-gray-600">Hạn mức tín dụng</div>
        <div class="text-2xl font-bold text-blue-600">{{ formatCurrency(supplier?.credit_limit || 0) }}</div>
      </div>
      
      <div class="bg-white p-4 rounded-lg shadow-sm border">
        <div class="text-sm text-gray-600">Hạn mức còn lại</div>
        <div class="text-2xl font-bold text-green-600">{{ formatCurrency(supplier?.credit_remaining || 0) }}</div>
      </div>
      
      <div class="bg-white p-4 rounded-lg shadow-sm border">
        <div class="text-sm text-gray-600">Số ngày thanh toán</div>
        <div class="text-2xl font-bold text-gray-800">{{ supplier?.payment_terms || 0 }} ngày</div>
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
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mã tham chiếu</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Loại</th>
              <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Số tiền</th>
              <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Tổng nợ sau GD</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ghi chú</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ngày ghi nhận</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Người tạo</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-for="debt in debtData" :key="debt.id" class="hover:bg-gray-50">
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-gray-900">{{ debt.ref_code || '---' }}</div>
              </td>
              
              <td class="px-6 py-4 whitespace-nowrap">
                <span 
                  class="inline-flex px-2 py-1 text-xs font-semibold rounded-full"
                  :class="getDebtTypeClass(debt.type)"
                >
                  {{ getDebtTypeText(debt.type) }}
                </span>
              </td>
              
              <td class="px-6 py-4 whitespace-nowrap text-right">
                <div 
                  class="text-sm font-medium"
                  :class="debt.amount > 0 ? 'text-red-600' : 'text-green-600'"
                >
                  {{ debt.amount > 0 ? '+' : '' }}{{ formatCurrency(debt.amount) }}
                </div>
              </td>
              
              <td class="px-6 py-4 whitespace-nowrap text-right">
                <div class="text-sm font-medium text-gray-900">
                  {{ formatCurrency(debt.debt_total) }}
                </div>
              </td>
              
              <td class="px-6 py-4">
                <div 
                  v-if="debt.note" 
                  class="text-sm text-gray-500 max-w-xs truncate"
                  :title="debt.note"
                >
                  {{ debt.note }}
                </div>
                <span v-else class="text-gray-400">---</span>
              </td>
              
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                {{ debt.recorded_at }}
              </td>
              
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                {{ debt.created_by || '---' }}
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Empty State -->
        <div v-if="debtData.length === 0" class="text-center py-12">
          <div class="text-gray-400 text-6xl mb-4">💰</div>
          <h3 class="text-lg font-medium text-gray-900 mb-2">Không có giao dịch công nợ</h3>
          <p class="text-gray-500">Chưa có giao dịch công nợ nào với nhà cung cấp này.</p>
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
  name: 'SupplierDebtHistory',
  props: {
    supplierId: {
      type: [Number, String],
      required: true
    },
    supplier: {
      type: Object,
      default: null
    }
  },
  
  data() {
    return {
      loading: false,
      error: null,
      debtData: [],
      pagination: null,
      filters: {
        type: '',
        from_date: '',
        to_date: '',
        sort_by: 'recorded_at|desc',
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
      
      for (let i = Math.max(1, current - delta); i <= Math.min(last, current + delta); i++) {
        pages.push(i);
      }
      
      return pages;
    }
  },
  
  mounted() {
    this.fetchDebtHistory();
  },
  
  methods: {
    async fetchDebtHistory() {
      this.loading = true;
      this.error = null;
      
      try {
        const response = await supplierApi.getDebtHistory(this.supplierId, this.filters);
        this.debtData = response.data.data;
        this.pagination = response.data.pagination;
      } catch (error) {
        this.error = error.message || 'Có lỗi xảy ra khi tải dữ liệu';
        console.error('Error fetching debt history:', error);
      } finally {
        this.loading = false;
      }
    },
    
    async exportDebtHistory() {
      try {
        this.loading = true;
        // Implement export debt history if needed
        alert('Chức năng xuất lịch sử công nợ sẽ được phát triển');
      } catch (error) {
        this.error = 'Lỗi khi xuất file: ' + error.message;
      } finally {
        this.loading = false;
      }
    },
    
    resetFilters() {
      this.filters = {
        type: '',
        from_date: '',
        to_date: '',
        page: 1,
        per_page: 15
      };
      this.fetchDebtHistory();
    },
    
    changePage(page) {
      if (page < 1 || page > this.pagination.last_page) return;
      this.filters.page = page;
      this.fetchDebtHistory();
    },
    
    getDebtTypeClass(type) {
      const classes = {
        'purchase': 'bg-red-100 text-red-800',
        'payment': 'bg-green-100 text-green-800',
        'adjustment': 'bg-yellow-100 text-yellow-800'
      };
      return classes[type] || 'bg-gray-100 text-gray-800';
    },

    getDebtTypeText(type) {
      const texts = {
        'purchase': 'Mua hàng',
        'payment': 'Thanh toán',
        'adjustment': 'Điều chỉnh'
      };
      return texts[type] || 'Không xác định';
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