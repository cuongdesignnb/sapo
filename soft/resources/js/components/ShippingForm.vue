<template>
  <div class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg w-full max-w-2xl max-h-screen overflow-y-auto">
      <form @submit.prevent="handleSubmit">
        <!-- Header -->
        <div class="flex items-center justify-between p-6 border-b">
          <h3 class="text-lg font-semibold">Tạo đơn giao hàng</h3>
          <button type="button" @click="$emit('close')" class="text-gray-400 hover:text-gray-600">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
          </button>
        </div>

        <div class="p-6 space-y-4">
          <!-- Provider & Method -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Đơn vị vận chuyển *</label>
              <select
                v-model="form.provider_id"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                :class="{ 'border-red-500': errors.provider_id }"
              >
                <option value="">Chọn đơn vị vận chuyển</option>
                <option v-for="provider in providers" :key="provider.id" :value="provider.id">
                  {{ provider.name }}
                </option>
              </select>
              <p v-if="errors.provider_id" class="mt-1 text-sm text-red-600">{{ errors.provider_id }}</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Phương thức</label>
              <select
                v-model="form.shipping_method"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="standard">Tiêu chuẩn</option>
                <option value="express">Nhanh</option>
                <option value="same_day">Trong ngày</option>
              </select>
            </div>
          </div>

          <!-- Shipping Fee & Payment By -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Phí vận chuyển *</label>
              <input
                type="number"
                v-model.number="form.shipping_fee"
                min="0"
                step="1000"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                :class="{ 'border-red-500': errors.shipping_fee }"
                placeholder="0"
              />
              <p v-if="errors.shipping_fee" class="mt-1 text-sm text-red-600">{{ errors.shipping_fee }}</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Bên thanh toán *</label>
              <select
                v-model="form.payment_by"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                :class="{ 'border-red-500': errors.payment_by }"
              >
                <option value="sender">Người gửi</option>
                <option value="receiver">Người nhận</option>
              </select>
              <p v-if="errors.payment_by" class="mt-1 text-sm text-red-600">{{ errors.payment_by }}</p>
            </div>
          </div>

          <!-- 
          <div v-if="form.shipping_fee > 0" class="bg-blue-50 border border-blue-200 rounded-md p-3">
            <div class="flex items-center">
              <svg class="w-5 h-5 text-blue-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
              </svg>
              <div class="text-sm">
                <p class="font-medium text-blue-900">
                  {{ form.payment_by === 'sender' ? 'Người gửi' : 'Người nhận' }} sẽ trả phí vận chuyển: 
                  <span class="font-bold">{{ formatCurrency(form.shipping_fee) }}</span>
                </p>
                <p v-if="form.payment_by === 'sender'" class="text-blue-700 mt-1">
                  Phí sẽ được cộng vào tổng đơn hàng
                </p>
                <p v-else class="text-blue-700 mt-1">
                  Phí sẽ được thu khi giao hàng (COD)
                </p>
              </div>
            </div>
          </div> Payment Info Display -->

          <!-- Delivery Info -->
          <div class="border-t pt-4">
            <h4 class="text-md font-medium text-gray-900 mb-3">Thông tin giao hàng</h4>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Người nhận *</label>
                <input
                  type="text"
                  v-model="form.delivery_contact"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                  :class="{ 'border-red-500': errors.delivery_contact }"
                />
                <p v-if="errors.delivery_contact" class="mt-1 text-sm text-red-600">{{ errors.delivery_contact }}</p>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Số điện thoại *</label>
                <input
                  type="text"
                  v-model="form.delivery_phone"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                  :class="{ 'border-red-500': errors.delivery_phone }"
                />
                <p v-if="errors.delivery_phone" class="mt-1 text-sm text-red-600">{{ errors.delivery_phone }}</p>
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Địa chỉ giao hàng *</label>
              <textarea
                v-model="form.delivery_address"
                rows="2"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                :class="{ 'border-red-500': errors.delivery_address }"
              ></textarea>
              <p v-if="errors.delivery_address" class="mt-1 text-sm text-red-600">{{ errors.delivery_address }}</p>
            </div>
          </div>

          <!-- Package Info -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Khối lượng (kg)</label>
              <input
                type="number"
                v-model.number="form.weight"
                min="0"
                step="0.1"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Kích thước (DxRxC cm)</label>
              <input
                type="text"
                v-model="form.dimensions"
                placeholder="30x20x10"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>
          </div>

          <!-- COD Amount -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Tiền thu hộ (COD)</label>
            <input
              type="number"
              v-model.number="form.cod_amount"
              min="0"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>

          <!-- Note -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Ghi chú</label>
            <textarea
              v-model="form.note"
              rows="2"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="Ghi chú về giao hàng..."
            ></textarea>
          </div>
        </div>

        <!-- Buttons -->
        <div class="flex justify-end space-x-3 mt-6 pt-6 border-t px-6 pb-6">
          <button
            type="button"
            @click="$emit('close')"
            class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
          >
            Hủy
          </button>
          <button
            type="submit"
            :disabled="loading"
            class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 disabled:opacity-50"
          >
            <span v-if="loading">Đang tạo...</span>
            <span v-else>Tạo đơn giao hàng</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script>
