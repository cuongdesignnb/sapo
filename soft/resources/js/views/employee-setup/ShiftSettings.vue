<template>
    <div class="bg-white rounded-xl border border-slate-200">
        <div
            class="p-6 border-b border-slate-100 flex items-center justify-between"
        >
            <div>
                <div class="text-lg font-semibold text-slate-900">
                    Danh sách ca làm việc
                </div>
                <div class="text-sm text-slate-500 mt-1">
                    Quản lý ca làm việc và thời gian cho phép chấm công
                </div>
            </div>

            <button
                class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700"
                type="button"
                @click="openCreate"
            >
                + Thêm ca làm việc
            </button>
        </div>

        <div v-if="loading" class="flex justify-center items-center py-12">
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
                            class="text-left p-4 text-sm font-medium text-slate-600 w-16"
                        >
                            STT
                        </th>
                        <th
                            class="text-left p-4 text-sm font-medium text-slate-600"
                        >
                            Ca làm việc
                        </th>
                        <th
                            class="text-left p-4 text-sm font-medium text-slate-600"
                        >
                            Thời gian
                        </th>
                        <th
                            class="text-left p-4 text-sm font-medium text-slate-600"
                        >
                            Tổng giờ làm việc
                        </th>
                        <th
                            class="text-left p-4 text-sm font-medium text-slate-600"
                        >
                            Hoạt động
                        </th>
                        <th
                            class="text-left p-4 text-sm font-medium text-slate-600 w-32"
                        >
                            Thao tác
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr
                        v-for="(s, idx) in shifts"
                        :key="s.id"
                        class="hover:bg-slate-50"
                    >
                        <td class="p-4 text-sm text-slate-600">
                            {{ idx + 1 }}
                        </td>
                        <td class="p-4">
                            <div class="font-medium text-slate-900">
                                {{ s.name }}
                            </div>
                            <div
                                v-if="s.checkin_time_text"
                                class="text-xs text-slate-500 mt-1"
                            >
                                Giờ cho phép chấm công:
                                {{ s.checkin_time_text }}
                            </div>
                        </td>
                        <td class="p-4 text-sm text-slate-700">
                            {{
                                s.work_time_text ||
                                s.start_time + " - " + s.end_time
                            }}
                        </td>
                        <td class="p-4 text-sm text-slate-700">
                            {{ s.duration_text || "-" }}
                        </td>
                        <td class="p-4">
                            <button
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition"
                                type="button"
                                :class="
                                    s.status === 'active'
                                        ? 'bg-blue-600'
                                        : 'bg-slate-300'
                                "
                                @click="toggle(s)"
                            >
                                <span class="sr-only">Toggle</span>
                                <span
                                    class="inline-block h-5 w-5 transform rounded-full bg-white transition"
                                    :class="
                                        s.status === 'active'
                                            ? 'translate-x-5'
                                            : 'translate-x-1'
                                    "
                                ></span>
                            </button>
                        </td>
                        <td class="p-4">
                            <div class="flex items-center justify-end gap-2">
                                <button
                                    class="text-slate-600 hover:text-slate-900"
                                    type="button"
                                    title="Sửa"
                                    @click="openEdit(s)"
                                >
                                    ✎
                                </button>
                                <button
                                    class="text-slate-600 hover:text-slate-900"
                                    type="button"
                                    title="Xóa"
                                    @click="remove(s)"
                                >
                                    🗑
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="shifts.length === 0">
                        <td colspan="6" class="p-10 text-center text-slate-500">
                            Chưa có ca làm việc
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Modal -->
        <div
            v-if="showModal"
            class="fixed inset-0 bg-black/40 flex items-center justify-center z-50"
        >
            <div class="bg-white rounded-xl w-full max-w-3xl p-6">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold">
                        {{
                            editing
                                ? "Cập nhật ca làm việc"
                                : "Thêm ca làm việc"
                        }}
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
                    <div class="col-span-2">
                        <label
                            class="block text-sm font-medium text-slate-700 mb-1"
                            >Tên</label
                        >
                        <input
                            v-model="form.name"
                            type="text"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg"
                            placeholder="VD: Ca hành chính"
                        />
                    </div>

                    <div>
                        <label
                            class="block text-sm font-medium text-slate-700 mb-1"
                            >Giờ làm việc</label
                        >
                        <div class="flex items-center gap-3">
                            <input
                                v-model="form.start_time"
                                type="time"
                                class="w-40 px-3 py-2 border border-slate-300 rounded-lg"
                            />
                            <span class="text-slate-500">Đến</span>
                            <input
                                v-model="form.end_time"
                                type="time"
                                class="w-40 px-3 py-2 border border-slate-300 rounded-lg"
                            />
                            <span class="text-slate-600 text-sm">{{
                                durationText
                            }}</span>
                        </div>
                    </div>

                    <div>
                        <label
                            class="block text-sm font-medium text-slate-700 mb-1"
                            >Giờ cho phép chấm công</label
                        >
                        <div class="flex items-center gap-3">
                            <input
                                v-model="form.checkin_start_time"
                                type="time"
                                class="w-40 px-3 py-2 border border-slate-300 rounded-lg"
                            />
                            <span class="text-slate-500">Đến</span>
                            <input
                                v-model="form.checkin_end_time"
                                type="time"
                                class="w-40 px-3 py-2 border border-slate-300 rounded-lg"
                            />
                        </div>
                    </div>

                    <div>
                        <label
                            class="block text-sm font-medium text-slate-700 mb-1"
                            >Cho phép đi muộn (phút)</label
                        >
                        <input
                            v-model.number="form.allow_late_minutes"
                            type="number"
                            min="0"
                            max="1440"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg"
                        />
                    </div>

                    <div>
                        <label
                            class="block text-sm font-medium text-slate-700 mb-1"
                            >Cho phép về sớm (phút)</label
                        >
                        <input
                            v-model.number="form.allow_early_minutes"
                            type="number"
                            min="0"
                            max="1440"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg"
                        />
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
import { computed, onMounted, ref } from "vue";
import employeeApi from "@/api/employeeApi";

