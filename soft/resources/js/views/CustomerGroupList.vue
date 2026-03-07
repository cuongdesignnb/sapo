<template>
  <div class="bg-white">
    <!-- Header -->
    <div class="p-6 border-b">
      <h1 class="text-2xl font-semibold text-gray-900">Nhóm khách hàng</h1>
    </div>

    <!-- Action Buttons -->
    <div class="p-6 border-b bg-gray-50">
      <div class="flex items-center justify-between">
        <div class="flex items-center space-x-4">
          <button 
            class="flex items-center space-x-2 text-gray-600 hover:text-gray-800"
            @click="exportGroups"
            :disabled="loading"
          >
            <span>⬇</span>
            <span>Xuất file</span>
          </button>
          <button 
            class="flex items-center space-x-2 text-gray-600 hover:text-gray-800"
            @click="showImportModal = true"
          >
            <span>⬆</span>
            <span>Nhập file</span>
          </button>
          <button 
            v-if="selectedIds.length > 0"
            class="flex items-center space-x-2 text-red-600 hover:text-red-800"
            @click="bulkDelete"
            :disabled="deleting"
          >
            <span>🗑️</span>
            <span>Xóa đã chọn ({{ selectedIds.length }})</span>
          </button>
        </div>
        <button 
          class="bg-blue-500 text-white px-4 py-2 rounded flex items-center space-x-2 hover:bg-blue-600"
          @click="createGroup"
        >
          <span>+</span>
          <span>Thêm nhóm khách hàng</span>
        </button>
      </div>
    </div>

    <!-- Filters -->
    <div class="p-6 border-b">
      <div class="mb-4">
        <button class="text-blue-500 border-b-2 border-blue-500 pb-2">
          Tất cả nhóm khách hàng ({{ pagination.total || 0 }})
        </button>
      </div>
      
      <div class="grid grid-cols-12 gap-4 items-center">
        <!-- Search -->
        <div class="col-span-4">
          <div class="relative">
            <input
              type="text"
              placeholder="Tìm kiếm theo mã, tên nhóm..."
              class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md"
              v-model="searchQuery"
              @input="debouncedSearch"
            />
            <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400">🔍</span>
          </div>
        </div>
        
        <!-- Type Filter -->
        <div class="col-span-2">
          <select 
            class="w-full px-3 py-2 border border-gray-300 rounded-md"
            v-model="filters.type"
            @change="applyFilters"
          >
            <option value="">Tất cả loại</option>
            <option value="vip">VIP</option>
            <option value="normal">Thường</option>
            <option value="local">Địa phương</option>
            <option value="import">Xuất nhập khẩu</option>
          </select>
        </div>
        
        <!-- Has Customers Filter -->
        <div class="col-span-2">
          <select 
            class="w-full px-3 py-2 border border-gray-300 rounded-md"
            v-model="filters.has_customers"
            @change="applyFilters"
          >
            <option value="">Tất cả</option>
            <option value="yes">Có khách hàng</option>
            <option value="no">Chưa có khách hàng</option>
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
            <option value="name">Tên nhóm</option>
            <option value="customers_count">Số khách hàng</option>
            <option value="discount_percent">Chiết khấu</option>
          </select>
        </div>
        
        <!-- Reset -->
        <div class="col-span-2">
          <button 
            class="w-full px-3 py-2 border border-gray-300 rounded-md text-gray-600 hover:bg-gray-50"
            @click="resetFilters"
          >
            🔄 Reset bộ lọc
          </button>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading && groups.length === 0" class="flex justify-center items-center py-12">
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
            <th class="text-left p-4 text-sm font-medium text-gray-600 cursor-pointer" @click="sortBy('code')">
              Mã nhóm
              <span class="ml-1">↕</span>
            </th>
            <th class="text-left p-4 text-sm font-medium text-gray-600 cursor-pointer" @click="sortBy('name')">
              Tên nhóm
              <span class="ml-1">↕</span>
            </th>
            <th class="text-left p-4 text-sm font-medium text-gray-600">Loại</th>
            <th class="text-right p-4 text-sm font-medium text-gray-600 cursor-pointer" @click="sortBy('discount_percent')">
              Chiết khấu
              <span class="ml-1">↕</span>
            </th>
            <th class="text-right p-4 text-sm font-medium text-gray-600 cursor-pointer" @click="sortBy('customers_count')">
              Số khách hàng
              <span class="ml-1">↕</span>
            </th>
            <th class="text-left p-4 text-sm font-medium text-gray-600">Ngày tạo</th>
            <th class="text-left p-4 text-sm font-medium text-gray-600">Thao tác</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <tr 
            v-for="group in groups" 
            :key="group.id"
            class="hover:bg-gray-50"
          >
            <td class="p-4">
              <input 
                type="checkbox" 
                class="rounded" 
                :checked="selectedIds.includes(group.id)"
                @change="toggleSelection(group.id)"
              />
            </td>
            <td class="p-4">
              <div class="text-blue-500 hover:text-blue-700 font-medium cursor-pointer" @click="viewGroup(group)">
                {{ group.code }}
              </div>
            </td>
            <td class="p-4 font-medium">
              {{ group.name }}
            </td>
            <td class="p-4">
              <span 
                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                :class="group.type_color_class"
              >
                {{ group.type_text }}
              </span>
            </td>
            <td class="p-4 text-right">
              <span class="font-medium">{{ group.formatted_discount }}</span>
            </td>
            <td class="p-4 text-right">
              <span 
                class="font-medium cursor-pointer"
                :class="group.customers_count > 0 ? 'text-blue-600 hover:text-blue-800' : 'text-gray-400'"
                @click="group.customers_count > 0 ? viewGroupCustomers(group) : null"
              >
                {{ group.customers_count }}
              </span>
            </td>
            <td class="p-4 text-sm text-gray-600">
              {{ group.created_at }}
            </td>
            <td class="p-4">
              <div class="flex items-center space-x-2">
                <button
                  @click="viewGroup(group)"
                  class="text-blue-600 hover:text-blue-800 text-sm"
                  title="Xem chi tiết"
                >
                  👁️
                </button>
                <button
                  @click="editGroup(group)"
                  class="text-blue-600 hover:text-blue-800 text-sm"
                  title="Sửa"
                >
                  ✏️
                </button>
                <button
                  @click="deleteGroup(group.id)"
                  class="text-red-600 hover:text-red-800 text-sm"
                  title="Xóa"
                >
                  🗑️
                </button>
              </div>
            </td>
          </tr>
          
          <!-- Empty State -->
          <tr v-if="groups.length === 0 && !loading">
            <td colspan="8" class="text-center py-12 text-gray-500">
              <div class="text-4xl mb-4">👥</div>
              <div>Không có nhóm khách hàng nào</div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="pagination.total > 0" class="px-6 py-4 border-t flex justify-between items-center">
      <div class="text-sm text-gray-700">
        Hiển thị {{ pagination.from }}-{{ pagination.to }} của {{ pagination.total }} nhóm khách hàng
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

    <!-- Customer Group Detail Modal -->
    <CustomerGroupDetail 
      v-if="showDetail"
      :group="selectedGroup"
      :group-id="selectedGroup?.id"
      @close="closeDetail"
      @edit="editFromDetail"
      @delete="deleteGroup"
    />

    <!-- Customer Group Form Modal -->
    <CustomerGroupForm
      v-if="showForm"
      :group="editingGroup"
      @close="closeForm"
      @save="saveGroup"
    />

    <!-- Import Modal -->
    <div v-if="showImportModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg p-6 w-full max-w-md">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-lg font-medium text-gray-900">Nhập file nhóm khách hàng</h3>
          <button @click="closeImportModal" class="text-gray-400 hover:text-gray-600">×</button>
        </div>

        <div class="space-y-4">
          <div class="text-center">
            <button @click="downloadTemplate" class="text-blue-500 hover:text-blue-700 underline text-sm">
              📥 Tải file mẫu (.csv)
            </button>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Chọn file CSV</label>
            <input type="file" accept=".csv" @change="handleFileChange" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" />
          </div>
        </div>

        <div class="flex justify-end space-x-3 mt-6">
          <button @click="closeImportModal" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300">Hủy</button>
          <button @click="importGroups" :disabled="!importFile || importing" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 disabled:opacity-50">
            <span v-if="importing">⏳ Đang nhập...</span>
            <span v-else>📤 Nhập file</span>
          </button>
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
import CustomerGroupDetail from '../components/CustomerGroupDetail.vue'
import CustomerGroupForm from '../components/CustomerGroupForm.vue'
import customerGroupApi from '../api/customerGroupApi.js'
import axios from 'axios'

