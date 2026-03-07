<template>
  <!-- Modal Overlay -->
  <div class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl max-h-screen overflow-y-auto">
      
      <!-- Header -->
      <div class="flex items-center justify-between p-6 border-b">
        <h2 class="text-xl font-semibold text-gray-900">
          {{ isEdit ? 'Sửa nhóm khách hàng' : 'Thêm nhóm khách hàng mới' }}
        </h2>
        <button @click="$emit('close')" class="text-gray-400 hover:text-gray-600">
          <span class="text-2xl">&times;</span>
        </button>
      </div>

      <!-- Form -->
      <form @submit.prevent="handleSubmit" class="p-6">
        
        <!-- Basic Information -->
        <div class="space-y-6">
          
          <!-- Code & Name Row -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Mã nhóm -->
            <div>
              <label class="form-label">Mã nhóm khách hàng</label>
              <input
                type="text"
                v-model="form.code"
                class="form-input"
                :class="{ 'border-red-500': errors.code }"
                placeholder="Tự động tạo nếu để trống (CG001, CG002...)"
                :disabled="isEdit"
              />
              <div v-if="errors.code" class="form-error">{{ errors.code }}</div>
              <div class="text-xs text-gray-500 mt-1">
                {{ isEdit ? 'Không thể thay đổi mã nhóm' : 'Tự động tạo: CG001, CG002...' }}
              </div>
            </div>

            <!-- Tên nhóm -->
            <div>
              <label class="form-label">Tên nhóm khách hàng *</label>
              <input
                type="text"
                v-model="form.name"
                class="form-input"
                :class="{ 'border-red-500': errors.name }"
                placeholder="Nhập tên nhóm khách hàng"
                required
              />
              <div v-if="errors.name" class="form-error">{{ errors.name }}</div>
            </div>
          </div>

          <!-- Type -->
          <div>
            <label class="form-label">Loại nhóm *</label>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
              <div v-for="type in groupTypes" :key="type.value" class="relative">
                <input
                  type="radio"
                  :id="type.value"
                  :value="type.value"
                  v-model="form.type"
                  class="sr-only"
                  required
                />
                <label
                  :for="type.value"
                  class="flex flex-col items-center p-4 border-2 rounded-lg cursor-pointer transition-all"
                  :class="form.type === type.value ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-gray-300'"
                >
                  <span class="text-2xl mb-2">{{ type.icon }}</span>
                  <span class="text-sm font-medium">{{ type.label }}</span>
                  <span class="text-xs text-gray-500 text-center mt-1">{{ type.description }}</span>
                </label>
              </div>
            </div>
            <div v-if="errors.type" class="form-error">{{ errors.type }}</div>
          </div>

          <!-- Description -->
          <div>
            <label class="form-label">Mô tả</label>
            <textarea
              v-model="form.description"
              class="form-input"
              :class="{ 'border-red-500': errors.description }"
              rows="3"
              placeholder="Mô tả về nhóm khách hàng này..."
            ></textarea>
            <div v-if="errors.description" class="form-error">{{ errors.description }}</div>
          </div>

          <!-- Financial Benefits Section -->
          <div class="border-t pt-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">💰 Ưu đãi & Điều kiện</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              
              <!-- Discount Percent -->
              <div>
                <label class="form-label">Chiết khấu (%)</label>
                <div class="relative">
                  <input
                    type="number"
                    v-model.number="form.discount_percent"
                    class="form-input pr-8"
                    :class="{ 'border-red-500': errors.discount_percent }"
                    min="0"
                    max="100"
                    step="0.1"
                    placeholder="0.0"
                  />
                  <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500">%</span>
                </div>
                <div v-if="errors.discount_percent" class="form-error">{{ errors.discount_percent }}</div>
                <div class="text-xs text-gray-500 mt-1">
                  Chiết khấu từ 0% đến 100%
                </div>
              </div>

              <!-- Payment Terms -->
              <div>
                <label class="form-label">Điều kiện thanh toán (ngày)</label>
                <div class="relative">
                  <input
                    type="number"
                    v-model.number="form.payment_terms"
                    class="form-input pr-12"
                    :class="{ 'border-red-500': errors.payment_terms }"
                    min="0"
                    max="365"
                    placeholder="0"
                  />
                  <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500">ngày</span>
                </div>
                <div v-if="errors.payment_terms" class="form-error">{{ errors.payment_terms }}</div>
                <div class="text-xs text-gray-500 mt-1">
                  0 = Thanh toán ngay, >0 = Số ngày được nợ
                </div>
              </div>

            </div>

            <!-- Benefits Preview -->
            <div class="mt-4 p-4 bg-gray-50 rounded-lg">
              <h4 class="text-sm font-medium text-gray-700 mb-2">🎯 Preview ưu đãi:</h4>
              <div class="text-sm text-gray-600 space-y-1">
                <div v-if="form.discount_percent > 0">
                  • Chiết khấu: <span class="font-medium text-green-600">{{ formatDiscount(form.discount_percent) }}</span>
                </div>
                <div v-if="form.payment_terms > 0">
                  • Thanh toán: <span class="font-medium text-blue-600">{{ formatPaymentTerms(form.payment_terms) }}</span>
                </div>
                <div v-if="form.type === 'vip'">
                  • Ưu tiên: <span class="font-medium text-purple-600">Dịch vụ VIP</span>
                </div>
                <div v-if="form.discount_percent === 0 && form.payment_terms === 0 && form.type !== 'vip'">
                  • <span class="text-gray-500">Chưa có ưu đãi đặc biệt</span>
                </div>
              </div>
            </div>
          </div>

        </div>

        <!-- Buttons -->
        <div class="flex justify-end space-x-3 mt-8 pt-6 border-t">
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
            <span v-if="loading">Đang lưu...</span>
            <span v-else>{{ isEdit ? 'Cập nhật' : 'Tạo mới' }}</span>
          </button>
        </div>

      </form>
    </div>
  </div>
