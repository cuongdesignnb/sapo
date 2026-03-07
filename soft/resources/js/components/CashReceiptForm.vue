<template>
  <div class="bg-white rounded-lg shadow">
    <!-- Header -->
    <div class="px-6 py-4 border-b">
      <h3 class="text-lg font-medium text-gray-900">
        {{ isEdit ? 'Sửa phiếu thu' : 'Thêm phiếu thu' }}
      </h3>
    </div>

    <!-- Form -->
    <form @submit.prevent="handleSubmit" class="p-6">
      <div class="grid grid-cols-2 gap-6">
        <!-- Left Column -->
        <div class="space-y-4">
          <h4 class="font-medium text-gray-900">Thông tin chung</h4>
          
          <!-- Receipt Type -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Loại phiếu thu <span class="text-red-500">*</span>
            </label>
            <div class="flex space-x-2">
              <select 
                v-model="form.type_id" 
                @change="onReceiptTypeChange"
                class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                required
              >
                <option value="">Chọn loại phiếu thu</option>
                <option 
                  v-for="type in localReceiptTypes" 
                  :key="type.id" 
                  :value="type.id"
                >
                  {{ type.name }}
                </option>
              </select>
              <button 
                type="button"
                @click="showQuickAddType = !showQuickAddType"
                class="px-3 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-lg font-bold flex-shrink-0"
                title="Thêm loại phiếu thu mới"
              >
                +
              </button>
            </div>
            <!-- Quick Add Type Form -->
            <div v-if="showQuickAddType" class="mt-2 p-3 bg-blue-50 border border-blue-200 rounded-md">
              <div class="grid grid-cols-2 gap-2 mb-2">
                <input v-model="newType.code" type="text" placeholder="Mã (VD: THU_NO_KH)" class="px-2 py-1 border border-gray-300 rounded text-sm" />
                <input v-model="newType.name" type="text" placeholder="Tên loại phiếu" class="px-2 py-1 border border-gray-300 rounded text-sm" />
                <select v-model="newType.recipient_type" class="px-2 py-1 border border-gray-300 rounded text-sm">
                  <option value="">Nhóm đối tượng</option>
                  <option value="customer">Khách hàng</option>
                  <option value="supplier">Nhà cung cấp</option>
                </select>
                <select v-model="newType.impact_type" class="px-2 py-1 border border-gray-300 rounded text-sm">
                  <option value="">Loại tác động</option>
                  <option value="debt">Công nợ</option>
                  <option value="revenue">Doanh thu</option>
                  <option value="advance">Tạm ứng</option>
                </select>
                <select v-model="newType.impact_action" class="px-2 py-1 border border-gray-300 rounded text-sm">
                  <option value="">Hành động</option>
                  <option value="increase">Tăng</option>
                  <option value="decrease">Giảm</option>
                </select>
                <input v-model="newType.description" type="text" placeholder="Mô tả (tùy chọn)" class="px-2 py-1 border border-gray-300 rounded text-sm" />
              </div>
              <div class="flex space-x-2">
                <button type="button" @click="quickCreateType" :disabled="creatingType" class="px-3 py-1 bg-blue-600 text-white rounded text-sm hover:bg-blue-700 disabled:opacity-50">
                  {{ creatingType ? 'Đang tạo...' : 'Tạo loại phiếu' }}
                </button>
                <button type="button" @click="showQuickAddType = false" class="px-3 py-1 border border-gray-300 rounded text-sm hover:bg-gray-50">
                  Đóng
                </button>
              </div>
            </div>
          </div>

          <!-- Recipient Type -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Nhóm người nộp <span class="text-red-500">*</span>
            </label>
            <select 
              v-model="form.recipient_type" 
              @change="onRecipientTypeChange"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              required
            >
              <option value="">Chọn nhóm người nộp</option>
              <option value="customer">Khách hàng</option>
              <option value="supplier">Nhà cung cấp</option>
            </select>
          </div>

          <!-- Recipient -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Tên người nộp <span class="text-red-500">*</span>
            </label>
            <select 
              v-model="form.recipient_id"
              @change="onRecipientChange"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              required
              :disabled="!form.recipient_type"
            >
              <option value="">
                {{ form.recipient_type === 'customer' ? 'Chọn khách hàng' : form.recipient_type === 'supplier' ? 'Chọn nhà cung cấp' : 'Chọn nhóm trước' }}
              </option>
              <option 
                v-for="recipient in recipients" 
                :key="recipient.id" 
                :value="recipient.id"
              >{{ recipient.name }}{{ recipient.phone ? ' - ' + recipient.phone : '' }}</option>
            </select>
            
            <!-- Recipient Info -->
            <div v-if="selectedRecipient" class="mt-2 text-sm text-gray-600">
              <div v-if="selectedRecipient.phone">📞 {{ selectedRecipient.phone }}</div>
              <div v-if="selectedRecipient.total_debt && selectedRecipient.total_debt > 0" class="text-orange-600">
                💰 Công nợ: {{ formatCurrency(selectedRecipient.total_debt) }}
              </div>
            </div>
          </div>

          <!-- Amount -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Số tiền <span class="text-red-500">*</span>
            </label>
            <input
              v-model="form.amount"
              type="number"
              step="1000"
              min="0"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="Nhập số tiền"
              required
            />
          </div>

          <!-- Payment Method -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Hình thức thanh toán <span class="text-red-500">*</span>
            </label>
            <select 
              v-model="form.payment_method"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              required
            >
              <option value="cash">Tiền mặt</option>
              <option value="transfer">Chuyển khoản</option>
            </select>
          </div>

          <!-- Status -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Trạng thái <span class="text-red-500">*</span>
            </label>
            <select 
              v-model="form.status"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              required
            >
              <option value="draft">Nháp</option>
              <option value="pending">Chờ duyệt</option>
              <option value="approved">Đã duyệt</option>
              <option value="cancelled">Đã hủy</option>
            </select>
          </div>
        </div>

        <!-- Right Column -->
        <div class="space-y-4">
          <h4 class="font-medium text-gray-900">Thông tin bổ sung</h4>
          
          <!-- Warehouse -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Chi nhánh <span class="text-red-500">*</span>
            </label>
            <select 
              v-model="form.warehouse_id"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              required
            >
              <option value="">Chọn chi nhánh</option>
              <option 
                v-for="warehouse in warehouses" 
                :key="warehouse.id" 
                :value="warehouse.id"
              >
                {{ warehouse.name }}
              </option>
            </select>
          </div>

          <!-- Receipt Date -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Ngày ghi nhận <span class="text-red-500">*</span>
            </label>
            <input
              v-model="form.receipt_date"
              type="date"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              required
            />
          </div>

          <!-- Reference Number -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Mã tham chiếu</label>
            <input
              v-model="form.reference_number"
              type="text"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="Nhập mã tham chiếu (tùy chọn)"
            />
          </div>

          <!-- Note -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Ghi chú</label>
            <textarea
              v-model="form.note"
              rows="3"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="Nhập ghi chú"
            ></textarea>
          </div>

          <!-- Impact Preview -->
          <div v-if="selectedReceiptType" class="bg-blue-50 border border-blue-200 rounded-md p-3">
            <h5 class="font-medium text-blue-900 mb-2">Tác động dự kiến:</h5>
            <p class="text-sm text-blue-800">
              {{ getImpactPreview() }}
            </p>
          </div>
        </div>
      </div>

      <!-- Actions -->
      <div class="flex justify-end space-x-3 mt-6 pt-4 border-t">
        <button
          type="button"
          @click="$emit('cancel')"
          class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
        >
          Hủy
        </button>
        <button
          type="submit"
          :disabled="loading"
          class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50"
        >
          {{ loading ? 'Đang xử lý...' : (isEdit ? 'Cập nhật' : 'Tạo phiếu') }}
        </button>
      </div>
    </form>
  </div>