import { ref, onMounted } from 'vue'
import { shippingApi } from '../api/shippingApi'

export default {
  name: 'ShippingForm',
  props: {
    order: {
      type: Object,
      required: true
    }
  },
  emits: ['close', 'success'],
  setup(props, { emit }) {
    const loading = ref(false)
    const providers = ref([])
    const errors = ref({})

    const form = ref({
      provider_id: '',
      shipping_method: 'standard',
      shipping_fee: 0,
      payment_by: 'sender',
      delivery_contact: props.order.delivery_contact || props.order.customer?.name || '',
      delivery_phone: props.order.delivery_phone || props.order.customer?.phone || '',
      delivery_address: props.order.delivery_address || '',
      weight: 0,
      dimensions: '',
      cod_amount: props.order.debt || 0,
      note: ''
    })

    const formatCurrency = (amount) => {
      return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND'
      }).format(amount)
    }

    const loadProviders = async () => {
      try {
        const response = await shippingApi.getProviders()
        if (response.success) {
          providers.value = response.data
        }
      } catch (error) {
        console.error('Error loading providers:', error)
      }
    }

    const handleSubmit = async () => {
      errors.value = {}
      
      // Validation
      if (!form.value.provider_id) {
        errors.value.provider_id = 'Vui lòng chọn đơn vị vận chuyển'
      }
      if (!form.value.shipping_fee || form.value.shipping_fee < 0) {
        errors.value.shipping_fee = 'Vui lòng nhập phí vận chuyển'
      }
      if (!form.value.payment_by) {
        errors.value.payment_by = 'Vui lòng chọn bên thanh toán'
      }
      if (!form.value.delivery_contact) {
        errors.value.delivery_contact = 'Vui lòng nhập tên người nhận'
      }
      if (!form.value.delivery_phone) {
        errors.value.delivery_phone = 'Vui lòng nhập số điện thoại'
      }
      if (!form.value.delivery_address) {
        errors.value.delivery_address = 'Vui lòng nhập địa chỉ giao hàng'
      }

      if (Object.keys(errors.value).length > 0) {
        return
      }

      loading.value = true

      try {
        const response = await shippingApi.createShipping(props.order.id, form.value)
        
        if (response.success) {
          emit('success', response.data)
          emit('close')
        }
      } catch (error) {
        console.error('Error creating shipping:', error)
        if (error.errors) {
          errors.value = error.errors
        }
      } finally {
        loading.value = false
      }
    }

    onMounted(() => {
      loadProviders()
    })

    return {
      loading,
      providers,
      errors,
      form,
      handleSubmit,
      formatCurrency
    }
  }
}
</script>