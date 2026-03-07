<template>
  <div class="bg-white">
    <div class="p-6 border-b flex items-center justify-between gap-4">
      <h1 class="text-2xl font-semibold text-gray-900">Lịch làm việc</h1>

      <div class="flex items-center gap-3">
        <button class="px-3 py-2 rounded border hover:bg-gray-50" @click="goPrevWeek">‹</button>
        <div class="px-3 py-2 rounded border bg-white min-w-[190px] text-center">
          Tuần {{ weekLabel }}
        </div>
        <button class="px-3 py-2 rounded border hover:bg-gray-50" @click="goNextWeek">›</button>
        <button class="px-4 py-2 rounded border hover:bg-gray-50" @click="goThisWeek">Tuần này</button>
      </div>
    </div>

    <div class="p-4 border-b bg-gray-50 flex flex-wrap items-center gap-3">
      <div class="flex-1 min-w-[260px]">
        <div class="relative">
          <input
            v-model="filters.search"
            type="text"
            placeholder="Tìm kiếm nhân viên"
            class="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md bg-white"
          />
          <div class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">🔎</div>
        </div>
      </div>

      <div class="min-w-[220px]">
        <select v-model="filters.employee_id" class="w-full px-3 py-2 border border-gray-300 rounded-md bg-white">
          <option :value="null">Tất cả nhân viên</option>
          <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.code }} - {{ e.name }}</option>
        </select>
      </div>

      <button class="px-4 py-2 rounded bg-blue-600 text-white hover:bg-blue-700" @click="load">Tải</button>
    </div>

    <div v-if="loading" class="flex justify-center items-center py-12">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
      <span class="ml-2 text-gray-600">Đang tải...</span>
    </div>

    <div v-else class="overflow-auto" style="max-height: calc(100vh - 210px);">
      <table class="min-w-[1180px] w-full border-separate border-spacing-0">
        <thead class="sticky top-0 z-10">
          <tr>
            <th class="bg-blue-50 text-left p-4 text-sm font-medium text-gray-900 border-b border-gray-200 sticky left-0 z-20 min-w-[280px]">Nhân viên</th>
            <th
              v-for="d in weekDays"
              :key="d.key"
              class="bg-blue-50 text-center p-4 text-sm font-medium text-gray-900 border-b border-gray-200 min-w-[140px]"
            >
              <div class="flex items-center justify-center gap-2">
                <span>{{ d.label }}</span>
                <span class="inline-flex items-center justify-center w-7 h-7 rounded-full" :class="d.isToday ? 'bg-blue-600 text-white' : 'bg-transparent text-gray-600'">{{ d.day }}</span>
              </div>
            </th>
            <th class="bg-blue-50 text-center p-4 text-sm font-medium text-gray-900 border-b border-gray-200 min-w-[160px]">Lương dự kiến</th>
          </tr>
        </thead>

        <tbody>
          <tr v-for="e in filteredEmployees" :key="e.id" class="hover:bg-gray-50">
            <td class="p-4 border-b border-gray-100 sticky left-0 bg-white z-10">
              <div class="font-medium text-gray-900">{{ e.name }}</div>
              <div class="text-sm text-gray-600">{{ e.code }}</div>
              <div v-if="e.department" class="text-sm text-gray-500">{{ e.department }}</div>
            </td>

            <td
              v-for="d in weekDays"
              :key="e.id + '-' + d.key"
              class="p-2 border-b border-gray-100 align-top relative group"
            >
              <div class="min-h-[44px] flex flex-col gap-2">
                <button
                  v-for="s in getCellSchedules(e.id, d.key)"
                  :key="s.id"
                  class="text-left px-3 py-2 rounded-lg bg-blue-100 text-blue-700 hover:bg-blue-200"
                  @click="openEditFromGrid(s)"
                  :title="scheduleTitle(s)"
                >
                  <div class="font-medium leading-5">{{ s.shift?.name || s.shift_name || 'Ca' }}</div>
                </button>
              </div>

              <button
                class="absolute top-2 right-2 w-7 h-7 rounded-full border bg-white shadow-sm opacity-0 group-hover:opacity-100 hover:bg-gray-50"
                title="Thêm lịch làm việc"
                @click="openAddFromGrid(e, d.key)"
              >
                +
              </button>
            </td>

            <td class="p-4 border-b border-gray-100 text-right">
              <div v-if="!e.salary_config" class="text-sm text-gray-500">Chưa thiết lập lương</div>
              <div v-else class="text-sm text-gray-900">—</div>
              <div class="text-xs text-gray-500">{{ getEmployeeScheduleCount(e.id) }} ca</div>
            </td>
          </tr>

          <tr v-if="filteredEmployees.length === 0">
            <td :colspan="weekDays.length + 2" class="p-8 text-center text-gray-500">Không có nhân viên phù hợp</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Add / Edit modal -->
    <div v-if="showModal" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
      <div class="bg-white rounded-xl w-full max-w-2xl p-6">
        <div class="flex items-center justify-between mb-2">
          <div>
            <h2 class="text-lg font-semibold">{{ editing ? 'Cập nhật lịch làm việc' : 'Thêm lịch làm việc' }}</h2>
            <div class="text-sm text-gray-600" v-if="modalEmployee && modalDate">
              {{ modalEmployee.name }} · {{ formatDateVN(modalDate) }}
            </div>
          </div>
          <button class="text-gray-500 hover:text-gray-700" @click="close">✕</button>
        </div>

        <div class="mt-4">
          <div class="flex items-center justify-between gap-3">
            <label class="block text-sm font-medium text-gray-700">Chọn ca làm việc</label>
            <button class="text-sm text-blue-600 hover:text-blue-800" type="button" @click="openCreateShift">+ Tạo ca mới</button>
          </div>

          <select v-model="form.shift_id" class="w-full mt-2 px-3 py-2 border border-gray-300 rounded-md">
            <option :value="null">Chọn ca...</option>
            <option v-for="sh in shifts" :key="sh.id" :value="sh.id">{{ sh.name }} ({{ sh.start_time }} - {{ sh.end_time }})</option>
          </select>

          <div class="grid grid-cols-2 gap-4 mt-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Slot</label>
              <input v-model.number="form.slot" type="number" min="1" max="20" class="w-full px-3 py-2 border border-gray-300 rounded-md" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Trạng thái</label>
              <select v-model="form.status" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                <option value="planned">planned</option>
                <option value="confirmed">confirmed</option>
              </select>
            </div>
          </div>

          <div class="mt-4 space-y-3">
            <label class="flex items-center justify-between gap-4">
              <div>
                <div class="font-medium text-gray-900">Lặp lại hàng tuần</div>
                <div class="text-sm text-gray-500">Tự tạo lịch cho các tuần tiếp theo (mặc định 4 tuần)</div>
              </div>
              <input type="checkbox" v-model="options.repeatWeekly" class="w-5 h-5" />
            </label>

            <label class="flex items-center justify-between gap-4">
              <div>
                <div class="font-medium text-gray-900">Thêm lịch tương tự cho nhân viên khác</div>
                <div class="text-sm text-gray-500">Áp dụng lịch cho các nhân viên được chọn</div>
              </div>
              <input type="checkbox" v-model="options.applyToOthers" class="w-5 h-5" />
            </label>

            <div v-if="options.applyToOthers" class="border rounded-lg p-3 bg-gray-50">
              <div class="text-sm font-medium text-gray-700 mb-2">Chọn nhân viên</div>
              <div class="max-h-40 overflow-auto space-y-2">
                <label
                  v-for="e in employeesForApply"
                  :key="e.id"
                  class="flex items-center gap-2 text-sm text-gray-800"
                >
                  <input type="checkbox" :value="e.id" v-model="options.otherEmployeeIds" class="w-4 h-4" />
                  <span>{{ e.code }} - {{ e.name }}</span>
                </label>
              </div>
            </div>
          </div>
        </div>

        <div class="flex items-center justify-end gap-3 mt-6">
          <button class="px-4 py-2 rounded border" @click="close">Bỏ qua</button>
          <button class="px-4 py-2 rounded bg-blue-600 text-white hover:bg-blue-700" @click="save" :disabled="saving">Lưu</button>
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

    <!-- Create shift modal (nested) -->
    <div v-if="createShiftModal" class="fixed inset-0 bg-black/40 flex items-center justify-center z-[60]">
      <div class="bg-white rounded-xl w-full max-w-xl p-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-semibold">Tạo ca mới</h3>
          <button class="text-gray-500 hover:text-gray-700" @click="closeCreateShift">✕</button>
        </div>

        <div class="grid grid-cols-12 gap-4">
          <div class="col-span-12">
            <label class="block text-sm font-medium text-gray-700 mb-1">Tên ca *</label>
            <input v-model="shiftForm.name" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="VD: Ca hành chính" />
          </div>

          <div class="col-span-6">
            <label class="block text-sm font-medium text-gray-700 mb-1">Giờ vào *</label>
            <input v-model="shiftForm.start_time" type="time" class="w-full px-3 py-2 border border-gray-300 rounded-md" />
          </div>
          <div class="col-span-6">
            <label class="block text-sm font-medium text-gray-700 mb-1">Giờ ra *</label>
            <input v-model="shiftForm.end_time" type="time" class="w-full px-3 py-2 border border-gray-300 rounded-md" />
          </div>

          <div class="col-span-6">
            <label class="block text-sm font-medium text-gray-700 mb-1">Cho phép đi muộn (phút)</label>
            <input v-model.number="shiftForm.allow_late_minutes" type="number" min="0" max="1440" class="w-full px-3 py-2 border border-gray-300 rounded-md" />
          </div>
          <div class="col-span-6">
            <label class="block text-sm font-medium text-gray-700 mb-1">Cho phép về sớm (phút)</label>
            <input v-model.number="shiftForm.allow_early_minutes" type="number" min="0" max="1440" class="w-full px-3 py-2 border border-gray-300 rounded-md" />
          </div>

          <div class="col-span-12">
            <label class="block text-sm font-medium text-gray-700 mb-1">Ghi chú</label>
            <textarea v-model="shiftForm.notes" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md"></textarea>
          </div>
        </div>

        <div class="flex items-center justify-end gap-3 mt-6">
          <button class="px-4 py-2 rounded border" @click="closeCreateShift">Bỏ qua</button>
          <button class="px-4 py-2 rounded bg-blue-600 text-white hover:bg-blue-700" @click="saveShift" :disabled="shiftSaving">
            Lưu
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue'
import employeeApi from '@/api/employeeApi'

