<template>
  <div class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
      <!-- Header -->
      <div class="flex items-center justify-between p-6 border-b">
        <h3 class="text-lg font-medium text-gray-900">
          {{ isEditing ? 'Sửa danh mục' : 'Thêm danh mục mới' }}
        </h3>
        <button
          @click="$emit('close')"
          class="text-gray-400 hover:text-gray-600"
        >
          <span class="text-xl">×</span>
        </button>
      </div>

      <!-- Form -->
      <form @submit.prevent="handleSubmit" class="p-6">
        <div class="space-y-4">
          <!-- Tên danh mục -->
          <div>
            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">
              Tên danh mục <span class="text-red-500">*</span>
            </label>
            <input
              id="name"
              type="text"
              v-model="form.name"
              :class="[
                'w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500',
                errors.name ? 'border-red-300 bg-red-50' : 'border-gray-300'
              ]"
              placeholder="Nhập tên danh mục"
              required
            />
            <p v-if="errors.name" class="mt-1 text-sm text-red-600">
              {{ errors.name }}
            </p>
          </div>

          <!-- Danh mục cha -->
          <div>
            <label for="parent_id" class="block text-sm font-medium text-gray-700 mb-1">
              Danh mục cha
            </label>
            <select
              id="parent_id"
              v-model="form.parent_id"
              :class="[
                'w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500',
                errors.parent_id ? 'border-red-300 bg-red-50' : 'border-gray-300'
              ]"
            >
              <option value="">Chọn danh mục cha (tùy chọn)</option>
              <template v-for="parentCategory in availableParents" :key="parentCategory.id">
                <option :value="parentCategory.id">
                  {{ parentCategory.name }}
                </option>
              </template>
            </select>
            <p v-if="errors.parent_id" class="mt-1 text-sm text-red-600">
              {{ errors.parent_id }}
            </p>
            <p class="mt-1 text-xs text-gray-500">
              Để trống nếu đây là danh mục gốc
            </p>
          </div>

          <!-- Ghi chú -->
          <div>
            <label for="note" class="block text-sm font-medium text-gray-700 mb-1">
              Ghi chú
            </label>
            <textarea
              id="note"
              v-model="form.note"
              rows="3"
              :class="[
                'w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500',
                errors.note ? 'border-red-300 bg-red-50' : 'border-gray-300'
              ]"
              placeholder="Nhập ghi chú (tùy chọn)"
            ></textarea>
            <p v-if="errors.note" class="mt-1 text-sm text-red-600">
              {{ errors.note }}
            </p>
          </div>
        </div>

        <!-- Form Actions -->
        <div class="flex justify-end space-x-3 mt-6 pt-4 border-t">
          <button
            type="button"
            @click="$emit('close')"
            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
            :disabled="loading"
          >
            Hủy
          </button>
          <button
            type="submit"
            :disabled="loading || !isFormValid"
            :class="[
              'px-4 py-2 text-sm font-medium text-white rounded-md',
              loading || !isFormValid 
                ? 'bg-gray-400 cursor-not-allowed' 
                : 'bg-blue-600 hover:bg-blue-700'
            ]"
          >
            <span v-if="loading">⏳ Đang lưu...</span>
            <span v-else>{{ isEditing ? 'Cập nhật' : 'Tạo mới' }}</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script>
import { ref, computed, onMounted, watch } from 'vue'

export default {
  name: 'CategoryForm',
  props: {
    category: {
      type: Object,
      default: null
    },
    parentCategories: {
      type: Array,
      default: () => []
    }
  },
  emits: ['close', 'save'],
  setup(props, { emit }) {
    const loading = ref(false)
    const errors = ref({})

    // Form data
    const form = ref({
      name: '',
      parent_id: '',
      note: ''
    })

    // Computed
    const isEditing = computed(() => {
      return props.category && props.category.id
    })

    const isFormValid = computed(() => {
      return form.value.name.trim().length > 0
    })

    // Available parent categories (exclude current category and its children)
    const availableParents = computed(() => {
      if (!isEditing.value) {
        return props.parentCategories
      }

      // When editing, exclude self and children to prevent circular reference
      return props.parentCategories.filter(parent => {
        return parent.id !== props.category.id
      })
    })

    // Methods
    const initializeForm = () => {
      if (isEditing.value) {
        form.value = {
          name: props.category.name || '',
          parent_id: props.category.parent_id || '',
          note: props.category.note || ''
        }
      } else {
        form.value = {
          name: '',
          parent_id: '',
          note: ''
        }
      }
      errors.value = {}
    }

    const validateForm = () => {
      errors.value = {}

      // Validate name
      if (!form.value.name.trim()) {
        errors.value.name = 'Tên danh mục là bắt buộc'
      } else if (form.value.name.trim().length < 2) {
        errors.value.name = 'Tên danh mục phải có ít nhất 2 ký tự'
      } else if (form.value.name.trim().length > 255) {
        errors.value.name = 'Tên danh mục không được vượt quá 255 ký tự'
      }

      // Validate parent_id (prevent self-reference)
      if (isEditing.value && form.value.parent_id == props.category.id) {
        errors.value.parent_id = 'Không thể chọn chính danh mục này làm danh mục cha'
      }

      return Object.keys(errors.value).length === 0
    }

    const handleSubmit = async () => {
      if (!validateForm()) {
        return
      }

      loading.value = true

      try {
        // Prepare data
        const submitData = {
          name: form.value.name.trim(),
          parent_id: form.value.parent_id || null,
          note: form.value.note.trim() || null
        }

        emit('save', submitData)
      } catch (error) {
        console.error('Form submit error:', error)
      } finally {
        loading.value = false
      }
    }

    // Watch for category prop changes
    watch(() => props.category, () => {
      initializeForm()
    }, { immediate: true })

    // Lifecycle
    onMounted(() => {
      initializeForm()
    })

    return {
      loading,
      errors,
      form,
      isEditing,
      isFormValid,
      availableParents,
      handleSubmit
    }
  }
}
</script>