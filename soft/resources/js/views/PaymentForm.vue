<template>
  <div class="bg-white">
    <!-- Header -->
    <div class="p-6 border-b flex items-center justify-between">
      <div class="flex items-center space-x-4">
        <button 
          @click="goBack" 
          class="text-gray-500 hover:text-gray-700"
        >
          ← Quay lại
        </button>
        <h1 class="text-2xl font-semibold text-gray-900">Thanh toán đơn hàng</h1>
      </div>
    </div>

    <!-- Order Info -->
    <div v-if="order" class="p-6 border-b bg-gray-50">
      <div class="grid grid-cols-4 gap-4 text-sm">
        <div>
          <span class="text-gray-600">Mã đơn hàng:</span>
          <span class="ml-2 font-medium">{{ order.code }}</span>
        </div>
        <div>
          <span class="text-gray-600">Khách hàng:</span>
          <span class="ml-2">{{ order.customer?.name }}</span>
        </div>
        <div>
          <span class="text-gray-600">Tổng tiền:</span>
          <span class="ml-2 font-medium">{{ formatCurrency(order.total) }}</span>
        </div>
        <div>
          <span class="text-gray-600">Còn nợ:</span>
          <span class="ml-2 font-medium text-red-600">{{ formatCurrency(order.debt) }}</span>
        </div>
      </div>
    </div>

    <!-- Payment Form -->
    <div class="p-6">
      <div class="max-w-2xl mx-auto">
        <form @submit.prevent="handleSubmit" class="space-y-6">
          <!-- Payment Type -->
          <div>
            <label class="form-label">Loại thanh toán *</label>
            <div class="grid grid-cols-2 gap-4">
              <button
                type="button"
                @click="form.payment_type = 'full'"
                :class="[
                  'p-4 border rounded-lg text-left transition-all',
                  form.payment_type === 'full' 
                    ? 'border-blue-500 bg-blue-50 text-blue-900' 
                    : 'border-gray-300 hover:border-gray-400'
                ]"
              >
                <div class="font-medium">Thanh toán đủ</div>
                <div class="text-sm text-gray-600">Thanh toán toàn bộ số tiền còn nợ</div>
              </button>
              <button
                type="button"
                @click="form.payment_type = 'partial'"
                :class="[
                  'p-4 border rounded-lg text-left transition-all',
                  form.payment_type === 'partial' 
                    ? 'border-blue-500 bg-blue-50 text-blue-900' 
                    : 'border-gray-300 hover:border-gray-400'
                ]"
              >
                <div class="font-medium">Thanh toán một phần</div>
                <div class="text-sm text-gray-600">Thanh toán một phần, số còn lại ghi công nợ</div>
              </button>
            </div>
          </div>

          <!-- Amount -->
          <div>
            <label class="form-label">Số tiền thanh toán *</label>
            <input
              type="number"
              v-model.number="form.amount"
              class="form-input"
              :max="order?.debt"
              min="0"
              step="1000"
              :readonly="form.payment_type === 'full'"
              required
            />
            <div v-if="errors.amount" class="form-error">{{ errors.amount }}</div>
            <div class="text-sm text-gray-500 mt-1">
              Số tiền tối đa: {{ formatCurrency(order?.debt || 0) }}
            </div>
          </div>

          <!-- Payment Method -->
          <div>
            <label class="form-label">Phương thức thanh toán *</label>
            <select v-model="form.payment_method" class="form-input" required>
              <option value="">Chọn phương thức thanh toán</option>
              <option value="cash">Tiền mặt</option>
              <option value="transfer">Chuyển khoản ngân hàng</option>
              <option value="card">Thẻ tín dụng/ghi nợ</option>
              <option value="wallet">Ví điện tử</option>
            </select>
            <div v-if="errors.payment_method" class="form-error">{{ errors.payment_method }}</div>
          </div>

          <!-- Transaction ID (for non-cash payments) -->
          <div v-if="form.payment_method && form.payment_method !== 'cash'">
            <label class="form-label">Mã giao dịch</label>
            <input
              type="text"
              v-model="form.transaction_id"
              class="form-input"
              placeholder="Nhập mã giao dịch (nếu có)"
            />
            <div class="text-sm text-gray-500 mt-1">
              Mã giao dịch từ ngân hàng hoặc ví điện tử
            </div>
          </div>

          <!-- Note -->
          <div>
            <label class="form-label">Ghi chú</label>
            <textarea
              v-model="form.note"
              class="form-input"
              rows="3"
              placeholder="Ghi chú về thanh toán..."
            ></textarea>
          </div>

          <!-- Payment Summary -->
          <div class="bg-gray-50 rounded-lg p-4">
            <h3 class="font-medium text-gray-900 mb-3">Tóm tắt thanh toán</h3>
            <div class="space-y-2 text-sm">
              <div class="flex justify-between">
                <span class="text-gray-600">Tổng tiền đơn hàng:</span>
                <span>{{ formatCurrency(order?.total || 0) }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-600">Đã thanh toán:</span>
                <span>{{ formatCurrency(order?.paid || 0) }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-600">Còn nợ:</span>
                <span class="text-red-600">{{ formatCurrency(order?.debt || 0) }}</span>
              </div>
              <hr class="my-2">
              <div class="flex justify-between font-medium">
                <span>Số tiền thanh toán:</span>
                <span class="text-blue-600">{{ formatCurrency(form.amount || 0) }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-600">Còn lại sau thanh toán:</span>
                <span :class="remainingDebt > 0 ? 'text-red-600' : 'text-green-600'">
                  {{ formatCurrency(remainingDebt) }}
                </span>
              </div>
            </div>
          </div>

          <!-- Submit Buttons -->
          <div class="flex justify-end space-x-3">
            <button
              type="button"
              @click="goBack"
              class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300"
            >
              Hủy
            </button>
            <button
              type="submit"
              :disabled="loading"
              class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 disabled:opacity-50"
            >
              <span v-if="loading">⏳ Đang xử lý...</span>
              <span v-else>💰 Thanh toán</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, computed, watch, onMounted } from 'vue'

export default {
  name: 'PaymentForm',
  setup() {
    const loading = ref(false)
    const errors = ref({})
    const order = ref(null)

    const form = ref({
      payment_type: 'full',
      amount: 0,
      payment_method: '',
      transaction_id: '',
      note: ''
    })

    const remainingDebt = computed(() => {
      return Math.max(0, (order.value?.debt || 0) - (form.value.amount || 0))
    })

    const loadOrder = async () => {
      // Get order ID from URL params
      const urlParams = new URLSearchParams(window.location.search)
      const orderId = urlParams.get('order_id')
      
      if (!orderId) {
        alert('Không tìm thấy mã đơn hàng')
        goBack()
        return
      }

      try {
        const response = await fetch(`/api/orders/${orderId}`)
        if (response.ok) {
          const data = await response.json()
          if (data.success) {
            order.value = data.data
            
            // Initialize form amount
            form.value.amount = order.value.debt || 0
          }
        }
      } catch (error) {
        console.error('Error loading order:', error)
        alert('Lỗi khi tải thông tin đơn hàng')
      }
    }

    const validateForm = () => {
      errors.value = {}
      
      if (!form.value.amount || form.value.amount <= 0) {
        errors.value.amount = 'Vui lòng nhập số tiền thanh toán hợp lệ'
      }
      
      if (form.value.amount > (order.value?.debt || 0)) {
        errors.value.amount = 'Số tiền thanh toán không được vượt quá số tiền còn nợ'
      }
      
      if (!form.value.payment_method) {
        errors.value.payment_method = 'Vui lòng chọn phương thức thanh toán'
      }
      
      return Object.keys(errors.value).length === 0
    }

    const handleSubmit = async () => {
      if (!validateForm()) return
      
      loading.value = true
      
      try {
        const response = await fetch(`/api/orders/${order.value.id}/payments`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
          },
          body: JSON.stringify({
            amount: form.value.amount,
            payment_method: form.value.payment_method,
            transaction_id: form.value.transaction_id,
            note: form.value.note
          })
        })
        
        const data = await response.json()
        
        if (data.success) {
          alert('Thanh toán thành công!')
          goBack()
        } else {
          if (data.errors) {
            errors.value = data.errors
          } else {
            alert(data.message || 'Có lỗi xảy ra')
          }
        }
      } catch (error) {
        console.error('Error:', error)
        alert('Có lỗi xảy ra khi thanh toán')
      } finally {
        loading.value = false
      }
    }

    const goBack = () => {
      window.history.back()
    }

    const formatCurrency = (amount) => {
      return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND'
      }).format(amount || 0)
    }

    // Watch payment type changes
    watch(() => form.value.payment_type, (newType) => {
      if (newType === 'full') {
        form.value.amount = order.value?.debt || 0
      } else {
        form.value.amount = 0
      }
    })

    onMounted(() => {
      loadOrder()
    })

    return {
      loading,
      errors,
      order,
      form,
      remainingDebt,
      loadOrder,
      validateForm,
      handleSubmit,
      goBack,
      formatCurrency
    }
  }
}
</script>

<style scoped>
.form-input {
  @apply w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent;
}

.form-label {
  @apply block text-sm font-medium text-gray-700 mb-2;
}

.form-error {
  @apply text-red-500 text-sm mt-1;
}
</style>