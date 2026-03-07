<template>
  <div class="bg-white">
    <!-- Header -->
    <div class="p-6 border-b">
      <h1 class="text-2xl font-semibold text-gray-900">Quản lý khách hàng</h1>
    </div>

    <!-- Action Buttons -->
    <div class="p-6 border-b bg-gray-50">
      <div class="flex items-center justify-between">
        <div class="flex items-center space-x-4">
          <button 
            v-if="selectedIds.length > 0"
            class="flex items-center space-x-2 text-red-600 hover:text-red-800"
            @click="bulkDelete"
            :disabled="deleting"
          >
            <span>🗑️</span>
            <span>Xóa đã chọn ({{ selectedIds.length }})</span>
          </button>
          <button 
            class="flex items-center space-x-2 text-blue-600 hover:text-blue-800"
            @click="exportData"
          >
            <span>📁</span>
            <span>Xuất file</span>
          </button>
          <button 
            class="flex items-center space-x-2 text-green-600 hover:text-green-800"
            @click="showImportModal = true"
          >
            <span>📄</span>
            <span>Nhập file</span>
          </button>
        </div>
        <button 
          class="bg-blue-500 text-white px-4 py-2 rounded flex items-center space-x-2 hover:bg-blue-600"
          @click="createCustomer"
        >
          <span>+</span>
          <span>Thêm khách hàng</span>
        </button>
      </div>
    </div>

    <!-- Filters -->
    <div class="p-6 border-b">
      <div class="mb-4">
        <button class="text-blue-500 border-b-2 border-blue-500 pb-2">
          Tất cả khách hàng ({{ pagination.total || 0 }})
        </button>
      </div>
      
      <div class="grid grid-cols-12 gap-4 items-center">
        <!-- Search -->
        <div class="col-span-4">
          <div class="relative">
            <input
              type="text"
              placeholder="Tìm kiếm theo mã khách hàng, tên, SĐT"
              class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md"
              v-model="searchQuery"
              @input="debouncedSearch"
            />
            <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400">🔍</span>
          </div>
        </div>
        
        <!-- Filter by Group -->
        <div class="col-span-3">
          <select 
            class="w-full px-3 py-2 border border-gray-300 rounded-md"
            v-model="filters.group_id"
            @change="applyFilters"
          >
            <option value="">Tất cả nhóm</option>
            <option v-for="group in customerGroups" :key="group.id" :value="group.id">
              {{ group.name }}
            </option>
          </select>
        </div>
        
        <!-- Filter by Status -->
        <div class="col-span-2">
          <select 
            class="w-full px-3 py-2 border border-gray-300 rounded-md"
            v-model="filters.status"
            @change="applyFilters"
          >
            <option value="">Tất cả trạng thái</option>
            <option value="active">Đang giao dịch</option>
            <option value="inactive">Ngừng giao dịch</option>
          </select>
        </div>
        
        <!-- Sort -->
        <div class="col-span-2">
          <select 
            class="w-full px-3 py-2 border border-gray-300 rounded-md"
            v-model="filters.sort_field"
            @change="applyFilters"
          >
            <option value="created_at">Ngày tạo</option>
            <option value="name">Tên khách hàng</option>
            <option value="total_spend">Tổng chi tiêu</option>
          </select>
        </div>
        
        <!-- Reset -->
        <div class="col-span-1">
          <button 
            class="w-full px-3 py-2 border border-gray-300 rounded-md text-gray-600 hover:bg-gray-50"
            @click="resetFilters"
          >
            🔄
          </button>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading && customers.length === 0" class="flex justify-center items-center py-12">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
      <span class="ml-2 text-gray-600">Đang tải dữ liệu...</span>
    </div>

    <!-- Table -->
    <div v-else class="overflow-x-auto">
      <table class="w-full">
        <thead class="bg-gray-50">
          <tr>
            <th class="w-12 p-4">
              <input 
                type="checkbox" 
                class="rounded"
                :checked="isAllSelected"
                @change="toggleSelectAll"
              />
            </th>
            <th class="text-left p-4 text-sm font-medium text-gray-600">Mã khách hàng</th>
            <th class="text-left p-4 text-sm font-medium text-gray-600">Tên khách hàng</th>
            <th class="text-left p-4 text-sm font-medium text-gray-600">Nhóm</th>
            <th class="text-left p-4 text-sm font-medium text-gray-600">Email</th>
            <th class="text-left p-4 text-sm font-medium text-gray-600">Số điện thoại</th>
            <th class="text-left p-4 text-sm font-medium text-gray-600 cursor-pointer" @click="sortBy('total_spend')">
              Công nợ hiện tại
              <span class="ml-1">↕</span>
            </th>
            <th class="text-left p-4 text-sm font-medium text-gray-600">Trạng thái</th>
            <th class="text-left p-4 text-sm font-medium text-gray-600 cursor-pointer" @click="sortBy('created_at')">
              Ngày tạo
              <span class="ml-1">↕</span>
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <tr 
            v-for="customer in customers" 
            :key="customer.id"
            class="hover:bg-gray-50 cursor-pointer"
            @click="viewCustomer(customer)"
          >
            <td class="p-4">
              <input 
                type="checkbox" 
                class="rounded" 
                :checked="selectedIds.includes(customer.id)"
                @click.stop="toggleSelection(customer.id)"
              />
            </td>
            <td class="p-4">
              <div class="text-blue-500 hover:text-blue-700 font-medium">
                {{ customer.code }}
              </div>
            </td>
            <td class="p-4">
              <div class="font-medium text-gray-900">{{ customer.name }}</div>
            </td>
            <td class="p-4 text-sm text-gray-600">
              {{ customer.group_name || 'Bán lẻ' }}
            </td>
            <td class="p-4 text-sm text-gray-600">
              {{ customer.email || '-' }}
            </td>
            <td class="p-4 text-sm text-gray-600">
              {{ customer.phone || '-' }}
            </td>
            <td class="p-4 text-sm text-gray-900 font-medium">
              {{ formatCurrency(customer.total_debt) }}
            </td>
            <td class="p-4">
              <span 
                class="px-2 py-1 text-xs font-medium rounded-full"
                :class="customer.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'"
              >
                {{ customer.status === 'active' ? 'Đang giao dịch' : 'Ngừng giao dịch' }}
              </span>
            </td>
            <td class="p-4 text-sm text-gray-600">
              {{ formatDate(customer.created_at) }}
            </td>
          </tr>
          
          <!-- Empty State -->
          <tr v-if="customers.length === 0 && !loading">
            <td colspan="9" class="text-center py-12 text-gray-500">
              <div class="text-4xl mb-4">👥</div>
              <div>Không có khách hàng nào</div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="pagination.total > 0" class="px-6 py-4 border-t flex justify-between items-center">
      <div class="text-sm text-gray-700">
        Hiển thị {{ pagination.from }}-{{ pagination.to }} của {{ pagination.total }} khách hàng
      </div>
      <div class="flex space-x-2">
        <button 
          class="px-3 py-1 border rounded text-sm"
          :disabled="pagination.current_page <= 1"
          @click="changePage(pagination.current_page - 1)"
        >
          Trước
        </button>
        
        <template v-for="page in visiblePages" :key="page">
          <button 
            class="px-3 py-1 border rounded text-sm"
            :class="page === pagination.current_page ? 'bg-blue-500 text-white' : 'bg-white'"
            @click="changePage(page)"
          >
            {{ page }}
          </button>
        </template>
        
        <button 
          class="px-3 py-1 border rounded text-sm"
          :disabled="pagination.current_page >= pagination.last_page"
          @click="changePage(pagination.current_page + 1)"
        >
          Tiếp
        </button>
      </div>
    </div>

    <!-- Customer Detail Modal -->
    <CustomerDetail 
      v-if="showDetail"
      :customer="selectedCustomer"
      @close="closeDetail"
      @edit="editFromDetail"
      @delete="deleteCustomer"
    />

    <!-- Customer Form Modal -->
    <CustomerForm
  v-if="showForm"
  :customer="editingCustomer"
  :customer-groups="customerGroups"
  @cancel="closeForm"
  @submit="saveCustomer"