</template>

<script>
import { ref, computed, watch, onMounted } from 'vue'
import customerGroupApi from '../api/customerGroupApi.js'

export default {
  name: 'CustomerGroupForm',
  props: {
    group: {
      type: Object,
      default: null
    }
  },
  emits: ['close', 'save'],
  setup(props, { emit }) {
    const loading = ref(false)
    const errors = ref({})

    // Group types with icons and descriptions
    const groupTypes = ref([
      {
        value: 'vip',
        label: 'VIP',
        icon: '👑',
        description: 'Khách hàng VIP, ưu tiên cao nhất'
      },
      {
        value: 'normal',
        label: 'Thường',
        icon: '👤',
        description: 'Khách hàng lẻ thông thường'
      },
      {
        value: 'local',
        label: 'Địa phương',
        icon: '🏪',
        description: 'Đại lý, cửa hàng địa phương'
      },
      {
        value: 'import',
        label: 'Xuất nhập khẩu',
        icon: '🌍',
        description: 'Đối tác quốc tế, xuất khẩu'
      }
    ])

    // Form data
    const form = ref({
      code: '',
      name: '',
      type: 'normal',
      description: '',
      discount_percent: 0,
      payment_terms: 0
    })

    // Computed
    const isEdit = computed(() => {
      return props.group && props.group.id
    })

    // Methods
    const initForm = () => {
      if (props.group) {
        // Edit mode - populate form with existing data
        form.value = {
          code: props.group.code || '',
          name: props.group.name || '',
          type: props.group.type || 'normal',
          description: props.group.description || '',
          discount_percent: props.group.discount_percent || 0,
          payment_terms: props.group.payment_terms || 0
        }
      } else {
        // Create mode - reset form
        form.value = {
          code: '',
          name: '',
          type: 'normal',
          description: '',
          discount_percent: 0,
          payment_terms: 0
        }
      }
      errors.value = {}
    }

    const validateForm = () => {
      errors.value = {}

      // Name validation
      if (!form.value.name?.trim()) {
        errors.value.name = 'Tên nhóm khách hàng là bắt buộc'
      } else if (form.value.name.length > 255) {
        errors.value.name = 'Tên nhóm không được vượt quá 255 ký tự'
      }

      // Type validation
      if (!form.value.type) {
        errors.value.type = 'Loại nhóm là bắt buộc'
      } else if (!['vip', 'normal', 'local', 'import'].includes(form.value.type)) {
        errors.value.type = 'Loại nhóm không hợp lệ'
      }

      // Description validation
      if (form.value.description && form.value.description.length > 1000) {
        errors.value.description = 'Mô tả không được vượt quá 1000 ký tự'
      }

      // Discount validation
      const discount = Number(form.value.discount_percent)
      if (isNaN(discount)) {
        errors.value.discount_percent = 'Chiết khấu phải là số'
      } else if (discount < 0) {
        errors.value.discount_percent = 'Chiết khấu không được âm'
      } else if (discount > 100) {
        errors.value.discount_percent = 'Chiết khấu không được vượt quá 100%'
      }

      // Payment terms validation
      const terms = Number(form.value.payment_terms)
      if (isNaN(terms)) {
        errors.value.payment_terms = 'Điều kiện thanh toán phải là số'
      } else if (terms < 0) {
        errors.value.payment_terms = 'Điều kiện thanh toán không được âm'
      } else if (terms > 365) {
        errors.value.payment_terms = 'Điều kiện thanh toán không được vượt quá 365 ngày'
      }

      return Object.keys(errors.value).length === 0
    }

    const handleSubmit = async () => {
      if (!validateForm()) {
        return
      }

      loading.value = true

      try {
        // Clean up form data
        const formData = {
          ...form.value,
          code: form.value.code?.trim() || null,
          name: form.value.name.trim(),
          type: form.value.type,
          description: form.value.description?.trim() || null,
          discount_percent: Number(form.value.discount_percent) || 0,
          payment_terms: Number(form.value.payment_terms) || 0
        }

        emit('save', formData)
      } catch (error) {
        console.error('Form submission error:', error)
      } finally {
        loading.value = false
      }
    }

    const formatDiscount = (value) => {
      return `${Number(value || 0).toFixed(1)}%`
    }

    const formatPaymentTerms = (value) => {
      const days = Number(value || 0)
      return days === 0 ? 'Thanh toán ngay' : `${days} ngày`
    }

    // Auto-suggest based on type
    const updateSuggestionsBasedOnType = () => {
      const typeSuggestions = {
        'vip': {
          discount_percent: 10,
          payment_terms: 30,
          description: 'Khách hàng VIP với ưu đãi đặc biệt và dịch vụ ưu tiên'
        },
        'normal': {
          discount_percent: 0,
          payment_terms: 0,
          description: 'Khách hàng bán lẻ thông thường'
        },
        'local': {
          discount_percent: 5,
          payment_terms: 15,
          description: 'Đại lý bán buôn tại địa phương'
        },
        'import': {
          discount_percent: 8,
          payment_terms: 45,
          description: 'Đối tác xuất nhập khẩu quốc tế'
        }
      }

      // Only apply suggestions for new groups, not when editing
      if (!isEdit.value && form.value.type && typeSuggestions[form.value.type]) {
        const suggestion = typeSuggestions[form.value.type]
        
        // Only update if current values are default/empty
        if (form.value.discount_percent === 0) {
          form.value.discount_percent = suggestion.discount_percent
        }
        if (form.value.payment_terms === 0) {
          form.value.payment_terms = suggestion.payment_terms
        }
        if (!form.value.description) {
          form.value.description = suggestion.description
        }
      }
    }

    // Watch for type changes to provide suggestions
    watch(() => form.value.type, () => {
      updateSuggestionsBasedOnType()
    })

    // Watch for prop changes
    watch(() => props.group, initForm, { immediate: true })

    // Initialize on mount
    onMounted(() => {
      initForm()
    })

    return {
      loading,
      errors,
      groupTypes,
      form,
      isEdit,
      validateForm,
      handleSubmit,
      formatDiscount,
      formatPaymentTerms
    }
  }
}
</script>

<style scoped>
.form-label {
  @apply block text-sm font-medium text-gray-700 mb-1;
}

.form-input {
  @apply w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500;
}

.form-error {
  @apply text-red-600 text-sm mt-1;
}
</style>