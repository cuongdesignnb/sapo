<template>
  <div class="customer-debt-list">
    <!-- Header -->
    <div class="flex justify-between items-center mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Quản lý công nợ khách hàng</h1>
        <p class="text-gray-600 mt-1">Theo dõi và quản lý các giao dịch công nợ</p>
      </div>
      <div class="flex gap-3">
        <button @click="showQuickPayment = true" class="btn btn-success">
          <i class="fas fa-credit-card mr-2"></i>Thanh toán nhanh
        </button>
        <button @click="showForm = true" class="btn btn-primary">
          <i class="fas fa-plus mr-2"></i>Thêm giao dịch
        </button>
      </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow-sm p-4 mb-6">
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Customer Filter -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Khách hàng</label>
          <input
            v-model="filters.customer_name"
            type="text"
            placeholder="Tìm theo tên khách hàng..."
            class="form-input w-full"
          />
        </div>

        <!-- Date Range -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Từ ngày</label>
          <input
            v-model="filters.start_date"
            type="date"
            class="form-input w-full"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Đến ngày</label>
          <input
            v-model="filters.end_date"
            type="date"
            class="form-input w-full"
          />
        </div>

        <!-- Type Filter -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Loại giao dịch</label>
          <select v-model="filters.type" class="form-select w-full">
            <option value="">Tất cả</option>
            <option value="debt">Nợ phát sinh</option>
            <option value="payment">Thanh toán</option>
          </select>
        </div>

        <!-- Ref Code -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Mã tham chiếu</label>
          <input
            v-model="filters.ref_code"
            type="text"
            placeholder="Mã tham chiếu..."
            class="form-input w-full"
          />
        </div>

        <!-- Amount Range -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Số tiền từ</label>
          <input
            v-model.number="filters.min_amount"
            type="number"
            placeholder="0"
            class="form-input w-full"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Số tiền đến</label>
          <input
            v-model.number="filters.max_amount"
            type="number"
            placeholder="0"
            class="form-input w-full"
          />
        </div>

        <!-- Filter Actions -->
        <div class="flex items-end gap-2">
          <button @click="loadDebts" class="btn btn-primary flex-1">
            <i class="fas fa-search mr-2"></i>Lọc
          </button>
          <button @click="resetFilters" class="btn btn-outline">
            <i class="fas fa-undo mr-2"></i>Reset
          </button>
        </div>
      </div>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6" v-if="statistics">
      <div class="bg-white rounded-lg shadow-sm p-4">
        <div class="flex items-center">
          <div class="p-2 bg-blue-100 rounded-lg">
            <i class="fas fa-list text-blue-600"></i>
          </div>
          <div class="ml-3">
            <p class="text-sm text-gray-600">Tổng giao dịch</p>
            <p class="text-xl font-bold">{{ statistics.total_transactions || 0 }}</p>
          </div>
        </div>
      </div>
      
      <div class="bg-white rounded-lg shadow-sm p-4">
        <div class="flex items-center">
          <div class="p-2 bg-red-100 rounded-lg">
            <i class="fas fa-arrow-up text-red-600"></i>
          </div>
          <div class="ml-3">
            <p class="text-sm text-gray-600">Tổng nợ phát sinh</p>
            <p class="text-xl font-bold text-red-600">{{ formatCurrency(statistics.total_debt_amount) }}</p>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-lg shadow-sm p-4">
        <div class="flex items-center">
          <div class="p-2 bg-green-100 rounded-lg">
            <i class="fas fa-arrow-down text-green-600"></i>
          </div>
          <div class="ml-3">
            <p class="text-sm text-gray-600">Tổng thanh toán</p>
            <p class="text-xl font-bold text-green-600">{{ formatCurrency(statistics.total_payment_amount) }}</p>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-lg shadow-sm p-4">
        <div class="flex items-center">
          <div class="p-2 bg-purple-100 rounded-lg">
            <i class="fas fa-users text-purple-600"></i>
          </div>
          <div class="ml-3">
            <p class="text-sm text-gray-600">Khách hàng</p>
            <p class="text-xl font-bold">{{ statistics.unique_customers || 0 }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Actions Bar -->
    <div class="flex justify-between items-center mb-4">
      <div class="flex items-center gap-4">
        <div class="flex items-center gap-2" v-if="selectedDebts.length > 0">
          <span class="text-sm text-gray-600">Đã chọn {{ selectedDebts.length }} mục</span>
          <button @click="bulkDelete" class="btn btn-danger btn-sm">
            <i class="fas fa-trash mr-1"></i>Xóa
          </button>
        </div>
      </div>

      <div class="flex items-center gap-2">
        <button @click="exportData" class="btn btn-outline btn-sm">
          <i class="fas fa-download mr-1"></i>Xuất Excel
        </button>
        <select v-model="perPage" @change="loadDebts" class="form-select text-sm">
          <option value="15">15/trang</option>
          <option value="25">25/trang</option>
          <option value="50">50/trang</option>
          <option value="100">100/trang</option>
        </select>
      </div>
    </div>

    <!-- Data Table -->
    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left">
                <input
                  type="checkbox"
                  :checked="allSelected"
                  @change="toggleSelectAll"
                  class="rounded border-gray-300"
                />
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Khách hàng
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Mã tham chiếu
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Số tiền
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Tổng nợ
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Loại
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Ngày ghi nhận
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Người tạo
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Thao tác
              </th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-if="loading" class="animate-pulse">
              <td colspan="9" class="px-6 py-4 text-center text-gray-500">
                <i class="fas fa-spinner fa-spin mr-2"></i>Đang tải dữ liệu...
              </td>
            </tr>
            <tr v-else-if="debts.length === 0">
              <td colspan="9" class="px-6 py-4 text-center text-gray-500">
                <i class="fas fa-inbox mr-2"></i>Không có dữ liệu
              </td>
            </tr>
            <tr v-else v-for="debt in debts" :key="debt.id" class="hover:bg-gray-50">
              <td class="px-6 py-4">
                <input
                  type="checkbox"
                  :value="debt.id"
                  v-model="selectedDebts"
                  class="rounded border-gray-300"
                />
              </td>
              <td class="px-6 py-4">
                <div class="flex flex-col">
                  <span class="font-medium text-gray-900">{{ debt.customer?.name }}</span>
                  <span class="text-sm text-gray-500">{{ debt.customer?.code }}</span>
                  <span class="text-sm text-gray-500">{{ debt.customer?.phone }}</span>
                </div>
              </td>
              <td class="px-6 py-4">
                <span class="font-mono text-sm">{{ debt.ref_code }}</span>
                <div v-if="debt.order" class="text-xs text-gray-500">
                  Đơn hàng: {{ debt.order.code }}
                </div>
              </td>
              <td class="px-6 py-4">
                <span :class="getAmountColorClass(debt.amount)" class="font-bold">
                  {{ formatAmount(debt.amount) }}
                </span>
              </td>
              <td class="px-6 py-4">
                <span class="font-bold">{{ formatCurrency(debt.debt_total) }}</span>
              </td>
              <td class="px-6 py-4">
                <span :class="getBadgeClass(debt.amount)" class="px-2 py-1 rounded-full text-xs font-medium">
                  {{ getTransactionType(debt.amount) }}
                </span>
              </td>
              <td class="px-6 py-4">
                <div class="text-sm">
                  <div>{{ formatDate(debt.recorded_at) }}</div>
                  <div class="text-gray-500">{{ formatTime(debt.recorded_at) }}</div>
                </div>
              </td>
              <td class="px-6 py-4">
                <span class="text-sm">{{ debt.creator?.name || '-' }}</span>
              </td>
              <td class="px-6 py-4">
                <div class="flex items-center gap-2">
                  <button @click="viewDetail(debt)" class="btn-icon btn-primary" title="Xem chi tiết">
                    <i class="fas fa-eye"></i>
                  </button>
                  <button @click="editDebt(debt)" class="btn-icon btn-warning" title="Sửa">
                    <i class="fas fa-edit"></i>
                  </button>
                  <button @click="deleteDebt(debt)" class="btn-icon btn-danger" title="Xóa">
                    <i class="fas fa-trash"></i>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="px-6 py-3 border-t border-gray-200" v-if="pagination.last_page > 1">
        <div class="flex items-center justify-between">
          <div class="text-sm text-gray-700">
            Hiển thị {{ pagination.from }} - {{ pagination.to }} trong tổng {{ pagination.total }} kết quả
          </div>
          <div class="flex items-center gap-2">
            <button
              @click="goToPage(pagination.current_page - 1)"
              :disabled="pagination.current_page <= 1"
              class="btn btn-outline btn-sm"
            >
              <i class="fas fa-chevron-left"></i>
            </button>
            
            <template v-for="page in getVisiblePages()" :key="page">
              <button
                v-if="page === '...'"
                disabled
                class="btn btn-outline btn-sm"
              >
                ...
              </button>
              <button
                v-else
                @click="goToPage(page)"
                :class="[
                  'btn btn-sm',
                  page === pagination.current_page ? 'btn-primary' : 'btn-outline'
                ]"
              >
                {{ page }}
              </button>
            </template>

            <button
              @click="goToPage(pagination.current_page + 1)"
              :disabled="pagination.current_page >= pagination.last_page"
              class="btn btn-outline btn-sm"
            >
              <i class="fas fa-chevron-right"></i>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Customer Debt Form Modal -->
    <CustomerDebtForm
      v-if="showForm"
      :debt="selectedDebt"
      @close="closeForm"
      @saved="handleSaved"
    />

    <!-- Customer Debt Detail Modal -->
    <CustomerDebtDetail
      v-if="showDetail"
      :debt="selectedDebt"
      @close="showDetail = false"
      @edit="editDebt"
      @delete="deleteDebt"
    />

    <!-- Quick Payment Modal -->
    <div v-if="showQuickPayment" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg p-6 w-full max-w-md">
        <h3 class="text-lg font-bold mb-4">Thanh toán nhanh</h3>
        <form @submit.prevent="submitQuickPayment">
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Khách hàng *</label>
              <select v-model="quickPayment.customer_id" class="form-select w-full" required>
                <option value="">Chọn khách hàng</option>
                <option v-for="customer in customersWithDebt" :key="customer.id" :value="customer.id">
                  {{ customer.name }} ({{ formatCurrency(customer.total_debt) }})
                </option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Số tiền thanh toán *</label>
              <input
                v-model.number="quickPayment.amount"
                type="number"
                step="1000"
                min="1"
                class="form-input w-full"
                required
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Ghi chú</label>
              <textarea
                v-model="quickPayment.note"
                class="form-textarea w-full"
                rows="3"
                placeholder="Ghi chú thanh toán..."
              ></textarea>
            </div>
          </div>
          <div class="flex justify-end gap-3 mt-6">
            <button type="button" @click="showQuickPayment = false" class="btn btn-outline">
              Hủy
            </button>
            <button type="submit" :disabled="quickPaymentLoading" class="btn btn-success">
              <i v-if="quickPaymentLoading" class="fas fa-spinner fa-spin mr-2"></i>
              Thanh toán
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import customerDebtApi from '../api/customerDebtApi.js';
import CustomerDebtForm from '../components/CustomerDebtForm.vue';
import CustomerDebtDetail from '../components/CustomerDebtDetail.vue';

