<template>
  <div class="bg-white">
    <!-- Header -->
    <div class="p-6 border-b">
      <div class="flex justify-between items-center">
        <div>
          <h1 class="text-2xl font-semibold text-gray-900">Sổ quỹ</h1>
          <nav class="mt-1">
            <ol class="flex items-center space-x-2 text-sm text-gray-500">
              <li><a href="/dashboard" class="hover:text-gray-700">Trang chủ</a></li>
              <li>•</li>
              <li class="text-gray-900">Sổ quỹ</li>
            </ol>
          </nav>
        </div>
        <div class="flex space-x-3">
          <button @click="exportData" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
            📥 Xuất báo cáo
          </button>
        </div>
      </div>
    </div>

    <!-- Summary Cards -->
    <div class="p-6 border-b bg-gray-50">
      <div class="grid grid-cols-4 gap-6 mb-6">
        <div class="bg-white rounded-lg shadow p-6">
          <div class="flex items-center">
            <div class="flex-shrink-0">
              <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                <span class="text-green-600 text-sm">📈</span>
              </div>
            </div>
            <div class="ml-4">
              <p class="text-sm text-gray-600">Tổng thu</p>
              <p class="text-lg font-semibold text-green-600">{{ formatCurrency(summary.total_receipts || 0) }}</p>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
          <div class="flex items-center">
            <div class="flex-shrink-0">
              <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center">
                <span class="text-red-600 text-sm">📉</span>
              </div>
            </div>
            <div class="ml-4">
              <p class="text-sm text-gray-600">Tổng chi</p>
              <p class="text-lg font-semibold text-red-600">{{ formatCurrency(summary.total_payments || 0) }}</p>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
          <div class="flex items-center">
            <div class="flex-shrink-0">
              <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                <span class="text-blue-600 text-sm">💰</span>
              </div>
            </div>
            <div class="ml-4">
              <p class="text-sm text-gray-600">Số dư</p>
              <p class="text-lg font-semibold" :class="getBalanceClass(summary.net_balance || 0)">
                {{ formatBalance(summary.net_balance || 0) }}
              </p>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
          <div class="flex items-center">
            <div class="flex-shrink-0">
              <div class="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center">
                <span class="text-purple-600 text-sm">📊</span>
              </div>
            </div>
            <div class="ml-4">
              <p class="text-sm text-gray-600">Tổng giao dịch</p>
              <p class="text-lg font-semibold text-gray-900">{{ (summary.receipts_count || 0) + (summary.payments_count || 0) }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Filters -->
      <div class="bg-white rounded-lg border border-gray-200 p-6">
        <div class="grid grid-cols-5 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Loại giao dịch</label>
            <select v-model="filters.type" @change="applyFilters" 
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500">
              <option value="">Tất cả</option>
              <option value="receipt">Phiếu thu</option>
              <option value="payment">Phiếu chi</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Chi nhánh</label>
            <select v-model="filters.warehouse_id" @change="applyFilters" 
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500">
              <option value="">Tất cả chi nhánh</option>
              <option v-for="warehouse in warehouses" :key="warehouse.id" :value="warehouse.id">
                {{ warehouse.name }}
              </option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Từ ngày</label>
            <input v-model="filters.date_from" type="date" @change="applyFilters"
                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Đến ngày</label>
            <input v-model="filters.date_to" type="date" @change="applyFilters"
                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500" />
          </div>
          <div class="flex items-end">
            <button @click="resetFilters" class="w-full px-3 py-2 border border-gray-300 rounded-md text-gray-600 hover:bg-gray-50">
              🔄 Reset
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Table -->
    <div class="overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Ngày giao dịch
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Mã phiếu
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Loại giao dịch
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Đối tượng
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Tiền thu
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Tiền chi
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Số dư
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Ghi chú
              </th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-if="loading">
              <td colspan="8" class="px-6 py-12 text-center text-gray-500">
                <div class="flex items-center justify-center">
                  <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600"></div>
                  <span class="ml-2">Đang tải...</span>
                </div>
              </td>
            </tr>
            <tr v-else-if="transactions.length === 0">
              <td colspan="8" class="px-6 py-12 text-center text-gray-500">
                Không có giao dịch nào trong khoảng thời gian này
              </td>
            </tr>
            <tr v-else v-for="transaction in transactions" :key="`${transaction.type}-${transaction.id}`" class="hover:bg-gray-50">
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-900">{{ formatDate(transaction.date) }}</div>
                <div class="text-sm text-gray-500">{{ transaction.creator }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium" :class="transaction.type === 'receipt' ? 'text-blue-600' : 'text-red-600'">
                  {{ transaction.code }}
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span :class="getTransactionTypeClass(transaction.type)" class="inline-flex px-2 py-1 text-xs font-semibold rounded-full">
                  {{ getTransactionTypeText(transaction.type) }}
                </span>
                <div class="text-sm text-gray-500 mt-1">{{ transaction.voucher_type }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-900">{{ transaction.recipient_name }}</div>
                <div class="text-sm text-gray-500">{{ transaction.recipient_type === 'customer' ? 'Khách hàng' : 'Nhà cung cấp' }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-green-600">
                  {{ transaction.type === 'receipt' ? formatCurrency(transaction.amount) : '' }}
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-red-600">
                  {{ transaction.type === 'payment' ? formatCurrency(transaction.amount) : '' }}
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium" :class="getBalanceClass(transaction.balance)">
                  {{ formatBalance(transaction.balance) }}
                </div>
              </td>
              <td class="px-6 py-4">
                <div class="text-sm text-gray-500 max-w-xs truncate" :title="transaction.note">
                  {{ transaction.note || '-' }}
                </div>
                <div class="text-sm text-gray-400 mt-1">{{ transaction.warehouse }}</div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Summary Footer -->
    <div class="px-6 py-4 border-t bg-gray-50">
      <div class="flex justify-between items-center">
        <div class="text-sm text-gray-700">
          Hiển thị {{ transactions.length }} giao dịch
        </div>
        <div class="flex space-x-6 text-sm">
          <div class="text-green-600">
            <strong>Tổng thu: {{ formatCurrency(summary.total_receipts || 0) }}</strong>
          </div>
          <div class="text-red-600">
            <strong>Tổng chi: {{ formatCurrency(summary.total_payments || 0) }}</strong>
          </div>
          <div :class="getBalanceClass(summary.net_balance || 0)">
            <strong>Số dư: {{ formatBalance(summary.net_balance || 0) }}</strong>
          </div>
        </div>
      </div>
    </div>

    <!-- Transaction Detail Modal -->
    <div v-if="showDetailModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
      <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-2xl shadow-lg rounded-md bg-white">
        <div class="px-6 py-4 border-b">
          <h3 class="text-lg font-medium text-gray-900">Chi tiết giao dịch</h3>
        </div>
        <div class="p-6" v-if="selectedTransaction">
          <div class="grid grid-cols-2 gap-4">
            <div><strong>Mã phiếu:</strong> {{ selectedTransaction.code }}</div>
            <div><strong>Loại:</strong> {{ getTransactionTypeText(selectedTransaction.type) }}</div>
            <div><strong>Ngày:</strong> {{ formatDate(selectedTransaction.date) }}</div>
            <div><strong>Số tiền:</strong> {{ formatCurrency(selectedTransaction.amount) }}</div>
            <div><strong>Đối tượng:</strong> {{ selectedTransaction.recipient_name }}</div>
            <div><strong>Phương thức:</strong> {{ selectedTransaction.payment_method === 'cash' ? 'Tiền mặt' : 'Chuyển khoản' }}</div>
            <div><strong>Chi nhánh:</strong> {{ selectedTransaction.warehouse }}</div>
            <div><strong>Người tạo:</strong> {{ selectedTransaction.creator }}</div>
            <div class="col-span-2"><strong>Ghi chú:</strong> {{ selectedTransaction.note || 'Không có' }}</div>
          </div>
        </div>
        <div class="flex justify-end px-6 py-4 border-t">
          <button @click="showDetailModal = false" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
            Đóng
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { cashLedgerApi, cashLedgerHelpers } from '../api/cashLedgerApi'

export default {
  name: 'CashLedgerList',
  data() {
    return {
      loading: false,
      transactions: [],
      warehouses: [],
      summary: {
        total_receipts: 0,
        total_payments: 0,
        net_balance: 0,
        receipts_count: 0,
        payments_count: 0
      },
      filters: {
        type: '',
        warehouse_id: '',
        date_from: this.getDefaultDateFrom(),
        date_to: this.getDefaultDateTo()
      },
      showDetailModal: false,
      selectedTransaction: null
    }
  },
  mounted() {
    this.loadData()
    this.loadWarehouses()
  },
  methods: {
    getDefaultDateFrom() {
      const date = new Date()
      date.setDate(1) // First day of current month
      return date.toISOString().split('T')[0]
    },

    getDefaultDateTo() {
      const date = new Date()
      return date.toISOString().split('T')[0]
    },

    async loadData() {
      this.loading = true
      try {
        const params = { ...this.filters }
        
        // Remove empty params
        Object.keys(params).forEach(key => {
          if (params[key] === '' || params[key] === null) {
            delete params[key]
          }
        })

        const response = await cashLedgerApi.getAll(params)
        
        if (response.success) {
          this.transactions = response.data.transactions || []
          this.summary = response.data.summary || {}
        }
      } catch (error) {
        console.error('Error loading ledger data:', error)
        this.showNotification('Lỗi khi tải dữ liệu sổ quỹ', 'error')
      } finally {
        this.loading = false
      }
    },

    async loadWarehouses() {
      try {
        // You may need to adjust this endpoint
        const response = await cashLedgerApi.getAll()
        this.warehouses = response.data || []
      } catch (error) {
        console.error('Error loading warehouses:', error)
      }
    },

    async exportData() {
      try {
        const exportParams = { ...this.filters }
        
        await cashLedgerApi.export(exportParams)
        this.showNotification('Xuất báo cáo thành công')
      } catch (error) {
        console.error('Export error:', error)
        this.showNotification(error.message || 'Lỗi khi xuất báo cáo', 'error')
      }
    },

    applyFilters() {
      this.loadData()
    },

    resetFilters() {
      this.filters = {
        type: '',
        warehouse_id: '',
        date_from: this.getDefaultDateFrom(),
        date_to: this.getDefaultDateTo()
      }
      this.loadData()
    },

    viewTransaction(transaction) {
      this.selectedTransaction = transaction
      this.showDetailModal = true
    },

    // Helper methods using API helpers
    formatCurrency(amount) {
      return cashLedgerHelpers.formatCurrency(amount)
    },

    formatDate(date) {
      return cashLedgerHelpers.formatDate(date)
    },

    formatBalance(balance) {
      return cashLedgerHelpers.formatBalance(balance)
    },

    getBalanceClass(balance) {
      return cashLedgerHelpers.getBalanceClass(balance)
    },

    getTransactionTypeText(type) {
      return cashLedgerHelpers.getTransactionTypeText(type)
    },

    getTransactionTypeClass(type) {
      return cashLedgerHelpers.getTransactionTypeClass(type)
    },

    showNotification(message, type = 'success') {
      // Implement notification system
      console.log(`${type}: ${message}`)
    }
  }
}
</script>

<style scoped>
/* Custom styles for better UX */
.truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* Responsive table */
@media (max-width: 768px) {
  .overflow-x-auto {
    -webkit-overflow-scrolling: touch;
  }
}

/* Better hover effects */
tbody tr:hover {
  background-color: #f9fafb;
  cursor: pointer;
}

/* Status badges */
.inline-flex {
  display: inline-flex;
  align-items: center;
  padding: 0.25rem 0.5rem;
  border-radius: 9999px;
  font-size: 0.75rem;
  font-weight: 600;
  line-height: 1;
}

/* Balance styling */
.text-green-600 {
  color: #059669;
}

.text-red-600 {
  color: #dc2626;
}

/* Card shadows */
.shadow {
  box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
}

.shadow-lg {
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
}
</style>