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
        <h1 class="text-2xl font-semibold text-gray-900">Tạo đơn giao hàng</h1>
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
          <span class="text-gray-600">Trạng thái:</span>
          <span class="ml-2 px-2 py-1 rounded-full text-xs bg-green-100 text-green-800">
            {{ getStatusText(order.status) }}
          </span>
        </div>
      </div>
    </div>

    <!-- Shipping Form -->
    <div class="p-6">
      <form @submit.prevent="handleSubmit" class="max-w-4xl">
        <div class="grid grid-cols-2 gap-6">
          <!-- Provider Selection -->
          <div>
            <label class="form-label">Đơn vị vận chuyển *</label>
            <select v-model="form.provider_id" class="form-input" required>
              <option value="">Chọn đơn vị vận chuyển</option>
              <option 
                v-for="provider in providers" 
                :key="provider.id" 
                :value="provider.id"
              >
                {{ provider.name }}
              </option>
            </select>
            <div v-if="errors.provider_id" class="form-error">{{ errors.provider_id }}</div>
          </div>

          <!-- Shipping Method -->
          <div>
            <label class="form-label">Phương thức giao hàng *</label>
            <select v-model="form.shipping_method" class="form-input" required>
              <option value="">Chọn phương thức</option>
              <option value="standard">Tiêu chuẩn</option>
              <option value="express">Nhanh</option>
              <option value="same_day">Trong ngày</option>
            </select>
            <div v-if="errors.shipping_method" class="form-error">{{ errors.shipping_method }}</div>
          </div>

          <!-- Shipping Fee -->
          <div>
            <label class="form-label">Phí giao hàng *</label>
            <input 
              type="number" 
              v-model.number="form.shipping_fee" 
              class="form-input" 
              min="0" 
              step="1000"
              required
            />
            <div v-if="errors.shipping_fee" class="form-error">{{ errors.shipping_fee }}</div>
          </div>

          <!-- Payment By -->
          <div>
            <label class="form-label">Người thanh toán phí ship *</label>
            <select v-model="form.payment_by" class="form-input" required>
              <option value="">Chọn người thanh toán</option>
              <option value="sender">Người gửi</option>
              <option value="receiver">Người nhận</option>
            </select>
            <div v-if="errors.payment_by" class="form-error">{{ errors.payment_by }}</div>
          </div>

          <!-- Weight -->
          <div>
            <label class="form-label">Trọng lượng (kg)</label>
            <input 
              type="number" 
              v-model.number="form.weight" 
              class="form-input" 
              min="0" 
              step="0.1"
            />
          </div>

          <!-- Dimensions -->
          <div>
            <label class="form-label">Kích thước (D x R x C cm)</label>
            <input 
              type="text" 
              v-model="form.dimensions" 
              class="form-input" 
              placeholder="20 x 30 x 40"
            />
          </div>
        </div>

        <!-- Note -->
        <div class="mt-6">
          <label class="form-label">Ghi chú giao hàng</label>
          <textarea
            v-model="form.note"
            class="form-input"
            rows="3"
            placeholder="Ghi chú đặc biệt cho đơn vị vận chuyển"
          ></textarea>
        </div>

        <!-- Submit Button -->
        <div class="flex justify-end space-x-3 mt-6">
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
            <span v-if="loading">⏳ Đang tạo...</span>
            <span v-else>🚚 Tạo đơn giao hàng</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script>
import { ref, computed, watch, onMounted } from 'vue'
import { orderApi } from '../api/orderApi'
import { shippingApi } from '../api/shippingApi'