/>

    <!-- Import Modal -->
    <div v-if="showImportModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg p-6 w-full max-w-md">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-lg font-medium">Nhập file khách hàng</h3>
          <button @click="showImportModal = false" class="text-gray-400 hover:text-gray-600">✕</button>
        </div>
        <div class="space-y-4">
  <div class="flex justify-between items-center mb-4">
    <p class="text-sm text-gray-600">Chọn file CSV để nhập khách hàng</p>
    <button 
      @click="downloadTemplate" 
      class="flex items-center space-x-1 text-blue-600 hover:text-blue-800 text-sm"
    >
      <span>📄</span>
      <span>Tải file mẫu</span>
    </button>
  </div>
  
  <div class="border-2 border-dashed border-gray-300 rounded-lg p-4">
    <input 
      type="file" 
      accept=".csv"
      @change="handleFileSelect"
      class="w-full p-2 border border-gray-300 rounded"
    />
    <p class="text-xs text-gray-500 mt-2">
      Hỗ trợ file CSV. Vui lòng tải file mẫu để xem định dạng chuẩn.
    </p>
  </div>
          <div class="flex justify-end space-x-2">
            <button 
              @click="showImportModal = false"
              class="px-4 py-2 text-gray-600 border rounded hover:bg-gray-50"
            >
              Hủy
            </button>
            <button 
              @click="handleImport"
              :disabled="!selectedFile || importing"
              class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 disabled:opacity-50"
            >
              {{ importing ? 'Đang nhập...' : 'Nhập file' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Toast Notification -->
    <div v-if="notification.show" class="fixed top-4 right-4 z-50">
      <div 
        class="p-4 rounded-lg shadow-lg max-w-sm"
        :class="{
          'bg-green-100 border border-green-400 text-green-700': notification.type === 'success',
          'bg-red-100 border border-red-400 text-red-700': notification.type === 'error'
        }"
      >
        <div class="flex items-center">
          <span class="mr-2">{{ notification.type === 'success' ? '✅' : '❌' }}</span>
          <span>{{ notification.message }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue'
import CustomerDetail from '../components/CustomerDetail.vue'
import CustomerForm from '../components/CustomerForm.vue'
import customerApi from '../api/customerApi'

export default {
  name: 'CustomerList',
  components: {
    CustomerDetail,
    CustomerForm
  },
  setup() {
    // Reactive data
    const loading = ref(false)
    const deleting = ref(false)
    const importing = ref(false)
    const customers = ref([])
    const customerGroups = ref([])
    const selectedIds = ref([])
    const searchQuery = ref('')
    const showDetail = ref(false)
    const showForm = ref(false)
    const showImportModal = ref(false)
    const selectedCustomer = ref(null)
    const editingCustomer = ref(null)
    const selectedFile = ref(null)

    // Notification
    const notification = ref({
      show: false,
      type: 'success',
      message: ''
    })

    // Filters
    const filters = ref({
      group_id: '',
      status: '',
      sort_field: 'created_at',
      sort_direction: 'desc'
    })

    // Pagination
    const pagination = ref({
      current_page: 1,
      last_page: 1,
      per_page: 20,
      total: 0,
      from: 0,
      to: 0
    })

    // Computed
    const isAllSelected = computed(() => {
      return customers.value.length > 0 && 
             selectedIds.value.length === customers.value.length
    })

    const visiblePages = computed(() => {
      const current = pagination.value.current_page
      const last = pagination.value.last_page
      const pages = []
      
      for (let i = Math.max(1, current - 2); i <= Math.min(last, current + 2); i++) {
        pages.push(i)
      }
      
      return pages
    })

    // Notification helper
    const showNotification = (message, type = 'success') => {
      notification.value = {
        show: true,
        type,
        message
      }
      
      setTimeout(() => {
        notification.value.show = false
      }, 3000)
    }

    // Methods
    const fetchCustomers = async () => {
      loading.value = true
      
      try {
        const params = {
          ...filters.value,
          search: searchQuery.value,
          page: pagination.value.current_page,
          per_page: pagination.value.per_page
        }

        const response = await customerApi.getCustomers(params)

        if (response.success) {
          customers.value = response.data.data || []
          pagination.value = response.data || {}
        }
      } catch (error) {
        console.error('Error fetching customers:', error)
        showNotification('Lỗi khi tải dữ liệu', 'error')
      } finally {
        loading.value = false
      }
    }

    const loadCustomerGroups = async () => {
      try {
        console.log('Loading customer groups...')
        const response = await customerApi.getCreateData()
        console.log('API response:', response)
        if (response.success) {
          customerGroups.value = response.data.customer_groups || []
          console.log('customerGroups set to:', customerGroups.value)
        }
      } catch (error) {
        console.error('Error loading customer groups:', error)
        
      }
    }

    const applyFilters = async () => {
      pagination.value.current_page = 1
      await fetchCustomers()
    }

    const debouncedSearch = debounce(() => {
      applyFilters()
    }, 300)

    const resetFilters = () => {
      searchQuery.value = ''
      filters.value = {
        group_id: '',
        status: '',
        sort_field: 'created_at',
        sort_direction: 'desc'
      }
      applyFilters()
    }

    const sortBy = (field) => {
      if (filters.value.sort_field === field) {
        filters.value.sort_direction = filters.value.sort_direction === 'asc' ? 'desc' : 'asc'
      } else {
        filters.value.sort_field = field
        filters.value.sort_direction = 'desc'
      }
      applyFilters()
    }

    const changePage = (page) => {
      pagination.value.current_page = page
      fetchCustomers()
    }

    const toggleSelection = (id) => {
      const index = selectedIds.value.indexOf(id)
      if (index > -1) {
        selectedIds.value.splice(index, 1)
      } else {
        selectedIds.value.push(id)
      }
    }

    const toggleSelectAll = () => {
      if (isAllSelected.value) {
        selectedIds.value = []
      } else {
        selectedIds.value = customers.value.map(c => c.id)
      }
    }

    const createCustomer = () => {
        console.log('=== CREATE CUSTOMER DEBUG ===')
  console.log('customerGroups.value:', customerGroups.value)
  console.log('customerGroups length:', customerGroups.value.length)  
      editingCustomer.value = null
      showForm.value = true
    }

    const viewCustomer = (customer) => {
      selectedCustomer.value = customer
      showDetail.value = true
    }

    const editFromDetail = (customer) => {
  console.log('Edit customer:', customer) // THÊM LOG ĐỂ TEST
  editingCustomer.value = customer
  showDetail.value = false
  showForm.value = true
  console.log('showForm:', showForm.value) // THÊM LOG
}

    const closeDetail = () => {
      showDetail.value = false
      selectedCustomer.value = null
    }

    const closeForm = () => {
      showForm.value = false
      editingCustomer.value = null
    }

    const saveCustomer = async (data) => {
      try {
        if (editingCustomer.value?.id) {
          await customerApi.updateCustomer(editingCustomer.value.id, data)
          showNotification('Cập nhật khách hàng thành công')
        } else {
          await customerApi.createCustomer(data)
          showNotification('Tạo khách hàng thành công')
        }
        
        await Promise.all([
          fetchCustomers(),
          loadCustomerGroups()
        ])
        closeForm()
        closeDetail()
      } catch (error) {
        console.error('Error saving customer:', error)
        showNotification('Lỗi khi lưu khách hàng', 'error')
      }
    }

    const deleteCustomer = async (id) => {
      if (confirm('Bạn có chắc muốn xóa khách hàng này?')) {
        try {
          await customerApi.deleteCustomer(id)
          showNotification('Xóa khách hàng thành công')
          await Promise.all([
            fetchCustomers(),
            loadCustomerGroups()
          ])
          closeDetail()
        } catch (error) {
          console.error('Error deleting customer:', error)
          showNotification(error.message || 'Lỗi khi xóa khách hàng', 'error')
        }
      }
    }

    const bulkDelete = async () => {
      if (confirm(`Bạn có chắc muốn xóa ${selectedIds.value.length} khách hàng đã chọn?`)) {
        deleting.value = true
        try {
          await customerApi.bulkDeleteCustomers(selectedIds.value)
          showNotification(`Đã xóa ${selectedIds.value.length} khách hàng`)
          selectedIds.value = []
          await Promise.all([
            fetchCustomers(),
            loadCustomerGroups()
          ])
        } catch (error) {
          console.error('Error bulk deleting:', error)
          showNotification(error.message || 'Lỗi khi xóa hàng loạt', 'error')
        } finally {
          deleting.value = false
        }
      }
    }

    const exportData = async () => {
  try {
    showNotification('Đang xuất file...', 'info')
    
    // Generate CSV content
    const csvContent = generateCSV()
    downloadCSV(csvContent, 'customers_' + new Date().toISOString().slice(0,10) + '.csv')
    
    showNotification('Xuất file thành công')
  } catch (error) {
    showNotification('Lỗi khi xuất file', 'error')
  }
}

const generateCSV = () => {
  const headers = ['Mã KH', 'Tên', 'Nhóm', 'Email', 'Phone', 'Trạng thái', 'Tổng chi tiêu', 'Ngày tạo']
  const rows = customers.value.map(c => [
    c.code || '',
    c.name || '',
    c.group_name || 'Bán lẻ',
    c.email || '',
    c.phone || '',
    c.status === 'active' ? 'Đang giao dịch' : 'Ngừng giao dịch',
    c.total_spend || 0,
    formatDate(c.created_at)
  ])
  
  return [headers, ...rows].map(row => 
    row.map(field => `"${field}"`).join(',')
  ).join('\n')
}

const downloadCSV = (content, filename) => {
  const BOM = '\uFEFF' // UTF-8 BOM cho Excel
  const blob = new Blob([BOM + content], { type: 'text/csv;charset=utf-8;' })
  const link = document.createElement('a')
  link.href = URL.createObjectURL(blob)
  link.download = filename
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
}


const downloadTemplate = () => {
  const template = [
    // Header row - THÊM CÁC TRƯỜNG THIẾU
    ['Tên khách hàng*', 'Email', 'Số điện thoại', 'Ngày sinh', 'Giới tính', 'Mã số thuế', 'Website', 'Loại khách hàng', 'Nhân viên phụ trách', 'Trạng thái', 'Ghi chú'],
    // Sample data
    ['Nguyễn Văn A', 'nguyenvana@example.com', '0123456789', '1990-01-15', 'male', '0123456789', 'https://example.com', 'Cá nhân', 'Cao Đức Bình', 'active', 'Khách hàng VIP'],
    ['Trần Thị B', 'tranthib@example.com', '0987654321', '1985-05-20', 'female', '0987654321', '', 'Doanh nghiệp', 'Cao Đức Bình', 'active', 'Khách hàng thường'],
    ['Lê Văn C', 'levanc@example.com', '0369852147', '1992-12-10', 'male', '', '', 'Cá nhân', 'Nguyễn Văn A', 'active', ''],
    ['', '', '', '', '', '', '', '', '', '', ''],
    ['HƯỚNG DẪN:', '', '', '', '', '', '', '', '', '', ''],
    ['- Tên khách hàng: Bắt buộc (có dấu *)', '', '', '', '', '', '', '', '', '', ''],
    ['- Giới tính: male, female, other', '', '', '', '', '', '', '', '', '', ''],
    ['- Loại KH: Cá nhân, Doanh nghiệp, Đối tác', '', '', '', '', '', '', '', '', '', ''],
    ['- Trạng thái: active, inactive', '', '', '', '', '', '', '', '', '', ''],
    ['- Ngày sinh: YYYY-MM-DD (VD: 1990-01-15)', '', '', '', '', '', '', '', '', '', '']
  ]
  
  const csv = template.map(row => 
    row.map(field => `"${field}"`).join(',')
  ).join('\n')
  
  downloadCSV(csv, 'customer_import_template.csv')
  showNotification('Đã tải file mẫu thành công')
}
    const handleFileSelect = (event) => {
      selectedFile.value = event.target.files[0]
    }

    const handleImport = async () => {
  if (!selectedFile.value) {
    showNotification('Vui lòng chọn file', 'error')
    return
  }
  
  importing.value = true
  try {
    console.log('Importing file:', selectedFile.value.name) // DEBUG LOG
    
    const response = await customerApi.importCustomers(selectedFile.value)
    console.log('Import response:', response) // DEBUG LOG
    
    if (response.success) {
      showNotification(response.message || 'Nhập file thành công')
      showImportModal.value = false
      selectedFile.value = null
      fetchCustomers() // Reload data
    }
  } catch (error) {
    console.error('Import error:', error) // DEBUG LOG
    showNotification(error.message || 'Lỗi khi nhập file', 'error')
  } finally {
    importing.value = false
  }
}

    const formatDate = (dateString) => {
      return new Date(dateString).toLocaleDateString('vi-VN')
    }

    const formatCurrency = (amount) => {
      if (!amount) return '0'
      return new Intl.NumberFormat('vi-VN').format(amount)
    }

    // Debounce helper
    function debounce(func, wait) {
      let timeout
      return function executedFunction(...args) {
        const later = () => {
          clearTimeout(timeout)
          func(...args)
        }
        clearTimeout(timeout)
        timeout = setTimeout(later, wait)
      }
    }

    // Lifecycle
    onMounted(async () => {
      await Promise.all([
        fetchCustomers(),
        loadCustomerGroups()
      ])
    })

    return {
      loading,
      deleting,
      importing,
      customers,
      customerGroups,
      selectedIds,
      searchQuery,
      showDetail,
      showForm,
      showImportModal,
      selectedCustomer,
      editingCustomer,
      selectedFile,
      notification,
      filters,
      pagination,
      isAllSelected,
      visiblePages,
      fetchCustomers,
      loadCustomerGroups,
      applyFilters,
      debouncedSearch,
      resetFilters,
      sortBy,
      changePage,
      toggleSelection,
      toggleSelectAll,
      createCustomer,
      viewCustomer,
      editFromDetail,
      closeDetail,
      closeForm,
      saveCustomer,
      deleteCustomer,
      bulkDelete,
      exportData,
      handleFileSelect,
      handleImport,
      formatDate,
      formatCurrency,
      generateCSV,
      downloadCSV,
      downloadTemplate
    }
  }
}
</script>