<template>
  <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-lg shadow-xl max-w-5xl w-full max-h-[95vh] overflow-y-auto">
      <div class="p-8">
    <form @submit.prevent="handleSubmit" class="space-y-6">
      <!-- Basic Information -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Customer Code -->
        <div>
          <label for="code" class="block text-sm font-medium text-gray-700 mb-2">
            Mã khách hàng
          </label>
          <input
            id="code"
            v-model="formData.code"
            type="text"
            :disabled="isEditing"
            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:bg-gray-100"
            placeholder="Tự động tạo nếu để trống"
          />
          <p v-if="errors.code" class="mt-1 text-sm text-red-600">{{ errors.code[0] }}</p>
        </div>

        <!-- Customer Name -->
        <div>
          <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
            Tên khách hàng <span class="text-red-500">*</span>
          </label>
          <input
            id="name"
            v-model="formData.name"
            type="text"
            required
            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            placeholder="Nhập tên khách hàng"
          />
          <p v-if="errors.name" class="mt-1 text-sm text-red-600">{{ errors.name[0] }}</p>
        </div>

        <!-- Customer Group -->
        <!-- Customer Group -->
<div>
  <label for="group_id" class="block text-sm font-medium text-gray-700 mb-2">
    Nhóm khách hàng
  </label>
  <select
    id="group_id"
    v-model="formData.group_id"
    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent"
  >
    <option value="">Chọn nhóm khách hàng</option>
    <option 
      v-for="group in customerGroups" 
      :key="group.id" 
      :value="group.id"
    >
      {{ group.name }}
    </option>
  </select>
  <p v-if="errors.group_id" class="mt-1 text-sm text-red-600">{{ errors.group_id[0] }}</p>
