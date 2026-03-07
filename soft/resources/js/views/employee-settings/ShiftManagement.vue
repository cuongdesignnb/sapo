<template>
    <div class="bg-white rounded-lg border">
        <div class="p-5 border-b flex items-center justify-between">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">
                    Danh sách ca làm việc
                </h2>
                <p class="text-sm text-gray-500 mt-1">
                    Quản lý ca và thời gian cho phép chấm công
                </p>
            </div>
            <button
                class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"
                type="button"
                @click="openCreate"
            >
                + Thêm ca làm việc
            </button>
        </div>

        <div v-if="loading" class="p-6 flex items-center gap-2 text-gray-600">
            <div
                class="animate-spin rounded-full h-5 w-5 border-b-2 border-blue-500"
            ></div>
            <span>Đang tải...</span>
        </div>

        <div v-else class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th
                            class="text-left p-4 text-sm font-medium text-gray-600"
                        >
                            STT
                        </th>
                        <th
                            class="text-left p-4 text-sm font-medium text-gray-600"
                        >
                            Ca làm việc
                        </th>
                        <th
                            class="text-left p-4 text-sm font-medium text-gray-600"
                        >
                            Thời gian
                        </th>
                        <th
                            class="text-left p-4 text-sm font-medium text-gray-600"
                        >
                            Tổng giờ làm việc
                        </th>
                        <th
                            class="text-left p-4 text-sm font-medium text-gray-600"
                        >
                            Hoạt động
                        </th>
                        <th
                            class="text-left p-4 text-sm font-medium text-gray-600"
                        >
                            Thao tác
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr
                        v-for="(s, idx) in shifts"
                        :key="s.id"
                        class="hover:bg-gray-50"
                    >
                        <td class="p-4">{{ idx + 1 }}</td>
                        <td class="p-4 font-medium">{{ s.name }}</td>
                        <td class="p-4 text-sm text-gray-700">
                            <div>
                                {{
                                    s.work_time_text ||
                                    `${s.start_time} - ${s.end_time}`
                                }}
                            </div>
                            <div
                                v-if="s.checkin_time_text"
                                class="text-xs text-gray-500 mt-1"
                            >
                                Giờ cho phép chấm công:
                                {{ s.checkin_time_text }}
                            </div>
                        </td>
                        <td class="p-4 text-sm text-gray-700">
                            {{ s.duration_text || "-" }}
                        </td>
                        <td class="p-4">
                            <button
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition"
                                :class="
                                    s.status === 'active'
                                        ? 'bg-blue-600'
                                        : 'bg-gray-300'
                                "
                                type="button"
                                @click="toggle(s)"
                                :disabled="togglingId === s.id"
                            >
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
                            <div class="flex items-center gap-3">
                                <button
                                    class="text-slate-700 hover:text-slate-900 text-sm"
                                    type="button"
                                    @click="openEdit(s)"
                                >
                                    Sửa
                                </button>
                                <button
                                    class="text-red-600 hover:text-red-800 text-sm"
                                    type="button"
                                    @click="remove(s)"
                                >
                                    Xóa
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="shifts.length === 0">
                        <td colspan="6" class="p-8 text-center text-gray-500">
                            Chưa có ca làm việc
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            v-if="showModal"
            class="fixed inset-0 bg-black/40 flex items-center justify-center z-50"
        >
            <div class="bg-white rounded-lg w-full max-w-3xl p-6">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-lg font-semibold">
                        {{
                            editing
                                ? "Cập nhật ca làm việc"
                                : "Thêm ca làm việc"
                        }}
                    </h3>
                    <button
                        class="text-gray-500 hover:text-gray-700"
                        type="button"
                        @click="close"
                    >
                        ✕
                    </button>
                </div>

                <div class="grid grid-cols-2 gap-4 mt-4">
                    <div class="col-span-2">
                        <label
                            class="block text-sm font-medium text-gray-700 mb-1"
                            >Tên</label
                        >
                        <input
                            v-model="form.name"
                            type="text"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md"
                            placeholder="VD: Ca hành chính"
                        />
                    </div>

                    <div>
                        <label
                            class="block text-sm font-medium text-gray-700 mb-1"
                            >Giờ làm việc</label
                        >
                        <div class="flex items-center gap-2">
                            <input
                                v-model="form.start_time"
                                type="time"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md"
                            />
                            <span class="text-gray-500">Đến</span>
                            <input
                                v-model="form.end_time"
                                type="time"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md"
                            />
                        </div>
                        <div class="text-xs text-gray-500 mt-1">
                            {{ durationText }}
                        </div>
                    </div>

                    <div>
                        <label
                            class="block text-sm font-medium text-gray-700 mb-1"
                            >Giờ cho phép chấm công</label
                        >
                        <div class="flex items-center gap-2">
                            <input
                                v-model="form.checkin_start_time"
                                type="time"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md"
                            />
                            <span class="text-gray-500">Đến</span>
                            <input
                                v-model="form.checkin_end_time"
                                type="time"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md"
                            />
                        </div>
                        <div class="text-xs text-gray-500 mt-1">
                            (Tuỳ chọn) Nếu bỏ trống sẽ chấm công theo giờ làm
                            việc
                        </div>
                    </div>

                    <div>
                        <label
                            class="block text-sm font-medium text-gray-700 mb-1"
                            >Cho phép đi muộn (phút)</label
                        >
                        <input
                            v-model.number="form.allow_late_minutes"
                            type="number"
                            min="0"
                            max="1440"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md"
                        />
                    </div>

                    <div>
                        <label
                            class="block text-sm font-medium text-gray-700 mb-1"
                            >Cho phép về sớm (phút)</label
                        >
                        <input
                            v-model.number="form.allow_early_minutes"
                            type="number"
                            min="0"
                            max="1440"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md"
                        />
                    </div>

                    <div class="col-span-2">
                        <label
                            class="block text-sm font-medium text-gray-700 mb-1"
                            >Ghi chú</label
                        >
                        <textarea
                            v-model="form.notes"
                            rows="3"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md"
                        ></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 mt-6">
                    <button
                        class="px-4 py-2 rounded border"
                        type="button"
                        @click="close"
                    >
                        Bỏ qua
                    </button>
                    <button
                        class="px-4 py-2 rounded bg-blue-600 text-white hover:bg-blue-700"
                        type="button"
                        @click="save"
                        :disabled="saving"
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
                <div class="flex items-center">
                    <span class="mr-2">{{
                        toast.type === "success" ? "✓" : "!"
                    }}</span>
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
    name: "ShiftManagement",
    setup() {
        const loading = ref(false);
        const saving = ref(false);
        const togglingId = ref(null);
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
            setTimeout(() => (toast.value.show = false), 3000);
        };

        const timeToMinutes = (t) => {
            if (!t) return null;
            const parts = String(t).split(":");
            if (parts.length < 2) return null;
            const h = Number(parts[0]);
            const m = Number(parts[1]);
            if (Number.isNaN(h) || Number.isNaN(m)) return null;
            return h * 60 + m;
        };

        const durationText = computed(() => {
            const start = timeToMinutes(form.value.start_time);
            const end = timeToMinutes(form.value.end_time);
            if (start === null || end === null) return "";
            let diff = end - start;
            if (diff <= 0) diff += 24 * 60;
            const hours = Math.floor(diff / 60);
            const mins = diff % 60;
            if (mins === 0) return `${hours} giờ`;
            return `${hours} giờ ${mins} phút`;
        });

        const load = async () => {
            loading.value = true;
            try {
                const res = await employeeApi.getShifts({ per_page: 200 });
                shifts.value = res?.data?.data || [];
            } catch {
                showToast("Lỗi khi tải danh sách ca làm việc", "error");
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

        const normalizePayload = () => {
            const payload = { ...form.value };
            if (!payload.checkin_start_time) delete payload.checkin_start_time;
            if (!payload.checkin_end_time) delete payload.checkin_end_time;
            return payload;
        };

        const save = async () => {
            if (
                !form.value.name ||
                !form.value.start_time ||
                !form.value.end_time
            ) {
                showToast("Vui lòng nhập tên và giờ làm việc", "error");
                return;
            }

            saving.value = true;
            try {
                if (editing.value) {
                    await employeeApi.updateShift(
                        editing.value.id,
                        normalizePayload(),
                    );
                    showToast("Đã cập nhật ca làm việc");
                } else {
                    await employeeApi.createShift(normalizePayload());
                    showToast("Đã thêm ca làm việc");
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
            togglingId.value = s.id;
            try {
                const res = await employeeApi.toggleShift(s.id);
                const updated = res?.data?.data;
                if (updated) {
                    const idx = shifts.value.findIndex(
                        (x) => x.id === updated.id,
                    );
                    if (idx >= 0) shifts.value[idx] = updated;
                } else {
                    await load();
                }
            } catch {
                showToast("Không cập nhật được trạng thái", "error");
            } finally {
                togglingId.value = null;
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
                    e?.response?.data?.message || "Lỗi khi xóa ca làm việc",
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
            toast,
            togglingId,
            openCreate,
            openEdit,
            close,
            save,
            toggle,
            remove,
        };
    },
};
</script>
