<template>
  <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg w-full max-w-4xl max-h-[90vh] overflow-hidden">
      <!-- Header -->
      <div class="flex items-center justify-between p-6 border-b border-gray-200">
        <div>
          <h2 class="text-xl font-bold text-gray-900">Chi tiết giao dịch công nợ</h2>
          <p class="text-gray-600 mt-1">{{ debt.ref_code }}</p>
        </div>
        <button @click="$emit('close')" class="btn-icon btn-outline">
          <i class="fas fa-times"></i>
        </button>
      </div>

      <!-- Content -->
      <div class="flex flex-1 overflow-hidden">
        <!-- Sidebar -->
        <div class="w-1/3 bg-gray-50 p-6 border-r border-gray-200 overflow-y-auto">
          <!-- Transaction Info -->
          <div class="mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Thông tin giao dịch</h3>
            
            <!-- Transaction Type -->
            <div class="mb-4">
              <span :class="getBadgeClass(debt.amount)" class="px-3 py-1 rounded-full text-sm font-medium">
                <i :class="debt.amount > 0 ? 'fas fa-arrow-up' : 'fas fa-arrow-down'" class="mr-1"></i>
                {{ getTransactionType(debt.amount) }}
              </span>
            </div>

            <!-- Amount -->
            <div class="mb-4">
              <label class="block text-sm font-medium text-gray-700 mb-1">Số tiền</label>
              <div :class="getAmountColorClass(debt.amount)" class="text-2xl font-bold">
                {{ formatAmount(debt.amount) }}
              </div>
            </div>

            <!-- Debt Total -->
            <div class="mb-4">
              <label class="block text-sm font-medium text-gray-700 mb-1">Tổng nợ sau giao dịch</label>
              <div class="text-xl font-bold text-gray-900">
                {{ formatCurrency(debt.debt_total) }}
              </div>
            </div>

            <!-- Ref Code -->
            <div class="mb-4">
              <label class="block text-sm font-medium text-gray-700 mb-1">Mã tham chiếu</label>
              <div class="font-mono text-sm bg-gray-100 p-2 rounded">
                {{ debt.ref_code }}
              </div>
            </div>

            <!-- Order Reference -->
            <div class="mb-4" v-if="debt.order">
              <label class="block text-sm font-medium text-gray-700 mb-1">Đơn hàng liên quan</label>
              <div class="bg-blue-50 p-3 rounded">
                <div class="font-medium text-blue-900">{{ debt.order.code }}</div>
                <div class="text-sm text-blue-700">Tổng: {{ formatCurrency(debt.order.total) }}</div>
                <div class="text-sm text-blue-700">Trạng thái: {{ debt.order.status }}</div>
              </div>
            </div>

            <!-- Recorded Date -->
            <div class="mb-4">
              <label class="block text-sm font-medium text-gray-700 mb-1">Ngày ghi nhận</label>
              <div class="text-sm">
                <div>{{ formatDate(debt.recorded_at) }}</div>
                <div class="text-gray-500">{{ formatTime(debt.recorded_at) }}</div>
              </div>
            </div>

            <!-- Creator -->
            <div class="mb-4">
              <label class="block text-sm font-medium text-gray-700 mb-1">Người tạo</label>
              <div class="text-sm">{{ debt.creator?.name || 'N/A' }}</div>
            </div>

            <!-- Note -->
            <div class="mb-4" v-if="debt.note">
              <label class="block text-sm font-medium text-gray-700 mb-1">Ghi chú</label>
              <div class="text-sm bg-gray-100 p-3 rounded">{{ debt.note }}</div>
            </div>

            <!-- Created/Updated -->
            <div class="text-xs text-gray-500 border-t pt-4">
              <div>Tạo: {{ formatDateTime(debt.created_at) }}</div>
              <div v-if="debt.updated_at !== debt.created_at">
                Cập nhật: {{ formatDateTime(debt.updated_at) }}
              </div>
            </div>
          </div>

          <!-- Quick Actions -->
          <div class="space-y-2">
            <button @click="quickPayment" class="btn btn-success w-full" v-if="customerSummary">
              <i class="fas fa-credit-card mr-2"></i>Thanh toán nhanh
            </button>
            <button @click="$emit('edit', debt)" class="btn btn-warning w-full">
              <i class="fas fa-edit mr-2"></i>Sửa giao dịch
            </button>
            <button @click="exportCustomerDebt" class="btn btn-outline w-full">
              <i class="fas fa-download mr-2"></i>Xuất dữ liệu
            </button>
          </div>
        </div>

        <!-- Main Content -->
        <div class="flex-1 overflow-y-auto">
          <!-- Tabs -->
          <div class="border-b border-gray-200">
            <nav class="flex space-x-8 px-6 pt-4">
              <button
                @click="activeTab = 'customer'"
                :class="[
                  'py-2 px-1 border-b-2 font-medium text-sm',
                  activeTab === 'customer'
                    ? 'border-blue-500 text-blue-600'
                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                ]"
              >
                Thông tin khách hàng
              </button>
              <button
                @click="activeTab = 'timeline'"
                :class="[
                  'py-2 px-1 border-b-2 font-medium text-sm',
                  activeTab === 'timeline'
                    ? 'border-blue-500 text-blue-600'
                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                ]"
              >
                Lịch sử giao dịch
              </button>
              <button
                @click="activeTab = 'summary'"
                :class="[
                  'py-2 px-1 border-b-2 font-medium text-sm',
                  activeTab === 'summary'
                    ? 'border-blue-500 text-blue-600'
                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                ]"
              >
                Tổng hợp công nợ
              </button>
            </nav>
          </div>

          <!-- Tab Content -->
          <div class="p-6">
            <!-- Customer Info Tab -->
            <div v-if="activeTab === 'customer'" class="space-y-6">
              <div class="bg-white border border-gray-200 rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Thông tin khách hàng</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tên khách hàng</label>
                    <div class="text-lg font-bold">{{ debt.customer?.name }}</div>
                  </div>
                  
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Mã khách hàng</label>
                    <div class="font-mono">{{ debt.customer?.code }}</div>
                  </div>
                  
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <div>{{ debt.customer?.email || 'N/A' }}</div>
                  </div>
                  
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Số điện thoại</label>
                    <div>{{ debt.customer?.phone || 'N/A' }}</div>
                  </div>
                  
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Loại khách hàng</label>
                    <div>{{ debt.customer?.customer_type || 'N/A' }}</div>
                  </div>
                  
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Người phụ trách</label>
                    <div>{{ debt.customer?.person_in_charge || 'N/A' }}</div>
                  </div>
                  
                  <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tổng nợ hiện tại</label>
                    <div class="text-xl font-bold text-red-600">
                      {{ formatCurrency(debt.customer?.total_debt) }}
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Timeline Tab -->
            <div v-if="activeTab === 'timeline'" class="space-y-4">
              <div class="flex justify-between items-center">
                <h3 class="text-lg font-semibold text-gray-900">Lịch sử giao dịch</h3>
                <button @click="loadTimeline" :disabled="timelineLoading" class="btn btn-primary btn-sm">
                  <i :class="timelineLoading ? 'fas fa-spinner fa-spin' : 'fas fa-sync'" class="mr-1"></i>
                  Làm mới
                </button>
              </div>

              <div v-if="timelineLoading" class="text-center py-8">
                <i class="fas fa-spinner fa-spin text-gray-400 text-2xl"></i>
                <p class="text-gray-500 mt-2">Đang tải lịch sử...</p>
              </div>

              <div v-else-if="timeline.length === 0" class="text-center py-8">
                <i class="fas fa-inbox text-gray-400 text-2xl"></i>
                <p class="text-gray-500 mt-2">Không có giao dịch nào</p>
              </div>

              <div v-else class="space-y-3">
                <div
                  v-for="transaction in timeline"
                  :key="transaction.id"
                  :class="[
                    'border rounded-lg p-4 transition-colors',
                    transaction.id === debt.id ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:bg-gray-50'
                  ]"
                >
                  <div class="flex items-start justify-between">
                    <div class="flex-1">
                      <div class="flex items-center gap-3 mb-2">
                        <span :class="getBadgeClass(transaction.amount)" class="px-2 py-1 rounded-full text-xs font-medium">
                          {{ getTransactionType(transaction.amount) }}
                        </span>
                        <span class="font-mono text-sm text-gray-600">{{ transaction.ref_code }}</span>
                        <span v-if="transaction.id === debt.id" class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs">
                          Hiện tại
                        </span>
                      </div>
                      
                      <div class="grid grid-cols-3 gap-4 text-sm">
                        <div>
                          <span class="text-gray-500">Số tiền:</span>
                          <div :class="getAmountColorClass(transaction.amount)" class="font-bold">
                            {{ formatAmount(transaction.amount) }}
                          </div>
                        </div>
                        <div>
                          <span class="text-gray-500">Tổng nợ:</span>
                          <div class="font-bold">{{ formatCurrency(transaction.debt_total) }}</div>
                        </div>
                        <div>
                          <span class="text-gray-500">Ngày:</span>
                          <div>{{ formatDate(transaction.recorded_at) }}</div>
                        </div>
                      </div>
                      
                      <div v-if="transaction.note" class="mt-2 text-sm text-gray-600">
                        {{ transaction.note }}
                      </div>
                      
                      <div v-if="transaction.order" class="mt-2 text-xs text-blue-600">
                        Đơn hàng: {{ transaction.order.code }}
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Summary Tab -->
            <div v-if="activeTab === 'summary'" class="space-y-6">
              <div class="flex justify-between items-center">
                <h3 class="text-lg font-semibold text-gray-900">Tổng hợp công nợ</h3>
                <button @click="loadCustomerSummary" :disabled="summaryLoading" class="btn btn-primary btn-sm">
                  <i :class="summaryLoading ? 'fas fa-spinner fa-spin' : 'fas fa-sync'" class="mr-1"></i>
                  Làm mới
                </button>
              </div>

              <div v-if="summaryLoading" class="text-center py-8">
                <i class="fas fa-spinner fa-spin text-gray-400 text-2xl"></i>
                <p class="text-gray-500 mt-2">Đang tải tổng hợp...</p>
              </div>

              <div v-else-if="customerSummary" class="space-y-4">
                <!-- Summary Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                  <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                    <div class="flex items-center">
                      <div class="p-2 bg-red-100 rounded-lg">
                        <i class="fas fa-arrow-up text-red-600"></i>
                      </div>
                      <div class="ml-3">
                        <p class="text-sm text-red-700">Tổng nợ phát sinh</p>
                        <p class="text-xl font-bold text-red-600">{{ formatCurrency(customerSummary.summary.total_debt) }}</p>
                        <p class="text-xs text-red-500">{{ customerSummary.summary.debt_transactions }} giao dịch</p>
                      </div>
                    </div>
                  </div>

                  <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                    <div class="flex items-center">
                      <div class="p-2 bg-green-100 rounded-lg">
                        <i class="fas fa-arrow-down text-green-600"></i>
                      </div>
                      <div class="ml-3">
                        <p class="text-sm text-green-700">Tổng thanh toán</p>
                        <p class="text-xl font-bold text-green-600">{{ formatCurrency(customerSummary.summary.total_paid) }}</p>
                        <p class="text-xs text-green-500">{{ customerSummary.summary.payment_transactions }} giao dịch</p>
                      </div>
                    </div>
                  </div>

                  <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <div class="flex items-center">
                      <div class="p-2 bg-blue-100 rounded-lg">
                        <i class="fas fa-balance-scale text-blue-600"></i>
                      </div>
                      <div class="ml-3">
                        <p class="text-sm text-blue-700">Số dư hiện tại</p>
                        <p class="text-xl font-bold text-blue-600">{{ formatCurrency(customerSummary.summary.current_balance) }}</p>
                        <p class="text-xs text-blue-500">
                          {{ customerSummary.summary.last_transaction_date ? 
                            'Cập nhật: ' + formatDate(customerSummary.summary.last_transaction_date) : 
                            'Chưa có giao dịch' }}
                        </p>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Quick Payment Form -->
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                  <h4 class="font-semibold text-gray-900 mb-3">Thanh toán nhanh</h4>
                  <form @submit.prevent="submitQuickPayment" class="space-y-3">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                      <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Số tiền thanh toán</label>
                        <input
                          v-model.number="quickPaymentForm.amount"
                          type="number"
                          step="1000"
                          min="1"
                          class="form-input w-full"
                          placeholder="Nhập số tiền..."
                        />
                      </div>
                      <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Ghi chú</label>
                        <input
                          v-model="quickPaymentForm.note"
                          type="text"
                          class="form-input w-full"
                          placeholder="Ghi chú thanh toán..."
                        />
                      </div>
                    </div>
                    <div class="flex justify-end">
                      <button 
                        type="submit" 
                        :disabled="!quickPaymentForm.amount || quickPaymentLoading"
                        class="btn btn-success"
                      >
                        <i v-if="quickPaymentLoading" class="fas fa-spinner fa-spin mr-2"></i>
                        <i v-else class="fas fa-credit-card mr-2"></i>
                        Thanh toán
                      </button>
                    </div>
                  </form>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Footer -->
      <div class="flex justify-end gap-3 p-6 border-t border-gray-200">
        <button @click="$emit('close')" class="btn btn-outline">
          Đóng
        </button>
        <button @click="$emit('edit', debt)" class="btn btn-warning">
          <i class="fas fa-edit mr-2"></i>Sửa
        </button>
        <button @click="$emit('delete', debt)" class="btn btn-danger">
          <i class="fas fa-trash mr-2"></i>Xóa
        </button>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, reactive, onMounted } from 'vue';
