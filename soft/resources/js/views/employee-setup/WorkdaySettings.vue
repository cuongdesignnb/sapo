<template>
    <div class="space-y-6">
        <!-- Weekdays -->
        <div class="bg-white rounded-xl border border-slate-200">
            <div class="p-6 border-b border-slate-100">
                <div class="text-lg font-semibold text-slate-900">
                    Ngày làm việc
                </div>
                <div class="text-sm text-slate-500 mt-1">
                    Thiết lập ngày làm việc trong tuần
                </div>
            </div>

            <div class="p-6">
                <div
                    v-if="weekdaysLoading"
                    class="flex items-center gap-2 text-slate-600"
                >
                    <div
                        class="animate-spin rounded-full h-5 w-5 border-b-2 border-blue-500"
                    ></div>
                    <span>Đang tải...</span>
                </div>

                <div v-else class="flex flex-wrap gap-2">
                    <button
                        v-for="d in weekDayButtons"
                        :key="d.key"
                        type="button"
                        class="px-3 py-2 rounded-lg border text-sm"
                        :class="
                            weekdays[d.key]
                                ? 'bg-blue-50 border-blue-300 text-blue-700'
                                : 'bg-white border-slate-300 text-slate-700'
                        "
                        @click="weekdays[d.key] = !weekdays[d.key]"
                    >
                        {{ d.label }}
                    </button>
                </div>

                <div class="mt-4 flex justify-end">
                    <button
                        class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700"
                        type="button"
                        :disabled="weekdaysSaving"
                        @click="saveWeekdays"
                    >
                        Lưu
                    </button>
                </div>
            </div>
        </div>

        <!-- Holidays -->
        <div class="bg-white rounded-xl border border-slate-200">
            <div
                class="p-6 border-b border-slate-100 flex items-center justify-between"
            >
                <div>
                    <div class="text-lg font-semibold text-slate-900">
                        Ngày lễ, tết
                    </div>
                    <div class="text-sm text-slate-500 mt-1">
                        Danh sách ngày lễ áp dụng trong tính công và tính lương
                    </div>
                </div>

                <button
                    class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700"
                    type="button"
                    @click="openCreate"
                >
                    + Thêm ngày lễ
                </button>
            </div>

            <div
                class="p-6 border-b border-slate-100 flex flex-wrap items-center gap-3"
            >
                <div>
                    <label class="block text-xs text-slate-500 mb-1">Từ</label>
                    <input
                        v-model="filters.from"
                        type="date"
                        class="px-3 py-2 border border-slate-300 rounded-lg text-sm"
                    />
                </div>
                <div>
                    <label class="block text-xs text-slate-500 mb-1">Đến</label>
                    <input
                        v-model="filters.to"
                        type="date"
                        class="px-3 py-2 border border-slate-300 rounded-lg text-sm"
                    />
                </div>
                <div>
                    <label class="block text-xs text-slate-500 mb-1"
                        >Trạng thái</label
                    >
                    <select
                        v-model="filters.status"
                        class="px-3 py-2 border border-slate-300 rounded-lg text-sm"
                    >
                        <option value="">Tất cả</option>
                        <option value="active">Đang áp dụng</option>
                        <option value="inactive">Ngừng áp dụng</option>
                    </select>
                </div>

                <div class="flex-1"></div>
                <button
                    class="px-4 py-2 rounded-lg border border-slate-300 text-sm hover:bg-slate-50"
                    type="button"
                    @click="loadHolidays"
                >
                    Lọc
                </button>
            </div>

            <div
                v-if="holidaysLoading"
                class="flex justify-center items-center py-12"
            >
                <div
                    class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"
                ></div>
                <span class="ml-2 text-slate-600">Đang tải...</span>
            </div>

            <div v-else class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-slate-50">
                        <tr>
                            <th
                                class="text-left p-4 text-sm font-medium text-slate-600"
                            >
                                Ngày
                            </th>
                            <th
                                class="text-left p-4 text-sm font-medium text-slate-600"
                            >
                                Tên
                            </th>
                            <th
                                class="text-left p-4 text-sm font-medium text-slate-600"
                            >
                                Hệ số
                            </th>
                            <th
                                class="text-left p-4 text-sm font-medium text-slate-600"
                            >
                                Nghỉ hưởng lương
                            </th>
                            <th
                                class="text-left p-4 text-sm font-medium text-slate-600"
                            >
                                Trạng thái
                            </th>
                            <th
                                class="text-right p-4 text-sm font-medium text-slate-600 w-28"
                            >
                                Thao tác
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr
                            v-for="h in holidays"
                            :key="h.id"
                            class="hover:bg-slate-50"
                        >
                            <td class="p-4 text-sm text-slate-700">
                                {{ h.holiday_date }}
                            </td>
                            <td class="p-4">
                                <div class="font-medium text-slate-900">
                                    {{ h.name }}
                                </div>
                                <div
                                    v-if="h.notes"
                                    class="text-xs text-slate-500 mt-1"
                                >
                                    {{ h.notes }}
                                </div>
                            </td>
                            <td class="p-4 text-sm text-slate-700">
                                {{ h.multiplier }}
                            </td>
                            <td class="p-4 text-sm text-slate-700">
                                {{ h.paid_leave ? "Có" : "Không" }}
                            </td>
                            <td class="p-4">
                                <span
                                    class="inline-flex px-2 py-1 rounded text-xs"
                                    :class="
                                        h.status === 'active'
                                            ? 'bg-green-100 text-green-700'
                                            : 'bg-slate-100 text-slate-700'
                                    "
                                >
                                    {{
                                        h.status === "active"
                                            ? "Đang áp dụng"
                                            : "Ngừng áp dụng"
                                    }}
                                </span>
                            </td>
                            <td class="p-4">
                                <div class="flex justify-end gap-2">
                                    <button
                                        class="text-slate-600 hover:text-slate-900"
                                        type="button"
                                        title="Sửa"
                                        @click="openEdit(h)"
                                    >
                                        ✎
                                    </button>
                                    <button
                                        class="text-slate-600 hover:text-slate-900"
                                        type="button"
                                        title="Xóa"
                                        @click="remove(h)"
                                    >
                                        🗑
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="holidays.length === 0">
                            <td
                                colspan="6"
                                class="p-10 text-center text-slate-500"
                            >
                                Chưa có ngày lễ
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Holiday modal -->
        <div
            v-if="showModal"
            class="fixed inset-0 bg-black/40 flex items-center justify-center z-50"
        >
            <div class="bg-white rounded-xl w-full max-w-3xl p-6">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold">
                        {{ editing ? "Cập nhật ngày lễ" : "Thêm ngày lễ" }}
                    </h2>
                    <button
                        class="text-slate-500 hover:text-slate-700"
                        type="button"
                        @click="close"
                    >
                        ✕
                    </button>
                </div>

                <div class="grid grid-cols-2 gap-4 mt-5">
                    <div>
                        <label
                            class="block text-sm font-medium text-slate-700 mb-1"
                            >Ngày *</label
                        >
                        <input
                            v-model="form.holiday_date"
                            type="date"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg"
                        />
                    </div>

                    <div>
                        <label
                            class="block text-sm font-medium text-slate-700 mb-1"
                            >Hệ số</label
                        >
                        <input
                            v-model.number="form.multiplier"
                            type="number"
                            min="0"
                            step="0.1"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg"
                        />
                    </div>

                    <div class="col-span-2">
                        <label
                            class="block text-sm font-medium text-slate-700 mb-1"
                            >Tên *</label
                        >
                        <input
                            v-model="form.name"
                            type="text"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg"
                            placeholder="VD: Tết dương lịch"
                        />
                    </div>

                    <div>
                        <label
                            class="block text-sm font-medium text-slate-700 mb-1"
                            >Nghỉ hưởng lương</label
                        >
                        <select
                            v-model="form.paid_leave"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg"
                        >
                            <option :value="false">Không</option>
                            <option :value="true">Có</option>
                        </select>
                    </div>

                    <div>
                        <label
                            class="block text-sm font-medium text-slate-700 mb-1"
                            >Trạng thái</label
                        >
                        <select
                            v-model="form.status"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg"
                        >
                            <option value="active">Đang áp dụng</option>
                            <option value="inactive">Ngừng áp dụng</option>
                        </select>
                    </div>

                    <div class="col-span-2">
                        <label
                            class="block text-sm font-medium text-slate-700 mb-1"
                            >Ghi chú</label
                        >
                        <textarea
                            v-model="form.notes"
                            rows="3"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg"
                        ></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 mt-6">
                    <button
                        class="px-4 py-2 rounded-lg border border-slate-300"
                        type="button"
                        @click="close"
                    >
                        Bỏ qua
                    </button>
                    <button
                        class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700"
                        type="button"
                        :disabled="saving"
                        @click="save"
                    >
                        Lưu
                    </button>
                </div>
            </div>
        </div>

        <div v-if="toast.show" class="fixed top-4 right-4 z-50">
            <div
                class="p-4 rounded-lg shadow-lg max-w-sm"
                :class="
                    toast.type === 'success'
                        ? 'bg-green-100 border border-green-400 text-green-700'
                        : 'bg-red-100 border border-red-400 text-red-700'
                "
            >
                <div class="flex items-center gap-2">
                    <span>{{ toast.message }}</span>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { onMounted, ref } from "vue";
