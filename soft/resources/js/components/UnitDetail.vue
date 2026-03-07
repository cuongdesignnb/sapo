<template>
  <div class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-lg mx-4">
      <!-- Header -->
      <div class="flex items-center justify-between p-6 border-b">
        <h3 class="text-lg font-medium text-gray-900">Chi tiết đơn vị tính</h3>
        <div class="flex items-center space-x-2">
          <button
            @click="$emit('edit', unit)"
            class="text-blue-600 hover:text-blue-800 px-3 py-1 border border-blue-300 rounded-md text-sm"
          >
            ✏️ Sửa
          </button>
          <button
            @click="$emit('delete', unit.id)"
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
        <div class="space-y-6">
          <!-- Unit Icon & Name -->
          <div class="text-center">
            <div class="w-16 h-16 mx-auto bg-blue-100 rounded-full flex items-center justify-center mb-4">
              <span class="text-2xl">📏</span>
            </div>
            <h2 class="text-xl font-semibold text-gray-900">{{ unit.name }}</h2>
          </div>

          <!-- Details Grid -->
          <div class="grid grid-cols-1 gap-4">
            <div class="bg-gray-50 p-4 rounded-lg">
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Tên đơn vị tính
              </label>
              <div class="text-lg font-medium text-gray-900">
                {{ unit.name }}
              </div>
            </div>

            <div class="bg-gray-50 p-4 rounded-lg">
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Ghi chú
              </label>
              <div class="text-gray-900 min-h-[60px]">
                {{ unit.note || 'Không có ghi chú' }}
              </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div class="bg-gray-50 p-4 rounded-lg">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                  Ngày tạo
                </label>
                <div class="text-gray-900">
                  {{ formatDate(unit.created_at) }}
                </div>
              </div>

              <div class="bg-gray-50 p-4 rounded-lg">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                  Cập nhật lần cuối
                </label>
                <div class="text-gray-900">
                  {{ formatDate(unit.updated_at) }}
                </div>
              </div>
            </div>

            <!-- Usage Info -->
            <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
              <label class="block text-sm font-medium text-blue-700 mb-2">
                📊 Thông tin sử dụng
              </label>
              <div class="text-sm text-blue-600">
                <p>• ID: {{ unit.id }}</p>
                <p>• Trạng thái: Đang hoạt động</p>
                <p>• Loại: Đơn vị tính cơ bản</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Footer -->
      <div class="px-6 py-4 border-t bg-gray-50 flex justify-end space-x-3">
        <button
          @click="$emit('edit', unit)"
          class="px-4 py-2 text-sm font-medium text-blue-700 bg-blue-100 border border-blue-300 rounded-md hover:bg-blue-200"
        >
          ✏️ Chỉnh sửa
        </button>
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
  name: 'UnitDetail',
  props: {
    unit: {
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