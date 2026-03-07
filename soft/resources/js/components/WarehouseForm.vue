<template>
  <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 w-full max-w-2xl max-h-screen overflow-y-auto">
      <div class="flex justify-between items-center mb-6">
        <h3 class="text-xl font-semibold text-gray-900">
          {{ isEdit ? 'Chỉnh sửa kho hàng' : 'Thêm kho hàng mới' }}
        </h3>
        <button @click="$emit('close')" class="text-gray-400 hover:text-gray-600 text-2xl">✕</button>
      </div>

      <form @submit.prevent="handleSubmit">
        <div class="space-y-6">
          <!-- Basic Info Section -->
          <div class="bg-gray-50 rounded-lg p-4">
            <h4 class="font-medium text-gray-900 mb-4">Thông tin cơ bản</h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <!-- Mã kho -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Mã kho <span class="text-red-500">*</span>
                </label>
                <input 
                  type="text" 
                  v-model="form.code"
                  :disabled="isEdit"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 disabled:bg-gray-100"
                  :class="{ 'border-red-500': errors.code }"
                  placeholder="VD: WH001"
                  required
                />
                <p v-if="errors.code" class="text-red-500 text-sm mt-1">{{ errors.code[0] }}</p>
              </div>

              <!-- Tên kho -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Tên kho <span class="text-red-500">*</span>
                </label>
                <input 
                  type="text" 
                  v-model="form.name"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  :class="{ 'border-red-500': errors.name }"
                  placeholder="VD: Kho Hà Nội"
                  required
                />
                <p v-if="errors.name" class="text-red-500 text-sm mt-1">{{ errors.name[0] }}</p>
              </div>

              <!-- Trạng thái -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Trạng thái <span class="text-red-500">*</span>
                </label>
                <select 
                  v-model="form.status"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  required
                >
                  <option value="active">Hoạt động</option>
                  <option value="inactive">Ngưng hoạt động</option>
                  <option value="maintenance">Bảo trì</option>
                </select>
              </div>

              <!-- Dung lượng -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Dung lượng kho (VNĐ)</label>
                <input 
                  type="number" 
                  v-model="form.capacity"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  placeholder="100000000"
                  min="0"
                />
                <p class="text-gray-500 text-xs mt-1">Để trống nếu không giới hạn dung lượng</p>
              </div>
            </div>

            <!-- Địa chỉ -->
            <div class="mt-4">
              <label class="block text-sm font-medium text-gray-700 mb-2">Địa chỉ kho</label>
              <textarea 
                v-model="form.address"
                rows="2"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                placeholder="Nhập địa chỉ đầy đủ của kho hàng"
              ></textarea>
            </div>
          </div>

          <!-- Manager Info Section -->
          <div class="bg-blue-50 rounded-lg p-4">
            <h4 class="font-medium text-gray-900 mb-4">Thông tin quản lý</h4>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <!-- Tên quản lý -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Tên quản lý</label>
                <input 
                  type="text" 
                  v-model="form.manager_name"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  placeholder="Nguyễn Văn A"
                />
              </div>

              <!-- Điện thoại -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Số điện thoại</label>
                <input 
                  type="tel" 
                  v-model="form.phone"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  placeholder="0123456789"
                />
              </div>

              <!-- Email -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                <input 
                  type="email" 
                  v-model="form.email"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  placeholder="manager@warehouse.com"
                />
              </div>
            </div>
          </div>

          <!-- Current Status (Edit Mode Only) -->
          <div v-if="isEdit && warehouse" class="bg-green-50 rounded-lg p-4">
            <h4 class="font-medium text-gray-900 mb-4">Tình trạng hiện tại</h4>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
              <div class="text-center">
                <div class="text-2xl font-bold text-blue-600">{{ formatCurrency(warehouse.current_value) }}</div>
                <div class="text-xs text-gray-500">Giá trị hiện tại</div>
              </div>
              <div class="text-center">
                <div class="text-2xl font-bold text-green-600">{{ warehouse.capacity_usage_percent || 0 }}%</div>
                <div class="text-xs text-gray-500">Tỷ lệ sử dụng</div>
              </div>
              <div class="text-center">
                <div class="text-2xl font-bold text-purple-600">{{ warehouse.total_product_types || 0 }}</div>
                <div class="text-xs text-gray-500">Loại sản phẩm</div>
              </div>
              <div class="text-center">
                <div class="text-2xl font-bold text-orange-600">{{ warehouse.total_products || 0 }}</div>
                <div class="text-xs text-gray-500">Tổng số lượng</div>
              </div>
            </div>
          </div>

          <!-- Note Section -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Ghi chú</label>
            <textarea 
              v-model="form.note"
              rows="3"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              placeholder="Ghi chú thêm về kho hàng, quy định, lưu ý đặc biệt..."
            ></textarea>
          </div>

          <!-- Error Display -->
          <div v-if="Object.keys(errors).length > 0" class="bg-red-50 border border-red-200 rounded-lg p-4">
            <h4 class="font-medium text-red-800 mb-2">Vui lòng kiểm tra lại:</h4>
            <ul class="text-sm text-red-700 space-y-1">
              <li v-for="(error, field) in errors" :key="field">
                • {{ error[0] }}
              </li>
            </ul>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex justify-end space-x-3 mt-8 pt-6 border-t">
          <button 
            type="button"
            @click="$emit('close')"
            class="px-6 py-2 text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
            :disabled="loading"
          >
            Hủy bỏ
          </button>
          <button 
            type="submit"
            :disabled="loading"
            class="px-6 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors flex items-center space-x-2"
          >
            <div v-if="loading" class="animate-spin rounded-full h-4 w-4 border-b-2 border-white"></div>
            <span>{{ loading ? 'Đang lưu...' : (isEdit ? 'Cập nhật' : 'Tạo kho mới') }}</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script>
