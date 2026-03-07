<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';
import SetupSidebar from '@/Pages/Employees/Partials/SetupSidebar.vue';

const props = defineProps({
    overview: {
        type: Object,
        default: () => ({
            employees_total: 0,
            shifts_total: 0,
            schedules_total: 0,
            devices_total: 0,
            salary_configs_total: 0,
            payroll_sheets_total: 0,
            employees_scheduled_distinct: 0,
        }),
    },
    employees: {
        type: Array,
        default: () => [],
    },
    shifts: {
        type: Array,
        default: () => [],
    },
    branches: {
        type: Array,
        default: () => [],
    },
    departments: {
        type: Array,
        default: () => [],
    },
    jobTitles: {
        type: Array,
        default: () => [],
    },
});

const overview = ref({ ...props.overview });
watch(
    () => props.overview,
    (value) => {
        overview.value = { ...value };
    },
    { deep: true }
);

const localShifts = ref([...(props.shifts || [])]);
watch(
    () => props.shifts,
    (value) => {
        localShifts.value = [...(value || [])];
    },
    { deep: true }
);

const showEmployeeModal = ref(false);
const employeeRows = ref([]);
const employeeSubmitting = ref(false);
const employeeError = ref('');

const defaultBranchId = computed(() => props.branches?.[0]?.id || null);

const createEmployeeRow = () => ({
    code: '',
    attendance_code: '',
    name: '',
    phone: '',
    email: '',
    cccd: '',
    branch_id: defaultBranchId.value,
    department_id: null,
    job_title_id: null,
    notes: '',
    is_active: true,
});

const resetEmployeeRows = () => {
    employeeRows.value = [createEmployeeRow()];
    employeeError.value = '';
};

const openEmployeeModal = () => {
    resetEmployeeRows();
    showEmployeeModal.value = true;
};

const addEmployeeRow = () => {
    employeeRows.value.push(createEmployeeRow());
};

const removeEmployeeRow = (index) => {
    if (employeeRows.value.length === 1) {
        return;
    }
    employeeRows.value.splice(index, 1);
};

const submitEmployees = () => {
    const payload = employeeRows.value
        .map((row) => ({
            ...row,
            name: (row.name || '').trim(),
            phone: (row.phone || '').trim(),
            email: (row.email || '').trim(),
            code: (row.code || '').trim(),
            attendance_code: (row.attendance_code || '').trim(),
        }))
        .filter((row) => row.name.length > 0);

    if (payload.length === 0) {
        employeeError.value = 'Vui lòng nhập ít nhất 1 nhân viên.';
        return;
    }

    employeeSubmitting.value = true;
    employeeError.value = '';

    router.post(
        '/employees/bulk',
        { employees: payload },
        {
            preserveScroll: true,
            onSuccess: () => {
                showEmployeeModal.value = false;
                resetEmployeeRows();
            },
            onError: () => {
                employeeError.value = 'Không thể thêm nhân viên. Vui lòng kiểm tra dữ liệu.';
            },
            onFinish: () => {
                employeeSubmitting.value = false;
            },
        }
    );
};

const showShiftModal = ref(false);
const shiftForm = reactive({
    name: '',
    start_time: '',
    end_time: '',
    allow_late_minutes: 0,
    allow_early_minutes: 0,
    notes: '',
    status: 'active',
});
const shiftSubmitting = ref(false);
const shiftError = ref('');

const resetShiftForm = () => {
    shiftForm.name = '';
    shiftForm.start_time = '';
    shiftForm.end_time = '';
    shiftForm.allow_late_minutes = 0;
    shiftForm.allow_early_minutes = 0;
    shiftForm.notes = '';
    shiftForm.status = 'active';
    shiftError.value = '';
};

const openShiftModal = () => {
    resetShiftForm();
    showShiftModal.value = true;
};