</div>

        <!-- Customer Type -->
        <div>
          <label for="customer_type" class="block text-sm font-medium text-gray-700 mb-2">
            Loại khách hàng
          </label>
          <select
            id="customer_type"
            v-model="formData.customer_type"
            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent"
          >
            <option value="">Chọn loại khách hàng</option>
            <option value="Bán lẻ">Bán lẻ</option>
            <option value="Bán buôn">Bán buôn</option>
            <option value="VIP">VIP</option>
            <option value="Đại lý">Đại lý</option>
          </select>
          <p v-if="errors.customer_type" class="mt-1 text-sm text-red-600">{{ errors.customer_type[0] }}</p>
        </div>
      </div>

      <!-- Contact Information -->
      <div class="border-t pt-6">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Thông tin liên hệ</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <!-- Email -->
          <div>
            <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
              Email
            </label>
            <input
              id="email"
              v-model="formData.email"
              type="email"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              placeholder="email@example.com"
            />
            <p v-if="errors.email" class="mt-1 text-sm text-red-600">{{ errors.email[0] }}</p>
          </div>

          <!-- Phone -->
          <div>
            <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">
              Số điện thoại
            </label>
            <input
              id="phone"
              v-model="formData.phone"
              type="tel"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              placeholder="0123456789"
            />
            <p v-if="errors.phone" class="mt-1 text-sm text-red-600">{{ errors.phone[0] }}</p>
          </div>

          <!-- Website -->
          <div>
            <label for="website" class="block text-sm font-medium text-gray-700 mb-2">
              Website
            </label>
            <input
              id="website"
              v-model="formData.website"
              type="url"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              placeholder="https://example.com"
            />
            <p v-if="errors.website" class="mt-1 text-sm text-red-600">{{ errors.website[0] }}</p>
          </div>

          <!-- Tax Code -->
          <div>
            <label for="tax_code" class="block text-sm font-medium text-gray-700 mb-2">
              Mã số thuế
            </label>
            <input
              id="tax_code"
              v-model="formData.tax_code"
              type="text"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              placeholder="0123456789"
            />
            <p v-if="errors.tax_code" class="mt-1 text-sm text-red-600">{{ errors.tax_code[0] }}</p>
          </div>
        </div>
      </div>

      <!-- Personal Information -->
      <div class="border-t pt-6">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Thông tin cá nhân</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <!-- Birthday -->
          <div>
            <label for="birthday" class="block text-sm font-medium text-gray-700 mb-2">
              Ngày sinh
            </label>
            <input
              id="birthday"
              v-model="formData.birthday"
              type="date"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
            <p v-if="errors.birthday" class="mt-1 text-sm text-red-600">{{ errors.birthday[0] }}</p>
          </div>

          <!-- Gender -->
          <div>
            <label for="gender" class="block text-sm font-medium text-gray-700 mb-2">
              Giới tính
            </label>
            <select
              id="gender"
              v-model="formData.gender"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            >
              <option value="">Chọn giới tính</option>
              <option value="male">Nam</option>
              <option value="female">Nữ</option>
              <option value="other">Khác</option>
            </select>
            <p v-if="errors.gender" class="mt-1 text-sm text-red-600">{{ errors.gender[0] }}</p>
          </div>

          <!-- Person in Charge -->
          <div>
            <label for="person_in_charge" class="block text-sm font-medium text-gray-700 mb-2">
              Nhân viên phụ trách
            </label>
            <input
              id="person_in_charge"
              v-model="formData.person_in_charge"
              type="text"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              placeholder="Cao Đức Bình"
            />
            <p v-if="errors.person_in_charge" class="mt-1 text-sm text-red-600">{{ errors.person_in_charge[0] }}</p>
          </div>

          <!-- Status -->
          <div>
            <label for="status" class="block text-sm font-medium text-gray-700 mb-2">
              Trạng thái
            </label>
            <select
              id="status"
              v-model="formData.status"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            >
              <option value="active">Đang giao dịch</option>
              <option value="inactive">Ngừng giao dịch</option>
            </select>
            <p v-if="errors.status" class="mt-1 text-sm text-red-600">{{ errors.status[0] }}</p>
          </div>
        </div>
      </div>

      <!-- Tags and Notes -->
      <div class="border-t pt-6">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Thông tin bổ sung</h3>
        <div class="space-y-4">
          <!-- Tags -->
          <div>
            <label for="tags" class="block text-sm font-medium text-gray-700 mb-2">
              Tags
            </label>
            <input
              id="tags"
              v-model="formData.tags"
              type="text"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              placeholder="vip, khach-hang-lon, ..."
            />
            <p class="mt-1 text-sm text-gray-500">Phân cách các tag bằng dấu phẩy</p>
            <p v-if="errors.tags" class="mt-1 text-sm text-red-600">{{ errors.tags[0] }}</p>
          </div>

          <!-- Notes -->
          <div>
            <label for="note" class="block text-sm font-medium text-gray-700 mb-2">
              Ghi chú
            </label>
            <textarea
              id="note"
              v-model="formData.note"
              rows="3"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              placeholder="Nhập ghi chú về khách hàng..."
            ></textarea>
            <p v-if="errors.note" class="mt-1 text-sm text-red-600">{{ errors.note[0] }}</p>
          </div>
        </div>
      </div>

      <!-- Form Actions -->
      <div class="flex justify-end space-x-3 pt-6 border-t">
        <button
          type="button"
          @click="$emit('cancel')"
          class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
        >
          Hủy
        </button>
        <button
          type="submit"
          :disabled="isSubmitting"
          class="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:opacity-50 disabled:cursor-not-allowed"
        >
          <i v-if="isSubmitting" class="fas fa-spinner fa-spin mr-2"></i>
          {{ isSubmitting ? 'Đang lưu...' : (isEditing ? 'Cập nhật' : 'Thêm mới') }}
        </button>
      </div>
    </form>

    <!-- Form Preview (Optional) -->
    <div v-if="showPreview" class="mt-8 p-4 bg-gray-50 rounded-lg">
      <h4 class="text-lg font-medium text-gray-900 mb-4">Xem trước thông tin</h4>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
        <div><strong>Mã khách hàng:</strong> {{ formData.code || 'Tự động tạo' }}</div>
        <div><strong>Tên khách hàng:</strong> {{ formData.name || '---' }}</div>
        <div><strong>Nhóm:</strong> {{ getGroupName(formData.group_id) || '---' }}</div>
        <div><strong>Loại:</strong> {{ formData.customer_type || '---' }}</div>
        <div><strong>Email:</strong> {{ formData.email || '---' }}</div>
        <div><strong>Số điện thoại:</strong> {{ formData.phone || '---' }}</div>
        <div><strong>Ngày sinh:</strong> {{ formatDate(formData.birthday) || '---' }}</div>
        <div><strong>Giới tính:</strong> {{ getGenderText(formData.gender) || '---' }}</div>
        <div><strong>Trạng thái:</strong> {{ formData.status === 'active' ? 'Đang giao dịch' : 'Ngừng giao dịch' }}</div>
        <div><strong>Người phụ trách:</strong> {{ formData.person_in_charge || '---' }}</div>
      </div>
      <div v-if="formData.note" class="mt-4">
        <strong>Ghi chú:</strong> {{ formData.note }}
      </div>
    </div>
  </div>
  </div>
  </div>
</template>

<script>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import customerApi from '../api/customerApi.js'