import warehouseApi from '../api/warehouseApi.js'

export default {
  name: 'WarehouseForm',
  props: {
    warehouse: {
      type: Object,
      default: null
    }
  },
  emits: ['close', 'saved'],
  data() {
    return {
      loading: false,
      errors: {},
      form: {
        code: '',
        name: '',
        address: '',
        manager_name: '',
        phone: '',
        email: '',
        capacity: '',
        status: 'active',
        note: ''
      }
    }
  },
  computed: {
    isEdit() {
      return !!this.warehouse
    }
  },
  created() {
    if (this.warehouse) {
      this.fillForm()
    }
  },
  methods: {
    fillForm() {
      this.form = {
        code: this.warehouse.code || '',
        name: this.warehouse.name || '',
        address: this.warehouse.address || '',
        manager_name: this.warehouse.manager_name || '',
        phone: this.warehouse.phone || '',
        email: this.warehouse.email || '',
        capacity: this.warehouse.capacity || '',
        status: this.warehouse.status || 'active',
        note: this.warehouse.note || ''
      }
    },

    async handleSubmit() {
      this.loading = true
      this.errors = {}

      try {
        let response

        if (this.isEdit) {
          response = await warehouseApi.updateWarehouse(this.warehouse.id, this.form)
        } else {
          response = await warehouseApi.createWarehouse(this.form)
        }

        if (response.success) {
          this.$emit('saved', response.data)
        } else {
          this.errors = response.errors || {}
        }
      } catch (error) {
        console.error('Error saving warehouse:', error)
        
        if (error.type === 'validation') {
          this.errors = error.errors || {}
        } else {
          // Show general error
          this.errors = {
            general: ['Có lỗi xảy ra khi lưu dữ liệu. Vui lòng thử lại.']
          }
        }
      } finally {
        this.loading = false
      }
    },

    formatCurrency(amount) {
      if (!amount) return '0 ₫'
      return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND'
      }).format(amount)
    }
  }
}
</script>

<style scoped>
.animate-spin {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

.transition-colors {
  transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out;
}

/* Custom scrollbar for modal */
.max-h-screen {
  max-height: 90vh;
}

/* Focus states */
input:focus, select:focus, textarea:focus {
  outline: none;
}

/* Error states */
.border-red-500:focus {
  ring-color: rgb(239 68 68);
  border-color: rgb(239 68 68);
}
</style>