export default {
  name: 'EmployeeSchedules',
  setup() {
    const loading = ref(false)
    const saving = ref(false)
    const employees = ref([])
    const shifts = ref([])
    const schedules = ref([])

    const filters = ref({ search: '', employee_id: null })

    const weekStart = ref(toYmd(getMonday(new Date())))

    const showModal = ref(false)
    const editing = ref(null)
    const modalEmployee = ref(null)
    const modalDate = ref(null)

    const form = ref({ employee_id: null, work_date: null, slot: 1, shift_id: null, shift_name: '', start_time: null, end_time: null, status: 'planned', notes: '' })
    const options = ref({ repeatWeekly: false, applyToOthers: false, otherEmployeeIds: [] })

    const createShiftModal = ref(false)
    const shiftSaving = ref(false)
    const shiftForm = ref({
      name: '',
      start_time: '',
      end_time: '',
      allow_late_minutes: 0,
      allow_early_minutes: 0,
      notes: ''
    })

    const toast = ref({ show: false, type: 'success', message: '' })
    const showToast = (message, type = 'success') => {
      toast.value = { show: true, type, message }
      setTimeout(() => (toast.value.show = false), 3000)
    }

    const loadEmployees = async () => {
      const res = await employeeApi.getEmployees({ per_page: 500 })
      employees.value = res.data?.data || []
      if (!filters.value.employee_id && employees.value.length) filters.value.employee_id = null
    }

    const loadShifts = async () => {
      const res = await employeeApi.getShifts({ per_page: 200 })
      shifts.value = res.data?.data || []
    }

    const openCreateShift = () => {
      shiftForm.value = {
        name: '',
        start_time: '',
        end_time: '',
        allow_late_minutes: 0,
        allow_early_minutes: 0,
        notes: ''
      }
      createShiftModal.value = true
    }

    const closeCreateShift = () => {
      createShiftModal.value = false
    }

    const saveShift = async () => {
      if (!shiftForm.value.name || !shiftForm.value.start_time || !shiftForm.value.end_time) {
        showToast('Vui lòng nhập tên ca và giờ vào/ra', 'error')
        return
      }

      shiftSaving.value = true
      try {
        const res = await employeeApi.createShift({
          name: shiftForm.value.name,
          start_time: shiftForm.value.start_time,
          end_time: shiftForm.value.end_time,
          allow_late_minutes: shiftForm.value.allow_late_minutes ?? 0,
          allow_early_minutes: shiftForm.value.allow_early_minutes ?? 0,
          notes: shiftForm.value.notes || null,
          status: 'active'
        })

        const created = res.data?.data
        await loadShifts()

        if (created?.id) {
          form.value.shift_id = created.id
        }

        showToast('Đã tạo ca mới')
        createShiftModal.value = false
      } catch {
        showToast('Lỗi khi tạo ca', 'error')
      } finally {
        shiftSaving.value = false
      }
    }

    const load = async () => {
      loading.value = true
      try {
        const from = weekStart.value
        const to = toYmd(addDays(from, 6))
        const res = await employeeApi.getSchedules({ employee_id: filters.value.employee_id, from, to, per_page: 5000 })
        schedules.value = res.data?.data || []
      } catch {
        showToast('Lỗi khi tải lịch làm việc', 'error')
      } finally {
        loading.value = false
      }
    }

    const openAddFromGrid = (employee, dateYmd) => {
      editing.value = null
      modalEmployee.value = employee
      modalDate.value = dateYmd

      const nextSlot = getNextSlot(employee.id, dateYmd)
      form.value = {
        employee_id: employee.id,
        work_date: dateYmd,
        slot: nextSlot,
        shift_id: null,
        shift_name: '',
        start_time: null,
        end_time: null,
        status: 'planned',
        notes: ''
      }
      options.value = { repeatWeekly: false, applyToOthers: false, otherEmployeeIds: [] }
      showModal.value = true
    }

    const openEditFromGrid = (s) => {
      editing.value = s
      modalEmployee.value = employees.value.find((x) => x.id === s.employee_id) || null
      modalDate.value = s.work_date

      form.value = {
        employee_id: s.employee_id,
        work_date: s.work_date,
        slot: s.slot || 1,
        shift_id: s.shift_id || (s.shift?.id ?? null),
        shift_name: s.shift_name || (s.shift?.name ?? ''),
        start_time: s.start_time || s.shift?.start_time || null,
        end_time: s.end_time || s.shift?.end_time || null,
        status: s.status || 'planned',
        notes: s.notes || ''
      }
      options.value = { repeatWeekly: false, applyToOthers: false, otherEmployeeIds: [] }
      showModal.value = true
    }

    const close = () => (showModal.value = false)

    const save = async () => {
      saving.value = true
      try {
        if (editing.value) {
          const payload = buildSchedulePayload(form.value)
          await employeeApi.updateSchedule(editing.value.id, payload)
          showToast('Đã cập nhật lịch')
        } else {
          const employeeIds = unique([form.value.employee_id, ...(options.value.applyToOthers ? options.value.otherEmployeeIds : [])]).filter(Boolean)
          const dates = buildDatesForCreate(form.value.work_date, options.value.repeatWeekly)
          const shift = shifts.value.find((x) => x.id === form.value.shift_id) || null

          await Promise.all(
            employeeIds.flatMap((employeeId) =>
              dates.map((dateYmd) => {
                const base = buildSchedulePayload({ ...form.value, employee_id: employeeId, work_date: dateYmd })
                const payload = {
                  ...base,
                  shift_name: base.shift_name || shift?.name || null,
                  start_time: base.start_time || shift?.start_time || null,
                  end_time: base.end_time || shift?.end_time || null
                }
                return employeeApi.saveSchedule(payload)
              })
            )
          )

          showToast('Đã lưu lịch')
        }
        showModal.value = false
        await load()
      } catch {
        showToast('Lỗi khi lưu lịch', 'error')
      } finally {
        saving.value = false
      }
    }

    // Helpers for grid
    const weekDays = ref([])
    const rebuildWeekDays = () => {
      const start = weekStart.value
      const labels = ['Thứ hai', 'Thứ ba', 'Thứ tư', 'Thứ năm', 'Thứ sáu', 'Thứ bảy', 'Chủ nhật']
      const todayYmd = toYmd(new Date())

      weekDays.value = Array.from({ length: 7 }, (_, i) => {
        const d = addDays(start, i)
        const key = toYmd(d)
        const parts = key.split('-')
        const day = String(parseInt(parts[2], 10))
        return { key, label: labels[i], day, isToday: key === todayYmd }
      })
    }

    const getCellSchedules = (employeeId, dateYmd) => {
      return schedules.value
        .filter((s) => s.employee_id === employeeId && s.work_date === dateYmd)
        .slice()
        .sort((a, b) => (a.slot || 1) - (b.slot || 1))
    }

    const getNextSlot = (employeeId, dateYmd) => {
      const slots = getCellSchedules(employeeId, dateYmd).map((s) => s.slot || 1)
      const max = slots.length ? Math.max(...slots) : 0
      return Math.min(max + 1, 20)
    }

    const getEmployeeScheduleCount = (employeeId) => {
      return schedules.value.filter((s) => s.employee_id === employeeId).length
    }

    const filteredEmployees = computed(() => {
      const search = (filters.value.search || '').trim().toLowerCase()
      return employees.value.filter((e) => {
        if (filters.value.employee_id && e.id !== filters.value.employee_id) return false
        if (!search) return true
        return `${e.code || ''} ${e.name || ''} ${e.phone || ''}`.toLowerCase().includes(search)
      })
    })

    const employeesForApply = computed(() => {
      const baseId = form.value.employee_id
      return employees.value.filter((e) => e.id !== baseId)
    })

    const weekLabel = ref('')
    const rebuildWeekLabel = () => {
      // Show: "1 - Th. 1 2026" like Kiot
      const start = parseYmd(weekStart.value)
      weekLabel.value = `${start.getDate()} - Th. ${start.getMonth() + 1} ${start.getFullYear()}`
    }

    const goPrevWeek = async () => {
      weekStart.value = toYmd(addDays(weekStart.value, -7))
      rebuildWeekDays()
      rebuildWeekLabel()
      await load()
    }
    const goNextWeek = async () => {
      weekStart.value = toYmd(addDays(weekStart.value, 7))
      rebuildWeekDays()
      rebuildWeekLabel()
      await load()
    }
    const goThisWeek = async () => {
      weekStart.value = toYmd(getMonday(new Date()))
      rebuildWeekDays()
      rebuildWeekLabel()
      await load()
    }

    const scheduleTitle = (s) => {
      const name = s.shift?.name || s.shift_name || 'Ca'
      const st = s.start_time || s.shift?.start_time
      const et = s.end_time || s.shift?.end_time
      const time = st && et ? `${st} - ${et}` : ''
      return time ? `${name} (${time})` : name
    }

    onMounted(async () => {
      rebuildWeekDays()
      rebuildWeekLabel()
      await loadEmployees()
      await loadShifts()
      await load()
    })

    return {
      loading,
      saving,
      employees,
      shifts,
      schedules,
      filters,
      weekStart,
      weekDays,
      weekLabel,
      filteredEmployees,
      employeesForApply,
      showModal,
      editing,
      form,
      options,
      modalEmployee,
      modalDate,
      createShiftModal,
      shiftForm,
      shiftSaving,
      load,
      goPrevWeek,
      goNextWeek,
      goThisWeek,
      openAddFromGrid,
      openEditFromGrid,
      openCreateShift,
      closeCreateShift,
      saveShift,
      close,
      save,
      getCellSchedules,
      getEmployeeScheduleCount,
      scheduleTitle,
      formatDateVN,
      toast,
      showToast,
    }
  }
}

