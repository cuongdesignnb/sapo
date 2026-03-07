<template>
  <div class="bg-white">
    <!-- Header -->
    <div class="p-6 border-b">
      <h1 class="text-2xl font-semibold text-gray-900">Danh mục sản phẩm</h1>
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
        </div>
        <button 
          class="bg-blue-500 text-white px-4 py-2 rounded flex items-center space-x-2 hover:bg-blue-600"
          @click="createCategory"
        >
          <span>+</span>
          <span>Thêm danh mục</span>
        </button>
      </div>
    </div>

    <!-- Filters -->
    <div class="p-6 border-b">
      <div class="mb-4">
        <button class="text-blue-500 border-b-2 border-blue-500 pb-2">
          Tất cả danh mục ({{ pagination.total || 0 }})
        </button>
      </div>
      
      <div class="grid grid-cols-12 gap-4 items-center">
        <!-- Search -->
        <div class="col-span-4">
          <div class="relative">
            <input
              type="text"
              placeholder="Tìm kiếm theo tên danh mục"
              class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md"
              v-model="searchQuery"
              @input="debouncedSearch"
            />
            <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400">🔍</span>
          </div>
        </div>
        
        <!-- Filter by Parent -->
        <div class="col-span-3">
          <select 
            class="w-full px-3 py-2 border border-gray-300 rounded-md"
            v-model="filters.parent_id"
            @change="applyFilters"
          >
            <option value="">Tất cả danh mục</option>
            <option value="null">Danh mục gốc</option>
            <option v-for="category in parentCategories" :key="category.id" :value="category.id">
              {{ category.name }}
            </option>
          </select>
        </div>
        
        <!-- Sort -->
        <div class="col-span-3">
          <select 
            class="w-full px-3 py-2 border border-gray-300 rounded-md"
            v-model="filters.sort_field"
            @change="applyFilters"
          >
            <option value="created_at">Ngày tạo</option>
            <option value="name">Tên danh mục</option>
          </select>
        </div>
        
        <!-- Reset -->
        <div class="col-span-2">
          <button 
            class="w-full px-3 py-2 border border-gray-300 rounded-md text-gray-600 hover:bg-gray-50"
            @click="resetFilters"
          >
            🔄 Reset
          </button>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading && categories.length === 0" class="flex justify-center items-center py-12">
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
            <th class="text-left p-4 text-sm font-medium text-gray-600">Tên danh mục</th>
            <th class="text-left p-4 text-sm font-medium text-gray-600">Danh mục cha</th>
            <th class="text-left p-4 text-sm font-medium text-gray-600">Số danh mục con</th>
            <th class="text-left p-4 text-sm font-medium text-gray-600">Ghi chú</th>
            <th class="text-left p-4 text-sm font-medium text-gray-600 cursor-pointer" @click="sortBy('created_at')">
              Ngày tạo
              <span class="ml-1">↕</span>
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <tr 
            v-for="category in categories" 
            :key="category.id"
            class="hover:bg-gray-50 cursor-pointer"
            @click="viewCategory(category)"
          >
            <td class="p-4">
              <input 
                type="checkbox" 
                class="rounded" 
                :checked="selectedIds.includes(category.id)"
                @click.stop="toggleSelection(category.id)"
              />
            </td>
            <td class="p-4">
              <div class="text-blue-500 hover:text-blue-700 font-medium">
                {{ category.name }}
              </div>
            </td>
            <td class="p-4 text-sm text-gray-600">
              {{ category.parent ? category.parent.name : 'Danh mục gốc' }}
            </td>
            <td class="p-4 text-sm text-gray-600">
              {{ category.children ? category.children.length : 0 }}
            </td>
            <td class="p-4 text-sm text-gray-600">
              {{ category.note || '-' }}
            </td>
            <td class="p-4 text-sm text-gray-600">
              {{ formatDate(category.created_at) }}
            </td>
          </tr>
          
          <!-- Empty State -->
          <tr v-if="categories.length === 0 && !loading">
            <td colspan="6" class="text-center py-12 text-gray-500">
              <div class="text-4xl mb-4">📁</div>
              <div>Không có danh mục nào</div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="pagination.total > 0" class="px-6 py-4 border-t flex justify-between items-center">
      <div class="text-sm text-gray-700">
        Hiển thị {{ pagination.from }}-{{ pagination.to }} của {{ pagination.total }} danh mục
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

    <!-- Category Detail Modal -->
    <CategoryDetail 
      v-if="showDetail"
      :category="selectedCategory"
      @close="closeDetail"
      @edit="editFromDetail"
      @delete="deleteCategory"
    />

    <!-- Category Form Modal -->
    <CategoryForm
      v-if="showForm"
      :category="editingCategory"
      :parentCategories="parentCategories"
      @close="closeForm"
      @save="saveCategory"
    />

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
import CategoryDetail from '../components/CategoryDetail.vue'
import CategoryForm from '../components/CategoryForm.vue'
import { categoryApi } from '../api/categoryApi'