const submitShift = async () => {
    if (!shiftForm.name || !shiftForm.start_time || !shiftForm.end_time) {
        shiftError.value = 'Vui lòng nhập tên ca và giờ bắt đầu/kết thúc.';
        return;
    }

    shiftSubmitting.value = true;
    shiftError.value = '';

    try {
        const response = await axios.post('/api/shifts', {
            name: shiftForm.name,
            start_time: shiftForm.start_time,
            end_time: shiftForm.end_time,
            allow_late_minutes: shiftForm.allow_late_minutes,
            allow_early_minutes: shiftForm.allow_early_minutes,
            notes: shiftForm.notes || null,
            status: shiftForm.status,
        });

        const createdShift = response?.data?.data;
        if (createdShift) {
            localShifts.value.unshift(createdShift);
        }

        showShiftModal.value = false;
        router.reload({ only: ['overview', 'shifts'], preserveScroll: true, preserveState: true });
    } catch (error) {
        shiftError.value = error?.response?.data?.message || 'Không thể tạo ca làm việc.';
    } finally {
        shiftSubmitting.value = false;
    }
};

const showScheduleModal = ref(false);
const scheduleLoading = ref(false);
const scheduleSavingKey = ref('');
const selectedShiftId = ref(null);
const employeeSearch = ref('');
const weekBaseDate = ref(new Date());
const schedulesMap = ref({});

watch(localShifts, (value) => {
    if (!selectedShiftId.value && value.length > 0) {
        selectedShiftId.value = value[0].id;
    }
}, { immediate: true, deep: true });

const toDateString = (date) => {
    const y = date.getFullYear();
    const m = `${date.getMonth() + 1}`.padStart(2, '0');
    const d = `${date.getDate()}`.padStart(2, '0');
    return `${y}-${m}-${d}`;
};

const startOfWeek = (dateValue) => {
    const date = new Date(dateValue);
    const day = date.getDay();
    const diff = day === 0 ? -6 : 1 - day;
    date.setDate(date.getDate() + diff);
    date.setHours(0, 0, 0, 0);
    return date;
};

const weekStart = computed(() => startOfWeek(weekBaseDate.value));

const weekDays = computed(() => {
    const start = weekStart.value;
    const dayNames = ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'];

    return Array.from({ length: 7 }).map((_, index) => {
        const date = new Date(start);
        date.setDate(start.getDate() + index);

        return {
            date: toDateString(date),
            label: dayNames[index],
            display: new Intl.DateTimeFormat('vi-VN', { day: '2-digit', month: '2-digit' }).format(date),
        };
    });
});

const weekLabel = computed(() => {
    const first = weekDays.value[0];
    const last = weekDays.value[6];
    if (!first || !last) {
        return '';
    }
    return `${first.display} - ${last.display}`;
});

const getScheduleKey = (employeeId, date) => `${employeeId}_${date}`;

const scheduleForCell = (employeeId, date) => {
    return schedulesMap.value[getScheduleKey(employeeId, date)] || null;
};

const filteredEmployees = computed(() => {
    const keyword = (employeeSearch.value || '').trim().toLowerCase();
    if (!keyword) {
        return props.employees;
    }

    return props.employees.filter((employee) => {
        const name = (employee.name || '').toLowerCase();
        const code = (employee.code || '').toLowerCase();
        const phone = (employee.phone || '').toLowerCase();
        return name.includes(keyword) || code.includes(keyword) || phone.includes(keyword);
    });
});

const formatShiftTime = (shift) => {
    const start = (shift?.start_time || '').slice(0, 5);
    const end = (shift?.end_time || '').slice(0, 5);
    if (!start || !end) {
        return '';
    }
    return `${start} - ${end}`;
};

const loadSchedules = async () => {
    if (!showScheduleModal.value || weekDays.value.length === 0) {
        return;
    }

    scheduleLoading.value = true;
    try {
        const response = await axios.get('/api/employee-schedules', {
            params: {
                from: weekDays.value[0].date,
                to: weekDays.value[6].date,
            },
        });

        const rows = response?.data?.data || [];
        const nextMap = {};

        rows.forEach((item) => {
            const employeeId = item.employee_id || item.employee?.id;
            if (!employeeId || !item.work_date) {
                return;
            }
            nextMap[getScheduleKey(employeeId, item.work_date)] = item;
        });

        schedulesMap.value = nextMap;
    } finally {
        scheduleLoading.value = false;
    }
};

const changeWeek = (offset) => {
    const next = new Date(weekBaseDate.value);
    next.setDate(next.getDate() + offset * 7);
    weekBaseDate.value = next;
    loadSchedules();
};

const openScheduleModal = () => {
    showScheduleModal.value = true;
    loadSchedules();
};