export default {
  name: 'CustomerGroupList',
  components: {
    CustomerGroupDetail,
    CustomerGroupForm
  },
  setup() {
    // Reactive data
    const loading = ref(false)
    const deleting = ref(false)
    const importing = ref(false)
    const groups = ref([])
    const selectedIds = ref([])
    const searchQuery = ref('')
    const showDetail = ref(false)
    const showForm = ref(false)
    const showImportModal = ref(false)
    const selectedGroup = ref(null)
    const editingGroup = ref(null)
    const importFile = ref(null)

    // Notification
    const notification = ref({
      show: false,
      type: 'success',
      message: ''
    })

    // Filters
    const filters = ref({
      type: '',
      has_customers: '',
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
      return groups.value.length > 0 && 
             selectedIds.value.length === groups.value.length
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
    const fetchGroups = async () => {
      loading.value = true
      
      try {
        const params = {
          ...filters.value,
          search: searchQuery.value,
          page: pagination.value.current_page,
          per_page: pagination.value.per_page
        }

        const response = await customerGroupApi.getCustomerGroups(params)

        if (response.data.success) {
          groups.value = response.data.data || []
          pagination.value = response.data.pagination || {}
        }
      } catch (error) {
        console.error('Error fetching groups:', error)
        showNotification('Lỗi khi tải dữ liệu', 'error')
      } finally {
        loading.value = false
      }
    }

    const applyFilters = async () => {
      pagination.value.current_page = 1
      await fetchGroups()
    }

    const debouncedSearch = debounce(() => {
      applyFilters()
    }, 300)

    const resetFilters = () => {
      searchQuery.value = ''
      filters.value = {
        type: '',
        has_customers: '',
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
      fetchGroups()
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
        selectedIds.value = groups.value.map(g => g.id)
      }
    }

    const createGroup = () => {
      editingGroup.value = null
      showForm.value = true
    }

    const viewGroup = (group) => {
      selectedGroup.value = group
      showDetail.value = true
    }

    const editGroup = (group) => {
      editingGroup.value = group
      showForm.value = true
    }

    const editFromDetail = (group) => {
      editingGroup.value = group
      showDetail.value = false
      showForm.value = true
    }

    const closeDetail = () => {
      showDetail.value = false
      selectedGroup.value = null
    }

    const closeForm = () => {
      showForm.value = false
      editingGroup.value = null
    }

    const saveGroup = async (data) => {
      try {
        if (editingGroup.value?.id) {
          await customerGroupApi.updateCustomerGroup(editingGroup.value.id, data)
          showNotification('Cập nhật nhóm khách hàng thành công')
        } else {
          await customerGroupApi.createCustomerGroup(data)
          showNotification('Tạo nhóm khách hàng thành công')
        }
        
        await fetchGroups()
        closeForm()
        closeDetail()
      } catch (error) {
        console.error('Error saving group:', error)
        showNotification(error.message || 'Lỗi khi lưu nhóm khách hàng', 'error')
      }
    }

    const deleteGroup = async (id) => {
      if (confirm('Bạn có chắc muốn xóa nhóm khách hàng này?')) {
        try {
          await customerGroupApi.deleteCustomerGroup(id)
          showNotification('Xóa nhóm khách hàng thành công')
          await fetchGroups()
          closeDetail()
        } catch (error) {
          console.error('Error deleting group:', error)
          showNotification(error.message || 'Lỗi khi xóa nhóm khách hàng', 'error')
        }
      }
    }

    const bulkDelete = async () => {
      if (confirm(`Bạn có chắc muốn xóa ${selectedIds.value.length} nhóm khách hàng đã chọn?`)) {
        deleting.value = true
        try {
          await customerGroupApi.bulkDelete(selectedIds.value)
          showNotification(`Đã xóa ${selectedIds.value.length} nhóm khách hàng`)
          selectedIds.value = []
          await fetchGroups()
        } catch (error) {
          console.error('Error bulk deleting:', error)
          showNotification(error.message || 'Lỗi khi xóa hàng loạt', 'error')
        } finally {
          deleting.value = false
        }
      }
    }

    const exportGroups = async () => {
      try {
        loading.value = true
        showNotification('Đang xuất file...', 'info')
        
        const exportParams = {
          search: searchQuery.value,
          type: filters.value.type,
          has_customers: filters.value.has_customers
        }

        if (selectedIds.value.length > 0) {
          exportParams.selected_ids = selectedIds.value
        }

        customerGroupApi.exportCustomerGroups(exportParams)
        showNotification('Xuất file thành công!', 'success')
        
      } catch (error) {
        console.error('Export error:', error)
        showNotification('Lỗi khi xuất file', 'error')
      } finally {
        loading.value = false
      }
    }

    const handleFileChange = (event) => {
      importFile.value = event.target.files[0]
    }

    const importGroups = async () => {
      if (!importFile.value) {
        showNotification('Vui lòng chọn file để nhập', 'error')
        return
      }

      try {
        importing.value = true
        showNotification('Đang nhập dữ liệu...', 'info')

        const response = await customerGroupApi.importCustomerGroups(importFile.value)

        if (response.data.success) {
          showNotification(response.data.message, 'success')
          await fetchGroups()
          closeImportModal()
        }
      } catch (error) {
        console.error('Import error:', error)
        showNotification('Lỗi khi nhập file', 'error')
      } finally {
        importing.value = false
      }
    }

    const downloadTemplate = () => {
      customerGroupApi.downloadTemplate()
      showNotification('Tải template thành công!', 'success')
    }

    const closeImportModal = () => {
      showImportModal.value = false
      importFile.value = null
    }

    const viewGroupCustomers = (group) => {
      // Navigate to customers page with group filter
      viewGroup(group)
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
      await fetchGroups()
    })

    return {
      loading,
      deleting,
      importing,
      groups,
      selectedIds,
      searchQuery,
      showDetail,
      showForm,
      showImportModal,
      selectedGroup,
      editingGroup,
      importFile,
      notification,
      filters,
      pagination,
      isAllSelected,
      visiblePages,
      fetchGroups,
      applyFilters,
      debouncedSearch,
      resetFilters,
      sortBy,
      changePage,
      toggleSelection,
      toggleSelectAll,
      createGroup,
      viewGroup,
      editGroup,
      editFromDetail,
      closeDetail,
      closeForm,
      saveGroup,
      deleteGroup,
      bulkDelete,
      exportGroups,
      handleFileChange,
      importGroups,
      downloadTemplate,
      closeImportModal,
      viewGroupCustomers
    }
  }
}
</script>