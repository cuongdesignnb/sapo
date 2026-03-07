<template>
  <!-- Modal Overlay -->
  <div class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl max-h-screen overflow-y-auto">
      
      <!-- Header -->
      <div class="flex items-center justify-between p-6 border-b">
        <h2 class="text-xl font-semibold text-gray-900">
          {{ isEdit ? 'Sửa đơn vị vận chuyển' : 'Thêm đơn vị vận chuyển' }}
        </h2>
        <button @click="$emit('close')" class="text-gray-400 hover:text-gray-600">
          <span class="text-2xl">&times;</span>
        </button>
      </div>

      <!-- Form -->
      <form @submit.prevent="handleSubmit" class="p-6">
        <div class="space-y-6">
          
          <!-- Code & Name -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Mã đơn vị *</label>
              <input
                type="text"
                v-model="form.code"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                :class="{ 'border-red-500': errors.code }"
                placeholder="VD: GHTK, GHN..."
                required
              />
              <div v-if="errors.code" class="text-red-500 text-sm mt-1">{{ errors.code }}</div>
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Tên đơn vị *</label>
              <input
                type="text"
                v-model="form.name"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                :class="{ 'border-red-500': errors.name }"
                placeholder="VD: Giao Hàng Tiết Kiệm"
                required
              />
              <div v-if="errors.name" class="text-red-500 text-sm mt-1">{{ errors.name }}</div>
            </div>
          </div>

          <!-- Type & Status -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Loại đơn vị *</label>
              <select 
                v-model="form.type" 
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                required
              >
                <option value="">Chọn loại đơn vị</option>
                <option value="internal">Nội bộ</option>
                <option value="ghtk">GHTK</option>
                <option value="ghn">GHN</option>
                <option value="viettelpost">Viettel Post</option>
                <option value="custom">Tùy chỉnh</option>
              </select>
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Trạng thái *</label>
              <select 
                v-model="form.status" 
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                required
              >
                <option value="active">Hoạt động</option>
                <option value="inactive">Ngừng hoạt động</option>
              </select>
            </div>
          </div>

          <!-- API Config (hiển thị khi không phải internal) -->
          <div v-if="form.type && form.type !== 'internal'" class="space-y-4">
            <h3 class="text-lg font-medium text-gray-900">Cấu hình API</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">API URL</label>
                <input
                  type="url"
                  v-model="form.api_config.api_url"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                  placeholder="https://api.example.com"
                />
              </div>
              
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Token/API Key</label>
                <input
                  type="text"
                  v-model="form.api_config.token"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                  placeholder="API Token hoặc Key"
                />
              </div>
            </div>

            <!-- GHN specific -->
            <div v-if="form.type === 'ghn'">
              <label class="block text-sm font-medium text-gray-700 mb-2">Shop ID</label>
              <input
                type="text"
                v-model="form.api_config.shop_id"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="Shop ID của GHN"
              />
            </div>

            <!-- Viettel Post specific -->
            <div v-if="form.type === 'viettelpost'" class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Username</label>
                <input
                  type="text"
                  v-model="form.api_config.username"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                  placeholder="Username Viettel Post"
                />
              </div>
              
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                <input
                  type="password"
                  v-model="form.api_config.password"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                  placeholder="Password Viettel Post"
                />
              </div>
            </div>
          </div>

          <!-- Pricing Config -->
          <div class="space-y-4">
            <h3 class="text-lg font-medium text-gray-900">Cấu hình giá cước</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Phí cơ bản (VNĐ)</label>
                <input
                  type="number"
                  v-model.number="form.pricing_config.base_fee"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                  placeholder="25000"
                  min="0"
                />
              </div>
              
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Phí theo km (VNĐ/km)</label>
                <input
                  type="number"
                  v-model.number="form.pricing_config.per_km"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                  placeholder="5000"
                  min="0"
                />
              </div>
              
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Phí COD (%)</label>
                <input
                  type="number"
                  v-model.number="form.pricing_config.cod_fee"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                  placeholder="1.5"
                  min="0"
                  max="100"
                  step="0.1"
                />
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Phí bảo hiểm (%)</label>
              <input
                type="number"
                v-model.number="form.pricing_config.insurance_fee"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="0.5"
                min="0"
                max="100"
                step="0.1"
              />
            </div>
          </div>

        </div>

        <!-- Buttons -->
        <div class="flex justify-end space-x-3 mt-6 pt-6 border-t">
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
            <span v-if="loading">Đang {{ isEdit ? 'cập nhật' : 'tạo' }}...</span>
            <span v-else>{{ isEdit ? 'Cập nhật' : 'Tạo mới' }}</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue'
import { shippingProviderApi } from '../api/shippingProviderApi.js'

export default {
  name: 'ProviderModal',
  props: {
    provider: {
      type: Object,
      default: null
    }
  },
  emits: ['close', 'success'],
  setup(props, { emit }) {
    const loading = ref(false)
    const errors = ref({})

    const isEdit = computed(() => !!props.provider)

    const form = ref({
      code: '',
      name: '',
      type: '',
      status: 'active',
      api_config: {
        api_url: '',
        token: '',
        shop_id: '',
        username: '',
        password: ''
      },
      pricing_config: {
        base_fee: 25000,
        per_km: 5000,
        cod_fee: 1.5,
        insurance_fee: 0.5
      }
    })

    const initForm = () => {
      if (props.provider) {
        form.value = {
          code: props.provider.code || '',
          name: props.provider.name || '',
          type: props.provider.type || '',
          status: props.provider.status || 'active',
          api_config: {
            api_url: props.provider.api_config?.api_url || '',
            token: props.provider.api_config?.token || '',
            shop_id: props.provider.api_config?.shop_id || '',
            username: props.provider.api_config?.username || '',
            password: props.provider.api_config?.password || ''
          },
          pricing_config: {
            base_fee: props.provider.pricing_config?.base_fee || 25000,
            per_km: props.provider.pricing_config?.per_km || 5000,
            cod_fee: props.provider.pricing_config?.cod_fee || 1.5,
            insurance_fee: props.provider.pricing_config?.insurance_fee || 0.5
          }
        }
      }
    }

    const handleSubmit = async () => {
      errors.value = {}
      
      if (!form.value.code) {
        errors.value.code = 'Vui lòng nhập mã đơn vị'
        return
      }

      if (!form.value.name) {
        errors.value.name = 'Vui lòng nhập tên đơn vị'
        return
      }

      loading.value = true

      try {
        const response = isEdit.value 
          ? await shippingProviderApi.update(props.provider.id, form.value)
          : await shippingProviderApi.create(form.value)
        
        if (response.success) {
          emit('success', response.data)
        } else {
          if (response.errors) {
            errors.value = response.errors
          } else {
            alert(response.message || 'Có lỗi xảy ra')
          }
        }
      } catch (error) {
        console.error('Error saving provider:', error)
        if (error.errors) {
          errors.value = error.errors
        } else {
          alert('Có lỗi xảy ra khi lưu')
        }
      } finally {
        loading.value = false
      }
    }

    onMounted(() => {
      initForm()
    })

    return {
      loading,
      errors,
      isEdit,
      form,
      handleSubmit
    }
  }
}
</script>