const toggleSchedule = async (employee, date) => {
    if (!selectedShiftId.value) {
        return;
    }

    const key = getScheduleKey(employee.id, date);
    scheduleSavingKey.value = key;

    try {
        const current = schedulesMap.value[key];
        if (current?.id) {
            await axios.delete(`/api/employee-schedules/${current.id}`);
            delete schedulesMap.value[key];
        } else {
            const response = await axios.post('/api/employee-schedules', {
                employee_id: employee.id,
                work_date: date,
                shift_id: selectedShiftId.value,
                slot: 1,
            });

            const created = response?.data?.data;
            if (created) {
                schedulesMap.value[key] = created;
            }
        }

        router.reload({ only: ['overview'], preserveScroll: true, preserveState: true });
    } finally {
        scheduleSavingKey.value = '';
    }
};

const quickItems = computed(() => [
    {
        key: 'employees',
        title: 'Thêm nhân viên',
        subtitle: `Cửa hàng đang có ${overview.value.employees_total} nhân viên.`,
        done: overview.value.employees_total > 0,
        linkText: 'Xem danh sách',
        actionText: 'Thêm nhân viên',
    },
    {
        key: 'shifts',
        title: 'Tạo ca làm việc',
        subtitle: `Cửa hàng đang có ${overview.value.shifts_total} ca làm việc.`,
        done: overview.value.shifts_total > 0,
        linkText: 'Xem danh sách',
        actionText: 'Tạo ca',
    },
    {
        key: 'schedules',
        title: 'Xếp lịch làm việc',
        subtitle: `Đã xếp lịch cho ${overview.value.employees_scheduled_distinct}/${overview.value.employees_total} nhân viên trong cửa hàng.`,
        done: overview.value.employees_total > 0 && overview.value.employees_scheduled_distinct > 0,
        linkText: 'Xem lịch',
        actionText: 'Xếp lịch',
    },
    {
        key: 'attendance',
        title: 'Hình thức chấm công',
        subtitle: `Cửa hàng đã thiết lập ${overview.value.devices_total} máy chấm công.`,
        done: overview.value.devices_total > 0,
        linkText: 'Xem chi tiết',
        actionText: 'Thiết lập',
    },
    {
        key: 'salary',
        title: 'Thiết lập lương',
        subtitle: `Đã thiết lập lương cho ${overview.value.salary_configs_total}/${overview.value.employees_total} nhân viên.`,
        done: overview.value.employees_total > 0 && overview.value.salary_configs_total > 0,
        linkText: 'Xem chi tiết',
        actionText: 'Thiết lập',
    },
    {
        key: 'paysheets',
        title: 'Thiết lập tính lương',
        subtitle: 'Cấu hình ngày tính lương, mẫu lương và tự động tạo bảng lương.',
        done: overview.value.payroll_sheets_total > 0,
        linkText: 'Xem chi tiết',
        actionText: 'Thiết lập',
    },
]);

const handleItemAction = (key) => {
    if (key === 'employees') {
        openEmployeeModal();
        return;
    }

    if (key === 'shifts') {
        openShiftModal();
        return;
    }

    if (key === 'schedules') {
        openScheduleModal();
        return;
    }

    if (key === 'attendance') {
        router.visit('/employees/attendance/settings');
        return;
    }

    if (key === 'salary') {
        router.visit('/employees');
        return;
    }

    if (key === 'paysheets') {
        router.visit('/employees/payroll/settings');
    }
};

const handleItemLink = (key) => {
    if (key === 'employees') {
        router.visit('/employees');
        return;
    }

    if (key === 'shifts') {
        openShiftModal();
        return;
    }

    if (key === 'schedules') {
        router.visit('/employees/schedules');
        return;
    }

    if (key === 'attendance') {
        router.visit('/employees/attendance/settings');
        return;
    }

    if (key === 'salary') {
        router.visit('/employees');
        return;
    }

    if (key === 'paysheets') {
        router.visit('/employees/payroll/settings');
    }
};
</script>

