<template>
  <!-- Modal Overlay -->
  <div 
    v-if="show" 
    class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
    @click="closeModal"
  >
    <!-- Modal Content -->
    <div 
      class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4 max-h-screen overflow-y-auto"
      @click.stop
    >
      <!-- Header -->
      <div class="px-6 py-4 border-b border-gray-200">
        <div class="flex items-center justify-between">
          <h3 class="text-lg font-semibold text-gray-900">Ghi nhận thanh toán</h3>
          <button 
            @click="closeModal"
            class="text-gray-400 hover:text-gray-600 transition-colors"
          >
            <span class="text-xl">&times;</span>
          </button>
        </div>
        
        <!-- Order Info -->
        <div class="mt-3 text-sm text-gray-600">
          <div class="font-medium">{{ order?.code }}</div>
          <div>{{ order?.supplier?.name }}</div>
        </div>
      </div>

      <!-- Body -->
      <div class="px-6 py-4">
        <!-- Payment Summary -->
        <div class="bg-gray-50 rounded-lg p-4 mb-6">
          <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
              <span class="text-gray-600">Tổng tiền:</span>
              <div class="font-semibold">{{ formatCurrency(order?.total || 0) }}</div>
            </div>
            <div>
              <span class="text-gray-600">Đã thanh toán:</span>
              <div class="font-semibold text-green-600">{{ formatCurrency(order?.paid || 0) }}</div>
            </div>
            <div>
              <span class="text-gray-600">Còn lại:</span>
              <div class="font-semibold text-red-600">{{ formatCurrency(order?.need_pay || 0) }}</div>
            </div>
            <div>
              <span class="text-gray-600">Sau thanh toán:</span>
              <div class="font-semibold text-blue-600">{{ formatCurrency(remainingAfterPayment) }}</div>
            </div>
          </div>
        </div>

        <!-- Payment Form -->
        <form @submit.prevent="submitPayment">
          <!-- Amount -->
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Số tiền thanh toán <span class="text-red-500">*</span>
            </label>
            <input
              type="number"
              step="1000"
              min="1000"
              :max="order?.need_pay"
              v-model="form.amount"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              :class="{ 'border-red-500': errors.amount }"
              placeholder="Nhập số tiền"
              required
            />
            <div v-if="errors.amount" class="mt-1 text-sm text-red-600">
              {{ errors.amount }}
            </div>
          </div>

          <!-- Payment Method -->
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Phương thức thanh toán <span class="text-red-500">*</span>
            </label>
            <select
              v-model="form.payment_method"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              :class="{ 'border-red-500': errors.payment_method }"
              required
            >
              <option value="">Chọn phương thức</option>
              <option value="cash">Tiền mặt</option>
              <option value="transfer">Chuyển khoản</option>
              <option value="bank">Ngân hàng</option>
            </select>
            <div v-if="errors.payment_method" class="mt-1 text-sm text-red-600">
              {{ errors.payment_method }}
            </div>
          </div>

          <!-- Reference Number -->
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Số tham chiếu
            </label>
            <input
              type="text"
              v-model="form.reference_number"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              placeholder="Mã giao dịch, số séc..."
            />
          </div>

          <!-- Payment Date -->
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Ngày thanh toán <span class="text-red-500">*</span>
            </label>
            <input
              type="date"
              v-model="form.payment_date"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              :class="{ 'border-red-500': errors.payment_date }"
              required
            />
            <div v-if="errors.payment_date" class="mt-1 text-sm text-red-600">
              {{ errors.payment_date }}
            </div>
          </div>

          <!-- Note -->
          <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Ghi chú
            </label>
            <textarea
              v-model="form.note"
              rows="3"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              placeholder="Ghi chú về thanh toán..."
            ></textarea>
          </div>

          <!-- Error Message -->
          <div v-if="errorMessage" class="mb-4 p-3 bg-red-50 border border-red-200 rounded-md">
            <div class="text-sm text-red-600">{{ errorMessage }}</div>
          </div>

          <!-- Actions -->
          <div class="flex space-x-3">
            <button
              type="button"
              @click="closeModal"
              class="flex-1 px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-500"
              :disabled="loading"
            >
              Hủy
            </button>
            <button
              type="submit"
              class="flex-1 px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
              :disabled="loading || !isFormValid"
            >
              <span v-if="loading" class="flex items-center justify-center">
                <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Đang xử lý...
              </span>
              <span v-else>Ghi nhận thanh toán</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, reactive, computed, watch, nextTick } from 'vue'
