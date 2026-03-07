<template>
  <!-- Modal Overlay -->
  <div 
    v-if="show" 
    class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
    @click="closeModal"
  >
    <!-- Modal Content -->
    <div 
      class="bg-white rounded-lg shadow-xl max-w-4xl w-full mx-4 max-h-screen overflow-y-auto"
      @click.stop
    >
      <!-- Header -->
      <div class="px-6 py-4 border-b border-gray-200">
        <div class="flex items-center justify-between">
          <h3 class="text-lg font-semibold text-gray-900">Lịch sử thanh toán</h3>
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

      <!-- Loading State -->
      <div v-if="loading" class="flex justify-center items-center py-12">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
        <span class="ml-2 text-gray-600">Đang tải dữ liệu...</span>
      </div>

      <!-- Content -->
      <div v-else class="px-6 py-4">
        <!-- Payment Summary -->
        <div class="bg-gray-50 rounded-lg p-4 mb-6">
          <h4 class="font-medium text-gray-900 mb-3">Tổng quan thanh toán</h4>
          
          <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
            <div class="bg-white rounded p-3">
              <div class="text-gray-600">Tổng tiền</div>
              <div class="text-lg font-semibold text-gray-900">
                {{ formatCurrency(paymentData?.purchase_order?.total || 0) }}
              </div>
            </div>
            <div class="bg-white rounded p-3">
              <div class="text-gray-600">Đã thanh toán</div>
              <div class="text-lg font-semibold text-green-600">
                {{ formatCurrency(paymentData?.purchase_order?.paid || 0) }}
              </div>
            </div>
            <div class="bg-white rounded p-3">
              <div class="text-gray-600">Còn lại</div>
              <div class="text-lg font-semibold text-red-600">
                {{ formatCurrency(paymentData?.purchase_order?.need_pay || 0) }}
              </div>
            </div>
            <div class="bg-white rounded p-3">
              <div class="text-gray-600">Tiến độ</div>
              <div class="text-lg font-semibold text-blue-600">
                {{ paymentData?.purchase_order?.payment_progress || 0 }}%
              </div>
            </div>
          </div>

          <!-- Progress Bar -->
          <div class="mt-4">
            <div class="flex justify-between text-sm text-gray-600 mb-1">
              <span>Tiến độ thanh toán</span>
              <span>{{ paymentData?.purchase_order?.payment_progress || 0 }}%</span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2">
              <div 
                class="bg-blue-500 h-2 rounded-full transition-all duration-300"
                :style="{ width: (paymentData?.purchase_order?.payment_progress || 0) + '%' }"
              ></div>
            </div>
          </div>

          <!-- Payment Status Badge -->
          <div class="mt-3">
            <span 
              class="px-3 py-1 text-xs rounded-full font-medium"
              :class="getPaymentStatusClass(paymentData?.purchase_order?.payment_status)"
            >
              {{ getPaymentStatusText(paymentData?.purchase_order?.payment_status) }}
            </span>
          </div>
        </div>

        <!-- Error State -->
        <div v-if="error" class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
          <div class="text-red-600">{{ error }}</div>
        </div>

        <!-- Payments List -->
        <div class="bg-white border border-gray-200 rounded-lg">
          <div class="px-4 py-3 border-b border-gray-200">
            <h4 class="font-medium text-gray-900">
              Danh sách thanh toán ({{ paymentData?.summary?.total_payments || 0 }} lần)
            </h4>
          </div>

          <!-- Empty State -->
          <div v-if="!paymentData?.payments || paymentData.payments.length === 0" class="text-center py-8">
            <div class="text-4xl mb-2">💳</div>
            <div class="text-gray-500">Chưa có lịch sử thanh toán</div>
          </div>

          <!-- Payments Table -->
          <div v-else class="overflow-x-auto">
            <table class="w-full">
              <thead class="bg-gray-50">
                <tr>
                  <th class="text-left px-4 py-3 text-sm font-medium text-gray-600">Ngày thanh toán</th>
                  <th class="text-left px-4 py-3 text-sm font-medium text-gray-600">Số tiền</th>
                  <th class="text-left px-4 py-3 text-sm font-medium text-gray-600">Phương thức</th>
                  <th class="text-left px-4 py-3 text-sm font-medium text-gray-600">Số tham chiếu</th>
                  <th class="text-left px-4 py-3 text-sm font-medium text-gray-600">Ghi chú</th>
                  <th class="text-left px-4 py-3 text-sm font-medium text-gray-600">Người thực hiện</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200">
                <tr 
                  v-for="payment in paymentData.payments" 
                  :key="payment.id"
                  class="hover:bg-gray-50"
                >
                  <td class="px-4 py-3 text-sm text-gray-900">
                    {{ formatDate(payment.recorded_at) }}
                  </td>
                  <td class="px-4 py-3 text-sm font-medium text-green-600">
                    {{ formatCurrency(Math.abs(payment.amount)) }}
                  </td>
                  <td class="px-4 py-3 text-sm text-gray-600">
                    <span class="capitalize">{{ getPaymentMethodText(payment.ref_code) }}</span>
                  </td>
                  <td class="px-4 py-3 text-sm text-gray-600">
                    {{ extractReferenceNumber(payment.ref_code) }}
                  </td>
                  <td class="px-4 py-3 text-sm text-gray-600">
                    <div class="max-w-xs truncate" :title="payment.note">
                      {{ payment.note || '-' }}
                    </div>
                  </td>
                  <td class="px-4 py-3 text-sm text-gray-600">
                    {{ payment.creator?.name || '-' }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Summary Footer -->
        <div v-if="paymentData?.summary" class="mt-4 text-sm text-gray-600">
          <div class="flex justify-between items-center">
            <div>
              Tổng {{ paymentData.summary.total_payments }} lần thanh toán
            </div>
            <div class="font-medium">
              Tổng đã trả: {{ formatCurrency(paymentData.summary.paid_amount) }}
            </div>
          </div>
        </div>
      </div>

      <!-- Footer Actions -->
      <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
        <div class="flex justify-between items-center">
          <button
            @click="refreshData"
            class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-500"
            :disabled="loading"
          >
            🔄 Làm mới
          </button>
          
          <div class="flex space-x-3">
            <button
              @click="exportPayments"
              class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-500"
              :disabled="loading || !paymentData?.payments?.length"
            >
              📊 Xuất Excel
            </button>
            <button
              @click="closeModal"
              class="px-4 py-2 text-sm bg-gray-500 text-white rounded-md hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-500"
            >
              Đóng
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, reactive, watch, nextTick } from 'vue'
import { purchaseOrderApi } from '../api/purchaseOrderApi'

