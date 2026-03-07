<template>
  <div class="bg-white">
    <div class="px-4 py-3 border-b flex items-center justify-between gap-3">
      <div>
        <h1 class="text-xl font-semibold text-gray-900">Bảng chấm công</h1>
      </div>

      <div class="flex items-center gap-2">
        <div class="relative w-[220px]">
          <input v-model="filters.search" type="text" placeholder="Tìm kiếm nhân viên" class="w-full pl-8 pr-2 py-1.5 text-sm border border-gray-300 rounded-md" />
          <div class="absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 text-sm">🔎</div>
        </div>

        <select v-model="filters.view" class="px-2 py-1.5 text-sm border border-gray-300 rounded-md bg-white">
          <option value="week">Theo tuần</option>
        </select>

        <div class="flex items-center gap-1">
          <button class="px-2 py-1.5 text-sm rounded border hover:bg-gray-50" @click="goPrevWeek">‹</button>
          <div class="px-2 py-1.5 text-sm rounded border bg-white min-w-[170px] text-center">Tuần {{ weekLabel }}</div>
          <button class="px-2 py-1.5 text-sm rounded border hover:bg-gray-50" @click="goNextWeek">›</button>
          <button class="px-3 py-1.5 text-sm rounded border hover:bg-gray-50" @click="goThisWeek">Chọn</button>
        </div>

        <div class="relative">
          <select v-model="filters.groupBy" class="px-2 py-1.5 text-sm border border-gray-300 rounded-md bg-white">
            <option value="shift">Xem theo ca</option>
            <option value="employee">Xem theo nhân viên (sắp có)</option>
          </select>
        </div>

        <button class="px-3 py-1.5 text-sm rounded border hover:bg-gray-50" type="button" @click="recalculateWeek">Duyệt chấm công</button>
      </div>
    </div>

    <div v-if="loading" class="flex justify-center items-center py-12">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
      <span class="ml-2 text-gray-600">Đang tải...</span>
    </div>

    <div v-else class="overflow-auto" style="max-height: calc(100vh - 190px);">
      <table class="min-w-[1240px] w-full border-separate border-spacing-0">
        <thead class="sticky top-0 z-10">
          <tr>
            <th class="bg-gray-50 text-left px-3 py-2 text-xs font-medium text-gray-900 border-b border-gray-200 sticky left-0 z-20 min-w-[180px]">
              <div class="flex items-center justify-between">
                <span>Ca làm việc</span>
                <button class="w-6 h-6 text-sm rounded-full border bg-white hover:bg-gray-50" title="Thêm lịch" type="button" @click="showToast('Tạo lịch làm việc: dùng màn Lịch làm việc', 'error')">+</button>
              </div>
            </th>
            <th v-for="d in weekDays" :key="d.key" class="bg-gray-50 text-left px-2 py-2 text-xs font-medium text-gray-900 border-b border-gray-200 min-w-[140px]">
              <div class="flex items-center gap-1.5">
                <span>{{ d.label }}</span>
                <span class="inline-flex items-center justify-center w-6 h-6 text-xs rounded-full" :class="d.isToday ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600'">{{ d.day }}</span>
              </div>
            </th>
          </tr>
        </thead>

        <tbody>
          <tr v-for="sh in shifts" :key="sh.id" class="hover:bg-gray-50">
            <td class="px-3 py-2 border-b border-gray-100 sticky left-0 bg-white z-10">
              <div class="font-medium text-sm text-gray-900">{{ sh.name }}</div>
              <div class="text-xs text-gray-600">{{ sh.start_time }} - {{ sh.end_time }}</div>
            </td>

            <td v-for="d in weekDays" :key="sh.id + '-' + d.key" class="p-1.5 border-b border-gray-100 align-top">
              <div class="flex flex-col gap-1.5">
                <button
                  v-for="item in getShiftDayItems(sh.id, d.key)"
                  :key="item.schedule.id"
                  class="text-left px-2 py-1.5 rounded-md border"
                  :class="cardClass(item)"
                  @click="openAttendance(item)"
                >
                  <div class="font-medium text-xs leading-tight">{{ item.employee.name }}</div>
                  <div class="text-xs leading-tight mt-0.5">
                    <span>{{ item.checkInText }}</span>
                    <span class="mx-0.5">-</span>
                    <span>{{ item.checkOutText }}</span>
                  </div>
                  <div v-if="item.meta" class="text-[10px] mt-0.5">{{ item.meta }}</div>
                </button>
              </div>
            </td>
          </tr>

          <tr v-if="shifts.length === 0">
            <td :colspan="weekDays.length + 1" class="p-8 text-center text-gray-500">Chưa có ca làm việc</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Attendance modal -->
    <div v-if="modal.show" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
      <div class="bg-white rounded-xl w-full max-w-4xl p-6">
        <div class="flex items-start justify-between gap-6">
          <div class="flex-1">
            <div class="flex items-center gap-2">
              <h2 class="text-xl font-semibold">Chấm công</h2>
              <span class="px-2 py-1 rounded bg-gray-100 text-gray-700 text-xs">{{ modal.employee?.name }}</span>
              <span class="px-2 py-1 rounded bg-gray-100 text-gray-700 text-xs">{{ modal.employee?.code }}</span>
              <span class="px-2 py-1 rounded text-xs" :class="statusBadgeClass(modal.previewState)">{{ modal.previewLabel }}</span>
            </div>

            <div class="mt-3 grid grid-cols-12 gap-4 items-center">
              <div class="col-span-6">
                <div class="text-sm text-gray-600">Thời gian</div>
                <div class="text-sm font-medium text-gray-900">{{ modal.weekdayLabel }}, {{ formatDateVN(modal.workDate) }}</div>
              </div>
              <div class="col-span-6">
                <div class="text-sm text-gray-600">Ca làm việc</div>
                <div class="text-sm font-medium text-gray-900">{{ modal.shift?.name }} ({{ modal.shift?.start_time }} - {{ modal.shift?.end_time }})</div>
              </div>
            </div>

            <div class="mt-3">
              <div class="text-sm text-gray-600">Ghi chú</div>
              <input v-model="modal.form.notes" type="text" class="w-full px-3 py-2 border rounded-md" placeholder="" />
            </div>
          </div>

          <button class="text-gray-500 hover:text-gray-700" @click="closeModal">✕</button>
        </div>

        <div class="mt-4 border-b">
          <div class="flex items-center gap-6 text-sm">
            <button class="py-3" :class="modal.tab === 'attendance' ? 'text-blue-600 border-b-2 border-blue-600 font-medium' : 'text-gray-600'" @click="modal.tab='attendance'">Chấm công</button>
            <button class="py-3" :class="modal.tab === 'history' ? 'text-blue-600 border-b-2 border-blue-600 font-medium' : 'text-gray-600'" @click="modal.tab='history'">Lịch sử chấm công</button>
            <button class="py-3 text-gray-400" type="button">Phạt vi phạm</button>
            <button class="py-3 text-gray-400" type="button">Thưởng</button>
          </div>
        </div>

        <div class="mt-4" v-if="modal.tab === 'attendance'">
          <div class="flex items-center gap-6">
            <div class="font-medium text-gray-900">Chấm công</div>
            <label class="inline-flex items-center gap-2">
              <input type="radio" value="work" v-model="modal.form.attendance_type" />
              <span>Đi làm</span>
            </label>
            <label class="inline-flex items-center gap-2">
              <input type="radio" value="leave_paid" v-model="modal.form.attendance_type" />
              <span>Nghỉ có phép</span>
            </label>
            <label class="inline-flex items-center gap-2">
              <input type="radio" value="leave_unpaid" v-model="modal.form.attendance_type" />
              <span>Nghỉ không phép</span>
            </label>
          </div>

          <div class="mt-4 grid grid-cols-12 gap-4 items-center" v-if="modal.form.attendance_type === 'work'">
            <div class="col-span-12 md:col-span-6">
              <div class="flex items-center gap-4">
                <label class="inline-flex items-center gap-2">
                  <input type="checkbox" v-model="modal.form.has_check_in" />
                  <span>Vào</span>
                </label>
                <input :disabled="!modal.form.has_check_in" v-model="modal.form.check_in_time" type="time" class="px-3 py-2 border rounded-md" />
              </div>
            </div>
            <div class="col-span-12 md:col-span-6">
              <div class="flex items-center gap-4">
                <label class="inline-flex items-center gap-2">
                  <input type="checkbox" v-model="modal.form.has_check_out" />
                  <span>Ra</span>
                </label>
                <input :disabled="!modal.form.has_check_out" v-model="modal.form.check_out_time" type="time" class="px-3 py-2 border rounded-md" />
              </div>
            </div>

            <div class="col-span-12">
              <div class="flex items-center gap-4">
                <label class="inline-flex items-center gap-2">
                  <input type="checkbox" v-model="modal.form.has_ot" />
                  <span>Làm thêm</span>
                </label>
                <input :disabled="!modal.form.has_ot" v-model.number="modal.form.ot_hours" type="number" min="0" class="w-20 px-3 py-2 border rounded-md" />
                <span>giờ</span>
                <input :disabled="!modal.form.has_ot" v-model.number="modal.form.ot_minutes" type="number" min="0" max="59" class="w-20 px-3 py-2 border rounded-md" />
                <span>phút</span>
              </div>
            </div>
          </div>

          <div class="mt-6 flex items-center justify-end gap-3">
            <button class="px-4 py-2 rounded border" @click="closeModal">Bỏ qua</button>
            <button class="px-4 py-2 rounded bg-blue-600 text-white hover:bg-blue-700" :disabled="modal.saving" @click="saveAttendance">Lưu</button>
          </div>
        </div>

        <div class="mt-4" v-else-if="modal.tab === 'history'">
          <div class="text-sm font-medium text-gray-900 mb-2">Log chấm công trong ngày</div>
          <div class="border rounded-lg overflow-hidden">
            <table class="w-full">
              <thead class="bg-gray-50">
                <tr>
                  <th class="text-left p-3 text-sm font-medium text-gray-600">Thời gian</th>
                  <th class="text-left p-3 text-sm font-medium text-gray-600">Thiết bị</th>
                  <th class="text-left p-3 text-sm font-medium text-gray-600">Loại</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200">
                <tr v-for="l in modal.logs" :key="l.id">
                  <td class="p-3 text-sm text-gray-900">{{ formatDateTimeVN(l.punched_at) }}</td>
                  <td class="p-3 text-sm text-gray-700">{{ l.device?.name || '-' }}</td>
                  <td class="p-3 text-sm text-gray-700">{{ l.event_type || '-' }}</td>
                </tr>
                <tr v-if="modal.logs.length === 0">
                  <td colspan="3" class="p-6 text-center text-gray-500">Không có log</td>
                </tr>
              </tbody>
            </table>
          </div>
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
  name: 'AttendanceSheet',
  setup() {
    const loading = ref(false)
    const employees = ref([])
    const shifts = ref([])
    const schedules = ref([])
    const filters = ref({ search: '', view: 'week', groupBy: 'shift', employee_id: null })

    const weekStart = ref(toYmd(getMonday(new Date())))
    const weekDays = ref([])
    const weekLabel = ref('')

    const modal = ref({
      show: false,
      tab: 'attendance',
      saving: false,
      schedule: null,
      employee: null,
      shift: null,
      workDate: null,
      weekdayLabel: '',
      logs: [],
      previewState: 'not_checked',
      previewLabel: 'Chưa chấm công',
      form: {
        attendance_type: 'work',
        has_check_in: true,
        has_check_out: true,
        check_in_time: '',
        check_out_time: '',
        has_ot: false,
        ot_hours: 0,
        ot_minutes: 0,
        notes: ''
      }
    })

    const toast = ref({ show: false, type: 'success', message: '' })
    const showToast = (message, type = 'success') => {
      toast.value = { show: true, type, message }
      setTimeout(() => (toast.value.show = false), 3000)
    }

    const loadEmployees = async () => {
      const res = await employeeApi.getEmployees({ per_page: 500 })
      employees.value = res.data?.data || []
    }

    const loadShifts = async () => {
      const res = await employeeApi.getShifts({ per_page: 200 })
      shifts.value = res.data?.data || []
    }

    const load = async () => {
      loading.value = true
      try {
        const from = weekStart.value
        const to = toYmd(addDays(from, 6))
        const res = await employeeApi.getSchedules({ employee_id: filters.value.employee_id, from, to, per_page: 5000 })
        schedules.value = res.data?.data || []
      } catch {
        showToast('Lỗi khi tải bảng chấm công', 'error')
      } finally {
        loading.value = false
      }
    }

    const rebuildWeekDays = () => {
      const start = weekStart.value
      const labels = ['Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7', 'Chủ nhật']
      const todayYmd = toYmd(new Date())
      weekDays.value = Array.from({ length: 7 }, (_, i) => {
        const d = addDays(start, i)
        const key = toYmd(d)
        const day = String(parseInt(key.split('-')[2], 10)).padStart(2, '0')
        return { key, label: labels[i], day, isToday: key === todayYmd }
      })
    }

    const rebuildWeekLabel = () => {
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

    const getShiftDayItems = (shiftId, dateYmd) => {
      const list = schedules.value
        .filter((s) => (s.shift_id || s.shift?.id) === shiftId && toYmd(s.work_date) === dateYmd)
        .map((s) => {
          const employee = s.employee || employees.value.find((e) => e.id === s.employee_id) || { id: s.employee_id, name: 'NV', code: '' }
          const rec = s.timekeeping_record || s.timekeepingRecord || s.timekeeping_record
          const derived = buildDerivedState(s, rec)
          const employeeMatch = `${employee.code || ''} ${employee.name || ''}`.toLowerCase()
          if (filters.value.search && !employeeMatch.includes(filters.value.search.trim().toLowerCase())) {
            return null
          }
          return { schedule: s, employee, record: rec, ...derived }
        })
        .filter(Boolean)

      // Sort by employee name
      return list.sort((a, b) => (a.employee.name || '').localeCompare(b.employee.name || ''))
    }

    const cardClass = (item) => {
      switch (item.state) {
        case 'on_time':
          return 'bg-blue-50 border-blue-200 text-blue-900'
        case 'late_early':
          return 'bg-purple-50 border-purple-200 text-purple-900'
        case 'missing':
          return 'bg-red-50 border-red-200 text-red-900'
        case 'leave':
          return 'bg-gray-100 border-gray-200 text-gray-800'
        default:
          return 'bg-orange-50 border-orange-200 text-orange-900'
      }
    }

    const statusBadgeClass = (state) => {
      switch (state) {
        case 'on_time':
          return 'bg-blue-100 text-blue-700'
        case 'late_early':
          return 'bg-purple-100 text-purple-700'
        case 'missing':
          return 'bg-red-100 text-red-700'
        case 'leave':
          return 'bg-gray-200 text-gray-700'
        default:
          return 'bg-orange-100 text-orange-700'
      }
    }

    const openAttendance = async (item) => {
      const s = item.schedule
      const employee = item.employee
      const sh = s.shift || shifts.value.find((x) => x.id === s.shift_id) || null
      const dateYmd = toYmd(s.work_date)

      modal.value.show = true
      modal.value.tab = 'attendance'
      modal.value.saving = false
      modal.value.schedule = s
      modal.value.employee = employee
      modal.value.shift = sh
      modal.value.workDate = dateYmd
      modal.value.weekdayLabel = weekdayOfYmd(dateYmd)
      modal.value.previewState = item.state
      modal.value.previewLabel = item.label

      const rec = item.record
      const attendanceType = (rec?.attendance_type || 'work')
      const checkInTime = rec?.check_in_at ? formatTime(rec.check_in_at) : ''
      const checkOutTime = rec?.check_out_at ? formatTime(rec.check_out_at) : ''

      const otTotal = Number(rec?.ot_minutes || 0)

      modal.value.form = {
        attendance_type: attendanceType,
        has_check_in: attendanceType === 'work' ? !!checkInTime : false,
        has_check_out: attendanceType === 'work' ? !!checkOutTime : false,
        check_in_time: checkInTime,
        check_out_time: checkOutTime,
        has_ot: attendanceType === 'work' ? otTotal > 0 : false,
        ot_hours: Math.floor(otTotal / 60),
        ot_minutes: otTotal % 60,
        notes: rec?.notes || ''
      }

      // Load logs for the day
      try {
        const logsRes = await employeeApi.getAttendanceLogs({ employee_id: employee.id, from: dateYmd, to: dateYmd, per_page: 200 })
        modal.value.logs = logsRes.data?.data || []
      } catch {
        modal.value.logs = []
      }
    }

    const closeModal = () => {
      modal.value.show = false
    }

    const saveAttendance = async () => {
      if (!modal.value.schedule) return
      modal.value.saving = true
      try {
        const payload = {
          employee_work_schedule_id: modal.value.schedule.id,
          attendance_type: modal.value.form.attendance_type,
          notes: modal.value.form.notes || null
        }

        if (modal.value.form.attendance_type === 'work') {
          payload.check_in_time = modal.value.form.has_check_in ? (modal.value.form.check_in_time || null) : null
          payload.check_out_time = modal.value.form.has_check_out ? (modal.value.form.check_out_time || null) : null
          const otMinutes = modal.value.form.has_ot ? (Number(modal.value.form.ot_hours || 0) * 60 + Number(modal.value.form.ot_minutes || 0)) : 0
          payload.ot_minutes = otMinutes
        }

        const res = await employeeApi.upsertTimekeepingRecord(payload)
        const record = res.data?.data

        // Patch schedule in-place so UI updates without refetch
        const idx = schedules.value.findIndex((x) => x.id === modal.value.schedule.id)
        if (idx >= 0) {
          schedules.value[idx] = { ...schedules.value[idx], timekeepingRecord: record }
        }

        showToast('Đã lưu chấm công')
        closeModal()
      } catch {
        showToast('Lỗi khi lưu chấm công', 'error')
      } finally {
        modal.value.saving = false
      }
    }

    const recalculateWeek = async () => {
      try {
        const from = weekStart.value
        const to = toYmd(addDays(from, 6))
        await employeeApi.recalculateTimekeeping({ from, to, employee_id: filters.value.employee_id })
        await load()
        showToast('Đã duyệt/tính lại chấm công')
      } catch {
        showToast('Lỗi khi duyệt chấm công', 'error')
      }
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
      employees,
      shifts,
      schedules,
      filters,
      weekDays,
      weekLabel,
      load,
      goPrevWeek,
      goNextWeek,
      goThisWeek,
      getShiftDayItems,
      cardClass,
      statusBadgeClass,
      openAttendance,
      closeModal,
      saveAttendance,
      recalculateWeek,
      modal,
      toast,
      showToast,
      formatDateVN,
      formatDateTimeVN
    }
  }
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
  const day = d.getDay()
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

function weekdayOfYmd(ymd) {
  const d = parseYmd(ymd)
  const map = ['Chủ nhật', 'Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7']
  return map[d.getDay()]
}

function formatTime(dt) {
  if (dt === null || dt === undefined) return '--:--'
  const s = String(dt).trim()

  // Already a time string
  if (/^\d{2}:\d{2}(:\d{2})?$/.test(s)) {
    return s.slice(0, 5)
  }

  // Normalize common server formats
  const isoLike = s.includes('T') ? s : s.replace(' ', 'T')
  const d = new Date(isoLike)
  if (!Number.isNaN(d.getTime())) {
    const hh = String(d.getHours()).padStart(2, '0')
    const mm = String(d.getMinutes()).padStart(2, '0')
    return `${hh}:${mm}`
  }

  // Fallback: old behavior
  const t = s.includes('T') ? s.split('T')[1] : s
  return t.slice(0, 5)
}

function formatDateTimeVN(dt) {
  if (dt === null || dt === undefined) return ''
  const s = String(dt).trim()
  const isoLike = s.includes('T') ? s : s.replace(' ', 'T')
  const d = new Date(isoLike)
  if (Number.isNaN(d.getTime())) return s
  const dd = String(d.getDate()).padStart(2, '0')
  const mm = String(d.getMonth() + 1).padStart(2, '0')
  const yyyy = d.getFullYear()
  const hh = String(d.getHours()).padStart(2, '0')
  const mi = String(d.getMinutes()).padStart(2, '0')
  return `${dd}/${mm}/${yyyy} ${hh}:${mi}`
}

function buildDerivedState(schedule, record) {
  const attendanceType = record?.attendance_type || 'work'

  if (attendanceType === 'leave_paid') {
    return { state: 'leave', label: 'Nghỉ có phép', checkInText: '--:--', checkOutText: '--:--', meta: 'Nghỉ' }
  }
  if (attendanceType === 'leave_unpaid') {
    return { state: 'leave', label: 'Nghỉ không phép', checkInText: '--:--', checkOutText: '--:--', meta: 'Nghỉ' }
  }

  const checkIn = record?.check_in_at ? formatTime(record.check_in_at) : '--:--'
  const checkOut = record?.check_out_at ? formatTime(record.check_out_at) : '--:--'

  const hasIn = !!record?.check_in_at
  const hasOut = !!record?.check_out_at

  if (!record || (!hasIn && !hasOut)) {
    return { state: 'not_checked', label: 'Chưa chấm công', checkInText: '--:--', checkOutText: '--:--', meta: 'Chưa chấm công' }
  }

  if (hasIn !== hasOut) {
    const missingText = hasIn ? 'Chưa chấm ra' : 'Chưa chấm vào'
    return { state: 'missing', label: 'Chấm công thiếu', checkInText: checkIn, checkOutText: checkOut, meta: missingText }
  }

  const late = Number(record?.late_minutes || 0)
  const early = Number(record?.early_minutes || 0)
  const ot = Number(record?.ot_minutes || 0)

  if (late > 0 || early > 0) {
    const parts = []
    if (late > 0) parts.push(`Đi muộn ${late}p`)
    if (early > 0) parts.push(`Về sớm ${early}p`)
    if (ot > 0) parts.push(`Làm thêm ${Math.floor(ot / 60)}h ${ot % 60}p`)
    return { state: 'late_early', label: 'Đi muộn / Về sớm', checkInText: checkIn, checkOutText: checkOut, meta: parts.join(', ') }
  }

  if (ot > 0) {
    return { state: 'on_time', label: 'Đúng giờ', checkInText: checkIn, checkOutText: checkOut, meta: `Làm thêm ${Math.floor(ot / 60)}h ${ot % 60}p` }
  }

  return { state: 'on_time', label: 'Đúng giờ', checkInText: checkIn, checkOutText: checkOut, meta: '' }
}
</script>