export default {
  name: 'CategoryList',
  components: {
    CategoryDetail,
    CategoryForm
  },
  setup() {
    // Reactive data
    const loading = ref(false)
    const deleting = ref(false)
    const categories = ref([])
    const parentCategories = ref([])
    const selectedIds = ref([])
    const searchQuery = ref('')
    const showDetail = ref(false)
    const showForm = ref(false)
    const selectedCategory = ref(null)
    const editingCategory = ref(null)

    // Notification
    const notification = ref({
      show: false,
      type: 'success',
      message: ''
    })

    // Filters
    const filters = ref({
      parent_id: '',
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
      return categories.value.length > 0 && 
             selectedIds.value.length === categories.value.length
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
    const fetchCategories = async () => {
      loading.value = true
      
      try {
        const params = {
          ...filters.value,
          search: searchQuery.value,
          page: pagination.value.current_page,
          per_page: pagination.value.per_page
        }

        const response = await categoryApi.getAll(params)

        if (response.success) {
          categories.value = response.data || []
          pagination.value = response.pagination || {}
        }
      } catch (error) {
        console.error('Error fetching categories:', error)
        showNotification('Lỗi khi tải dữ liệu', 'error')
      } finally {
        loading.value = false
      }
    }

    const loadParentCategories = async () => {
      try {
        const response = await categoryApi.getAll({ per_page: 1000 })
        if (response.success) {
          parentCategories.value = response.data || []
        }
      } catch (error) {
        console.error('Error loading parent categories:', error)
      }
    }

    const applyFilters = async () => {
      pagination.value.current_page = 1
      await fetchCategories()
    }

    const debouncedSearch = debounce(() => {
      applyFilters()
    }, 300)

    const resetFilters = () => {
      searchQuery.value = ''
      filters.value = {
        parent_id: '',
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
      fetchCategories()
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
        selectedIds.value = categories.value.map(c => c.id)
      }
    }

    const createCategory = () => {
      editingCategory.value = null
      showForm.value = true
    }

    const viewCategory = (category) => {
      selectedCategory.value = category
      showDetail.value = true
    }

    const editFromDetail = (category) => {
      editingCategory.value = category
      showDetail.value = false
      showForm.value = true
    }

    const closeDetail = () => {
      showDetail.value = false
      selectedCategory.value = null
    }

    const closeForm = () => {
      showForm.value = false
      editingCategory.value = null
    }

    const saveCategory = async (data) => {
      try {
        if (editingCategory.value?.id) {
          await categoryApi.update(editingCategory.value.id, data)
          showNotification('Cập nhật danh mục thành công')
        } else {
          await categoryApi.create(data)
          showNotification('Tạo danh mục thành công')
        }
        
        await Promise.all([
          fetchCategories(),
          loadParentCategories()
        ])
        closeForm()
        closeDetail()
      } catch (error) {
        console.error('Error saving category:', error)
        showNotification('Lỗi khi lưu danh mục', 'error')
      }
    }

    const deleteCategory = async (id) => {
      if (confirm('Bạn có chắc muốn xóa danh mục này?')) {
        try {
          await categoryApi.delete(id)
          showNotification('Xóa danh mục thành công')
          await Promise.all([
            fetchCategories(),
            loadParentCategories()
          ])
          closeDetail()
        } catch (error) {
          console.error('Error deleting category:', error)
          showNotification(error.message || 'Lỗi khi xóa danh mục', 'error')
        }
      }
    }

    const bulkDelete = async () => {
      if (confirm(`Bạn có chắc muốn xóa ${selectedIds.value.length} danh mục đã chọn?`)) {
        deleting.value = true
        try {
          await categoryApi.bulkDelete(selectedIds.value)
          showNotification(`Đã xóa ${selectedIds.value.length} danh mục`)
          selectedIds.value = []
          await Promise.all([
            fetchCategories(),
            loadParentCategories()
          ])
        } catch (error) {
          console.error('Error bulk deleting:', error)
          showNotification(error.message || 'Lỗi khi xóa hàng loạt', 'error')
        } finally {
          deleting.value = false
        }
      }
    }

    const formatDate = (dateString) => {
      return new Date(dateString).toLocaleDateString('vi-VN')
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
        fetchCategories(),
        loadParentCategories()
      ])
    })

    return {
      loading,
      deleting,
      categories,
      parentCategories,
      selectedIds,
      searchQuery,
      showDetail,
      showForm,
      selectedCategory,
      editingCategory,
      notification,
      filters,
      pagination,
      isAllSelected,
      visiblePages,
      fetchCategories,
      loadParentCategories,
      applyFilters,
      debouncedSearch,
      resetFilters,
      sortBy,
      changePage,
      toggleSelection,
      toggleSelectAll,
      createCategory,
      viewCategory,
      editFromDetail,
      closeDetail,
      closeForm,
      saveCategory,
      deleteCategory,
      bulkDelete,
      formatDate
    }
  }
}
</script>