export default {
  name: 'ShippingForm',
  setup() {
    const loading = ref(false)
    const errors = ref({})
    const order = ref(null)
    const providers = ref([])

    const form = ref({
      provider_id: '',
      shipping_method: 'standard',
      shipping_fee: 0,
      payment_by: 'sender',
      weight: 0,
      dimensions: '',
      note: ''
    })

    // Computed
    const canSubmit = computed(() => {
      return form.value.provider_id && 
             form.value.shipping_method && 
             form.value.shipping_fee >= 0 && 
             form.value.payment_by
    })

    // Real API Methods using existing API files
    const loadOrder = async () => {
      const urlParams = new URLSearchParams(window.location.search)
      const orderId = urlParams.get('order_id')
      
      console.log('Loading order ID:', orderId)
      
      if (!orderId) {
        alert('Không tìm thấy mã đơn hàng')
        goBack()
        return
      }

      try {
        console.log('🔍 Calling orderApi.getById:', orderId)
        const response = await orderApi.getById(orderId)
        
        console.log('📊 Order API response:', response)
        
        if (response.success) {
          order.value = response.data
          
          // Check if order can create shipping
          if (order.value.status !== 'confirmed') {
            alert('Chỉ có thể tạo đơn giao hàng cho đơn hàng đã được duyệt')
            goBack()
            return
          }
          
          console.log('✅ Order loaded successfully:', order.value)
        } else {
          console.error('❌ Order API failed:', response.message)
          alert(response.message || 'Không tìm thấy đơn hàng')
          goBack()
        }
      } catch (error) {
        console.error('💥 Error loading order:', error)
        alert('Lỗi khi tải thông tin đơn hàng: ' + (error.message || 'Unknown error'))
        goBack()
      }
    }

    const loadProviders = async () => {
      try {
        console.log('🚚 Calling shippingApi.getProviders')
        const response = await shippingApi.getProviders()
        
        console.log('📊 Providers API response:', response)
        
        if (response.success && Array.isArray(response.data)) {
          providers.value = response.data
          console.log('✅ Providers loaded successfully:', providers.value)
        } else {
          console.warn('⚠️ No providers found or invalid response')
          providers.value = []
        }
      } catch (error) {
        console.error('💥 Error loading providers:', error)
        alert('Lỗi khi tải danh sách đơn vị vận chuyển: ' + (error.message || 'Unknown error'))
        providers.value = []
      }
    }

    // Form Validation
    const validateForm = () => {
      errors.value = {}
      
      if (!form.value.provider_id) {
        errors.value.provider_id = 'Vui lòng chọn đơn vị vận chuyển'
      }
      
      if (!form.value.shipping_method) {
        errors.value.shipping_method = 'Vui lòng chọn phương thức giao hàng'
      }
      
      if (form.value.shipping_fee === null || form.value.shipping_fee === undefined || form.value.shipping_fee < 0) {
        errors.value.shipping_fee = 'Vui lòng nhập phí giao hàng hợp lệ (>= 0)'
      }
      
      if (!form.value.payment_by) {
        errors.value.payment_by = 'Vui lòng chọn người thanh toán phí ship'
      }
      
      if (form.value.weight < 0) {
        errors.value.weight = 'Trọng lượng không được âm'
      }
      
      const isValid = Object.keys(errors.value).length === 0
      console.log('📝 Form validation result:', isValid, errors.value)
      return isValid
    }

    // Form Submission using shippingApi
    const handleSubmit = async () => {
      console.log('🚀 Form submission started')
      
      if (!validateForm()) {
        console.log('❌ Form validation failed')
        return
      }
      
      if (!order.value?.id) {
        alert('Không tìm thấy thông tin đơn hàng')
        return
      }
      
      loading.value = true
      
      try {
        console.log('📤 Submitting shipping form:', form.value)
        
        const requestData = {
          provider_id: form.value.provider_id,
          shipping_method: form.value.shipping_method,
          shipping_fee: form.value.shipping_fee,
          payment_by: form.value.payment_by,
          weight: form.value.weight || 0,
          dimensions: form.value.dimensions || '',
          note: form.value.note || '',
          delivery_address: order.value.delivery_address,
          delivery_phone: order.value.delivery_phone,
          delivery_contact: order.value.delivery_contact
        }
        
        console.log('📋 Request data:', requestData)
        console.log('🔗 Calling shippingApi.createShipping:', order.value.id)
        
        const response = await shippingApi.createShipping(order.value.id, requestData)
        
        console.log('📊 Shipping API response:', response)
        
        if (response.success) {
          const selectedProvider = providers.value.find(p => p.id == form.value.provider_id)
          alert(`Tạo đơn giao hàng thành công!\n\nMã vận đơn: ${response.data.tracking_number || 'Đang cập nhật'}\nĐơn vị: ${selectedProvider?.name}\nPhí ship: ${formatCurrency(form.value.shipping_fee)}`)
          goBack()
        } else {
          if (response.errors) {
            errors.value = response.errors
            console.log('📝 Validation errors from API:', response.errors)
            
            // Show first error
            const firstError = Object.values(response.errors)[0]
            if (Array.isArray(firstError)) {
              alert(firstError[0])
            } else {
              alert(firstError)
            }
          } else {
            alert(response.message || 'Có lỗi xảy ra khi tạo đơn giao hàng')
          }
        }
      } catch (error) {
        console.error('💥 Error submitting shipping form:', error)
        
        if (error.errors) {
          errors.value = error.errors
          
          // Show validation errors
          const errorMessages = Object.values(error.errors).flat()
          alert('Lỗi validation:\n' + errorMessages.join('\n'))
        } else if (error.message) {
          alert('Lỗi: ' + error.message)
        } else {
          alert('Có lỗi xảy ra khi tạo đơn giao hàng')
        }
      } finally {
        loading.value = false
      }
    }

    // Navigation
    const goBack = () => {
      console.log('🔙 Navigating back')
      window.history.back()
    }

    // Utility Functions
    const formatCurrency = (amount) => {
      if (!amount && amount !== 0) return '0 ₫'
      return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND'
      }).format(amount)
    }

    const getStatusText = (status) => {
      const statusMap = {
        'pending': 'Chờ duyệt',
        'confirmed': 'Đã duyệt', 
        'shipping': 'Đang giao hàng',
        'delivered': 'Đã giao hàng',
        'completed': 'Hoàn thành',
        'cancelled': 'Đã hủy'
      }
      return statusMap[status] || status
    }

    const getMethodText = (method) => {
      const methodMap = {
        'standard': 'Tiêu chuẩn',
        'express': 'Nhanh',
        'same_day': 'Trong ngày'
      }
      return methodMap[method] || method
    }

    const getPaymentByText = (paymentBy) => {
      const paymentMap = {
        'sender': 'Người gửi',
        'receiver': 'Người nhận'
      }
      return paymentMap[paymentBy] || paymentBy
    }

    // Watchers
    watch(() => form.value.provider_id, (newProviderId) => {
      console.log('🔄 Provider changed to:', newProviderId)
      
      if (newProviderId) {
        const provider = providers.value.find(p => p.id == newProviderId)
        if (provider) {
          console.log('📋 Selected provider:', provider)
          
          // Set default shipping fee based on provider type
          switch (provider.type) {
            case 'internal':
              form.value.shipping_fee = 30000
              break
            case 'ghtk':
              form.value.shipping_fee = 25000
              break
            case 'ghn':
              form.value.shipping_fee = 28000
              break
            case 'viettelpost':
              form.value.shipping_fee = 32000
              break
            default:
              form.value.shipping_fee = 35000
          }
          
          console.log('💰 Auto-set shipping fee:', form.value.shipping_fee)
        }
      }
    })

    watch(() => form.value.shipping_method, (newMethod) => {
      console.log('🔄 Shipping method changed to:', newMethod)
      
      // Adjust fee based on method
      const currentFee = form.value.shipping_fee
      if (newMethod === 'express' && currentFee > 0) {
        form.value.shipping_fee = Math.max(Math.floor(currentFee * 1.5), 50000)
        console.log('💰 Adjusted fee for express:', form.value.shipping_fee)
      } else if (newMethod === 'same_day' && currentFee > 0) {
        form.value.shipping_fee = Math.max(Math.floor(currentFee * 2), 80000)
        console.log('💰 Adjusted fee for same day:', form.value.shipping_fee)
      }
    })

    // Lifecycle
    onMounted(async () => {
      console.log('🎬 ShippingForm mounted - loading real data using API files')
      
      try {
        // Load order first, then providers
        await loadOrder()
        await loadProviders()
        
        console.log('✅ Initial load completed')
      } catch (error) {
        console.error('💥 Error during initial load:', error)
      }
    })

    return {
      loading,
      errors,
      order,
      providers,
      form,
      canSubmit,
      loadOrder,
      loadProviders,
      validateForm,
      handleSubmit,
      goBack,
      formatCurrency,
      getStatusText,
      getMethodText,
      getPaymentByText
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