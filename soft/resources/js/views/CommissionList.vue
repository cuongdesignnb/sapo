<template>
  <div class="bg-white">
    <div class="p-6 border-b">
      <h1 class="text-2xl font-semibold text-gray-900">Bảng hoa hồng</h1>
    </div>

    <div class="p-6 border-b bg-gray-50 grid grid-cols-12 gap-4 items-end">
      <div class="col-span-4">
        <label class="block text-sm font-medium text-gray-700 mb-1">Nhân viên</label>
        <select v-model="filters.employee_id" class="w-full px-3 py-2 border border-gray-300 rounded-md">
          <option :value="null">Tất cả</option>
          <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.code }} - {{ e.name }}</option>
        </select>
      </div>
      <div class="col-span-3">
        <label class="block text-sm font-medium text-gray-700 mb-1">Từ</label>
        <input v-model="filters.from" type="date" class="w-full px-3 py-2 border border-gray-300 rounded-md" />
      </div>
      <div class="col-span-3">
        <label class="block text-sm font-medium text-gray-700 mb-1">Đến</label>
        <input v-model="filters.to" type="date" class="w-full px-3 py-2 border border-gray-300 rounded-md" />
      </div>
      <div class="col-span-2">
        <button class="w-full bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600" @click="load">Lọc</button>
      </div>
    </div>

    <div class="p-6 border-b bg-white flex justify-end">
      <button class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700" @click="openCreate">+ Thêm hoa hồng</button>
    </div>

    <div v-if="loading" class="flex justify-center items-center py-12">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
      <span class="ml-2 text-gray-600">Đang tải...</span>
    </div>

    <div v-else class="overflow-x-auto">
      <table class="w-full">
        <thead class="bg-gray-50">
          <tr>
            <th class="text-left p-4 text-sm font-medium text-gray-600">Nhân viên</th>
            <th class="text-left p-4 text-sm font-medium text-gray-600">Đơn</th>
            <th class="text-left p-4 text-sm font-medium text-gray-600">Ngày ghi nhận</th>
            <th class="text-left p-4 text-sm font-medium text-gray-600">Doanh số</th>
            <th class="text-left p-4 text-sm font-medium text-gray-600">Tỉ lệ</th>
            <th class="text-left p-4 text-sm font-medium text-gray-600">Hoa hồng</th>
            <th class="text-left p-4 text-sm font-medium text-gray-600">Thao tác</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <tr v-for="c in commissions" :key="c.id" class="hover:bg-gray-50">
            <td class="p-4">{{ c.employee?.code }} - {{ c.employee?.name }}</td>
            <td class="p-4 text-sm text-gray-600">{{ c.order_code || (c.order_id ? ('#' + c.order_id) : '-') }}</td>
            <td class="p-4 text-sm text-gray-600">{{ c.earned_at || '-' }}</td>
            <td class="p-4">{{ formatMoney(c.order_total) }}</td>
            <td class="p-4 text-sm text-gray-600">{{ c.commission_rate ?? '-' }}</td>
            <td class="p-4 font-medium">{{ formatMoney(c.commission_amount) }}</td>
            <td class="p-4">
              <button class="text-blue-600 hover:text-blue-800 text-sm mr-3" @click="openEdit(c)">Sửa</button>
              <button class="text-red-600 hover:text-red-800 text-sm" @click="remove(c)">Xóa</button>
            </td>
          </tr>
          <tr v-if="commissions.length === 0">
            <td colspan="7" class="p-8 text-center text-gray-500">Chưa có hoa hồng</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="showModal" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg w-full max-w-2xl p-6">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-lg font-semibold">{{ editing ? 'Cập nhật hoa hồng' : 'Thêm hoa hồng' }}</h2>
          <button class="text-gray-500 hover:text-gray-700" @click="close">✕</button>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nhân viên *</label>
            <select v-model="form.employee_id" class="w-full px-3 py-2 border border-gray-300 rounded-md" :disabled="!!editing">
              <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.code }} - {{ e.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Ngày ghi nhận</label>
            <input v-model="form.earned_at" type="date" class="w-full px-3 py-2 border border-gray-300 rounded-md" />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Mã đơn</label>
            <input v-model="form.order_code" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Doanh số</label>
            <input v-model.number="form.order_total" type="number" class="w-full px-3 py-2 border border-gray-300 rounded-md" />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tỉ lệ (ví dụ 0.02)</label>
            <input v-model.number="form.commission_rate" type="number" step="0.0001" class="w-full px-3 py-2 border border-gray-300 rounded-md" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Hoa hồng</label>
            <input v-model.number="form.commission_amount" type="number" class="w-full px-3 py-2 border border-gray-300 rounded-md" />
          </div>

          <div class="col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">Ghi chú</label>
            <textarea v-model="form.notes" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md"></textarea>
          </div>
        </div>

        <div class="flex items-center justify-end gap-3 mt-6">
          <button class="px-4 py-2 rounded border" @click="close">Bỏ qua</button>
          <button class="px-4 py-2 rounded bg-green-600 text-white hover:bg-green-700" @click="save" :disabled="saving">Lưu</button>
        </div>
      </div>
    </div>

    <div v-if="toast.show" class="fixed top-4 right-4 z-50">
      <div class="p-4 rounded-lg shadow-lg max-w-sm" :class="toast.type === 'success' ? 'bg-green-100 border border-green-400 text-green-700' : 'bg-red-100 border border-red-400 text-red-700'">
        <div class="flex items-center">
          <span class="mr-2">{{ toast.type === 'success' ? '✅' : '❌' }}</span>
          <span>{{ toast.message }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, onMounted } from 'vue'
import employeeApi from '@/api/employeeApi'

export default {
  name: 'CommissionList',
  setup() {
    const loading = ref(false)
    const saving = ref(false)
    const employees = ref([])
    const commissions = ref([])
    const filters = ref({ employee_id: null, from: null, to: null })

    const showModal = ref(false)
    const editing = ref(null)
    const form = ref({ employee_id: null, earned_at: null, order_code: '', order_total: 0, commission_rate: null, commission_amount: 0, notes: '' })

    const toast = ref({ show: false, type: 'success', message: '' })
    const showToast = (message, type = 'success') => {
      toast.value = { show: true, type, message }
      setTimeout(() => (toast.value.show = false), 3000)
    }

    const formatMoney = (v) => Number(v || 0).toLocaleString('vi-VN')

    const loadEmployees = async () => {
      const res = await employeeApi.getEmployees({ per_page: 200 })
      employees.value = res.data?.data || []
    }

    const load = async () => {
      loading.value = true
      try {
        const res = await employeeApi.getCommissions({ employee_id: filters.value.employee_id, from: filters.value.from, to: filters.value.to })
        commissions.value = res.data?.data || []
      } catch {
        showToast('Lỗi khi tải hoa hồng', 'error')
      } finally {
        loading.value = false
      }
    }

    const openCreate = () => {
      editing.value = null
      form.value = { employee_id: employees.value[0]?.id ?? null, earned_at: null, order_code: '', order_total: 0, commission_rate: null, commission_amount: 0, notes: '' }
      showModal.value = true
    }

    const openEdit = (c) => {
      editing.value = c
      form.value = {
        employee_id: c.employee_id,
        earned_at: c.earned_at || null,
        order_code: c.order_code || '',
        order_total: Number(c.order_total || 0),
        commission_rate: c.commission_rate,
        commission_amount: Number(c.commission_amount || 0),
        notes: c.notes || ''
      }
      showModal.value = true
    }

    const close = () => (showModal.value = false)

    const save = async () => {
      saving.value = true
      try {
        if (editing.value) {
          await employeeApi.updateCommission(editing.value.id, form.value)
          showToast('Đã cập nhật hoa hồng')
        } else {
          await employeeApi.createCommission(form.value)
          showToast('Đã tạo hoa hồng')
        }
        showModal.value = false
        await load()
      } catch {
        showToast('Lỗi khi lưu hoa hồng', 'error')
      } finally {
        saving.value = false
      }
    }

    const remove = async (c) => {
      if (!confirm('Xóa hoa hồng này?')) return
      try {
        await employeeApi.deleteCommission(c.id)
        showToast('Đã xóa hoa hồng')
        await load()
      } catch {
        showToast('Lỗi khi xóa hoa hồng', 'error')
      }
    }

    onMounted(async () => {
      await loadEmployees()
      await load()
    })

    return { loading, saving, employees, commissions, filters, load, openCreate, openEdit, showModal, editing, form, close, save, remove, formatMoney, toast }
  }
}
</script>
