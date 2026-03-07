<template>
  <div class="bg-white">
    <!-- Header -->
    <div class="p-6 border-b">
      <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-gray-900">Quản lý đơn vị vận chuyển</h1>
        <button 
          @click="showCreateModal = true"
          class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600"
        >
          Thêm đơn vị vận chuyển
        </button>
      </div>
    </div>

    <!-- Filters -->
    <div class="p-6 border-b bg-gray-50">
      <div class="grid grid-cols-12 gap-4 items-center">
        <!-- Search -->
        <div class="col-span-4">
          <div class="relative">
            <input
              type="text"
              placeholder="Tìm theo tên, mã đơn vị..."
              class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md"
              v-model="searchQuery"
              @input="debouncedSearch"
            />
            <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400">🔍</span>
          </div>
        </div>
        
        <!-- Status Filter -->
        <div class="col-span-2">
          <select 
            class="w-full px-3 py-2 border border-gray-300 rounded-md"
            v-model="filters.status"
            @change="applyFilters"
          >
            <option value="">Tất cả trạng thái</option>
            <option value="active">Hoạt động</option>
            <option value="inactive">Ngừng hoạt động</option>
          </select>
        </div>
        
        <!-- Type Filter -->
        <div class="col-span-2">
          <select 
            class="w-full px-3 py-2 border border-gray-300 rounded-md"
            v-model="filters.type"
            @change="applyFilters"
          >
            <option value="">Tất cả loại</option>
            <option value="internal">Nội bộ</option>
            <option value="ghtk">GHTK</option>
            <option value="ghn">GHN</option>
            <option value="viettelpost">Viettel Post</option>
            <option value="custom">Tùy chỉnh</option>
          </select>
        </div>
        
        <!-- Actions -->
        <div class="col-span-4 flex gap-2">
          <button 
            class="px-3 py-2 border border-gray-300 rounded-md text-gray-600 hover:bg-gray-50"
            @click="resetFilters"
          >
            Đặt lại
          </button>
          <button 
            v-if="selectedProviders.length > 0"
            @click="bulkDelete"
            class="px-3 py-2 bg-red-500 text-white rounded-md hover:bg-red-600"
          >
            Xóa {{ selectedProviders.length }} mục
          </button>
        </div>
      </div>
    </div>

    <!-- Stats -->
    <div class="p-6 border-b">
      <div class="grid grid-cols-4 gap-4">
        <div class="text-center">
          <div class="text-2xl font-bold text-blue-600">{{ stats.total || 0 }}</div>
          <div class="text-sm text-gray-600">Tổng số</div>
        </div>
        <div class="text-center">
          <div class="text-2xl font-bold text-green-600">{{ stats.active || 0 }}</div>
          <div class="text-sm text-gray-600">Hoạt động</div>
        </div>
        <div class="text-center">
          <div class="text-2xl font-bold text-red-600">{{ stats.inactive || 0 }}</div>
          <div class="text-sm text-gray-600">Ngừng hoạt động</div>
        </div>
        <div class="text-center">
          <div class="text-2xl font-bold text-purple-600">{{ Object.keys(stats.by_type || {}).length }}</div>
          <div class="text-sm text-gray-600">Loại đơn vị</div>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading && providers.length === 0" class="flex justify-center items-center py-12">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
      <span class="ml-2 text-gray-600">Đang tải dữ liệu...</span>
    </div>

    <!-- Table -->
    <div v-else class="overflow-x-auto">
      <table class="w-full">
        <thead class="bg-gray-50">
          <tr>
            <th class="text-left p-4">
              <input 
                type="checkbox" 
                @change="toggleSelectAll"
                :checked="selectedProviders.length === providers.length && providers.length > 0"
              />
            </th>
            <th class="text-left p-4 text-sm font-medium text-gray-600">Mã</th>
            <th class="text-left p-4 text-sm font-medium text-gray-600">Tên đơn vị</th>
            <th class="text-left p-4 text-sm font-medium text-gray-600">Loại</th>
            <th class="text-left p-4 text-sm font-medium text-gray-600">Trạng thái</th>
            <th class="text-left p-4 text-sm font-medium text-gray-600">Ngày tạo</th>
            <th class="text-left p-4 text-sm font-medium text-gray-600">Thao tác</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <tr 
            v-for="provider in providers" 
            :key="provider.id"
            class="hover:bg-gray-50"
          >
            <td class="p-4">
              <input 
                type="checkbox" 
                :value="provider.id"
                v-model="selectedProviders"
              />
            </td>
            <td class="p-4">
              <div class="font-medium text-blue-600">{{ provider.code }}</div>
            </td>
            <td class="p-4">
              <div class="font-medium">{{ provider.name }}</div>
            </td>
            <td class="p-4">
              <span 
                class="px-2 py-1 rounded-full text-xs font-medium"
                :class="getTypeClass(provider.type)"
              >
                {{ getTypeText(provider.type) }}
              </span>
            </td>
            <td class="p-4">
              <button
                @click="toggleStatus(provider)"
                class="px-2 py-1 rounded-full text-xs font-medium cursor-pointer"
                :class="getStatusClass(provider.status)"
              >
                {{ getStatusText(provider.status) }}
              </button>
            </td>
            <td class="p-4 text-sm text-gray-600">
              {{ formatDate(provider.created_at) }}
            </td>
            <td class="p-4">
              <div class="flex space-x-2">
                <button 
                  @click="editProvider(provider)"
                  class="text-blue-500 hover:text-blue-700 text-sm"
                >
                  Sửa
                </button>
                <button 
                  @click="deleteProvider(provider)"
                  class="text-red-500 hover:text-red-700 text-sm"
                >
                  Xóa
                </button>
              </div>
            </td>
          </tr>
          
          <!-- Empty State -->
          <tr v-if="providers.length === 0 && !loading">
            <td colspan="7" class="text-center py-12 text-gray-500">
              <div class="text-4xl mb-4">🚚</div>
              <div>Chưa có đơn vị vận chuyển nào</div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="pagination.total > 0" class="px-6 py-4 border-t flex justify-between items-center">
      <div class="text-sm text-gray-700">
        Hiển thị {{ pagination.from }}-{{ pagination.to }} của {{ pagination.total }} đơn vị
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

    <!-- Create/Edit Modal -->
    <ProviderModal
      v-if="showCreateModal || editingProvider"
      :provider="editingProvider"
      @close="closeModal"
      @success="handleSuccess"
    />
  </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue'
