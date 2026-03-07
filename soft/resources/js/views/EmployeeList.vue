<template>
  <div class="bg-gray-50 min-h-screen">
    <div class="bg-white border-b">
      <div class="p-6 flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-gray-900">Nhân viên</h1>

        <button class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700" @click="openCreate">+ Thêm nhân viên</button>
      </div>

      <div class="px-6 pb-5">
        <div class="relative max-w-xl">
          <input
            v-model="search"
            @input="debouncedLoad"
            type="text"
            placeholder="Tìm theo mã nhân viên, mã chấm công, tên, SĐT, CCCD, email..."
            class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md bg-white"
          />
          <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">🔎</span>
        </div>
      </div>
    </div>

    <div class="flex gap-4 p-6">
      <!-- Sidebar filters -->
      <aside class="w-72 shrink-0">
        <div class="bg-white border rounded-lg p-4">
          <div class="text-sm font-semibold text-gray-900 mb-3">Bộ lọc</div>

          <div class="mb-4">
            <div class="text-xs font-medium text-gray-600 mb-2">Trạng thái</div>
            <div class="space-y-2 text-sm">
              <label class="flex items-center gap-2 cursor-pointer">
                <input type="radio" value="" v-model="filters.status" @change="load" />
                <span>Tất cả</span>
              </label>
              <label class="flex items-center gap-2 cursor-pointer">
                <input type="radio" value="active" v-model="filters.status" @change="load" />
                <span>Đang làm</span>
              </label>
              <label class="flex items-center gap-2 cursor-pointer">
                <input type="radio" value="inactive" v-model="filters.status" @change="load" />
                <span>Đã nghỉ</span>
              </label>
            </div>
          </div>

          <div class="mb-4">
            <div class="text-xs font-medium text-gray-600 mb-2">Chi nhánh làm việc</div>
            <select v-model="filters.work_warehouse_id" class="w-full px-3 py-2 border border-gray-300 rounded-md" @change="load">
              <option :value="null">Tất cả</option>
              <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
            </select>
          </div>

          <div class="mb-4">
            <div class="text-xs font-medium text-gray-600 mb-2">Chi nhánh trả lương</div>
            <select v-model="filters.warehouse_id" class="w-full px-3 py-2 border border-gray-300 rounded-md" @change="load">
              <option :value="null">Tất cả</option>
              <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
            </select>
          </div>

          <div class="mb-4">
            <div class="text-xs font-medium text-gray-600 mb-2">Phòng ban</div>
            <input v-model="filters.department" @input="debouncedLoad" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="Ví dụ: Bán hàng" />
          </div>

          <div>
            <div class="text-xs font-medium text-gray-600 mb-2">Chức danh</div>
            <input v-model="filters.title" @input="debouncedLoad" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="Ví dụ: Thu ngân" />
          </div>
        </div>
      </aside>

      <!-- Content -->
      <main class="flex-1">
        <div class="bg-white border rounded-lg overflow-hidden">
          <div v-if="loading" class="flex justify-center items-center py-12">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
            <span class="ml-2 text-gray-600">Đang tải dữ liệu...</span>
          </div>

          <div v-else class="overflow-x-auto">
            <table class="w-full">
              <thead class="bg-gray-50">
                <tr>
                  <th class="text-left p-3 text-sm font-medium text-gray-600 w-10">#</th>
                  <th class="text-left p-3 text-sm font-medium text-gray-600">Ảnh</th>
                  <th class="text-left p-3 text-sm font-medium text-gray-600">Mã nhân viên</th>
                  <th class="text-left p-3 text-sm font-medium text-gray-600">Mã chấm công</th>
                  <th class="text-left p-3 text-sm font-medium text-gray-600">Tên nhân viên</th>
                  <th class="text-left p-3 text-sm font-medium text-gray-600">Số điện thoại</th>
                  <th class="text-left p-3 text-sm font-medium text-gray-600">Số CMND/CCCD</th>
                  <th class="text-left p-3 text-sm font-medium text-gray-600">Nợ & tạm ứng</th>
                  <th class="text-left p-3 text-sm font-medium text-gray-600">Ghi chú</th>
                  <th class="text-right p-3 text-sm font-medium text-gray-600">Thao tác</th>
                </tr>
              </thead>

              <tbody class="divide-y divide-gray-200">
                <tr v-for="(e, idx) in employees" :key="e.id" class="hover:bg-gray-50">
                  <td class="p-3 text-sm text-gray-500">{{ idx + 1 }}</td>
                  <td class="p-3">
                    <div class="h-9 w-9 rounded-full bg-gray-200 flex items-center justify-center text-xs text-gray-600 overflow-hidden">
                      <img v-if="e.avatar_path" :src="e.avatar_path" class="h-9 w-9 object-cover" />
                      <span v-else>NV</span>
                    </div>
                  </td>
                  <td class="p-3 font-medium text-blue-700">
                    <a class="hover:underline" :href="`/employees/${e.id}`">{{ e.code }}</a>
                  </td>
                  <td class="p-3 text-sm text-gray-700">{{ e.attendance_code || '-' }}</td>
                  <td class="p-3">
                    <div class="text-sm font-medium text-gray-900">
                      <a class="hover:underline" :href="`/employees/${e.id}`">{{ e.name }}</a>
                    </div>
                    <div class="text-xs text-gray-500">
                      <span
                        class="inline-flex items-center px-2 py-0.5 rounded-full"
                        :class="e.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700'"
                      >
                        {{ e.status === 'active' ? 'Đang làm' : 'Đã nghỉ' }}
                      </span>
                    </div>
                  </td>
                  <td class="p-3 text-sm text-gray-700">{{ e.phone || '-' }}</td>
                  <td class="p-3 text-sm text-gray-700">{{ e.id_number || '-' }}</td>
                  <td class="p-3 text-sm text-gray-700">-</td>
                  <td class="p-3 text-sm text-gray-700">{{ e.notes || '-' }}</td>
                  <td class="p-3 text-right">
                    <div class="inline-flex items-center gap-3">
                      <button class="text-blue-600 hover:text-blue-800 text-sm" @click="openEdit(e)">Cập nhật</button>
                      <button class="text-red-600 hover:text-red-800 text-sm" @click="remove(e)">Xóa</button>
                    </div>
                  </td>
                </tr>

                <tr v-if="employees.length === 0">
                  <td colspan="10" class="p-8 text-center text-gray-500">Chưa có nhân viên</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </main>
    </div>

    <!-- Modal create/edit -->
    <div v-if="showModal" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg w-full max-w-4xl overflow-hidden">
        <div class="px-6 py-4 border-b flex items-center justify-between">
          <h2 class="text-lg font-semibold">{{ editing ? 'Cập nhật nhân viên' : 'Thêm nhân viên' }}</h2>
          <button class="text-gray-500 hover:text-gray-700" @click="close">✕</button>
        </div>

        <div class="px-6 pt-4">
          <div class="flex items-center gap-4 border-b">
            <button
              class="px-1 pb-3 text-sm font-medium"
              :class="activeTab === 'info' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-600'"
              @click="activeTab = 'info'"
            >
              Thông tin
            </button>
            <button
              class="px-1 pb-3 text-sm font-medium"
              :class="activeTab === 'salary' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-600'"
              @click="activeTab = 'salary'"
            >
              Thiết lập lương
            </button>
          </div>
        </div>

        <div class="px-6 py-5">
          <div v-if="activeTab === 'info'" class="grid grid-cols-12 gap-6">
            <div class="col-span-12 md:col-span-4">
              <div class="text-sm font-medium text-gray-700 mb-2">Ảnh nhân viên</div>
              <div class="border rounded-lg p-4 flex flex-col items-center justify-center bg-gray-50">
                <div class="h-20 w-20 rounded-full bg-gray-200 flex items-center justify-center text-sm text-gray-600 overflow-hidden">
                  <img v-if="form.avatar_path" :src="form.avatar_path" class="h-20 w-20 object-cover" />
                  <span v-else>Ảnh</span>
                </div>
                <div class="text-xs text-gray-500 mt-2 text-center">(Chưa hỗ trợ upload — sẽ bổ sung)</div>
              </div>
            </div>

            <div class="col-span-12 md:col-span-8">
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Mã nhân viên</label>
                  <input
                    v-model="form.code"
                    type="text"
                    :disabled="true"
                    :placeholder="editing ? '' : 'Tự động sinh'"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50"
                  />
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Trạng thái</label>
                  <select v-model="form.status" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                    <option value="active">Đang làm</option>
                    <option value="inactive">Đã nghỉ</option>
                  </select>
                </div>

                <div class="col-span-2">
                  <label class="block text-sm font-medium text-gray-700 mb-1">Tên nhân viên *</label>
                  <input v-model="form.name" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md" />
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Số điện thoại</label>
                  <input v-model="form.phone" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md" />
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                  <input v-model="form.email" type="email" class="w-full px-3 py-2 border border-gray-300 rounded-md" />
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Chi nhánh trả lương</label>
                  <select v-model="form.warehouse_id" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                    <option :value="null">-</option>
                    <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                  </select>
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Chi nhánh làm việc</label>
                  <div class="border rounded-md p-2 max-h-32 overflow-auto">
                    <label v-for="w in warehouses" :key="w.id" class="flex items-center gap-2 text-sm py-1 cursor-pointer">
                      <input type="checkbox" :value="w.id" v-model="form.work_warehouse_ids" />
                      <span>{{ w.name }}</span>
                    </label>
                  </div>
                </div>

                <div class="col-span-2">
                  <button class="text-sm text-blue-600 hover:text-blue-800" @click="showMoreInfo = !showMoreInfo">
                    {{ showMoreInfo ? 'Ẩn thông tin thêm' : 'Thêm thông tin' }}
                  </button>
                </div>

                <template v-if="showMoreInfo">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Mã chấm công</label>
                    <input v-model="form.attendance_code" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md" />
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Số CMND/CCCD</label>
                    <input v-model="form.id_number" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md" />
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Phòng ban</label>
                    <input v-model="form.department" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md" />
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Chức danh</label>
                    <input v-model="form.title" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md" />
                  </div>
                  <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Ghi chú</label>
                    <textarea v-model="form.notes" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md"></textarea>
                  </div>
                </template>
              </div>
            </div>
          </div>

          <div v-else class="grid grid-cols-2 gap-4">
            <div class="col-span-2 text-sm text-gray-600">
              Thiết lập lương cơ bản (đơn giản). Nếu chọn “Mẫu lương” thì hệ thống sẽ tạo cấu hình lương cho nhân viên.
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Mẫu lương</label>
              <select v-model="salaryForm.salary_template_id" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                <option :value="null">-</option>
                <option v-for="t in salaryTemplates" :key="t.id" :value="t.id">{{ t.name }}</option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Chi nhánh trả lương</label>
              <select v-model="salaryForm.pay_warehouse_id" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                <option :value="null">(Theo nhân viên)</option>
                <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
              </select>
            </div>

            <div class="col-span-2">
              <label class="inline-flex items-center gap-2 text-sm cursor-pointer">
                <input type="checkbox" v-model="salaryForm.enable_commission" />
                <span>Áp dụng hoa hồng</span>
              </label>
            </div>

            <div v-if="salaryForm.enable_commission">
              <label class="block text-sm font-medium text-gray-700 mb-1">Tỷ lệ hoa hồng (%)</label>
              <input v-model.number="salaryForm.commission_rate" type="number" step="0.01" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-md" />
            </div>
          </div>
        </div>

        <div class="px-6 py-4 border-t flex items-center justify-end gap-3">
          <button class="px-4 py-2 rounded border" @click="close">Bỏ qua</button>
          <button class="px-4 py-2 rounded bg-green-600 text-white hover:bg-green-700" @click="save" :disabled="saving">
            Lưu
          </button>
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
import warehouseApi from '@/api/warehouseApi'

