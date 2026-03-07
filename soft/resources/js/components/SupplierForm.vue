<template>
  <!-- Modal Overlay -->
  <div class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl max-h-screen overflow-y-auto">
      
      <!-- Header -->
      <div class="flex items-center justify-between p-6 border-b">
        <h2 class="text-xl font-semibold text-gray-900">
          {{ isEdit ? 'Sửa nhà cung cấp' : 'Thêm nhà cung cấp mới' }}
        </h2>
        <button @click="$emit('close')" class="text-gray-400 hover:text-gray-600">
          <span class="text-2xl">&times;</span>
        </button>
      </div>

      <!-- Form -->
      <form @submit.prevent="handleSubmit" class="p-6">
        <!-- Basic Information -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          
          <!-- Tên nhà cung cấp -->
          <div>
            <label class="form-label">Tên nhà cung cấp *</label>
            <input
              type="text"
              v-model="form.name"
              class="form-input"
              :class="{ 'border-red-500': errors.name }"
              placeholder="Nhập tên nhà cung cấp"
              required
            />
            <div v-if="errors.name" class="form-error">{{ errors.name }}</div>
          </div>

          <!-- Mã nhà cung cấp -->
          <div>
            <label class="form-label">Mã nhà cung cấp</label>
            <input
              type="text"
              v-model="form.code"
              class="form-input"
              :class="{ 'border-red-500': errors.code }"
              placeholder="Tự động tạo nếu để trống"
            />
            <div v-if="errors.code" class="form-error">{{ errors.code }}</div>
          </div>

          <!-- Nhóm nhà cung cấp -->
          <div>
            <label class="form-label">Nhóm nhà cung cấp</label>
            <select v-model="form.group_id" class="form-input">
              <option value="">Chọn nhóm</option>
              <option v-for="group in groups" :key="group.value" :value="group.value">
                {{ group.label }}
              </option>
            </select>
          </div>

          <!-- Trạng thái -->
          <div>
            <label class="form-label">Trạng thái</label>
            <select v-model="form.status" class="form-input">
              <option value="active">Đang giao dịch</option>
              <option value="inactive">Tạm ngưng</option>
              <option value="suspended">Đình chỉ</option>
            </select>
          </div>

          <!-- Email -->
          <div>
            <label class="form-label">Email</label>
            <input
              type="email"
              v-model="form.email"
              class="form-input"
              :class="{ 'border-red-500': errors.email }"
              placeholder="email@example.com"
            />
            <div v-if="errors.email" class="form-error">{{ errors.email }}</div>
          </div>

          <!-- Số điện thoại -->
          <div>
            <label class="form-label">Số điện thoại</label>
            <input
              type="text"
              v-model="form.phone"
              class="form-input"
              :class="{ 'border-red-500': errors.phone }"
              placeholder="0123456789"
            />
            <div v-if="errors.phone" class="form-error">{{ errors.phone }}</div>
          </div>

          <!-- Website -->
          <div>
            <label class="form-label">Website</label>
            <input
              type="url"
              v-model="form.website"
              class="form-input"
              :class="{ 'border-red-500': errors.website }"
              placeholder="https://example.com"
            />
            <div v-if="errors.website" class="form-error">{{ errors.website }}</div>
          </div>

          <!-- Người phụ trách -->
          <div>
            <label class="form-label">Người phụ trách</label>
            <input
              type="text"
              v-model="form.person_in_charge"
              class="form-input"
              placeholder="Tên người phụ trách"
            />
          </div>

          <!-- Mã số thuế -->
          <div>
            <label class="form-label">Mã số thuế</label>
            <input
              type="text"
              v-model="form.tax_code"
              class="form-input"
              placeholder="Mã số thuế"
            />
          </div>

          <!-- Số ngày thanh toán -->
          <div>
            <label class="form-label">Số ngày thanh toán</label>
            <input
              type="number"
              v-model.number="form.payment_terms"
              class="form-input"
              min="0"
              placeholder="0"
            />
          </div>

        </div>

        <!-- Address -->
        <div class="mt-6">
          <label class="form-label">Địa chỉ</label>
          <textarea
            v-model="form.address"
            class="form-input"
            rows="2"
            placeholder="Nhập địa chỉ nhà cung cấp"
          ></textarea>
        </div>

        <!-- Financial Information -->
        <div class="mt-6 border-t pt-6">
          <h3 class="text-lg font-medium text-gray-900 mb-4">Thông tin tài chính</h3>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            
            <!-- Hạn mức tín dụng -->
            <div>
              <label class="form-label">Hạn mức tín dụng</label>
              <input
                type="number"
                v-model.number="form.credit_limit"
                class="form-input"
                min="0"
                step="1000"
                placeholder="0"
              />
            </div>

            <!-- Tài khoản ngân hàng -->
            <div>
              <label class="form-label">Tài khoản ngân hàng</label>
              <input
                type="text"
                v-model="form.bank_account"
                class="form-input"
                placeholder="Số tài khoản ngân hàng"
              />
            </div>

            <!-- Tên ngân hàng -->
            <div class="md:col-span-2">
              <label class="form-label">Tên ngân hàng</label>
              <input
                type="text"
                v-model="form.bank_name"
                class="form-input"
                placeholder="Vietcombank, Techcombank, BIDV..."
              />
            </div>

          </div>
        </div>

        <!-- Contacts Section -->
        <div class="mt-6 border-t pt-6">
          <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-medium text-gray-900">Danh bạ liên hệ</h3>
            <button type="button" @click="addContact" class="px-3 py-1 bg-blue-500 text-white rounded text-sm hover:bg-blue-600">
              + Thêm liên hệ
            </button>
          </div>
          
          <div v-if="form.contacts.length === 0" class="text-center py-6 text-gray-500 border-2 border-dashed border-gray-300 rounded-lg">
            Chưa có thông tin liên hệ
          </div>
          
          <div v-else class="space-y-4">
            <div v-for="(contact, index) in form.contacts" :key="index" class="border rounded-lg p-4">
              <div class="flex justify-between items-start mb-3">
                <h4 class="font-medium text-gray-900">Liên hệ {{ index + 1 }}</h4>
                <button type="button" @click="removeContact(index)" class="text-red-500 hover:text-red-700">
                  <span class="text-lg">&times;</span>
                </button>
              </div>
              
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="form-label">Họ tên *</label>
                  <input
                    type="text"
                    v-model="contact.name"
                    class="form-input"
                    placeholder="Tên người liên hệ"
                    required
                  />
                </div>
                
                <div>
                  <label class="form-label">Chức vụ</label>
                  <input
                    type="text"
                    v-model="contact.position"
                    class="form-input"
                    placeholder="Chức vụ"
                  />
                </div>
                
                <div>
                  <label class="form-label">Số điện thoại</label>
                  <input
                    type="text"
                    v-model="contact.phone"
                    class="form-input"
                    placeholder="0123456789"
                  />
                </div>
                
                <div>
                  <label class="form-label">Email</label>
                  <input
                    type="email"
                    v-model="contact.email"
                    class="form-input"
                    placeholder="email@example.com"
                  />
                </div>
                
                <div>
                  <label class="form-label">Phòng ban</label>
                  <input
                    type="text"
                    v-model="contact.department"
                    class="form-input"
                    placeholder="Phòng ban"
                  />
                </div>
                
                <div class="flex items-center mt-6">
                  <input
                    type="checkbox"
                    v-model="contact.is_primary"
                    @change="setPrimaryContact(index)"
                    class="mr-2"
                  />
                  <label class="text-sm text-gray-700">Liên hệ chính</label>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Tags & Notes -->
        <div class="mt-6 border-t pt-6">
          <h3 class="text-lg font-medium text-gray-900 mb-4">Thông tin bổ sung</h3>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            
            <!-- Tags -->
            <div>
              <label class="form-label">Tags</label>
              <input
                type="text"
                v-model="form.tags"
                class="form-input"
                placeholder="VIP, Đối tác chiến lược... (phân cách bằng dấu phẩy)"
              />
            </div>

            <!-- Ghi chú -->
            <div>
              <label class="form-label">Ghi chú</label>
              <textarea
                v-model="form.note"
                class="form-input"
                rows="3"
                placeholder="Ghi chú thêm về nhà cung cấp..."
              ></textarea>
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
import supplierApi from '../api/supplierApi.js'

