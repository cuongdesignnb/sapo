<template>
  <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white max-w-7xl w-full mx-4 max-h-[90vh] overflow-y-auto rounded-lg shadow-xl">
    <!-- Header -->
    <div class="p-6 border-b border-gray-200">
      <div class="flex items-center justify-between">
        <div class="flex items-center space-x-4">
          <button @click="goBack" class="text-gray-500 hover:text-gray-700">
            <i class="fas fa-arrow-left mr-2"></i>
            Quay lại danh sách khách hàng
          </button>
        </div>
        <div class="flex items-center space-x-2">
          <button @click="showDeleteModal = true" class="px-4 py-2 text-sm text-red-600 bg-red-50 rounded-md hover:bg-red-100">
            Xóa khách hàng
          </button>
          <button @click="showEditModal = true" class="px-4 py-2 text-sm text-white bg-blue-600 rounded-md hover:bg-blue-700">
            Tạo phiếu thu/chi
          </button>
        </div>
      </div>
    </div>

    <!-- Customer Info -->
    <div v-if="loading" class="p-6 animate-pulse">
      <div class="h-8 bg-gray-200 rounded mb-4"></div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="h-32 bg-gray-200 rounded"></div>
        <div class="h-32 bg-gray-200 rounded"></div>
      </div>
    </div>

    <div v-else-if="customerData" class="p-6">
      <h1 class="text-2xl font-bold text-gray-900 mb-6">{{ customerData.name }}</h1>
      
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Left Column - Basic Info -->
        <div class="space-y-6">
          <!-- Personal Information -->
          <div class="bg-gray-50 rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">
              Thông tin cá nhân
              <button @click="editPersonalInfo" class="ml-4 text-sm text-blue-600 hover:text-blue-800">
                Cập nhật
              </button>
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Ngày sinh</label>
                <p class="text-sm text-gray-900">{{ formatDate(customerData.birthday) || '---' }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Giới tính</label>
                <p class="text-sm text-gray-900">{{ getGenderText(customerData.gender) }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Số điện thoại</label>
                <p class="text-sm text-gray-900">{{ customerData.phone || '---' }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <p class="text-sm text-gray-900">{{ customerData.email || '---' }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nhóm khách hàng</label>
                <p class="text-sm text-gray-900">{{ customerData.group_name }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Mã khách hàng</label>
                <p class="text-sm text-gray-900">{{ customerData.code }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Mã số thuế</label>
                <p class="text-sm text-gray-900">{{ customerData.tax_code || '---' }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Website</label>
                <p class="text-sm text-gray-900">{{ customerData.website || '---' }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nhân viên phụ trách</label>
                <p class="text-sm text-gray-900">{{ customerData.person_in_charge || '---' }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Mô tả</label>
                <p class="text-sm text-gray-900">{{ customerData.note || '---' }}</p>
              </div>
            </div>
          </div>

          <!-- Purchase Info -->
          <div class="bg-gray-50 rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">
              Thông tin mua hàng
              <button @click="viewTransactions" class="ml-4 text-sm text-blue-600 hover:text-blue-800">
                Chi tiết
              </button>
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tổng chi tiêu</label>
                <p class="text-sm font-medium text-gray-900">{{ formatCurrency(customerData.total_spend) }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tổng SL đơn hàng</label>
                <p class="text-sm text-gray-900">{{ customerData.total_orders || 0 }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tổng SL sản phẩm đã mua</label>
                <p class="text-sm text-gray-900">{{ customerData.total_products || 0 }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tổng SL sản phẩm hoàn trả</label>
                <p class="text-sm text-gray-900">{{ customerData.total_returns || 0 }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Ngày cuối cùng mua hàng</label>
                <p class="text-sm text-gray-900">{{ customerData.last_order_date || '---' }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Công nợ hiện tại</label>
                <p class="text-sm font-medium text-gray-900">{{ formatCurrency(customerData.current_debt) }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Right Column - Additional Info -->
        <div class="space-y-6">
          <!-- Pricing Info -->
          <div class="bg-gray-50 rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">
              Thông tin giá mặc định
              <button @click="editPricing" class="ml-4 text-sm text-blue-600 hover:text-blue-800">
                Cập nhật
              </button>
            </h3>
            <div class="grid grid-cols-1 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Chiết khấu khách hàng</label>
                <p class="text-sm text-gray-900">{{ customerData.discount_percent || 0 }}%</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Hình thức thanh toán mặc định</label>
                <p class="text-sm text-gray-900">{{ customerData.payment_method || '---' }}</p>
              </div>
            </div>
          </div>

          <!-- Credit Info -->
          <div class="bg-gray-50 rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">
              Thông tin tích điểm
              <button @click="editCredit" class="ml-4 text-sm text-blue-600 hover:text-blue-800">
                Cập nhật
              </button>
            </h3>
            <div class="grid grid-cols-1 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Điểm hiện tại</label>
                <p class="text-sm font-medium text-gray-900">{{ customerData.points || 0 }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Hạng thẻ hiện tại</label>
                <p class="text-sm text-gray-900">{{ customerData.tier || '---' }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Ngày hết hạn thẻ</label>
                <p class="text-sm text-gray-900">{{ formatDate(customerData.tier_expiry) || '---' }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Giá trị còn lại để lên hạng</label>
                <p class="text-sm text-gray-900">{{ formatCurrency(customerData.next_tier_value) || 0 }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Tabs -->
    <div class="border-b border-gray-200">
      <nav class="flex space-x-8 px-6">
        <button 
          v-for="tab in tabs" 
          :key="tab.id"
          @click="activeTab = tab.id"
          :class="[
            'py-4 px-1 border-b-2 font-medium text-sm whitespace-nowrap',
            activeTab === tab.id 
              ? 'border-blue-500 text-blue-600' 
              : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
          ]"
        >
          {{ tab.name }}
        </button>
      </nav>
    </div>

    <!-- Tab Content -->
    <div class="p-6">
      <!-- Purchase History Tab -->
      <div v-if="activeTab === 'orders'" class="space-y-4">
        <h3 class="text-lg font-medium text-gray-900">Lịch sử mua hàng</h3>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mã đơn hàng</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Trạng thái</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Thanh toán</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Xuất kho</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Giá trị</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Chi nhánh</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nguồn đơn</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nhân viên xử lý đơn</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ngày ghi nhận</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-if="loadingOrders" class="animate-pulse">
                <td colspan="9" class="px-6 py-4 text-center text-gray-500">
                  <i class="fas fa-spinner fa-spin mr-2"></i>
                  Đang tải...
                </td>
              </tr>
              <tr v-else-if="orders.length === 0">
                <td colspan="9" class="px-6 py-4 text-center text-gray-500">
                  Chưa có đơn hàng nào
                </td>
              </tr>
              <tr v-else v-for="order in orders" :key="order.id" class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap">
                  <button @click="viewOrder(order.id)" class="text-blue-600 hover:text-blue-900 font-medium">
                    {{ order.code }}
                  </button>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span :class="getStatusClass(order.status)">
                    {{ getStatusText(order.status) }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="w-2 h-2 rounded-full inline-block mr-2" :class="order.paid ? 'bg-green-500' : 'bg-red-500'"></span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="w-2 h-2 rounded-full inline-block mr-2" :class="order.shipped ? 'bg-green-500' : 'bg-red-500'"></span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap font-medium">
                  {{ formatCurrency(order.total) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ order.branch || 'Cửa hàng' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ order.source || 'Web' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ order.created_by || 'Cao Đức Bình' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ formatDate(order.created_at) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        
        <div class="flex justify-center mt-4">
          <button 
            @click="loadMoreOrders"
            v-if="hasMoreOrders"
            class="px-4 py-2 text-sm text-blue-600 hover:text-blue-800"
          >
            Hiển thị thêm
          </button>
        </div>
      </div>

      <!-- Debt History Tab -->
      <div v-if="activeTab === 'debts'" class="space-y-4">
        <h3 class="text-lg font-medium text-gray-900">Công nợ</h3>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mã tham chiếu</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Số tiền</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tổng nợ</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ghi chú</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ngày ghi nhận</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-if="loadingDebts" class="animate-pulse">
                <td colspan="5" class="px-6 py-4 text-center text-gray-500">
                  <i class="fas fa-spinner fa-spin mr-2"></i>
                  Đang tải...
                </td>
              </tr>
              <tr v-else-if="debts.length === 0">
                <td colspan="5" class="px-6 py-4 text-center text-gray-500">
                  Chưa có công nợ nào
                </td>
              </tr>
              <tr v-else v-for="debt in debts" :key="debt.id" class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap font-medium">
                  {{ debt.ref_code }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span :class="debt.amount > 0 ? 'text-red-600' : 'text-green-600'">
                    {{ formatCurrency(debt.amount) }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap font-medium">
                  {{ formatCurrency(debt.debt_total) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ debt.note || '---' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ formatDate(debt.recorded_at) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Addresses Tab -->
      <div v-if="activeTab === 'addresses'" class="space-y-4">
        <div class="flex items-center justify-between">
          <h3 class="text-lg font-medium text-gray-900">Địa chỉ</h3>
          <button @click="addAddress" class="px-4 py-2 text-sm text-blue-600 hover:text-blue-800">
            Thêm địa chỉ mới
          </button>
        </div>
        
        <div v-if="addresses.length === 0" class="text-center py-8 text-gray-500">
          Chưa có địa chỉ nào
        </div>
        
        <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div v-for="address in addresses" :key="address.id" class="bg-gray-50 rounded-lg p-4">
            <div class="flex items-start justify-between">
              <div class="flex-1">
                <p class="font-medium text-gray-900">{{ address.type || 'Địa chỉ mặc định' }}</p>
                <p class="text-sm text-gray-600 mt-1">{{ address.address }}</p>
                <p class="text-sm text-gray-600">{{ address.ward }}, {{ address.district }}, {{ address.province }}</p>
                <p class="text-sm text-gray-600">{{ address.phone }}</p>
              </div>
              <div class="flex items-center space-x-2">
                <button @click="editAddress(address)" class="text-blue-600 hover:text-blue-800">
                  <i class="fas fa-edit"></i>
                </button>
                <button @click="deleteAddress(address)" class="text-red-600 hover:text-red-800">
                  <i class="fas fa-trash"></i>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Customer Group Tab -->
      <div v-if="activeTab === 'groups'" class="space-y-4">
        <h3 class="text-lg font-medium text-gray-900">Nhóm khách hàng</h3>
        <div class="bg-gray-50 rounded-lg p-4">
          <div class="flex items-center justify-between">
            <div>
              <p class="font-medium text-gray-900">{{ customerData.group_name }}</p>
              <p class="text-sm text-gray-600">{{ customerData.group_description || 'Không có mô tả' }}</p>
            </div>
            <button @click="changeGroup" class="px-4 py-2 text-sm text-blue-600 hover:text-blue-800">
              Thay đổi nhóm
            </button>
          </div>
        </div>
      </div>
    </div>
    <!-- Contacts Tab -->
<div v-if="activeTab === 'contacts'" class="space-y-4">
  <h3 class="text-lg font-medium text-gray-900">Thông tin liên hệ</h3>
  <div class="bg-gray-50 rounded-lg p-4">
    <p>Email: {{ customerData.email || '---' }}</p>
    <p>Phone: {{ customerData.phone || '---' }}</p>
    <p>Website: {{ customerData.website || '---' }}</p>
  </div>
</div>

<!-- Notes Tab -->
<div v-if="activeTab === 'notes'" class="space-y-4">
  <h3 class="text-lg font-medium text-gray-900">Ghi chú</h3>
  <div class="bg-gray-50 rounded-lg p-4">
    <p>{{ customerData.note || 'Chưa có ghi chú nào' }}</p>
  </div>
</div>

    <!-- Delete Confirmation Modal -->
    <div v-if="showDeleteModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg p-6 w-full max-w-md">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Xác nhận xóa</h3>
        <p class="text-sm text-gray-600 mb-6">
          Bạn có chắc chắn muốn xóa khách hàng "{{ customerData?.name }}"? 
          Hành động này không thể hoàn tác.
        </p>
        <div class="flex justify-end space-x-3">
          <button 
            @click="showDeleteModal = false"
            class="px-4 py-2 text-sm text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200"
          >
            Hủy
          </button>
          <button 
            @click="confirmDelete"
            class="px-4 py-2 text-sm text-white bg-red-600 rounded-md hover:bg-red-700"
          >
            Xóa
          </button>
        </div>
      </div>
    </div>

    <!-- Toast Notifications -->
    <div v-if="toast.show" :class="[
      'fixed top-4 right-4 px-4 py-3 rounded-md shadow-lg z-50',
      toast.type === 'success' ? 'bg-green-500 text-white' : 'bg-red-500 text-white'
    ]">
      <div class="flex items-center">
        <i :class="[
          'mr-2',
          toast.type === 'success' ? 'fas fa-check-circle' : 'fas fa-exclamation-circle'
        ]"></i>
        {{ toast.message }}
      </div>
    </div>
  </div>
  </div>
</template>

<script>
import { ref, reactive, onMounted } from 'vue'
import customerApi from '../api/customerApi.js'

export default {
  name: 'CustomerDetail',
  props: {
    customer: {
      type: Object,
      required: true
    }
  },
  emits: ['close', 'edit', 'delete'],
  setup(props, { emit }) {
    
    // Reactive data
    const customerData = ref(props.customer)
    const loading = ref(false)
    const loadingOrders = ref(false)
    const loadingDebts = ref(false)
    const activeTab = ref('orders')
    const orders = ref([])
    const debts = ref([])
    const addresses = ref([])
    const showDeleteModal = ref(false)
    const hasMoreOrders = ref(true)
    const ordersPage = ref(1)

    const tabs = [
  { id: 'orders', name: 'Lịch sử mua hàng' },
  { id: 'debts', name: 'Công nợ' },
  { id: 'contacts', name: 'Liên hệ' },
  { id: 'addresses', name: 'Địa chỉ' },
  { id: 'notes', name: 'Ghi chú' },
  { id: 'groups', name: 'Nhóm khách hàng' }
]

    const toast = reactive({
      show: false,
      type: 'success',
      message: ''
    })

    // Methods
    const showToast = (type, message) => {
      toast.show = true
      toast.type = type
      toast.message = message
      setTimeout(() => {
        toast.show = false
      }, 3000)
    }

    const loadCustomer = async () => {
      loading.value = true
      try {
        const response = await customerApi.getCustomer(customerData.value.id)
        if (response.success) {
          customerData.value = response.data
          orders.value = response.data.recent_orders || []
          debts.value = response.data.recent_debts || []
          addresses.value = response.data.addresses || []
        }
      } catch (error) {
        showToast('error', error.message || 'Lỗi khi tải thông tin khách hàng')
      } finally {
        loading.value = false
      }
    }

    const loadMoreOrders = async () => {
      if (!hasMoreOrders.value || loadingOrders.value) return

      loadingOrders.value = true
      try {
        ordersPage.value += 1
        const response = await customerApi.getCustomerOrders(customerData.value.id, ordersPage.value)
        if (response.success) {
          orders.value.push(...response.data.data)
          hasMoreOrders.value = response.data.has_more_pages
        }
      } catch (error) {
        showToast('error', 'Lỗi khi tải thêm đơn hàng')
      } finally {
        loadingOrders.value = false
      }
    }

    const goBack = () => {
      emit('close')
    }

    const editPersonalInfo = () => {
      emit('edit', customerData.value)
    }

    const editPricing = () => {
      // Implementation for editing pricing
      showToast('info', 'Chức năng đang phát triển')
    }

    const editCredit = () => {
      // Implementation for editing credit info
      showToast('info', 'Chức năng đang phát triển')
    }

    const viewTransactions = () => {
      activeTab.value = 'orders'
    }

    const viewOrder = (orderId) => {
      window.open(`/orders/${orderId}`, '_blank')
    }

    const addAddress = () => {
      // Implementation for adding address
      showToast('info', 'Chức năng đang phát triển')
    }

    const editAddress = (address) => {
      // Implementation for editing address
      showToast('info', 'Chức năng đang phát triển')
    }

    const deleteAddress = (address) => {
      // Implementation for deleting address
      showToast('info', 'Chức năng đang phát triển')
    }

    const changeGroup = () => {
      // Implementation for changing customer group
      showToast('info', 'Chức năng đang phát triển')
    }

    const confirmDelete = async () => {
      try {
        const response = await customerApi.deleteCustomer(customerData.value.id)
        if (response.success) {
          showToast('success', 'Xóa khách hàng thành công')
          emit('delete', customerData.value.id)
          emit('close')
        }
      } catch (error) {
        showToast('error', error.message || 'Lỗi khi xóa khách hàng')
      } finally {
        showDeleteModal.value = false
      }
    }

    // Utility methods
    const formatCurrency = (amount) => {
      if (!amount) return '0'
      return new Intl.NumberFormat('vi-VN').format(amount)
    }

    const formatDate = (date) => {
      if (!date) return ''
      return new Date(date).toLocaleDateString('vi-VN')
    }

    const getGenderText = (gender) => {
      switch (gender) {
        case 'male': return 'Nam'
        case 'female': return 'Nữ'
        case 'other': return 'Khác'
        default: return '---'
      }
    }

    const getStatusClass = (status) => {
      const baseClass = 'px-2 py-1 text-xs font-medium rounded-full'
      switch (status) {
        case 'completed':
          return `${baseClass} bg-green-100 text-green-800`
        case 'pending':
          return `${baseClass} bg-yellow-100 text-yellow-800`
        case 'cancelled':
          return `${baseClass} bg-red-100 text-red-800`
        case 'processing':
          return `${baseClass} bg-blue-100 text-blue-800`
        default:
          return `${baseClass} bg-gray-100 text-gray-800`
      }
    }

    const getStatusText = (status) => {
      switch (status) {
        case 'completed': return 'Hoàn thành'
        case 'pending': return 'Chờ xử lý'
        case 'cancelled': return 'Đã hủy'
        case 'processing': return 'Đang xử lý'
        default: return 'Không xác định'
      }
    }

    // Lifecycle
    onMounted(() => {
      if (customerData.value.id) {
        loadCustomer()
      }
    })

    return {
      customerData,
      loading,
      loadingOrders,
      loadingDebts,
      activeTab,
      orders,
      debts,
      addresses,
      showDeleteModal,
      hasMoreOrders,
      tabs,
      toast,
      loadMoreOrders,
      goBack,
      editPersonalInfo,
      editPricing,
      editCredit,
      viewTransactions,
      viewOrder,
      addAddress,
      editAddress,
      deleteAddress,
      changeGroup,
      confirmDelete,
      formatCurrency,
      formatDate,
      getGenderText,
      getStatusClass,
      getStatusText
    }
  }
}
</script>