export default {
  name: 'CustomerForm',
  props: {
    customer: {
      type: Object,
      default: null
    },
    customerGroups: {
      type: Array,
      default: () => []
    },
    showPreview: {
      type: Boolean,
      default: false
    }
  },
  emits: ['submit', 'cancel'],
  setup(props, { emit }) {
    // Reactive data
    const isSubmitting = ref(false)
    const errors = ref({})

    const formData = reactive({
      code: '',
      name: '',
      group_id: '',
      email: '',
      phone: '',
      birthday: '',
      gender: '',
      tax_code: '',
      website: '',
      status: 'active',
      customer_type: 'Bán lẻ',
      person_in_charge: 'Cao Đức Bình',
      tags: '',
      note: ''
    })

    // Computed properties
    const isEditing = computed(() => !!props.customer)

    // Methods
    const initForm = () => {
      if (props.customer) {
        Object.keys(formData).forEach(key => {
          if (props.customer[key] !== undefined) {
            formData[key] = props.customer[key]
          }
        })
        
        // Format birthday for date input
        if (props.customer.birthday) {
          formData.birthday = new Date(props.customer.birthday).toISOString().split('T')[0]
        }
      }
    }

    const validateForm = () => {
      errors.value = {}
      let isValid = true

      // Required fields
      if (!formData.name.trim()) {
        errors.value.name = ['Tên khách hàng là bắt buộc']
        isValid = false
      }

      // Email validation
      if (formData.email && !isValidEmail(formData.email)) {
        errors.value.email = ['Email không hợp lệ']
        isValid = false
      }

      // Phone validation
      if (formData.phone && !isValidPhone(formData.phone)) {
        errors.value.phone = ['Số điện thoại không hợp lệ']
        isValid = false
      }

      // URL validation
      if (formData.website && !isValidURL(formData.website)) {
        errors.value.website = ['Website không hợp lệ']
        isValid = false
      }

      // Tax code validation
      if (formData.tax_code && formData.tax_code.length < 10) {
        errors.value.tax_code = ['Mã số thuế phải có ít nhất 10 ký tự']
        isValid = false
      }

      return isValid
    }

    const handleSubmit = async () => {
      if (!validateForm()) {
        return
      }

      isSubmitting.value = true
      errors.value = {}

      try {
        // Prepare data
        const submitData = { ...formData }
        
        // Clean up empty fields
        Object.keys(submitData).forEach(key => {
          if (submitData[key] === '') {
            submitData[key] = null
          }
        })

        // Convert group_id to number if exists
        if (submitData.group_id) {
          submitData.group_id = parseInt(submitData.group_id)
        }

        emit('submit', submitData)
      } catch (error) {
        if (error.type === 'validation') {
          errors.value = error.errors || {}
        }
      } finally {
        isSubmitting.value = false
      }
    }

    const resetForm = () => {
      Object.keys(formData).forEach(key => {
        if (key === 'status') {
          formData[key] = 'active'
        } else if (key === 'customer_type') {
          formData[key] = 'Bán lẻ'
        } else if (key === 'person_in_charge') {
          formData[key] = 'Cao Đức Bình'
        } else {
          formData[key] = ''
        }
      })
      errors.value = {}
    }

    // Utility functions
    const isValidEmail = (email) => {
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
      return emailRegex.test(email)
    }

    const isValidPhone = (phone) => {
      const phoneRegex = /^[0-9]{10,11}$/
      return phoneRegex.test(phone.replace(/\s/g, ''))
    }

    const isValidURL = (url) => {
      try {
        new URL(url)
        return true
      } catch {
        return false
      }
    }

    const getGroupName = (groupId) => {
      if (!groupId) return ''
      const group = props.customerGroups.find(g => g.id == groupId)
      return group ? group.name : ''
    }

    const getGenderText = (gender) => {
      switch (gender) {
        case 'male': return 'Nam'
        case 'female': return 'Nữ'
        case 'other': return 'Khác'
        default: return ''
      }
    }

    const formatDate = (date) => {
      if (!date) return ''
      return new Date(date).toLocaleDateString('vi-VN')
    }

    // Auto-save draft (Optional)
    const saveDraft = () => {
      if (!isEditing.value) {
        localStorage.setItem('customer_form_draft', JSON.stringify(formData))
      }
    }

    const loadDraft = () => {
      if (!isEditing.value) {
        const draft = localStorage.getItem('customer_form_draft')
        if (draft) {
          try {
            const draftData = JSON.parse(draft)
            Object.keys(draftData).forEach(key => {
              if (formData.hasOwnProperty(key)) {
                formData[key] = draftData[key]
              }
            })
          } catch (error) {
            console.error('Error loading draft:', error)
          }
        }
      }
    }

    const clearDraft = () => {
      localStorage.removeItem('customer_form_draft')
    }

    // Watch for changes to auto-save draft
    watch(
      () => formData,
      () => {
        if (!isEditing.value) {
          saveDraft()
        }
      },
      { deep: true }
    )

    // Lifecycle
    onMounted(() => {
      initForm()
      if (!isEditing.value) {
        loadDraft()
      }
    })

    return {
      formData,
      errors,
      isSubmitting,
      isEditing,
      handleSubmit,
      resetForm,
      getGroupName,
      getGenderText,
      formatDate,
      clearDraft
    }
  }
}
</script>

<style scoped>
/* Custom styles for form */
.form-section {
  @apply border-t pt-6;
}

.form-section:first-child {
  @apply border-t-0 pt-0;
}

/* Focus states */
input:focus,
select:focus,
textarea:focus {
  @apply outline-none ring-2 ring-blue-500 border-transparent;
}

/* Error states */
.error input,
.error select,
.error textarea {
  @apply border-red-300 ring-red-500;
}

/* Loading button */
.loading {
  @apply opacity-50 cursor-not-allowed;
}

/* Preview section */
.preview {
  @apply bg-gray-50 border rounded-lg p-4 mt-4;
}

.preview-item {
  @apply text-sm;
}

.preview-item strong {
  @apply font-medium text-gray-900;
}
</style>