export default {
  name: 'SupplierForm',
  props: {
    supplier: {
      type: Object,
      default: null
    }
  },
  emits: ['close', 'save'],
  setup(props, { emit }) {
    const loading = ref(false)
    const errors = ref({})
    const groups = ref([])

    // Form data
    const form = ref({
      code: '',
      name: '',
      group_id: '',
      email: '',
      phone: '',
      address: '',
      tax_code: '',
      website: '',
      person_in_charge: '',
      bank_account: '',
      bank_name: '',
      status: 'active',
      credit_limit: 0,
      payment_terms: 0,
      tags: '',
      note: '',
      contacts: []
    })

    // Computed
    const isEdit = computed(() => {
      return props.supplier && props.supplier.id
    })

    // Methods
    const loadGroups = async () => {
      try {
        const response = await supplierApi.getSupplierGroups()
        if (response.data.success) {
          groups.value = response.data.data
        }
      } catch (error) {
        console.error('Error loading groups:', error)
      }
    }

    const initForm = () => {
      if (props.supplier) {
        // Edit mode - populate form with existing data
        form.value = {
          code: props.supplier.code || '',
          name: props.supplier.name || '',
          group_id: props.supplier.group?.id || '',
          email: props.supplier.email || '',
          phone: props.supplier.phone || '',
          address: props.supplier.address || '',
          tax_code: props.supplier.tax_code || '',
          website: props.supplier.website || '',
          person_in_charge: props.supplier.person_in_charge || '',
          bank_account: props.supplier.bank_account || '',
          bank_name: props.supplier.bank_name || '',
          status: props.supplier.status || 'active',
          credit_limit: props.supplier.credit_limit || 0,
          payment_terms: props.supplier.payment_terms || 0,
          tags: props.supplier.tags || '',
          note: props.supplier.note || '',
          contacts: props.supplier.contacts || []
        }
      } else {
        // Create mode - reset form
        form.value = {
          code: '',
          name: '',
          group_id: '',
          email: '',
          phone: '',
          address: '',
          tax_code: '',
          website: '',
          person_in_charge: '',
          bank_account: '',
          bank_name: '',
          status: 'active',
          credit_limit: 0,
          payment_terms: 0,
          tags: '',
          note: '',
          contacts: []
        }
      }
      errors.value = {}
    }

    const validateForm = () => {
      errors.value = {}

      if (!form.value.name?.trim()) {
        errors.value.name = 'Tên nhà cung cấp là bắt buộc'
      }

      if (form.value.email && !isValidEmail(form.value.email)) {
        errors.value.email = 'Email không đúng định dạng'
      }

      if (form.value.website && !isValidUrl(form.value.website)) {
        errors.value.website = 'Website không đúng định dạng'
      }

      if (form.value.credit_limit < 0) {
        errors.value.credit_limit = 'Hạn mức tín dụng không được âm'
      }

      if (form.value.payment_terms < 0) {
        errors.value.payment_terms = 'Số ngày thanh toán không được âm'
      }

      return Object.keys(errors.value).length === 0
    }

    const isValidEmail = (email) => {
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
      return emailRegex.test(email)
    }

    const isValidUrl = (url) => {
      try {
        new URL(url)
        return true
      } catch {
        return false
      }
    }

    const addContact = () => {
      form.value.contacts.push({
        name: '',
        position: '',
        phone: '',
        email: '',
        department: '',
        is_primary: form.value.contacts.length === 0
      })
    }

    const removeContact = (index) => {
      if (confirm('Bạn có chắc chắn muốn xóa liên hệ này?')) {
        form.value.contacts.splice(index, 1)
        
        // Ensure there's still a primary contact
        if (form.value.contacts.length > 0 && !form.value.contacts.some(c => c.is_primary)) {
          form.value.contacts[0].is_primary = true
        }
      }
    }

    const setPrimaryContact = (index) => {
      // Unset other primary contacts
      form.value.contacts.forEach((contact, i) => {
        contact.is_primary = i === index
      })
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
          group_id: form.value.group_id || null,
          email: form.value.email?.trim() || null,
          phone: form.value.phone?.trim() || null,
          address: form.value.address?.trim() || null,
          tax_code: form.value.tax_code?.trim() || null,
          website: form.value.website?.trim() || null,
          person_in_charge: form.value.person_in_charge?.trim() || null,
          bank_account: form.value.bank_account?.trim() || null,
          bank_name: form.value.bank_name?.trim() || null,
          credit_limit: Number(form.value.credit_limit) || 0,
          payment_terms: Number(form.value.payment_terms) || 0,
          tags: form.value.tags?.trim() || null,
          note: form.value.note?.trim() || null,
          contacts: form.value.contacts.filter(c => c.name.trim())
        }

        emit('save', formData)
      } catch (error) {
        console.error('Form submission error:', error)
      } finally {
        loading.value = false
      }
    }

    // Watch for supplier changes
    watch(() => props.supplier, initForm, { immediate: true })

    // Initialize on mount
    onMounted(() => {
      loadGroups()
      initForm()
    })

    return {
      loading,
      errors,
      groups,
      form,
      isEdit,
      validateForm,
      addContact,
      removeContact,
      setPrimaryContact,
      handleSubmit
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