export default {
  name: 'PaymentHistoryModal',
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
  emits: ['close'],
  setup(props, { emit }) {
    const loading = ref(false)
    const error = ref('')
    const paymentData = ref(null)

    // Watchers
    watch(() => props.show, (newVal) => {
      if (newVal && props.order?.id) {
        fetchPaymentHistory()
      } else {
        resetData()
      }
    })

    // Methods
    const resetData = () => {
      paymentData.value = null
      error.value = ''
    }

    const fetchPaymentHistory = async () => {
      if (!props.order?.id) return

      loading.value = true
      error.value = ''

      try {
        const response = await purchaseOrderApi.getPaymentHistory(props.order.id)

        if (response.success) {
          paymentData.value = response.data
        } else {
          error.value = response.message || 'Có lỗi xảy ra khi tải dữ liệu'
        }
      } catch (err) {
        console.error('Error fetching payment history:', err)
        error.value = err.message || 'Có lỗi xảy ra khi tải dữ liệu'
      } finally {
        loading.value = false
      }
    }

    const refreshData = () => {
      fetchPaymentHistory()
    }

    const exportPayments = () => {
      // TODO: Implement export functionality
      console.log('Export payments for order:', props.order.code)
      
      // Simple implementation - convert to CSV
      if (!paymentData.value?.payments?.length) return

      const headers = ['Ngày', 'Số tiền', 'Phương thức', 'Số tham chiếu', 'Ghi chú', 'Người thực hiện']
      const rows = paymentData.value.payments.map(payment => [
        formatDate(payment.recorded_at),
        Math.abs(payment.amount),
        getPaymentMethodText(payment.ref_code),
        extractReferenceNumber(payment.ref_code),
        payment.note || '',
        payment.creator?.name || ''
      ])

      const csvContent = [headers, ...rows]
        .map(row => row.map(field => `"${field}"`).join(','))
        .join('\n')

      const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' })
      const link = document.createElement('a')
      const url = URL.createObjectURL(blob)
      link.setAttribute('href', url)
      link.setAttribute('download', `payment-history-${props.order.code}-${new Date().toISOString().split('T')[0]}.csv`)
      document.body.appendChild(link)
      link.click()
      document.body.removeChild(link)
    }

    const closeModal = () => {
      emit('close')
    }

    const formatCurrency = (amount) => {
      return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND'
      }).format(amount || 0)
    }

    const formatDate = (date) => {
      if (!date) return ''
      return new Date(date).toLocaleString('vi-VN', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
      })
    }

    const getPaymentStatusText = (status) => {
      const texts = {
        'unpaid': 'Chưa thanh toán',
        'partial': 'Thanh toán một phần',
        'paid': 'Đã thanh toán đầy đủ'
      }
      return texts[status] || status
    }

    const getPaymentStatusClass = (status) => {
      const classes = {
        'unpaid': 'bg-red-100 text-red-800',
        'partial': 'bg-yellow-100 text-yellow-800',
        'paid': 'bg-green-100 text-green-800'
      }
      return classes[status] || 'bg-gray-100 text-gray-800'
    }

    const getPaymentMethodText = (refCode) => {
      if (!refCode) return ''
      
      // Extract payment method from reference code patterns
      if (refCode.includes('-PAY-')) {
        return 'Thanh toán thường'
      } else if (refCode.includes('-BULK-PAY-')) {
        return 'Thanh toán hàng loạt'
      }
      return 'Khác'
    }

    const extractReferenceNumber = (refCode) => {
      if (!refCode) return ''
      
      // Extract the timestamp part as reference
      const parts = refCode.split('-')
      return parts[parts.length - 1] || refCode
    }

    return {
      loading,
      error,
      paymentData,
      fetchPaymentHistory,
      refreshData,
      exportPayments,
      closeModal,
      formatCurrency,
      formatDate,
      getPaymentStatusText,
      getPaymentStatusClass,
      getPaymentMethodText,
      extractReferenceNumber
    }
  }
}
</script>