import customerDebtApi from '../api/customerDebtApi.js';

export default {
  name: 'CustomerDebtDetail',
  props: {
    debt: {
      type: Object,
      required: true
    }
  },
  emits: ['close', 'edit', 'delete'],
  setup(props, { emit }) {
    // Reactive data
    const activeTab = ref('customer');
    const timeline = ref([]);
    const customerSummary = ref(null);
    const timelineLoading = ref(false);
    const summaryLoading = ref(false);
    const quickPaymentLoading = ref(false);

    // Quick payment form
    const quickPaymentForm = reactive({
      amount: '',
      note: ''
    });

    // Methods
    const loadTimeline = async () => {
      timelineLoading.value = true;
      try {
        const response = await customerDebtApi.getCustomerTimeline(props.debt.customer_id);
        timeline.value = response.data;
      } catch (error) {
        console.error('Load timeline error:', error);
      } finally {
        timelineLoading.value = false;
      }
    };

    const loadCustomerSummary = async () => {
      summaryLoading.value = true;
      try {
        const response = await customerDebtApi.getCustomerSummary(props.debt.customer_id);
        customerSummary.value = response.data;
      } catch (error) {
        console.error('Load customer summary error:', error);
      } finally {
        summaryLoading.value = false;
      }
    };

    const submitQuickPayment = async () => {
      if (!quickPaymentForm.amount) return;

      quickPaymentLoading.value = true;
      try {
        await customerDebtApi.addPayment({
          customer_id: props.debt.customer_id,
          amount: quickPaymentForm.amount,
          note: quickPaymentForm.note || 'Thanh toán nhanh'
        });

        // Reset form
        quickPaymentForm.amount = '';
        quickPaymentForm.note = '';

        // Reload data
        await loadTimeline();
        await loadCustomerSummary();
        
        alert('Thanh toán đã được ghi nhận thành công!');
      } catch (error) {
        alert('Có lỗi xảy ra khi tạo thanh toán');
      } finally {
        quickPaymentLoading.value = false;
      }
    };

    const quickPayment = () => {
      activeTab.value = 'summary';
      if (!customerSummary.value) {
        loadCustomerSummary();
      }
    };

    const exportCustomerDebt = async () => {
      try {
        const response = await customerDebtApi.export({
          customer_id: props.debt.customer_id
        });
        customerDebtApi.exportToCSV(response.data, `cong-no-${props.debt.customer?.code}`);
      } catch (error) {
        alert('Có lỗi xảy ra khi xuất dữ liệu');
      }
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

    const formatDateTime = (dateString) => {
      const date = new Date(dateString);
      return date.toLocaleString('vi-VN');
    };

    // Lifecycle
    onMounted(() => {
      loadTimeline();
      loadCustomerSummary();
    });

    return {
      // Data
      activeTab,
      timeline,
      customerSummary,
      timelineLoading,
      summaryLoading,
      quickPaymentLoading,
      quickPaymentForm,

      // Methods
      loadTimeline,
      loadCustomerSummary,
      submitQuickPayment,
      quickPayment,
      exportCustomerDebt,

      // Utilities
      formatCurrency,
      formatAmount,
      getTransactionType,
      getAmountColorClass,
      getBadgeClass,
      formatDate,
      formatTime,
      formatDateTime
    };
  }
};
</script>