function unique(arr) {
  return Array.from(new Set(arr))
}

function toYmd(date) {
  if (typeof date === 'string') return date.slice(0, 10)
  const d = new Date(date)
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}

function parseYmd(ymd) {
  if (ymd instanceof Date) return ymd
  const [y, m, d] = String(ymd).split('-').map((x) => parseInt(x, 10))
  return new Date(y, (m || 1) - 1, d || 1)
}

function addDays(dateOrYmd, days) {
  const d = parseYmd(dateOrYmd)
  const next = new Date(d)
  next.setDate(d.getDate() + days)
  return next
}

function getMonday(date) {
  const d = new Date(date)
  const day = d.getDay() // 0=Sun
  const diff = (day === 0 ? -6 : 1) - day
  d.setDate(d.getDate() + diff)
  d.setHours(0, 0, 0, 0)
  return d
}

function formatDateVN(ymd) {
  const d = parseYmd(ymd)
  const dd = String(d.getDate()).padStart(2, '0')
  const mm = String(d.getMonth() + 1).padStart(2, '0')
  const yyyy = d.getFullYear()
  return `${dd}/${mm}/${yyyy}`
}

function buildSchedulePayload(form) {
  const shiftId = form.shift_id || null
  return {
    employee_id: form.employee_id,
    work_date: form.work_date,
    slot: form.slot || 1,
    shift_id: shiftId,
    shift_name: form.shift_name || null,
    start_time: form.start_time || null,
    end_time: form.end_time || null,
    status: form.status || 'planned',
    notes: form.notes || null
  }
}

function buildDatesForCreate(workDate, repeatWeekly) {
  const dates = [workDate]
  if (!repeatWeekly) return dates

  // Default: create same weekday for next 4 weeks
  for (let i = 1; i <= 4; i++) {
    dates.push(toYmd(addDays(workDate, 7 * i)))
  }
  return dates
}
</script>
