<template>
  <!-- Modal Overlay -->
  <div class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-start justify-center pt-4">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-6xl mx-4 max-h-screen overflow-y-auto">
      
      <!-- Header -->
      <div class="flex items-center justify-between p-6 border-b">
        <div class="flex items-center space-x-4">
          <button @click="$emit('close')" class="text-gray-500 hover:text-gray-700">
            ← Quay lại danh sách nhà cung cấp
          </button>
        </div>
        <div class="text-center">
          <h2 class="text-xl font-semibold text-red-500">CHI TIẾT NHÀ CUNG CẤP</h2>
        </div>
        <div class="flex items-center space-x-3">
          <button @click="$emit('close')" class="px-4 py-2 border border-gray-300 rounded text-gray-600 hover:bg-gray-50">
            Thoát
          </button>
          <button @click="$emit('delete', supplier.id)" class="px-4 py-2 border border-red-300 rounded text-red-600 hover:bg-red-50">
            Xoá
          </button>
          <button @click="editSupplier" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">
            Sửa nhà cung cấp
          </button>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="flex justify-center items-center py-12">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
        <span class="ml-2 text-gray-600">Đang tải dữ liệu...</span>
      </div>

      <!-- Content -->
      <div v-else-if="supplier" class="p-6">
        <!-- Tabs -->
        <div class="flex space-x-8 border-b mb-6">
          <button 
            :class="['pb-3', activeTab === 'info' ? 'border-b-2 border-blue-500 text-blue-500' : 'text-gray-600']"
            @click="activeTab = 'info'"
          >
            Thông tin nhà cung cấp
          </button>
          <button 
            :class="['pb-3', activeTab === 'purchase-history' ? 'border-b-2 border-blue-500 text-blue-500' : 'text-gray-600']"
            @click="activeTab = 'purchase-history'"
          >
            Lịch sử nhập hàng
          </button>
          <button 
            :class="['pb-3', activeTab === 'debts' ? 'border-b-2 border-blue-500 text-blue-500' : 'text-gray-600']"
            @click="activeTab = 'debts'"
          >
            Công nợ
          </button>
          <button 
            :class="['pb-3', activeTab === 'contacts' ? 'border-b-2 border-blue-500 text-blue-500' : 'text-gray-600']"
            @click="activeTab = 'contacts'"
          >
            Liên hệ
          </button>
        </div>

        <!-- Tab Content -->
        <div v-if="activeTab === 'info'" class="grid grid-cols-1 lg:grid-cols-3 gap-8">
          
          <!-- Left Column - Supplier Info -->
          <div class="lg:col-span-2 space-y-6">
            <!-- Basic Info -->
            <div class="grid grid-cols-2 gap-x-8 gap-y-4 text-sm">
              <div class="flex">
                <span class="w-32 text-gray-600">Mã nhà cung cấp</span>
                <span class="text-gray-900">: {{ supplier.code }}</span>
              </div>
              <div class="flex">
                <span class="w-32 text-gray-600">Nhóm</span>
                <span class="text-gray-900">: {{ supplier.group?.name || 'N/A' }}</span>
              </div>
              
              <div class="flex">
                <span class="w-32 text-gray-600">Email</span>
                <span class="text-gray-900">: {{ supplier.email || 'N/A' }}</span>
              </div>
              <div class="flex">
                <span class="w-32 text-gray-600">Số điện thoại</span>
                <span class="text-gray-900">: {{ supplier.formatted_phone || 'N/A' }}</span>
              </div>
              
              <div class="flex">
                <span class="w-32 text-gray-600">Website</span>
                <span class="text-gray-900">: 
                  <a v-if="supplier.website" :href="supplier.website" target="_blank" class="text-blue-500 hover:underline">
                    {{ supplier.website }}
                  </a>
                  <span v-else>N/A</span>
                </span>
              </div>
              <div class="flex">
                <span class="w-32 text-gray-600">Mã số thuế</span>
                <span class="text-gray-900">: {{ supplier.tax_code || 'N/A' }}</span>
              </div>
              
              <div class="flex">
                <span class="w-32 text-gray-600">Người phụ trách</span>
                <span class="text-gray-900">: {{ supplier.person_in_charge || 'N/A' }}</span>
              </div>
              <div class="flex">
                <span class="w-32 text-gray-600">Trạng thái</span>
                <span :class="['inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium', getStatusClass(supplier.status)]">
                  {{ supplier.status_text }}
                </span>
              </div>
              
              <div class="flex">
                <span class="w-32 text-gray-600">Ngày tạo</span>
                <span class="text-gray-900">: {{ supplier.created_at }}</span>
              </div>
              <div class="flex">
                <span class="w-32 text-gray-600">Cập nhật cuối</span>
                <span class="text-gray-900">: {{ supplier.updated_at }}</span>
              </div>
            </div>

            <!-- Address -->
            <div class="border-t pt-6">
              <h3 class="text-lg font-semibold mb-4">Địa chỉ</h3>
              <div class="text-sm text-gray-900">
                {{ supplier.address || 'Chưa có thông tin địa chỉ' }}
              </div>
            </div>

            <!-- Financial Info -->
            <div class="border-t pt-6">
              <h3 class="text-lg font-semibold mb-4">Thông tin tài chính</h3>
              <div class="grid grid-cols-2 gap-x-8 gap-y-4 text-sm">
                <div class="flex">
                  <span class="w-32 text-gray-600">Công nợ hiện tại</span>
                  <span :class="['font-medium', supplier.total_debt > 0 ? 'text-red-600' : 'text-gray-900']">
                    : {{ formatCurrency(supplier.total_debt) }}
                  </span>
                </div>
                <div class="flex">
                  <span class="w-32 text-gray-600">Hạn mức tín dụng</span>
                  <span class="text-gray-900">: {{ formatCurrency(supplier.credit_limit) }}</span>
                </div>
                
                <div class="flex">
                  <span class="w-32 text-gray-600">Số ngày thanh toán</span>
                  <span class="text-gray-900">: {{ supplier.payment_terms || 0 }} ngày</span>
                </div>
                <div class="flex">
                  <span class="w-32 text-gray-600">Tài khoản ngân hàng</span>
                  <span class="text-gray-900">: {{ supplier.bank_account || 'N/A' }}</span>
                </div>
                
                <div class="flex">
                  <span class="w-32 text-gray-600">Ngân hàng</span>
                  <span class="text-gray-900">: {{ supplier.bank_name || 'N/A' }}</span>
                </div>
                <div class="flex">
                  <span class="w-32 text-gray-600">Hạn mức còn lại</span>
                  <span class="text-gray-900">: {{ formatCurrency(supplier.credit_remaining) }}</span>
                </div>
              </div>
            </div>

            <!-- Note -->
            <div class="border-t pt-6">
              <h3 class="text-lg font-semibold mb-4">Ghi chú</h3>
              <div class="text-sm text-gray-900">
                {{ supplier.note || 'Không có ghi chú' }}
              </div>
            </div>
          </div>

          <!-- Right Column - Stats & Actions -->
          <div class="space-y-6">
            <!-- Quick Stats -->
            <div class="border rounded-lg p-6">
              <h4 class="font-semibold mb-4">Thống kê</h4>
              <div class="space-y-4">
                <div class="flex justify-between">
                  <span class="text-gray-600">Đơn nhập hàng</span>
                  <span class="font-medium">{{ supplier.purchase_orders_count }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-600">Tổng giá trị</span>
                  <span class="font-medium">{{ formatCurrency(supplier.total_purchase_amount) }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-600">Số liên hệ</span>
                  <span class="font-medium">{{ supplier.contacts_count }}</span>
                </div>
              </div>
            </div>

            <!-- Credit Limit Progress -->
            <div v-if="supplier.credit_limit > 0" class="border rounded-lg p-6">
              <h4 class="font-semibold mb-4">Hạn mức tín dụng</h4>
              <div class="space-y-2">
                <div class="flex justify-between text-sm">
                  <span>Đã sử dụng</span>
                  <span>{{ formatCurrency(supplier.total_debt) }}</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                  <div 
                    class="h-2 rounded-full"
                    :class="supplier.is_over_credit_limit ? 'bg-red-500' : 'bg-blue-500'"
                    :style="{ width: Math.min(100, (supplier.total_debt / supplier.credit_limit) * 100) + '%' }"
                  ></div>
                </div>
                <div class="flex justify-between text-sm text-gray-600">
                  <span>Hạn mức: {{ formatCurrency(supplier.credit_limit) }}</span>
                  <span v-if="supplier.is_over_credit_limit" class="text-red-500">Vượt hạn mức</span>
                </div>
              </div>
            </div>

            <!-- Quick Actions -->
            <div class="border rounded-lg p-6">
              <h4 class="font-semibold mb-4">Thao tác nhanh</h4>
              <div class="space-y-3">
                <button 
                  @click="showAddDebtModal = true"
                  class="w-full px-4 py-2 text-left border rounded hover:bg-gray-50"
                >
                  💰 Ghi nhận công nợ
                </button>
                <button class="w-full px-4 py-2 text-left border rounded hover:bg-gray-50">
                  📋 Tạo đơn nhập hàng
                </button>
                <button class="w-full px-4 py-2 text-left border rounded hover:bg-gray-50">
                  📊 Xem báo cáo chi tiết
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Purchase History Tab - NEW -->
        <div v-else-if="activeTab === 'purchase-history'">
          <SupplierPurchaseHistory 
            :supplier-id="supplier.id"
            @view-detail="handleViewDetail"
          />
        </div>

        <!-- Debts Tab - NEW -->
        <div v-else-if="activeTab === 'debts'">
          <SupplierDebtHistory 
            :supplier-id="supplier.id"
            :supplier="supplier"
          />
        </div>

        <!-- Contacts Tab -->
        <div v-else-if="activeTab === 'contacts'">
          <div v-if="supplier.contacts?.length > 0" class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div v-for="contact in supplier.contacts" :key="contact.id" class="border rounded-lg p-4">
              <div class="flex justify-between items-start mb-2">
                <h4 class="font-semibold">{{ contact.name }}</h4>
                <span v-if="contact.is_primary" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                  Chính
                </span>
              </div>
              <div class="space-y-1 text-sm text-gray-600">
                <div v-if="contact.position">{{ contact.position }}</div>
                <div v-if="contact.department">{{ contact.department }}</div>
                <div v-if="contact.phone" class="flex items-center">
                  <span class="mr-2">📞</span>
                  {{ contact.phone }}
                </div>
                <div v-if="contact.email" class="flex items-center">
                  <span class="mr-2">✉️</span>
                  {{ contact.email }}
                </div>
              </div>
            </div>
          </div>
          <div v-else class="text-center py-12 text-gray-500">
            <p>Chưa có thông tin liên hệ</p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Add Debt Modal -->
  <div v-if="showAddDebtModal" class="fixed inset-0 bg-black bg-opacity-50 z-[9999] flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
      <div class="p-6">
        <h3 class="text-lg font-semibold mb-4">Ghi nhận công nợ</h3>
        <form @submit.prevent="addDebt">
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Loại giao dịch</label>
              <select v-model="debtForm.type" class="w-full border border-gray-300 rounded-md px-3 py-2">
                <option value="purchase">Mua hàng (tăng nợ)</option>
                <option value="payment">Thanh toán (giảm nợ)</option>
                <option value="adjustment">Điều chỉnh</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Số tiền</label>
              <input 
                v-model="debtForm.amount" 
                type="number" 
                class="w-full border border-gray-300 rounded-md px-3 py-2" 
                step="1000"
                required
              >
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Mã tham chiếu</label>
              <input 
                v-model="debtForm.ref_code" 
                type="text" 
                class="w-full border border-gray-300 rounded-md px-3 py-2"
              >
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Ghi chú</label>
              <textarea 
                v-model="debtForm.note" 
                class="w-full border border-gray-300 rounded-md px-3 py-2" 
                rows="3"
              ></textarea>
            </div>
          </div>
          <div class="flex justify-end space-x-3 mt-6">
            <button 
              type="button" 
              @click="showAddDebtModal = false"
              class="px-4 py-2 border border-gray-300 rounded text-gray-700 hover:bg-gray-50"
            >
              Hủy
            </button>
            <button 
              type="submit" 
              :disabled="addingDebt"
              class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 disabled:opacity-50"
            >
              {{ addingDebt ? 'Đang lưu...' : 'Ghi nhận' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, onMounted, watch } from 'vue'
import supplierApi from '../api/supplierApi.js'
import SupplierPurchaseHistory from '../components/SupplierPurchaseHistory.vue'
import SupplierDebtHistory from '../components/SupplierDebtHistory.vue'

export default {
  name: 'SupplierDetail',
  components: {
    SupplierPurchaseHistory,
    SupplierDebtHistory
  },
  props: {
    supplier: {
      type: Object,
      default: null
    },
    supplierId: {
      type: [String, Number],
      default: null
    }
  },
  emits: ['close', 'edit', 'delete'],
  setup(props, { emit }) {
    const loading = ref(false)
    const activeTab = ref('info')
    const showAddDebtModal = ref(false)
    const addingDebt = ref(false)

    const debtForm = ref({
      type: 'purchase',
      amount: '',
      ref_code: '',
      note: ''
    })

    const editSupplier = () => {
      emit('edit', props.supplier)
    }

    const loadSupplierDetail = async () => {
      if (!props.supplierId) return
      
      try {
        loading.value = true
        const response = await supplierApi.getSupplier(props.supplierId)
        if (response.data.success) {
          // Update supplier data
          Object.assign(props.supplier, response.data.data)
        }
      } catch (error) {
        console.error('Error loading supplier detail:', error)
      } finally {
        loading.value = false
      }
    }

    const handleViewDetail = (item) => {
      // Handle view detail for purchase history items
      console.log('View detail for:', item)
      // You can implement navigation to detail pages here
    }

    const addDebt = async () => {
      try {
        addingDebt.value = true
        const amount = debtForm.value.type === 'payment' 
          ? -Math.abs(debtForm.value.amount)
          : Math.abs(debtForm.value.amount)
        
        await supplierApi.addDebt(props.supplier.id, {
          ...debtForm.value,
          amount: amount
        })
        
        showAddDebtModal.value = false
        
        // ✅ RELOAD SUPPLIER DATA
        await loadSupplierDetail()
        
        // Reset form
        debtForm.value = {
          type: 'purchase',
          amount: '',
          ref_code: '',
          note: ''
        }
        
      } catch (error) {
        console.error('Error adding debt:', error)
      } finally {
        addingDebt.value = false
      }
    }

    const formatCurrency = (amount) => {
      return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND'
      }).format(amount || 0)
    }

    const getStatusClass = (status) => {
      const classes = {
        'active': 'bg-green-100 text-green-800',
        'inactive': 'bg-gray-100 text-gray-800',
        'suspended': 'bg-red-100 text-red-800'
      }
      return classes[status] || 'bg-gray-100 text-gray-800'
    }

    const getDebtTypeClass = (type) => {
      const classes = {
        'purchase': 'bg-red-100 text-red-800',
        'payment': 'bg-green-100 text-green-800',
        'adjustment': 'bg-yellow-100 text-yellow-800'
      }
      return classes[type] || 'bg-gray-100 text-gray-800'
    }

    const getDebtTypeText = (type) => {
      const texts = {
        'purchase': 'Mua hàng',
        'payment': 'Thanh toán',
        'adjustment': 'Điều chỉnh'
      }
      return texts[type] || 'Không xác định'
    }

    return {
      loading,
      activeTab,
      showAddDebtModal,
      addingDebt,
      debtForm,
      loadSupplierDetail,
      editSupplier,
      handleViewDetail,
      addDebt,
      formatCurrency,
      getStatusClass,
      getDebtTypeClass,
      getDebtTypeText
    }
  }
}
</script>