import ProviderModal from '../components/ProviderModal.vue'
import shippingProviderApi from '../api/shippingProviderApi'


export default {
  name: 'ShippingProviderList',
  components: {
    ProviderModal
  },
  setup() {
    const loading = ref(false)
    const providers = ref([])
    const selectedProviders = ref([])
    const searchQuery = ref('')
    const showCreateModal = ref(false)
    const editingProvider = ref(null)

    const stats = ref({
      total: 0,
      active: 0,
      inactive: 0,
      by_type: {}
    })

    const filters = ref({
      status: '',
      type: ''
    })

    const pagination = ref({
      current_page: 1,
      last_page: 1,
      per_page: 20,
      total: 0,
      from: 0,
      to: 0
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

    const loadProviders = async () => {
      loading.value = true
      
      try {
        const params = {
          page: pagination.value.current_page,
          per_page: pagination.value.per_page
        }

        if (searchQuery.value) params.search = searchQuery.value
        if (filters.value.status) params.status = filters.value.status
        if (filters.value.type) params.type = filters.value.type

        const response = await shippingProviderApi.getAll(params)
        
        if (response.success) {
          providers.value = response.data
          pagination.value = response.pagination
        }
        
      } catch (error) {
        console.error('Error loading providers:', error)
      } finally {
        loading.value = false
      }
    }

    const loadStats = async () => {
      try {
        const response = await shippingProviderApi.getStats()
        
        if (response.success) {
          stats.value = response.data
        }
      } catch (error) {
        console.error('Error loading stats:', error)
      }
    }

    const applyFilters = () => {
      pagination.value.current_page = 1
      loadProviders()
    }

    const debouncedSearch = debounce(() => {
      applyFilters()
    }, 300)

    const resetFilters = () => {
      searchQuery.value = ''
      filters.value = {
        status: '',
        type: ''
      }
      applyFilters()
    }

    const changePage = (page) => {
      pagination.value.current_page = page
      loadProviders()
    }

    const toggleSelectAll = () => {
      if (selectedProviders.value.length === providers.value.length) {
        selectedProviders.value = []
      } else {
        selectedProviders.value = providers.value.map(p => p.id)
      }
    }

    const editProvider = (provider) => {
      editingProvider.value = { ...provider }
    }

    const deleteProvider = async (provider) => {
      if (!confirm(`Bạn có chắc muốn xóa đơn vị vận chuyển "${provider.name}"?`)) {
        return
      }

      try {
        const response = await shippingProviderApi.delete(provider.id)
        
        if (response.success) {
          await loadProviders()
          await loadStats()
        } else {
          alert(response.message)
        }
      } catch (error) {
        console.error('Error deleting provider:', error)
        alert('Có lỗi xảy ra khi xóa')
      }
    }

    const bulkDelete = async () => {
      if (!confirm(`Bạn có chắc muốn xóa ${selectedProviders.value.length} đơn vị vận chuyển?`)) {
        return
      }

      try {
        const response = await shippingProviderApi.bulkDelete(selectedProviders.value)
        
        if (response.success) {
          selectedProviders.value = []
          await loadProviders()
          await loadStats()
        } else {
          alert(response.message)
        }
      } catch (error) {
        console.error('Error bulk deleting:', error)
        alert('Có lỗi xảy ra khi xóa')
      }
    }

    const toggleStatus = async (provider) => {
      try {
        const response = await shippingProviderApi.toggleStatus(provider.id)
        
        if (response.success) {
          await loadProviders()
          await loadStats()
        }
      } catch (error) {
        console.error('Error toggling status:', error)
      }
    }

    const closeModal = () => {
      showCreateModal.value = false
      editingProvider.value = null
    }

    const handleSuccess = async () => {
      closeModal()
      await loadProviders()
      await loadStats()
    }

    const getTypeClass = (type) => {
      const classMap = {
        'internal': 'bg-blue-100 text-blue-800',
        'ghtk': 'bg-green-100 text-green-800',
        'ghn': 'bg-orange-100 text-orange-800',
        'viettelpost': 'bg-red-100 text-red-800',
        'custom': 'bg-purple-100 text-purple-800'
      }
      return classMap[type] || 'bg-gray-100 text-gray-800'
    }

    const getTypeText = (type) => {
      const typeMap = {
        'internal': 'Nội bộ',
        'ghtk': 'GHTK',
        'ghn': 'GHN', 
        'viettelpost': 'Viettel Post',
        'custom': 'Tùy chỉnh'
      }
      return typeMap[type] || type
    }

    const getStatusClass = (status) => {
      return status === 'active' 
        ? 'bg-green-100 text-green-800 hover:bg-green-200' 
        : 'bg-red-100 text-red-800 hover:bg-red-200'
    }

    const getStatusText = (status) => {
      return status === 'active' ? 'Hoạt động' : 'Ngừng hoạt động'
    }

    const formatDate = (dateString) => {
      return new Date(dateString).toLocaleDateString('vi-VN')
    }

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

    onMounted(async () => {
      await Promise.all([
        loadProviders(),
        loadStats()
      ])
    })

    return {
      loading,
      providers,
      selectedProviders,
      searchQuery,
      showCreateModal,
      editingProvider,
      stats,
      filters,
      pagination,
      visiblePages,
      loadProviders,
      applyFilters,
      debouncedSearch,
      resetFilters,
      changePage,
      toggleSelectAll,
      editProvider,
      deleteProvider,
      bulkDelete,
      toggleStatus,
      closeModal,
      handleSuccess,
      getTypeClass,
      getTypeText,
      getStatusClass,
      getStatusText,
      formatDate
    }
  }
}
</script>