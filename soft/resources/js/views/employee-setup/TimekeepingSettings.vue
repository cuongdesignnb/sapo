<template>
    <div class="space-y-6">
        <div class="bg-white rounded-xl border border-slate-200">
            <div class="p-6 border-b border-slate-100">
                <div class="text-lg font-semibold text-slate-900">
                    Thiết lập chấm công
                </div>
                <div class="text-sm text-slate-500 mt-1">
                    Thiết lập phương thức chấm công và các tuỳ chọn liên quan
                </div>
            </div>

            <div class="p-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <div class="font-medium text-slate-900">
                            Thiết bị / máy chấm công
                        </div>
                        <div class="text-sm text-slate-500 mt-1">
                            Cấu hình máy, sync logs, kiểm tra kết nối
                        </div>
                    </div>
                    <a
                        class="inline-flex items-center px-4 py-2 rounded-lg border border-slate-300 text-sm hover:bg-slate-50"
                        href="/employees/settings/devices"
                        >Mở cấu hình máy chấm công</a
                    >
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-200">
            <div class="p-6 border-b border-slate-100">
                <div class="text-lg font-semibold text-slate-900">
                    Quy định chấm công
                </div>
                <div class="text-sm text-slate-500 mt-1">
                    Áp dụng chung (có thể mở rộng theo chi nhánh)
                </div>
            </div>

            <div class="p-6">
                <div
                    v-if="loading"
                    class="flex items-center gap-2 text-slate-600"
                >
                    <div
                        class="animate-spin rounded-full h-5 w-5 border-b-2 border-blue-500"
                    ></div>
                    <span>Đang tải...</span>
                </div>

                <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label
                            class="block text-sm font-medium text-slate-700 mb-1"
                            >Giờ công chuẩn / ngày</label
                        >
                        <input
                            v-model.number="form.standard_hours_per_day"
                            type="number"
                            min="0"
                            max="24"
                            step="0.5"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg"
                        />
                        <div class="text-xs text-slate-500 mt-1">
                            Dùng để quy đổi work_units nếu cần.
                        </div>
                    </div>

                    <div>
                        <label
                            class="block text-sm font-medium text-slate-700 mb-1"
                            >Ưu tiên phút cho phép trong ca</label
                        >
                        <select
                            v-model="form.use_shift_allowances"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg"
                        >
                            <option :value="true">
                                Có (dùng allow_late/allow_early của Ca)
                            </option>
                            <option :value="false">
                                Không (dùng cấu hình chung bên dưới)
                            </option>
                        </select>
                    </div>

                    <div>
                        <label
                            class="block text-sm font-medium text-slate-700 mb-1"
                            >Cho phép đi muộn (phút)</label
                        >
                        <input
                            v-model.number="form.late_grace_minutes"
                            type="number"
                            min="0"
                            max="300"
                            step="1"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg"
                            :disabled="form.use_shift_allowances"
                        />
                    </div>

                    <div>
                        <label
                            class="block text-sm font-medium text-slate-700 mb-1"
                            >Cho phép về sớm (phút)</label
                        >
                        <input
                            v-model.number="form.early_grace_minutes"
                            type="number"
                            min="0"
                            max="300"
                            step="1"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg"
                            :disabled="form.use_shift_allowances"
                        />
                    </div>

                    <div>
                        <label
                            class="block text-sm font-medium text-slate-700 mb-1"
                            >Nhiều ca / 1 lần vào-ra</label
                        >
                        <select
                            v-model="form.allow_multiple_shifts_one_inout"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg"
                        >
                            <option :value="false">Không</option>
                            <option :value="true">Có</option>
                        </select>
                        <div class="text-xs text-slate-500 mt-1">
                            Dùng khi một ngày có nhiều slot/ca nhưng máy chỉ có
                            1 lượt vào & 1 lượt ra.
                        </div>
                    </div>

                    <div>
                        <label
                            class="block text-sm font-medium text-slate-700 mb-1"
                            >Bắt buộc trong khung chấm công của ca</label
                        >
                        <select
                            v-model="form.enforce_shift_checkin_window"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg"
                        >
                            <option :value="false">Không</option>
                            <option :value="true">Có</option>
                        </select>
                        <div class="text-xs text-slate-500 mt-1">
                            Nếu bật: chỉ nhận log trong "Giờ cho phép chấm công"
                            của ca.
                        </div>
                    </div>

                    <div>
                        <label
                            class="block text-sm font-medium text-slate-700 mb-1"
                            >OT làm tròn (phút)</label
                        >
                        <input
                            v-model.number="form.ot_rounding_minutes"
                            type="number"
                            min="0"
                            max="120"
                            step="1"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg"
                        />
                    </div>

                    <div>
                        <label
                            class="block text-sm font-medium text-slate-700 mb-1"
                            >OT tính sau (phút)</label
                        >
                        <input
                            v-model.number="form.ot_after_minutes"
                            type="number"
                            min="0"
                            max="300"
                            step="1"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg"
                        />
                        <div class="text-xs text-slate-500 mt-1">
                            VD: 15 phút =&gt; chỉ tính OT sau khi vượt qua 15
                            phút.
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
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
    name: "TimekeepingSettings",
    setup() {
        const toast = ref({ show: false, type: "success", message: "" });
        const showToast = (message, type = "success") => {
            toast.value = { show: true, type, message };
            setTimeout(() => (toast.value.show = false), 2500);
        };

        const loading = ref(false);
        const saving = ref(false);

        const form = ref({
            standard_hours_per_day: 8,
            use_shift_allowances: true,
            late_grace_minutes: 0,
            early_grace_minutes: 0,
            allow_multiple_shifts_one_inout: false,
            enforce_shift_checkin_window: false,
            ot_rounding_minutes: 0,
            ot_after_minutes: 0,
        });

        const load = async () => {
            loading.value = true;
            try {
                const res = await employeeApi.getTimekeepingSettings();
                const data = res?.data?.data;
                if (data) {
                    form.value = {
                        ...form.value,
                        standard_hours_per_day: Number(
                            data.standard_hours_per_day ?? 8,
                        ),
                        use_shift_allowances: Boolean(
                            data.use_shift_allowances ?? true,
                        ),
                        late_grace_minutes: Number(
                            data.late_grace_minutes ?? 0,
                        ),
                        early_grace_minutes: Number(
                            data.early_grace_minutes ?? 0,
                        ),
                        allow_multiple_shifts_one_inout: Boolean(
                            data.allow_multiple_shifts_one_inout ?? false,
                        ),
                        enforce_shift_checkin_window: Boolean(
                            data.enforce_shift_checkin_window ?? false,
                        ),
                        ot_rounding_minutes: Number(
                            data.ot_rounding_minutes ?? 0,
                        ),
                        ot_after_minutes: Number(data.ot_after_minutes ?? 0),
                    };
                }
            } catch {
                // ignore
            } finally {
                loading.value = false;
            }
        };

        const save = async () => {
            saving.value = true;
            try {
                await employeeApi.saveTimekeepingSettings({
                    standard_hours_per_day: form.value.standard_hours_per_day,
                    use_shift_allowances: form.value.use_shift_allowances,
                    late_grace_minutes: form.value.late_grace_minutes,
                    early_grace_minutes: form.value.early_grace_minutes,
                    allow_multiple_shifts_one_inout:
                        form.value.allow_multiple_shifts_one_inout,
                    enforce_shift_checkin_window:
                        form.value.enforce_shift_checkin_window,
                    ot_rounding_minutes: form.value.ot_rounding_minutes,
                    ot_after_minutes: form.value.ot_after_minutes,
                });

                showToast("Đã lưu thiết lập chấm công");
            } catch (e) {
                showToast(
                    e?.response?.data?.message || "Lưu thất bại",
                    "error",
                );
            } finally {
                saving.value = false;
            }
        };

        onMounted(load);

        return { toast, loading, saving, form, save };
    },
};
</script>