import employeeApi from "@/api/employeeApi";

export default {
    name: "WorkdaySettings",
    setup() {
        const toast = ref({ show: false, type: "success", message: "" });
        const showToast = (message, type = "success") => {
            toast.value = { show: true, type, message };
            setTimeout(() => (toast.value.show = false), 2500);
        };

        // Weekly workdays
        const weekDayButtons = [
            { key: "mon", label: "Thứ 2" },
            { key: "tue", label: "Thứ 3" },
            { key: "wed", label: "Thứ 4" },
            { key: "thu", label: "Thứ 5" },
            { key: "fri", label: "Thứ 6" },
            { key: "sat", label: "Thứ 7" },
            { key: "sun", label: "Chủ nhật" },
        ];

        const weekdaysLoading = ref(false);
        const weekdaysSaving = ref(false);
        const weekdays = ref({
            mon: true,
            tue: true,
            wed: true,
            thu: true,
            fri: true,
            sat: true,
            sun: false,
        });

        const loadWeekdays = async () => {
            weekdaysLoading.value = true;
            try {
                const res = await employeeApi.getWorkdaySettings();
                const data = res?.data?.data;
                if (data?.week_days) {
                    weekdays.value = { ...weekdays.value, ...data.week_days };
                }
            } catch {
                // ignore
            } finally {
                weekdaysLoading.value = false;
            }
        };

        const saveWeekdays = async () => {
            weekdaysSaving.value = true;
            try {
                await employeeApi.saveWorkdaySettings({
                    week_days: weekdays.value,
                });
                showToast("Đã lưu ngày làm việc");
            } catch (e) {
                showToast(
                    e?.response?.data?.message || "Lưu thất bại",
                    "error",
                );
            } finally {
                weekdaysSaving.value = false;
            }
        };

        // Holidays
        const holidaysLoading = ref(false);
        const holidays = ref([]);

        const today = new Date();
        const yyyy = today.getFullYear();
        const defaultFrom = `${yyyy}-01-01`;
        const defaultTo = `${yyyy}-12-31`;

        const filters = ref({
            from: defaultFrom,
            to: defaultTo,
            status: "active",
        });

        const loadHolidays = async () => {
            holidaysLoading.value = true;
            try {
                const res = await employeeApi.getHolidays({
                    from: filters.value.from || undefined,
                    to: filters.value.to || undefined,
                    status: filters.value.status || undefined,
                });
                holidays.value = res?.data?.data || [];
            } catch {
                holidays.value = [];
            } finally {
                holidaysLoading.value = false;
            }
        };

        const showModal = ref(false);
        const saving = ref(false);
        const editing = ref(null);
        const form = ref({
            holiday_date: "",
            name: "",
            multiplier: 1,
            paid_leave: false,
            status: "active",
            notes: "",
        });

        const openCreate = () => {
            editing.value = null;
            form.value = {
                holiday_date: "",
                name: "",
                multiplier: 1,
                paid_leave: false,
                status: "active",
                notes: "",
            };
            showModal.value = true;
        };

        const openEdit = (h) => {
            editing.value = h;
            form.value = {
                holiday_date: h.holiday_date,
                name: h.name,
                multiplier: Number(h.multiplier ?? 1),
                paid_leave: Boolean(h.paid_leave),
                status: h.status || "active",
                notes: h.notes || "",
            };
            showModal.value = true;
        };

        const close = () => (showModal.value = false);

        const save = async () => {
            if (!form.value.holiday_date || !form.value.name) {
                showToast("Vui lòng nhập đủ: Ngày, Tên", "error");
                return;
            }

            saving.value = true;
            try {
                const payload = {
                    holiday_date: form.value.holiday_date,
                    name: form.value.name,
                    multiplier: form.value.multiplier ?? 1,
                    paid_leave: !!form.value.paid_leave,
                    status: form.value.status || "active",
                    notes: form.value.notes || null,
                };

                if (editing.value) {
                    await employeeApi.updateHoliday(editing.value.id, payload);
                    showToast("Đã cập nhật ngày lễ");
                } else {
                    await employeeApi.createHoliday(payload);
                    showToast("Đã tạo ngày lễ");
                }

                showModal.value = false;
                await loadHolidays();
            } catch (e) {
                showToast(
                    e?.response?.data?.message || "Lưu thất bại",
                    "error",
                );
            } finally {
                saving.value = false;
            }
        };

        const remove = async (h) => {
            if (!confirm(`Xóa ngày lễ ${h.name} (${h.holiday_date})?`)) return;
            try {
                await employeeApi.deleteHoliday(h.id);
                showToast("Đã xóa ngày lễ");
                await loadHolidays();
            } catch (e) {
                showToast(
                    e?.response?.data?.message || "Xóa thất bại",
                    "error",
                );
            }
        };

        onMounted(async () => {
            await loadWeekdays();
            await loadHolidays();
        });

        return {
            toast,
            weekDayButtons,
            weekdaysLoading,
            weekdaysSaving,
            weekdays,
            saveWeekdays,
            holidaysLoading,
            holidays,
            filters,
            loadHolidays,
            showModal,
            saving,
            editing,
            form,
            openCreate,
            openEdit,
            close,
            save,
            remove,
        };
    },
};
</script>