export default {
  name: 'CustomerDebtList',
  components: {
    CustomerDebtForm,
    CustomerDebtDetail
  },
  setup() {
    // Reactive data
    const loading = ref(false);
    const debts = ref([]);
    const pagination = ref({});
    const statistics = ref(null);
    const selectedDebts = ref([]);
    const showForm = ref(false);
    const showDetail = ref(false);
    const showQuickPayment = ref(false);
    const selectedDebt = ref(null);
    const perPage = ref(15);
    const customersWithDebt = ref([]);
    const quickPaymentLoading = ref(false);

    // Filters
    const filters = reactive({
      customer_name: '',
      start_date: '',
      end_date: '',
      type: '',
      ref_code: '',
      min_amount: '',
      max_amount: ''
    });

    // Quick payment form
    const quickPayment = reactive({
      customer_id: '',
      amount: '',
      note: ''
    });

    // Computed
    const allSelected = computed(() => {
      return debts.value.length > 0 && selectedDebts.value.length === debts.value.length;
    });

    // Methods
    const loadDebts = async (page = 1) => {
      loading.value = true;
      try {
        const params = {
          ...filters,
          page,
          per_page: perPage.value
        };

        const response = await customerDebtApi.getAll(params);
        debts.value = response.data.data;
        pagination.value = {
          current_page: response.data.current_page,
          last_page: response.data.last_page,
          per_page: response.data.per_page,
          total: response.data.total,
          from: response.data.from,
          to: response.data.to
        };
      } catch (error) {
        console.error('Load debts error:', error);
      } finally {
        loading.value = false;
      }
    };

    const loadStatistics = async () => {
      try {
        const params = {
          start_date: filters.start_date,
          end_date: filters.end_date
        };
        const response = await customerDebtApi.getStatistics(params);
        statistics.value = response.data.statistics;
      } catch (error) {
        console.error('Load statistics error:', error);
      }
    };

    const loadCustomersWithDebt = async () => {
      try {
        const response = await customerDebtApi.getCustomersWithDebt();
        customersWithDebt.value = response.data;
      } catch (error) {
        console.error('Load customers with debt error:', error);
      }
    };

    const resetFilters = () => {
      Object.keys(filters).forEach(key => {
        filters[key] = '';
      });
      loadDebts();
    };

    const toggleSelectAll = () => {
      if (allSelected.value) {
        selectedDebts.value = [];
      } else {
        selectedDebts.value = debts.value.map(debt => debt.id);
      }
    };

    const viewDetail = (debt) => {
      selectedDebt.value = debt;
      showDetail.value = true;
    };

    const editDebt = (debt) => {
      selectedDebt.value = debt;
      showForm.value = true;
    };

    const deleteDebt = async (debt) => {
      if (!confirm(`Bạn có chắc chắn muốn xóa giao dịch ${debt.ref_code}?`)) return;

      try {
        await customerDebtApi.delete(debt.id);
        await loadDebts(pagination.value.current_page);
        await loadStatistics();
      } catch (error) {
        alert('Có lỗi xảy ra khi xóa giao dịch');
      }
    };

    const bulkDelete = async () => {
      if (!confirm(`Bạn có chắc chắn muốn xóa ${selectedDebts.value.length} giao dịch đã chọn?`)) return;

      try {
        await customerDebtApi.bulkDelete(selectedDebts.value);
        selectedDebts.value = [];
        await loadDebts(pagination.value.current_page);
        await loadStatistics();
      } catch (error) {
        alert('Có lỗi xảy ra khi xóa các giao dịch');
      }
    };

    const exportData = async () => {
      try {
        const response = await customerDebtApi.export(filters);
        customerDebtApi.exportToCSV(response.data, 'cong-no-khach-hang');
      } catch (error) {
        alert('Có lỗi xảy ra khi xuất dữ liệu');
      }
    };

    const submitQuickPayment = async () => {
      quickPaymentLoading.value = true;
      try {
        await customerDebtApi.addPayment(quickPayment);
        
        // Reset form
        Object.keys(quickPayment).forEach(key => {
          quickPayment[key] = '';
        });
        
        showQuickPayment.value = false;
        await loadDebts(pagination.value.current_page);
        await loadStatistics();
        await loadCustomersWithDebt();
      } catch (error) {
        alert('Có lỗi xảy ra khi tạo thanh toán');
      } finally {
        quickPaymentLoading.value = false;
      }
    };

    const closeForm = () => {
      showForm.value = false;
      selectedDebt.value = null;
    };

    const handleSaved = () => {
      closeForm();
      loadDebts(pagination.value.current_page);
      loadStatistics();
    };

    const goToPage = (page) => {
      if (page >= 1 && page <= pagination.value.last_page) {
        loadDebts(page);
      }
    };

    const getVisiblePages = () => {
      const current = pagination.value.current_page;
      const last = pagination.value.last_page;
      const pages = [];

      if (last <= 7) {
        for (let i = 1; i <= last; i++) {
          pages.push(i);
        }
      } else {
        pages.push(1);
        if (current > 4) pages.push('...');
        
        const start = Math.max(2, current - 2);
        const end = Math.min(last - 1, current + 2);
        
        for (let i = start; i <= end; i++) {
          pages.push(i);
        }
        
        if (current < last - 3) pages.push('...');
        pages.push(last);
      }

      return pages;
    };

    // Utility methods
    const formatCurrency = (amount) => {
      if (!amount) return '0 VNĐ';
      return new Intl.NumberFormat('vi-VN').format(amount) + ' VNĐ';
    };

    const formatAmount = (amount) => {
      const prefix = amount > 0 ? '+' : '';
      return prefix + new Intl.NumberFormat('vi-VN').format(Math.abs(amount)) + ' VNĐ';
    };

    const getTransactionType = (amount) => {
      return amount > 0 ? 'Nợ' : 'Thanh toán';
    };

    const getAmountColorClass = (amount) => {
      return amount > 0 ? 'text-red-600' : 'text-green-600';
    };

    const getBadgeClass = (amount) => {
      return amount > 0 ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800';
    };

    const formatDate = (dateString) => {
      const date = new Date(dateString);
      return date.toLocaleDateString('vi-VN');
    };

    const formatTime = (dateString) => {
      const date = new Date(dateString);
      return date.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
    };

    // Watch filters
    watch(filters, () => {
      loadStatistics();
    }, { deep: true });

    // Lifecycle
    onMounted(() => {
      loadDebts();
      loadStatistics();
      loadCustomersWithDebt();
    });

    return {
      // Data
      loading,
      debts,
      pagination,
      statistics,
      selectedDebts,
      showForm,
      showDetail,
      showQuickPayment,
      selectedDebt,
      perPage,
      customersWithDebt,
      quickPaymentLoading,
      filters,
      quickPayment,

      // Computed
      allSelected,

      // Methods
      loadDebts,
      resetFilters,
      toggleSelectAll,
      viewDetail,
      editDebt,
      deleteDebt,
      bulkDelete,
      exportData,
      submitQuickPayment,
      closeForm,
      handleSaved,
      goToPage,
      getVisiblePages,

      // Utilities
      formatCurrency,
      formatAmount,
      getTransactionType,
      getAmountColorClass,
      getBadgeClass,
      formatDate,
      formatTime
    };
  }
};
</script>