export default {
  name: 'EmployeeList',
  setup() {
    const loading = ref(false)
    const saving = ref(false)
    const employees = ref([])
    const warehouses = ref([])
    const salaryTemplates = ref([])
    const search = ref('')

    const filters = ref({
      status: '',
      warehouse_id: null,
      work_warehouse_id: null,
      department: '',
      title: ''
    })

    const showModal = ref(false)
    const editing = ref(null)
    const activeTab = ref('info')
    const showMoreInfo = ref(false)
    const form = ref({
      warehouse_id: null,
      code: '',
      attendance_code: '',
      name: '',
      phone: '',
      email: '',
      id_number: '',
      department: '',
      title: '',
      avatar_path: '',
      work_warehouse_ids: [],
      status: 'active',
      notes: ''
    })

    const salaryForm = ref({
      salary_template_id: null,
      pay_warehouse_id: null,
      enable_commission: false,
      commission_rate: 0
    })

    const toast = ref({ show: false, type: 'success', message: '' })

    let debounceTimer = null
    const debouncedLoad = () => {
      clearTimeout(debounceTimer)
      debounceTimer = setTimeout(load, 300)
    }

    const showToast = (message, type = 'success') => {
      toast.value = { show: true, type, message }
      setTimeout(() => (toast.value.show = false), 3000)
    }

    const loadWarehouses = async () => {
      try {
        const res = await warehouseApi.getWarehouses({ per_page: 200 })
        // warehouseApi trả axios raw -> cần .data
        warehouses.value = res?.data?.data || res?.data || []
      } catch {
        warehouses.value = []
      }
    }

    const loadSalaryTemplates = async () => {
      try {
        const res = await employeeApi.getSalaryTemplates({})
        salaryTemplates.value = res?.data?.data || []
      } catch {
        salaryTemplates.value = []
      }
    }

    const load = async () => {
      loading.value = true
      try {
        const params = {
          search: search.value,
          status: filters.value.status || undefined,
          warehouse_id: filters.value.warehouse_id || undefined,
          work_warehouse_id: filters.value.work_warehouse_id || undefined,
          department: filters.value.department || undefined,
          title: filters.value.title || undefined,
          per_page: 50
        }
        const res = await employeeApi.getEmployees(params)
        employees.value = res.data?.data || []
      } catch (e) {
        showToast('Lỗi khi tải danh sách nhân viên', 'error')
      } finally {
        loading.value = false
      }
    }

    const openCreate = () => {
      editing.value = null
      activeTab.value = 'info'
      showMoreInfo.value = false
      form.value = {
        warehouse_id: null,
        code: '',
        attendance_code: '',
        name: '',
        phone: '',
        email: '',
        id_number: '',
        department: '',
        title: '',
        avatar_path: '',
        work_warehouse_ids: [],
        status: 'active',
        notes: ''
      }
      salaryForm.value = { salary_template_id: null, pay_warehouse_id: null, enable_commission: false, commission_rate: 0 }
      showModal.value = true
    }

    const openEdit = (e) => {
      editing.value = e
      activeTab.value = 'info'
      showMoreInfo.value = false
      form.value = {
        warehouse_id: e.warehouse_id ?? null,
        code: e.code,
        attendance_code: e.attendance_code || '',
        name: e.name,
        phone: e.phone || '',
        email: e.email || '',
        id_number: e.id_number || '',
        department: e.department || '',
        title: e.title || '',
        avatar_path: e.avatar_path || '',
        work_warehouse_ids: (e.work_warehouses || []).map((x) => x.id),
        status: e.status || 'active',
        notes: e.notes || ''
      }
      salaryForm.value = { salary_template_id: null, pay_warehouse_id: null, enable_commission: false, commission_rate: 0 }
      showModal.value = true
    }

    const close = () => {
      showModal.value = false
    }

    const save = async () => {
      saving.value = true
      try {
        if (editing.value) {
          await employeeApi.updateEmployee(editing.value.id, form.value)
          showToast('Đã cập nhật nhân viên')
        } else {
          const created = await employeeApi.createEmployee(form.value)
          const employee = created?.data?.data
          if (employee && salaryForm.value.salary_template_id) {
            await employeeApi.createEmployeeSalaryConfig({
              employee_id: employee.id,
              salary_template_id: salaryForm.value.salary_template_id,
              pay_warehouse_id: salaryForm.value.pay_warehouse_id || form.value.warehouse_id || null,
              commission_rate: salaryForm.value.enable_commission ? salaryForm.value.commission_rate : null
            })
          }
          showToast('Đã tạo nhân viên')
        }
        showModal.value = false
        await load()
      } catch (e) {
        showToast('Lỗi khi lưu nhân viên', 'error')
      } finally {
        saving.value = false
      }
    }

    const remove = async (e) => {
      if (!confirm(`Xóa nhân viên ${e.code} - ${e.name}?`)) return
      try {
        await employeeApi.deleteEmployee(e.id)
        showToast('Đã xóa nhân viên')
        await load()
      } catch {
        showToast('Lỗi khi xóa nhân viên', 'error')
      }
    }

    onMounted(async () => {
      await loadWarehouses()
      await loadSalaryTemplates()
      await load()
    })

    return {
      loading,
      saving,
      employees,
      warehouses,
      salaryTemplates,
      search,
      filters,
      debouncedLoad,
      showModal,
      editing,
      activeTab,
      showMoreInfo,
      form,
      salaryForm,
      openCreate,
      openEdit,
      close,
      save,
      remove,
      toast
    }
  }
}
</script>
