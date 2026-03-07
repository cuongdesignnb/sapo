<script setup>
import { computed, reactive, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';
import SetupSidebar from '@/Pages/Employees/Partials/SetupSidebar.vue';

const props = defineProps({
    payrollSetting: {
        type: Object,
        default: () => ({
            pay_cycle: 'monthly',
            start_day: 26,
            end_day: 25,
            start_in_prev_month: true,
            pay_day: 5,
            default_recalculate_timekeeping: true,
            auto_generate_enabled: false,
        }),
    },
    salaryTemplates: {
        type: Array,
        default: () => [],
    },
});

const saving = ref(false);
const templateSaving = ref(false);
const deletingTemplateId = ref(null);
const showPaydayModal = ref(false);
const showTemplatesModal = ref(false);
const showTemplateEditor = ref(false);
const toast = reactive({ show: false, type: 'success', message: '' });

const payroll = reactive({
    pay_cycle: props.payrollSetting?.pay_cycle ?? 'monthly',
    start_day: Number(props.payrollSetting?.start_day ?? 26),
    end_day: Number(props.payrollSetting?.end_day ?? 25),
    start_in_prev_month: Boolean(props.payrollSetting?.start_in_prev_month ?? true),
    pay_day: Number(props.payrollSetting?.pay_day ?? 5),
    default_recalculate_timekeeping: Boolean(props.payrollSetting?.default_recalculate_timekeeping ?? true),
    auto_generate_enabled: Boolean(props.payrollSetting?.auto_generate_enabled ?? false),
});

const paydayForm = reactive({
    start_day: Number(props.payrollSetting?.start_day ?? 26),
    end_day: Number(props.payrollSetting?.end_day ?? 25),
    start_in_prev_month: Boolean(props.payrollSetting?.start_in_prev_month ?? true),
    pay_day: Number(props.payrollSetting?.pay_day ?? 5),
});

const emptyTemplate = () => ({
    id: null,
    name: '',
    type: 'fixed',
    base_salary: 0,
    description: '',
});

const normalizeTemplate = (template) => ({
    id: template.id,
    name: template.name ?? '',
    type: template.type ?? 'fixed',
    base_salary: Number(template.base_salary ?? 0),
    description: template.description ?? '',
    created_at: template.created_at ?? null,
});

const templates = ref((props.salaryTemplates ?? []).map(normalizeTemplate));
const templateForm = reactive(emptyTemplate());

const setToast = (message, type = 'success') => {
    toast.show = true;
    toast.type = type;
    toast.message = message;

    window.clearTimeout(setToast.timeoutId);
    setToast.timeoutId = window.setTimeout(() => {
        toast.show = false;
    }, 2500);
};

const formatMoney = (value) => Number(value || 0).toLocaleString('vi-VN');

const templateTypeLabel = (type) => {
    if (type === 'hourly') {
        return 'Theo giờ';
    }

    if (type === 'monthly_commission') {
        return 'Lương tháng + hoa hồng';
    }

    return 'Cố định';
};

const cycleSummary = computed(() => {
    if (payroll.pay_cycle === 'biweekly') {
        return `Kỳ lương 2 tuần, trả lương vào ngày ${payroll.pay_day} hằng tháng.`;
    }

    const startLabel = payroll.start_in_prev_month
        ? `từ ngày ${payroll.start_day} tháng trước`
        : `từ ngày ${payroll.start_day} trong tháng`;

    return `${startLabel} đến ngày ${payroll.end_day} hằng tháng, trả lương vào ngày ${payroll.pay_day}.`;
});

const templateSummary = computed(() => {
    if (!templates.value.length) {
        return 'Chưa có mẫu lương nào được thiết lập.';
    }

    return `Đang có ${templates.value.length} mẫu lương sẵn sàng để áp dụng cho nhân viên.`;
});

const payrollRows = computed(() => [
    {
        key: 'payday',
        title: 'Ngày tính lương',
        description: cycleSummary.value,
        kind: 'action',
        action: () => {
            paydayForm.start_day = payroll.start_day;
            paydayForm.end_day = payroll.end_day;
            paydayForm.start_in_prev_month = payroll.start_in_prev_month;
            paydayForm.pay_day = payroll.pay_day;
            showPaydayModal.value = true;
        },
    },
    {
        key: 'recalculate',
        title: 'Tự động cập nhật dữ liệu công khi tạo bảng lương',
        description: 'Khi bật, bảng lương mới sẽ mặc định lấy dữ liệu chấm công đã tính lại.',
        kind: 'toggle',
        field: 'default_recalculate_timekeeping',
    },
    {
        key: 'auto-generate',
        title: 'Tự động tạo bảng tính lương',
        description: 'Lưu thiết lập để sẵn sàng nối với cron hoặc job tự động tạo bảng lương theo kỳ.',
        kind: 'toggle',
        field: 'auto_generate_enabled',
    },
    {
        key: 'templates',
        title: 'Danh sách mẫu lương',
        description: templateSummary.value,
        kind: 'action',
        action: () => {
            showTemplatesModal.value = true;
        },
    },
]);

const persistPayrollSettings = async () => {
    saving.value = true;

    try {
        await axios.post('/api/payroll-settings', {
            pay_cycle: payroll.pay_cycle,
            start_day: Number(payroll.start_day || 1),
            end_day: Number(payroll.end_day || 1),
            start_in_prev_month: Boolean(payroll.start_in_prev_month),
            pay_day: Number(payroll.pay_day || 1),
            default_recalculate_timekeeping: Boolean(payroll.default_recalculate_timekeeping),
            auto_generate_enabled: Boolean(payroll.auto_generate_enabled),
            status: 'active',
        });

        setToast('Đã lưu thiết lập tính lương.');
    } catch (error) {
        setToast(error?.response?.data?.message || 'Không thể lưu thiết lập tính lương.', 'error');
        throw error;
    } finally {
        saving.value = false;
    }
};

const togglePayrollField = async (field) => {
    const previous = payroll[field];
    payroll[field] = !previous;

    try {
        await persistPayrollSettings();
    } catch (error) {
        payroll[field] = previous;
    }
};

const savePaydayModal = async () => {
    payroll.pay_cycle = 'monthly';
    payroll.start_day = Number(paydayForm.start_day || 1);
    payroll.end_day = Number(paydayForm.end_day || 1);
    payroll.start_in_prev_month = Boolean(paydayForm.start_in_prev_month);
    payroll.pay_day = Number(paydayForm.pay_day || 1);
    await persistPayrollSettings();
    showPaydayModal.value = false;
};

const resetTemplateForm = () => {
    Object.assign(templateForm, emptyTemplate());
};

const openTemplateEditor = (template = null) => {
    resetTemplateForm();

    if (template) {
        Object.assign(templateForm, normalizeTemplate(template));
    }

    showTemplateEditor.value = true;
};

const closeTemplateEditor = () => {
    showTemplateEditor.value = false;
    resetTemplateForm();
};

const saveTemplate = async () => {
    if (!templateForm.name.trim()) {
        setToast('Tên mẫu lương là bắt buộc.', 'error');
        return;
    }

    templateSaving.value = true;

    try {
        const payload = {
            name: templateForm.name.trim(),
            type: templateForm.type,
            base_salary: Number(templateForm.base_salary || 0),
            description: templateForm.description?.trim() || null,
        };

        const response = templateForm.id
            ? await axios.put(`/api/salary-templates/${templateForm.id}`, payload)
            : await axios.post('/api/salary-templates', payload);

        const nextTemplate = normalizeTemplate(response.data?.data ?? payload);
        const currentIndex = templates.value.findIndex((item) => item.id === nextTemplate.id);

        if (currentIndex >= 0) {
            templates.value.splice(currentIndex, 1, nextTemplate);
        } else {
            templates.value.unshift(nextTemplate);
        }

        templates.value.sort((left, right) => right.id - left.id);
        setToast(response.data?.message || 'Đã lưu mẫu lương.');
        closeTemplateEditor();
    } catch (error) {
        setToast(error?.response?.data?.message || 'Không thể lưu mẫu lương.', 'error');
    } finally {
        templateSaving.value = false;
    }
};

const removeTemplate = async (template) => {
    if (!window.confirm(`Xóa mẫu lương "${template.name}"?`)) {
        return;
    }

    deletingTemplateId.value = template.id;

    try {
        await axios.delete(`/api/salary-templates/${template.id}`);
        templates.value = templates.value.filter((item) => item.id !== template.id);
        setToast('Xóa mẫu lương thành công.');
    } catch (error) {
        setToast(error?.response?.data?.message || 'Không thể xóa mẫu lương.', 'error');
    } finally {
        deletingTemplateId.value = null;
    }
};
</script>

<template>
    <Head title="Thiết lập tính lương - KiotViet Clone" />

    <AppLayout>
        <template #sidebar>
            <SetupSidebar active-main="payroll" />
        </template>

        <div class="space-y-4">
            <section class="overflow-hidden rounded-lg border border-gray-200 bg-white">
                <div class="flex flex-col gap-3 border-b border-gray-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 class="text-lg font-semibold text-gray-900">Thiết lập tính lương</h1>
                        <p class="mt-1 text-sm text-gray-500">
                            Cấu hình ngày tính lương, tự động tạo bảng lương và danh sách mẫu lương áp dụng cho nhân viên.
                        </p>
                    </div>

                    <Link
                        href="/employees/paysheets"
                        class="inline-flex items-center justify-center rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50"
                    >
                        Mở bảng lương
                    </Link>
                </div>

                <div class="divide-y divide-gray-100">
                    <div
                        v-for="row in payrollRows"
                        :key="row.key"
                        class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            <div class="font-medium text-gray-900">{{ row.title }}</div>
                            <div class="mt-1 text-sm text-gray-500">{{ row.description }}</div>
                        </div>

                        <button
                            v-if="row.kind === 'action'"
                            type="button"
                            class="inline-flex items-center justify-center rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50"
                            @click="row.action()"
                        >
                            Chi tiết
                        </button>

                        <button
                            v-else
                            type="button"
                            class="inline-flex h-7 w-12 items-center rounded-full transition"
                            :class="payroll[row.field] ? 'bg-blue-600' : 'bg-gray-300'"
                            :disabled="saving"
                            @click="togglePayrollField(row.field)"
                        >
                            <span
                                class="inline-block h-5 w-5 rounded-full bg-white shadow transition"
                                :class="payroll[row.field] ? 'translate-x-6' : 'translate-x-1'"
                            />
                        </button>
                    </div>
                </div>
            </section>
        </div>

        <div v-if="toast.show" class="fixed right-4 top-4 z-50">
            <div
                class="max-w-sm rounded-lg border px-4 py-3 text-sm shadow-lg"
                :class="toast.type === 'success'
                    ? 'border-green-200 bg-green-50 text-green-700'
                    : 'border-red-200 bg-red-50 text-red-700'"
            >
                {{ toast.message }}
            </div>
        </div>

        <div v-if="showPaydayModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4">
            <div class="w-full max-w-2xl rounded-xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">Ngày tính lương</h2>
                        <p class="mt-1 text-sm text-gray-500">Thiết lập kỳ lương mặc định để gợi ý khi tạo bảng lương.</p>
                    </div>

                    <button type="button" class="text-gray-400 transition hover:text-gray-600" @click="showPaydayModal = false">✕</button>
                </div>

                <div class="grid grid-cols-1 gap-4 px-6 py-5 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Từ ngày</label>
                        <input
                            v-model.number="paydayForm.start_day"
                            type="number"
                            min="1"
                            max="31"
                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                        />
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Đến ngày</label>
                        <input
                            v-model.number="paydayForm.end_day"
                            type="number"
                            min="1"
                            max="31"
                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                        />
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Ngày trả lương</label>
                        <input
                            v-model.number="paydayForm.pay_day"
                            type="number"
                            min="1"
                            max="31"
                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                        />
                    </div>

                    <div class="flex items-end">
                        <label class="flex items-center gap-3 rounded-md border border-gray-200 px-3 py-2.5 text-sm text-gray-700">
                            <input v-model="paydayForm.start_in_prev_month" type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                            <span>Bắt đầu từ tháng trước</span>
                        </label>
                    </div>
                </div>

                <div class="border-t border-gray-100 bg-gray-50 px-6 py-4">
                    <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-end">
                        <button
                            type="button"
                            class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-white"
                            @click="showPaydayModal = false"
                        >
                            Bỏ qua
                        </button>
                        <button
                            type="button"
                            class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700 disabled:opacity-60"
                            :disabled="saving"
                            @click="savePaydayModal"
                        >
                            {{ saving ? 'Đang lưu...' : 'Lưu' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showTemplatesModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4">
            <div class="flex max-h-[85vh] w-full max-w-5xl flex-col rounded-xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">Danh sách mẫu lương</h2>
                        <p class="mt-1 text-sm text-gray-500">Tạo và quản lý các mẫu lương để gán nhanh cho nhân viên.</p>
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700"
                            @click="openTemplateEditor()"
                        >
                            Thêm mẫu lương
                        </button>
                        <button type="button" class="text-gray-400 transition hover:text-gray-600" @click="showTemplatesModal = false">✕</button>
                    </div>
                </div>

                <div class="flex-1 overflow-auto px-6 py-5">
                    <div class="overflow-hidden rounded-lg border border-gray-200">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">STT</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Tên mẫu</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Loại lương</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Lương cơ bản</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Mô tả</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                <tr v-for="(template, index) in templates" :key="template.id">
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ index + 1 }}</td>
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ template.name }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600">{{ templateTypeLabel(template.type) }}</td>
                                    <td class="px-4 py-3 text-right text-sm text-gray-900">{{ formatMoney(template.base_salary) }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ template.description || 'Không có mô tả' }}</td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-end gap-2">
                                            <button
                                                type="button"
                                                class="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50"
                                                @click="openTemplateEditor(template)"
                                            >
                                                Sửa
                                            </button>
                                            <button
                                                type="button"
                                                class="rounded-md border border-red-200 px-3 py-1.5 text-sm font-medium text-red-700 transition hover:bg-red-50 disabled:opacity-60"
                                                :disabled="deletingTemplateId === template.id"
                                                @click="removeTemplate(template)"
                                            >
                                                {{ deletingTemplateId === template.id ? 'Đang xóa...' : 'Xóa' }}
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!templates.length">
                                    <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">Chưa có mẫu lương nào.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="border-t border-gray-100 bg-gray-50 px-6 py-4 text-right">
                    <button
                        type="button"
                        class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-white"
                        @click="showTemplatesModal = false"
                    >
                        Đóng
                    </button>
                </div>
            </div>
        </div>

        <div v-if="showTemplateEditor" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/40 px-4">
            <div class="w-full max-w-2xl rounded-xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">{{ templateForm.id ? 'Sửa mẫu lương' : 'Thêm mẫu lương' }}</h2>
                        <p class="mt-1 text-sm text-gray-500">Khai báo mẫu lương cơ bản để áp dụng nhanh cho nhân viên.</p>
                    </div>

                    <button type="button" class="text-gray-400 transition hover:text-gray-600" @click="closeTemplateEditor">✕</button>
                </div>

                <div class="grid grid-cols-1 gap-4 px-6 py-5 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-gray-700">Tên mẫu lương</label>
                        <input
                            v-model="templateForm.name"
                            type="text"
                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                            placeholder="Ví dụ: Lương cửa hàng tiêu chuẩn"
                        />
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Loại lương</label>
                        <select
                            v-model="templateForm.type"
                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                        >
                            <option value="fixed">Cố định</option>
                            <option value="hourly">Theo giờ</option>
                            <option value="monthly_commission">Lương tháng + hoa hồng</option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Lương cơ bản</label>
                        <input
                            v-model.number="templateForm.base_salary"
                            type="number"
                            min="0"
                            step="1000"
                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                        />
                    </div>

                    <div class="md:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-gray-700">Mô tả</label>
                        <textarea
                            v-model="templateForm.description"
                            rows="3"
                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                            placeholder="Ghi chú thêm về mẫu lương này"
                        />
                    </div>
                </div>

                <div class="border-t border-gray-100 bg-gray-50 px-6 py-4">
                    <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-end">
                        <button
                            type="button"
                            class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-white"
                            @click="closeTemplateEditor"
                        >
                            Bỏ qua
                        </button>
                        <button
                            type="button"
                            class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700 disabled:opacity-60"
                            :disabled="templateSaving"
                            @click="saveTemplate"
                        >
                            {{ templateSaving ? 'Đang lưu...' : 'Lưu' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>