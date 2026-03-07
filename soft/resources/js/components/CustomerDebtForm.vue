<template>
  <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded w-100" style="max-width: 900px; max-height: 95vh;overflow: auto;">
      <!-- Header -->
      <div class="flex items-center justify-between p-6 border-b border-gray-200">
        <div>
          <h2 class="text-xl font-bold text-gray-900">
            {{ isEdit ? 'Sửa giao dịch công nợ' : 'Thêm giao dịch công nợ' }}
          </h2>
          <p class="text-gray-600 mt-1">
            {{ isEdit ? `Cập nhật giao dịch ${debt?.ref_code}` : 'Tạo giao dịch công nợ mới' }}
          </p>
        </div>
        <button @click="$emit('close')" class="btn-icon btn-outline">
          <i class="fas fa-times"></i>
        </button>
      </div>

      <!-- Form Content -->
      <div class="overflow-y-auto max-h-[calc(90vh-140px)]">
        <form @submit.prevent="handleSubmit" class="p-6 space-y-6">
          <!-- Transaction Type Toggle -->
          <div class="bg-gray-50 rounded-lg p-4">
            <label class="block text-sm font-medium text-gray-700 mb-3">Loại giao dịch</label>
            <div class="flex gap-4">
              <label class="flex items-center cursor-pointer">
                <input
                  v-model="transactionType"
                  type="radio"
                  value="debt"
                  class="sr-only"
                />
                <div :class="[
                  'flex items-center px-4 py-2 rounded-lg border-2 transition-colors',
                  transactionType === 'debt' 
                    ? 'border-red-500 bg-red-50 text-red-700' 
                    : 'border-gray-300 bg-white text-gray-600 hover:border-gray-400'
                ]">
                  <i class="fas fa-arrow-up mr-2"></i>
                  Nợ phát sinh
                </div>
              </label>
              <label class="flex items-center cursor-pointer">
                <input
                  v-model="transactionType"
                  type="radio"
                  value="payment"
                  class="sr-only"
                />
                <div :class="[
                  'flex items-center px-4 py-2 rounded-lg border-2 transition-colors',
                  transactionType === 'payment' 
                    ? 'border-green-500 bg-green-50 text-green-700' 
                    : 'border-gray-300 bg-white text-gray-600 hover:border-gray-400'
                ]">
                  <i class="fas fa-arrow-down mr-2"></i>
                  Thanh toán
                </div>
              </label>
            </div>
          </div>

          <!-- Main Form Fields -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Customer -->
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Khách hàng <span class="text-red-500">*</span>
              </label>
              <div class="relative">
                <input
                  v-model="customerSearch"
                  @input="searchCustomers"
                  @focus="showCustomerDropdown = true"
                  type="text"
                  class="form-input w-full pr-10"
                  placeholder="Tìm kiếm khách hàng..."
                  autocomplete="off"
                />
                <i class="fas fa-search absolute right-3 top-3 text-gray-400"></i>
                
                <!-- Customer Dropdown -->
                <div v-if="showCustomerDropdown && customers.length > 0" 
     class="position-absolute w-100 mt-1 bg-white border rounded shadow-lg overflow-auto"
     style="z-index: 9999; max-height: 200px;">
                  <div
                    v-for="customer in customers"
                    :key="customer.id"
                    @click="selectCustomer(customer)"
                    class="px-4 py-2 hover:bg-gray-100 cursor-pointer"
                  >
                    <div class="font-medium">{{ customer.name }}</div>
                    <div class="text-sm text-gray-500">{{ customer.code }} - {{ customer.phone }}</div>
                    <div class="text-sm text-red-600" v-if="customer.total_debt > 0">
                      Nợ hiện tại: {{ formatCurrency(customer.total_debt) }}
                    </div>
                  </div>
                </div>
              </div>
              
              <!-- Selected Customer Info -->
              <div v-if="form.customer_id && selectedCustomer" class="mt-2 p-3 bg-blue-50 rounded-lg">
                <div class="flex justify-between items-start">
                  <div>
                    <div class="font-medium text-blue-900">{{ selectedCustomer.name }}</div>
                    <div class="text-sm text-blue-700">{{ selectedCustomer.code }} - {{ selectedCustomer.phone }}</div>
                  </div>
                  <button @click="clearCustomer" type="button" class="text-blue-600 hover:text-blue-800">
                    <i class="fas fa-times"></i>
                  </button>
                </div>
                <div v-if="selectedCustomer.total_debt > 0" class="mt-2 text-sm">
                  <span class="text-blue-700">Tổng nợ hiện tại: </span>
                  <span class="font-bold text-red-600">{{ formatCurrency(selectedCustomer.total_debt) }}</span>
                </div>
              </div>
              
              <div v-if="errors.customer_id" class="mt-1 text-sm text-red-600">
                {{ errors.customer_id }}
              </div>
            </div>

            <!-- Amount -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Số tiền <span class="text-red-500">*</span>
              </label>
              <div class="relative">
                <input
                  v-model.number="rawAmount"
                  @input="updateAmount"
                  type="number"
                  step="1000"
                  min="1"
                  class="form-input w-full pr-16"
                  placeholder="Nhập số tiền..."
                />
                <span class="absolute right-3 top-3 text-gray-500 text-sm">VNĐ</span>
              </div>
              
              <!-- Amount Preview -->
              <div v-if="rawAmount" class="mt-1 text-sm">
                <span class="text-gray-600">Hiển thị: </span>
                <span :class="getAmountColorClass(form.amount)" class="font-bold">
                  {{ formatAmount(form.amount) }}
                </span>
              </div>
              
              <div v-if="errors.amount" class="mt-1 text-sm text-red-600">
                {{ errors.amount }}
              </div>
            </div>

            <!-- Ref Code -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Mã tham chiếu</label>
              <div class="flex gap-2">
                <input
                  v-model="form.ref_code"
                  type="text"
                  class="form-input flex-1"
                  placeholder="Tự động tạo nếu để trống"
                />
                <button @click="generateRefCode" type="button" class="btn btn-outline btn-sm">
                  <i class="fas fa-sync"></i>
                </button>
              </div>
              <div v-if="errors.ref_code" class="mt-1 text-sm text-red-600">
                {{ errors.ref_code }}
              </div>
            </div>

            <!-- Order Reference -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Đơn hàng liên quan</label>
              <div class="relative">
                <input
                  v-model="orderSearch"
                  @input="searchOrders"
                  @focus="showOrderDropdown = true"
                  type="text"
                  class="form-input w-full pr-10"
                  placeholder="Tìm kiếm đơn hàng..."
                  autocomplete="off"
                />
                <i class="fas fa-search absolute right-3 top-3 text-gray-400"></i>
                
                <!-- Order Dropdown -->
                <div v-if="showOrderDropdown && orders.length > 0" 
                     class="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-auto">
                  <div
                    v-for="order in orders"
                    :key="order.id"
                    @click="selectOrder(order)"
                    class="px-4 py-2 hover:bg-gray-100 cursor-pointer"
                  >
                    <div class="font-medium">{{ order.code }}</div>
                    <div class="text-sm text-gray-500">
                      Tổng: {{ formatCurrency(order.total) }} - {{ order.status }}
                    </div>
                  </div>
                </div>
              </div>
              
              <!-- Selected Order Info -->
              <div v-if="form.order_id && selectedOrder" class="mt-2 p-3 bg-green-50 rounded-lg">
                <div class="flex justify-between items-start">
                  <div>
                    <div class="font-medium text-green-900">{{ selectedOrder.code }}</div>
                    <div class="text-sm text-green-700">
                      Tổng: {{ formatCurrency(selectedOrder.total) }} - {{ selectedOrder.status }}
                    </div>
                  </div>
                  <button @click="clearOrder" type="button" class="text-green-600 hover:text-green-800">
                    <i class="fas fa-times"></i>
                  </button>
                </div>
              </div>
            </div>

            <!-- Recorded Date -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Ngày ghi nhận <span class="text-red-500">*</span>
              </label>
              <input
                v-model="form.recorded_at"
                type="datetime-local"
                class="form-input w-full"
              />
              <div v-if="errors.recorded_at" class="mt-1 text-sm text-red-600">
                {{ errors.recorded_at }}
              </div>
            </div>
          </div>

          <!-- Note -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Ghi chú</label>
            <textarea
              v-model="form.note"
              class="form-textarea w-full"
              rows="3"
              placeholder="Mô tả chi tiết về giao dịch..."
            ></textarea>
            <div class="mt-1 text-sm text-gray-500">
              Mẹo: Ghi rõ lý do phát sinh nợ hoặc thanh toán để dễ tra cứu sau này
            </div>
          </div>

          <!-- Preview Section -->
          <div v-if="selectedCustomer && rawAmount" class="bg-gray-50 rounded-lg p-4">
            <h4 class="font-medium text-gray-900 mb-3">Xem trước giao dịch</h4>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
              <div>
                <span class="text-gray-600">Khách hàng:</span>
                <div class="font-medium">{{ selectedCustomer.name }}</div>
              </div>
              <div>
                <span class="text-gray-600">Loại giao dịch:</span>
                <div :class="getBadgeClass(form.amount)" class="inline-block px-2 py-1 rounded text-xs font-medium mt-1">
                  {{ getTransactionType(form.amount) }}
                </div>
              </div>
              <div>
                <span class="text-gray-600">Số tiền:</span>
                <div :class="getAmountColorClass(form.amount)" class="font-bold">
                  {{ formatAmount(form.amount) }}
                </div>
              </div>
              <div>
                <span class="text-gray-600">Nợ hiện tại:</span>
                <div class="font-medium">{{ formatCurrency(selectedCustomer.total_debt) }}</div>
              </div>
              <div>
                <span class="text-gray-600">Nợ sau giao dịch:</span>
                <div class="font-bold" :class="getBalanceColor(selectedCustomer.total_debt + form.amount)">
                  {{ formatCurrency(selectedCustomer.total_debt + form.amount) }}
                </div>
              </div>
              <div>
                <span class="text-gray-600">Thay đổi:</span>
                <div :class="getAmountColorClass(form.amount)" class="font-bold">
                  {{ form.amount > 0 ? '+' : '' }}{{ formatCurrency(Math.abs(form.amount)) }}
                </div>
              </div>
            </div>
          </div>
        </form>
      </div>

      <!-- Footer -->
      <div class="flex justify-between items-center p-6 border-t border-gray-200">
        <div class="text-sm text-gray-500">
          <span class="text-red-500">*</span> Các trường bắt buộc
        </div>
        <div class="flex gap-3">
          <button @click="$emit('close')" type="button" class="btn btn-outline">
            Hủy
          </button>
          <button @click="handleSubmit" :disabled="!isFormValid || loading" class="btn btn-primary">
            <i v-if="loading" class="fas fa-spinner fa-spin mr-2"></i>
            <i v-else class="fas fa-save mr-2"></i>
            {{ isEdit ? 'Cập nhật' : 'Tạo mới' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, reactive, computed, onMounted, watch, nextTick } from 'vue';
import customerDebtApi from '../api/customerDebtApi.js';

export default {
  name: 'CustomerDebtForm',
  props: {
    debt: {
      type: Object,
      default: null
    }
  },
  emits: ['close', 'saved'],
  setup(props, { emit }) {
    // Reactive data
    const loading = ref(false);
    const errors = ref({});
    const customers = ref([]);
    const orders = ref([]);
    const selectedCustomer = ref(null);
    const selectedOrder = ref(null);
    const customerSearch = ref('');
    const orderSearch = ref('');
    const showCustomerDropdown = ref(false);
    const showOrderDropdown = ref(false);
    const transactionType = ref('debt');
    const rawAmount = ref('');

    // Form data
    const form = reactive({
      customer_id: '',
      order_id: '',
      ref_code: '',
      amount: 0,
      note: '',
      recorded_at: ''
    });

    // Computed
    const isEdit = computed(() => !!props.debt);
    
    const isFormValid = computed(() => {
      return form.customer_id && 
             rawAmount.value && 
             parseFloat(rawAmount.value) !== 0 && 
             form.recorded_at;
    });

    // Methods
    const initializeForm = () => {
      if (props.debt) {
        // Edit mode
        form.customer_id = props.debt.customer_id;
        form.order_id = props.debt.order_id || '';
        form.ref_code = props.debt.ref_code || '';
        form.amount = props.debt.amount;
        form.note = props.debt.note || '';
        form.recorded_at = props.debt.recorded_at ? 
          new Date(props.debt.recorded_at).toISOString().slice(0, 16) : '';
        
        // Set transaction type and raw amount
        transactionType.value = props.debt.amount > 0 ? 'debt' : 'payment';
        rawAmount.value = Math.abs(props.debt.amount);
        
        // Set selected customer and order
        selectedCustomer.value = props.debt.customer;
        customerSearch.value = props.debt.customer?.name || '';
        
        if (props.debt.order) {
          selectedOrder.value = props.debt.order;
          orderSearch.value = props.debt.order.code;
        }
      } else {
        // Create mode
        form.recorded_at = new Date().toISOString().slice(0, 16);
        generateRefCode();
      }
    };

    const updateAmount = () => {
      const amount = parseFloat(rawAmount.value) || 0;
      form.amount = transactionType.value === 'debt' ? amount : -amount;
    };

    const searchCustomers = async () => {
      if (customerSearch.value.length < 2) {
        customers.value = [];
        return;
      }

      try {
        const response = await customerDebtApi.searchCustomers(customerSearch.value);
        customers.value = response.data.data || [];
      } catch (error) {
        console.error('Search customers error:', error);
      }
    };

    const selectCustomer = (customer) => {
      selectedCustomer.value = customer;
      form.customer_id = customer.id;
      customerSearch.value = customer.name;
      showCustomerDropdown.value = false;
    };

    const clearCustomer = () => {
      selectedCustomer.value = null;
      form.customer_id = '';
      customerSearch.value = '';
    };

    const searchOrders = async () => {
      if (orderSearch.value.length < 2) {
        orders.value = [];
        return;
      }

      try {
        const response = await customerDebtApi.searchOrders(orderSearch.value);
        orders.value = response.data.data || [];
      } catch (error) {
        console.error('Search orders error:', error);
      }
    };

    const selectOrder = (order) => {
      selectedOrder.value = order;
      form.order_id = order.id;
      orderSearch.value = order.code;
      showOrderDropdown.value = false;
    };

    const clearOrder = () => {
      selectedOrder.value = null;
      form.order_id = '';
      orderSearch.value = '';
    };

    const generateRefCode = () => {
      form.ref_code = customerDebtApi.generateRefCode();
    };

    const handleSubmit = async () => {
      errors.value = {};
      
      // Validate
      const validation = customerDebtApi.validateDebtData(form);
      if (!validation.isValid) {
        errors.value = validation.errors;
        return;
      }

      loading.value = true;
      try {
        if (isEdit.value) {
          await customerDebtApi.update(props.debt.id, form);
        } else {
          await customerDebtApi.create(form);
        }
        
        emit('saved');
      } catch (error) {
        if (error.errors) {
          errors.value = error.errors;
        } else {
          alert(error.message || 'Có lỗi xảy ra');
        }
      } finally {
        loading.value = false;
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

    const getBalanceColor = (balance) => {
      if (balance > 0) return 'text-red-600';
      if (balance < 0) return 'text-green-600';
      return 'text-gray-600';
    };

    // Close dropdowns when clicking outside
    const handleClickOutside = (event) => {
      if (!event.target.closest('.relative')) {
        showCustomerDropdown.value = false;
        showOrderDropdown.value = false;
      }
    };

    // Watchers
    watch(transactionType, () => {
      updateAmount();
    });

    watch(rawAmount, () => {
      updateAmount();
    });

    // Lifecycle
    onMounted(() => {
      initializeForm();
      document.addEventListener('click', handleClickOutside);
    });

    return {
      // Data
      loading,
      errors,
      customers,
      orders,
      selectedCustomer,
      selectedOrder,
      customerSearch,
      orderSearch,
      showCustomerDropdown,
      showOrderDropdown,
      transactionType,
      rawAmount,
      form,

      // Computed
      isEdit,
      isFormValid,

      // Methods
      updateAmount,
      searchCustomers,
      selectCustomer,
      clearCustomer,
      searchOrders,
      selectOrder,
      clearOrder,
      generateRefCode,
      handleSubmit,

      // Utilities
      formatCurrency,
      formatAmount,
      getTransactionType,
      getAmountColorClass,
      getBadgeClass,
      getBalanceColor
    };
  }
};
</script>