import { purchaseOrderApi } from '../api/purchaseOrderApi'

export default {
  name: 'PaymentModal',
  props: {
    show: {
      type: Boolean,
      default: false
    },
    order: {
      type: Object,
      default: () => ({})
    }
  },
  emits: ['close', 'payment-success'],
  setup(props, { emit }) {
    const loading = ref(false)
    const errorMessage = ref('')

    const form = reactive({
      amount: 0,
      payment_method: '',
      reference_number: '',
      payment_date: new Date().toISOString().split('T')[0],
      note: ''
    })

    const errors = reactive({
      amount: '',
      payment_method: '',
      payment_date: ''
    })

    // Computed
    const remainingAfterPayment = computed(() => {
      const remaining = (props.order?.need_pay || 0) - (form.amount || 0)
      return Math.max(0, remaining)
    })

    const isFormValid = computed(() => {
      return form.amount > 0 && 
             form.amount <= (props.order?.need_pay || 0) &&
             form.payment_method &&
             form.payment_date &&
             !Object.values(errors).some(error => error)
    })

    // Watchers
    watch(() => props.show, (newVal) => {
      if (newVal) {
        resetForm()
        nextTick(() => {
          // Auto focus on amount input
          const amountInput = document.querySelector('input[type="number"]')
          if (amountInput) amountInput.focus()
        })
      }
    })

    watch(() => form.amount, (newVal) => {
      validateAmount(newVal)
    })

    // Methods
    const resetForm = () => {
      form.amount = 0
      form.payment_method = ''
      form.reference_number = ''
      form.payment_date = new Date().toISOString().split('T')[0]
      form.note = ''
      
      errors.amount = ''
      errors.payment_method = ''
      errors.payment_date = ''
      
      errorMessage.value = ''
    }

    const validateAmount = (amount) => {
      const numAmount = Number(amount)
      
      if (!numAmount || numAmount <= 0) {
        errors.amount = 'Số tiền phải lớn hơn 0'
        return false
      }
      
      if (numAmount > (props.order?.need_pay || 0)) {
        errors.amount = `Số tiền không được vượt quá ${formatCurrency(props.order?.need_pay || 0)}`
        return false
      }
      
      errors.amount = ''
      return true
    }

    const validateForm = () => {
      let isValid = true

      // Validate amount
      if (!validateAmount(form.amount)) {
        isValid = false
      }

      // Validate payment method
      if (!form.payment_method) {
        errors.payment_method = 'Vui lòng chọn phương thức thanh toán'
        isValid = false
      } else {
        errors.payment_method = ''
      }

      // Validate payment date
      if (!form.payment_date) {
        errors.payment_date = 'Vui lòng chọn ngày thanh toán'
        isValid = false
      } else {
        errors.payment_date = ''
      }

      return isValid
    }

    const submitPayment = async () => {
      if (!validateForm()) return

      loading.value = true
      errorMessage.value = ''

      try {
        const paymentData = {
          amount: Number(form.amount),
          payment_method: form.payment_method,
          reference_number: form.reference_number || null,
          payment_date: form.payment_date,
          note: form.note || null
        }

        const response = await purchaseOrderApi.recordPayment(props.order.id, paymentData)

        if (response.success) {
          emit('payment-success', response.data)
          closeModal()
          
          // Show success message
          showNotification('Ghi nhận thanh toán thành công!', 'success')
        } else {
          errorMessage.value = response.message || 'Có lỗi xảy ra khi ghi nhận thanh toán'
        }
      } catch (error) {
        console.error('Payment error:', error)
        errorMessage.value = error.message || 'Có lỗi xảy ra khi ghi nhận thanh toán'
      } finally {
        loading.value = false
      }
    }

    const closeModal = () => {
      if (!loading.value) {
        emit('close')
      }
    }

    const formatCurrency = (amount) => {
      return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND'
      }).format(amount || 0)
    }

    const showNotification = (message, type = 'success') => {
      // Simple notification - can be enhanced with a proper notification system
      if (type === 'success') {
        console.log('✅', message)
      } else {
        console.log('❌', message) 
      }
    }

    return {
      loading,
      errorMessage,
      form,
      errors,
      remainingAfterPayment,
      isFormValid,
      submitPayment,
      closeModal,
      formatCurrency
    }
  }
}
</script>