export default {
    name: "ShiftSettings",
    setup() {
        const loading = ref(false);
        const saving = ref(false);
        const shifts = ref([]);

        const showModal = ref(false);
        const editing = ref(null);

        const form = ref({
            name: "",
            start_time: "",
            end_time: "",
            checkin_start_time: "",
            checkin_end_time: "",
            allow_late_minutes: 0,
            allow_early_minutes: 0,
            notes: "",
        });

        const toast = ref({ show: false, type: "success", message: "" });
        const showToast = (message, type = "success") => {
            toast.value = { show: true, type, message };
            setTimeout(() => (toast.value.show = false), 2500);
        };

        const toMinutes = (t) => {
            if (!t) return null;
            const m = String(t)
                .trim()
                .match(/^(\d{1,2}):(\d{2})/);
            if (!m) return null;
            const hh = parseInt(m[1], 10);
            const mm = parseInt(m[2], 10);
            if (Number.isNaN(hh) || Number.isNaN(mm)) return null;
            return hh * 60 + mm;
        };

        const durationText = computed(() => {
            const s = toMinutes(form.value.start_time);
            const e = toMinutes(form.value.end_time);
            if (s === null || e === null) return "";
            let diff = e - s;
            if (diff <= 0) diff += 24 * 60;
            const h = Math.floor(diff / 60);
            const m = diff % 60;
            return m === 0 ? `${h}h` : `${h}h ${m}m`;
        });

        const load = async () => {
            loading.value = true;
            try {
                const res = await employeeApi.getShifts({ per_page: 200 });
                shifts.value = res?.data?.data || [];
            } catch {
                shifts.value = [];
            } finally {
                loading.value = false;
            }
        };

        const openCreate = () => {
            editing.value = null;
            form.value = {
                name: "",
                start_time: "",
                end_time: "",
                checkin_start_time: "",
                checkin_end_time: "",
                allow_late_minutes: 0,
                allow_early_minutes: 0,
                notes: "",
            };
            showModal.value = true;
        };

        const openEdit = (s) => {
            editing.value = s;
            form.value = {
                name: s.name || "",
                start_time: (s.start_time || "").slice(0, 5),
                end_time: (s.end_time || "").slice(0, 5),
                checkin_start_time: (s.checkin_start_time || "").slice(0, 5),
                checkin_end_time: (s.checkin_end_time || "").slice(0, 5),
                allow_late_minutes: s.allow_late_minutes ?? 0,
                allow_early_minutes: s.allow_early_minutes ?? 0,
                notes: s.notes || "",
            };
            showModal.value = true;
        };

        const close = () => (showModal.value = false);

        const save = async () => {
            if (
                !form.value.name ||
                !form.value.start_time ||
                !form.value.end_time
            ) {
                showToast("Vui lòng nhập đủ: Tên, giờ làm việc", "error");
                return;
            }

            saving.value = true;
            try {
                const payload = {
                    name: form.value.name,
                    start_time: form.value.start_time,
                    end_time: form.value.end_time,
                    checkin_start_time: form.value.checkin_start_time || null,
                    checkin_end_time: form.value.checkin_end_time || null,
                    allow_late_minutes: form.value.allow_late_minutes ?? 0,
                    allow_early_minutes: form.value.allow_early_minutes ?? 0,
                    notes: form.value.notes || null,
                };

                if (editing.value) {
                    await employeeApi.updateShift(editing.value.id, payload);
                    showToast("Đã cập nhật ca làm việc");
                } else {
                    await employeeApi.createShift(payload);
                    showToast("Đã tạo ca làm việc");
                }

                showModal.value = false;
                await load();
            } catch (e) {
                showToast(
                    e?.response?.data?.message || "Lỗi khi lưu ca làm việc",
                    "error",
                );
            } finally {
                saving.value = false;
            }
        };

        const toggle = async (s) => {
            try {
                const res = await employeeApi.toggleShift(s.id);
                const updated = res?.data?.data;
                if (updated?.id) {
                    shifts.value = shifts.value.map((x) =>
                        x.id === updated.id ? updated : x,
                    );
                } else {
                    await load();
                }
            } catch (e) {
                showToast(
                    e?.response?.data?.message ||
                        "Không cập nhật được trạng thái",
                    "error",
                );
            }
        };

        const remove = async (s) => {
            if (!confirm(`Xóa ca làm việc ${s.name}?`)) return;
            try {
                await employeeApi.deleteShift(s.id);
                showToast("Đã xóa ca làm việc");
                await load();
            } catch (e) {
                showToast(
                    e?.response?.data?.message || "Xóa thất bại",
                    "error",
                );
            }
        };

        onMounted(load);

        return {
            loading,
            saving,
            shifts,
            showModal,
            editing,
            form,
            durationText,
            openCreate,
            openEdit,
            close,
            save,
            toggle,
            remove,
            toast,
        };
    },
};
</script>