</template>

<script>
import { cashReceiptApi } from '../api/cashReceiptApi'

export default {
  name: 'CashReceiptForm',
  props: {
    receipt: {
      type: Object,
      default: null
    },
    receiptTypes: {
      type: Array,
      default: () => []
    },
    warehouses: {
      type: Array,
      default: () => []
    }
  },
  emits: ['submit', 'cancel', 'types-changed'],
  data() {
    return {
      loading: false,
      recipients: [],
      showQuickAddType: false,
      creatingType: false,
      localReceiptTypes: [],
      newType: {
        code: '',
        name: '',
        recipient_type: '',
        impact_type: '',
        impact_action: '',
        description: ''
      },
      form: {
        type_id: '',
        recipient_type: '',
        recipient_id: '',
        warehouse_id: '',
        amount: '',
        note: '',
        payment_method: 'cash',
        status: 'draft', // Add default status
        reference_number: '',
        receipt_date: this.getTodayDate()
      }
    }
  },
  computed: {
    isEdit() {
      return !!this.receipt && !!this.receipt.id
    },
    selectedReceiptType() {
      if (!this.form.type_id || !this.localReceiptTypes) return null
      return this.localReceiptTypes.find(type => type && type.id == this.form.type_id) || null
    },
    selectedRecipient() {
      if (!this.form.recipient_id || !this.recipients) return null
      return this.recipients.find(recipient => recipient && recipient.id == this.form.recipient_id) || null
    }
  },
  watch: {
    receipt: {
      immediate: true,
      handler(receipt) {
        if (receipt && receipt.id) {
          this.populateForm(receipt)
        } else {
          this.resetForm()
        }
      }
    },
    receiptTypes: {
      immediate: true,
      handler(val) {
        this.localReceiptTypes = val || []
      }
    }
  },
  mounted() {
    this.localReceiptTypes = this.receiptTypes || []
    // Initialize form if editing
    if (this.receipt && this.receipt.id) {
      this.populateForm(this.receipt)
    }
  },
  methods: {
    getTodayDate() {
      return new Date().toISOString().split('T')[0]
    },

    populateForm(receipt) {
      this.form = {
        type_id: receipt.type_id || '',
        recipient_type: receipt.recipient_type || '',
        recipient_id: receipt.recipient_id || '',
        warehouse_id: receipt.warehouse_id || '',
        amount: receipt.amount || '',
        note: receipt.note || '',
        payment_method: receipt.payment_method || 'cash',
        status: receipt.status || 'draft', // Add status
        reference_number: receipt.reference_number || '',
        receipt_date: receipt.receipt_date || this.getTodayDate()
      }
      
      // Load recipients if recipient_type is set
      if (this.form.recipient_type) {
        this.loadRecipients()
      }
    },

    resetForm() {
      this.form = {
        type_id: '',
        recipient_type: '',
        recipient_id: '',
        warehouse_id: '',
        amount: '',
        note: '',
        payment_method: 'cash',
        status: 'draft', // Add default status
        reference_number: '',
        receipt_date: this.getTodayDate()
      }
      this.recipients = []
    },

    async onReceiptTypeChange() {
      const type = this.selectedReceiptType
      if (type && type.recipient_type) {
        this.form.recipient_type = type.recipient_type
        await this.loadRecipients()
      } else {
        this.form.recipient_type = ''
        this.form.recipient_id = ''
        this.recipients = []
      }
    },

    async onRecipientTypeChange() {
      this.form.recipient_id = ''
      this.recipients = []
      if (this.form.recipient_type) {
        await this.loadRecipients()
      }
    },

    onRecipientChange() {
      // Additional validation or logic can be added here
      console.log('Recipient changed:', this.selectedRecipient)
    },

    async loadRecipients() {
      if (!this.form.recipient_type) {
        this.recipients = []
        return
      }

      try {
        console.log('Loading recipients for type:', this.form.recipient_type)
        
        const data = await cashReceiptApi.getRecipients(this.form.recipient_type)
        
        if (data && data.success && data.data) {
          this.recipients = data.data
          console.log('Recipients loaded:', this.recipients.length)
        } else {
          this.recipients = []
          console.warn('No recipients data received')
        }
      } catch (error) {
        console.error('Error loading recipients:', error)
        this.recipients = []
        this.$emit('error', 'Lỗi khi tải danh sách đối tượng: ' + (error.message || 'Unknown error'))
      }
    },

    getImpactPreview() {
      const type = this.selectedReceiptType
      if (!type) return ''

      const actions = {
        increase: 'tăng',
        decrease: 'giảm'
      }

      const impacts = {
        debt: 'công nợ',
        revenue: 'doanh thu',
        advance: 'tạm ứng'
      }

      const targets = {
        customer: 'khách hàng',
        supplier: 'nhà cung cấp'
      }

      const action = actions[type.impact_action] || type.impact_action
      const impact = impacts[type.impact_type] || type.impact_type
      const target = targets[type.recipient_type] || type.recipient_type

      return `${type.name} sẽ ${action} ${impact} của ${target}`
    },

    formatCurrency(amount) {
      if (!amount || isNaN(amount)) return '0 ₫'
      return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND'
      }).format(amount)
    },

    validateForm() {
      const errors = []
      
      if (!this.form.type_id) errors.push('Vui lòng chọn loại phiếu thu')
      if (!this.form.recipient_type) errors.push('Vui lòng chọn nhóm người nộp')
      if (!this.form.recipient_id) errors.push('Vui lòng chọn người nộp')
      if (!this.form.warehouse_id) errors.push('Vui lòng chọn chi nhánh')
      if (!this.form.amount || this.form.amount <= 0) errors.push('Vui lòng nhập số tiền hợp lệ')
      if (!this.form.payment_method) errors.push('Vui lòng chọn hình thức thanh toán')
      if (!this.form.receipt_date) errors.push('Vui lòng chọn ngày ghi nhận')

      return errors
    },

    async handleSubmit() {
      const errors = this.validateForm()
      if (errors.length > 0) {
        this.$emit('error', errors.join(', '))
        return
      }

      this.loading = true
      try {
        await this.$emit('submit', { ...this.form })
      } catch (error) {
        console.error('Submit error:', error)
        this.$emit('error', error.message || 'Có lỗi xảy ra khi xử lý')
      } finally {
        this.loading = false
      }
    },

    async quickCreateType() {
      if (!this.newType.code || !this.newType.name || !this.newType.recipient_type || !this.newType.impact_type || !this.newType.impact_action) {
        alert('Vui lòng điền đầy đủ: Mã, Tên, Nhóm đối tượng, Loại tác động, Hành động')
        return
      }
      this.creatingType = true
      try {
        const data = await cashReceiptApi.createReceiptType(this.newType)
        if (data && data.success) {
          // Add new type to local list and select it
          this.localReceiptTypes = [...this.localReceiptTypes, data.data]
          this.form.type_id = data.data.id
          this.onReceiptTypeChange()
          this.showQuickAddType = false
          this.newType = { code: '', name: '', recipient_type: '', impact_type: '', impact_action: '', description: '' }
          this.$emit('types-changed')
        } else {
          alert(data?.message || 'Lỗi khi tạo loại phiếu')
        }
      } catch (error) {
        console.error('Error creating receipt type:', error)
        const errMsg = error?.errors ? Object.values(error.errors).flat().join(', ') : (error?.message || 'Lỗi khi tạo loại phiếu')
        alert(errMsg)
      } finally {
        this.creatingType = false
      }
    }
  }
}
</script>