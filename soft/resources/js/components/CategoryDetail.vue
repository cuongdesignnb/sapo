<template>
  <div class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl mx-4">
      <!-- Header -->
      <div class="flex items-center justify-between p-6 border-b">
        <h3 class="text-lg font-medium text-gray-900">Chi tiết danh mục</h3>
        <div class="flex items-center space-x-2">
          <button
            @click="$emit('edit', category)"
            class="text-blue-600 hover:text-blue-800 px-3 py-1 border border-blue-300 rounded-md text-sm"
          >
            ✏️ Sửa
          </button>
          <button
            @click="$emit('delete', category.id)"
            class="text-red-600 hover:text-red-800 px-3 py-1 border border-red-300 rounded-md text-sm"
          >
            🗑️ Xóa
          </button>
          <button
            @click="$emit('close')"
            class="text-gray-400 hover:text-gray-600"
          >
            <span class="text-xl">×</span>
          </button>
        </div>
      </div>

      <!-- Content -->
      <div class="p-6">
        <div class="grid grid-cols-2 gap-6">
          <!-- Left Column -->
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Tên danh mục
              </label>
              <div class="text-lg font-semibold text-gray-900">
                {{ category.name }}
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Danh mục cha
              </label>
              <div class="text-gray-900">
                {{ category.parent ? category.parent.name : 'Danh mục gốc' }}
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Số danh mục con
              </label>
              <div class="text-gray-900">
                {{ category.children ? category.children.length : 0 }} danh mục
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Ngày tạo
              </label>
              <div class="text-gray-900">
                {{ formatDate(category.created_at) }}
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Cập nhật lần cuối
              </label>
              <div class="text-gray-900">
                {{ formatDate(category.updated_at) }}
              </div>
            </div>
          </div>

          <!-- Right Column -->
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Ghi chú
              </label>
              <div class="text-gray-900 bg-gray-50 p-3 rounded-md min-h-[100px]">
                {{ category.note || 'Không có ghi chú' }}
              </div>
            </div>

            <!-- Children List -->
            <div v-if="category.children && category.children.length > 0">
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Danh mục con
              </label>
              <div class="bg-gray-50 p-3 rounded-md max-h-[200px] overflow-y-auto">
                <div v-for="child in category.children" :key="child.id" class="py-1">
                  <span class="text-sm text-gray-700">• {{ child.name }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Footer -->
      <div class="px-6 py-4 border-t bg-gray-50 flex justify-end">
        <button
          @click="$emit('close')"
          class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
        >
          Đóng
        </button>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'CategoryDetail',
  props: {
    category: {
      type: Object,
      required: true
    }
  },
  emits: ['close', 'edit', 'delete'],
  setup() {
    const formatDate = (dateString) => {
      return new Date(dateString).toLocaleDateString('vi-VN', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      })
    }

    return {
      formatDate
    }
  }
}
</script>