<template>
    <Head title="Thiết lập nhân viên - KiotViet Clone" />

    <AppLayout>
        <template #sidebar>
            <SetupSidebar active-main="init" />
        </template>

        <div class="space-y-4">
                <section class="rounded-lg border border-gray-200 bg-white">
                    <div class="border-b border-gray-100 px-5 py-4">
                        <h1 class="text-lg font-semibold text-gray-900">Thiết lập nhanh</h1>
                        <p class="mt-1 text-sm text-gray-500">
                            Chỉ vài bước cài đặt để quản lý nhân viên hiệu quả, tối ưu vận hành và tính lương chính xác.
                        </p>
                    </div>

                    <div class="divide-y divide-gray-100">
                        <div
                            v-for="item in quickItems"
                            :key="item.key"
                            class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div class="flex items-start gap-3">
                                <div
                                    class="mt-0.5 flex h-6 w-6 items-center justify-center rounded-full text-xs font-bold"
                                    :class="item.done ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                                >
                                    <span v-if="item.done">✓</span>
                                    <span v-else>•</span>
                                </div>

                                <div>
                                    <div class="font-medium text-gray-900">{{ item.title }}</div>
                                    <div class="text-sm text-gray-500">{{ item.subtitle }}</div>
                                    <button
                                        type="button"
                                        class="mt-1 text-sm text-blue-600 hover:text-blue-700"
                                        @click="handleItemLink(item.key)"
                                    >
                                        {{ item.linkText }}
                                    </button>
                                </div>
                            </div>

                            <button
                                type="button"
                                class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                                @click="handleItemAction(item.key)"
                            >
                                {{ item.actionText }}
                            </button>
                        </div>
                    </div>
                </section>

                <section class="rounded-lg border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-800">
                    Mục "Khởi tạo" đang được bật. Bạn có thể thao tác trực tiếp trên các nút bên trên để hoàn tất thiết lập nhân viên.
                </section>
        </div>

        <div v-if="showEmployeeModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4 py-6">
            <div class="w-full max-w-5xl overflow-hidden rounded-lg bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
                    <h3 class="text-lg font-semibold text-gray-900">Thêm nhân viên</h3>
                    <button type="button" class="text-gray-400 hover:text-gray-600" @click="showEmployeeModal = false">✕</button>
                </div>

                <div class="max-h-[65vh] overflow-auto px-6 py-5">
                    <div class="mb-3 flex justify-end">
                        <button
                            type="button"
                            class="rounded-md border border-blue-200 bg-blue-50 px-3 py-1.5 text-sm font-medium text-blue-700 hover:bg-blue-100"
                            @click="addEmployeeRow"
                        >
                            + Dòng nhân viên
                        </button>
                    </div>

                    <div class="overflow-auto rounded-md border border-gray-200">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-gray-600">
                                <tr>
                                    <th class="px-3 py-2 text-left">Tên nhân viên</th>
                                    <th class="px-3 py-2 text-left">Điện thoại</th>
                                    <th class="px-3 py-2 text-left">Chi nhánh</th>
                                    <th class="px-3 py-2 text-left">Phòng ban</th>
                                    <th class="px-3 py-2 text-left">Chức danh</th>
                                    <th class="px-3 py-2 text-center">Xóa</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(row, index) in employeeRows" :key="index" class="border-t border-gray-100">
                                    <td class="px-3 py-2">
                                        <input
                                            v-model="row.name"
                                            type="text"
                                            placeholder="Nhập tên"
                                            class="w-full rounded border border-gray-300 px-2.5 py-1.5 outline-none focus:border-blue-500"
                                        >
                                    </td>
                                    <td class="px-3 py-2">
                                        <input
                                            v-model="row.phone"
                                            type="text"
                                            placeholder="Số điện thoại"
                                            class="w-full rounded border border-gray-300 px-2.5 py-1.5 outline-none focus:border-blue-500"
                                        >
                                    </td>
                                    <td class="px-3 py-2">
                                        <select
                                            v-model="row.branch_id"
                                            class="w-full rounded border border-gray-300 px-2.5 py-1.5 outline-none focus:border-blue-500"
                                        >
                                            <option :value="null">Chọn chi nhánh</option>
                                            <option v-for="branch in branches" :key="branch.id" :value="branch.id">{{ branch.name }}</option>
                                        </select>
                                    </td>
                                    <td class="px-3 py-2">
                                        <select
                                            v-model="row.department_id"
                                            class="w-full rounded border border-gray-300 px-2.5 py-1.5 outline-none focus:border-blue-500"
                                        >
                                            <option :value="null">Chọn phòng ban</option>
                                            <option v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</option>
                                        </select>
                                    </td>
                                    <td class="px-3 py-2">
                                        <select
                                            v-model="row.job_title_id"
                                            class="w-full rounded border border-gray-300 px-2.5 py-1.5 outline-none focus:border-blue-500"
                                        >
                                            <option :value="null">Chọn chức danh</option>
                                            <option v-for="jobTitle in jobTitles" :key="jobTitle.id" :value="jobTitle.id">{{ jobTitle.name }}</option>
                                        </select>
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <button type="button" class="text-red-600 hover:text-red-700" @click="removeEmployeeRow(index)">Xóa</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <p v-if="employeeError" class="mt-3 text-sm text-red-600">{{ employeeError }}</p>
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-gray-200 bg-gray-50 px-6 py-4">
                    <button type="button" class="rounded border border-gray-300 px-4 py-2 text-sm hover:bg-gray-100" @click="showEmployeeModal = false">Bỏ qua</button>
                    <button
                        type="button"
                        class="rounded bg-blue-600 px-5 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="employeeSubmitting"
                        @click="submitEmployees"
                    >
                        Lưu
                    </button>
                </div>
            </div>
        </div>

        <div v-if="showShiftModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4 py-6">
            <div class="w-full max-w-lg rounded-lg bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
                    <h3 class="text-lg font-semibold text-gray-900">Tạo ca làm việc</h3>
                    <button type="button" class="text-gray-400 hover:text-gray-600" @click="showShiftModal = false">✕</button>
                </div>

                <div class="space-y-4 px-6 py-5">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Tên ca</label>
                        <input
                            v-model="shiftForm.name"
                            type="text"
                            class="w-full rounded border border-gray-300 px-3 py-2 outline-none focus:border-blue-500"
                            placeholder="Ví dụ: Ca hành chính"
                        >
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Bắt đầu</label>
                            <input v-model="shiftForm.start_time" type="time" class="w-full rounded border border-gray-300 px-3 py-2 outline-none focus:border-blue-500">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Kết thúc</label>
                            <input v-model="shiftForm.end_time" type="time" class="w-full rounded border border-gray-300 px-3 py-2 outline-none focus:border-blue-500">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Cho phép đi muộn (phút)</label>
                            <input v-model.number="shiftForm.allow_late_minutes" type="number" min="0" class="w-full rounded border border-gray-300 px-3 py-2 outline-none focus:border-blue-500">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Cho phép về sớm (phút)</label>
                            <input v-model.number="shiftForm.allow_early_minutes" type="number" min="0" class="w-full rounded border border-gray-300 px-3 py-2 outline-none focus:border-blue-500">
                        </div>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Ghi chú</label>
                        <textarea
                            v-model="shiftForm.notes"
                            rows="3"
                            class="w-full rounded border border-gray-300 px-3 py-2 outline-none focus:border-blue-500"
                            placeholder="Nội dung thêm"
                        ></textarea>
                    </div>

                    <p v-if="shiftError" class="text-sm text-red-600">{{ shiftError }}</p>
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-gray-200 bg-gray-50 px-6 py-4">
                    <button type="button" class="rounded border border-gray-300 px-4 py-2 text-sm hover:bg-gray-100" @click="showShiftModal = false">Bỏ qua</button>
                    <button
                        type="button"
                        class="rounded bg-blue-600 px-5 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="shiftSubmitting"
                        @click="submitShift"
                    >
                        Lưu
                    </button>
                </div>
            </div>
        </div>

        <div v-if="showScheduleModal" class="fixed inset-0 z-50 bg-black/40 px-4 py-6">
            <div class="mx-auto flex h-full w-full max-w-7xl flex-col overflow-hidden rounded-lg bg-white shadow-xl">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Xếp lịch làm việc</h3>
                        <p class="text-sm text-gray-500">Gán ca cho nhân viên theo từng ngày trong tuần.</p>
                    </div>

                    <div class="flex items-center gap-2">
                        <button type="button" class="rounded border border-gray-300 px-3 py-1.5 text-sm hover:bg-gray-50" @click="changeWeek(-1)">Tuần trước</button>
                        <div class="rounded border border-gray-200 bg-gray-50 px-3 py-1.5 text-sm font-medium text-gray-700">{{ weekLabel }}</div>
                        <button type="button" class="rounded border border-gray-300 px-3 py-1.5 text-sm hover:bg-gray-50" @click="changeWeek(1)">Tuần sau</button>
                    </div>
                </div>

                <div class="border-b border-gray-200 px-5 py-3">
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-[260px_minmax(0,1fr)]">
                        <input
                            v-model="employeeSearch"
                            type="text"
                            placeholder="Tìm nhân viên theo tên, mã, số điện thoại"
                            class="rounded border border-gray-300 px-3 py-2 text-sm outline-none focus:border-blue-500"
                        >

                        <select
                            v-model="selectedShiftId"
                            class="rounded border border-gray-300 px-3 py-2 text-sm outline-none focus:border-blue-500"
                        >
                            <option :value="null">Chọn ca làm việc để xếp lịch</option>
                            <option v-for="shift in localShifts" :key="shift.id" :value="shift.id">
                                {{ shift.name }} - {{ formatShiftTime(shift) }}
                            </option>
                        </select>
                    </div>
                </div>

                <div class="flex-1 overflow-auto">
                    <div v-if="scheduleLoading" class="px-5 py-6 text-sm text-gray-600">Đang tải dữ liệu lịch...</div>

                    <table v-else class="min-w-full text-sm">
                        <thead class="sticky top-0 bg-gray-50 text-gray-600">
                            <tr>
                                <th class="min-w-[220px] border-b border-r border-gray-200 px-4 py-3 text-left">Nhân viên</th>
                                <th v-for="day in weekDays" :key="day.date" class="min-w-[140px] border-b border-r border-gray-200 px-3 py-3 text-center last:border-r-0">
                                    <div class="font-semibold">{{ day.label }}</div>
                                    <div class="text-xs text-gray-500">{{ day.display }}</div>
                                </th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr v-if="filteredEmployees.length === 0">
                                <td :colspan="8" class="px-6 py-8 text-center text-gray-500">Không có nhân viên phù hợp.</td>
                            </tr>

                            <tr v-for="employee in filteredEmployees" :key="employee.id" class="hover:bg-gray-50">
                                <td class="border-b border-r border-gray-100 px-4 py-3 align-top">
                                    <div class="font-medium text-gray-900">{{ employee.name }}</div>
                                    <div class="text-xs text-gray-500">{{ employee.code }}</div>
                                </td>

                                <td
                                    v-for="day in weekDays"
                                    :key="`${employee.id}_${day.date}`"
                                    class="border-b border-r border-gray-100 px-2 py-2 text-center align-top last:border-r-0"
                                >
                                    <button
                                        type="button"
                                        class="w-full rounded-md border px-2 py-2 text-xs transition"
                                        :class="scheduleForCell(employee.id, day.date) ? 'border-blue-300 bg-blue-50 text-blue-700' : 'border-gray-200 bg-white text-gray-600 hover:border-blue-200 hover:bg-blue-50'"
                                        @click="toggleSchedule(employee, day.date)"
                                    >
                                        <template v-if="scheduleSavingKey === `${employee.id}_${day.date}`">
                                            Đang xử lý...
                                        </template>
                                        <template v-else-if="scheduleForCell(employee.id, day.date)">
                                            <div class="font-semibold">{{ scheduleForCell(employee.id, day.date)?.shift?.name || 'Đã xếp ca' }}</div>
                                            <div class="mt-0.5 text-[11px]">{{ formatShiftTime(scheduleForCell(employee.id, day.date)?.shift) }}</div>
                                            <div class="mt-1 text-[11px] font-medium">Bỏ xếp</div>
                                        </template>
                                        <template v-else>
                                            <div class="font-medium">Xếp ca</div>
                                        </template>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex items-center justify-between border-t border-gray-200 bg-gray-50 px-5 py-3">
                    <p class="text-xs text-gray-500">Nhấn vào ô để bật/tắt lịch cho nhân viên trong ngày đã chọn.</p>
                    <div class="flex items-center gap-2">
                        <Link href="/employees/schedules" class="rounded border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-100">
                            Mở trang lịch đầy đủ
                        </Link>
                        <button type="button" class="rounded bg-blue-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-700" @click="showScheduleModal = false">
                            Xong
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
