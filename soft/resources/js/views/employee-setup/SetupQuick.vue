<template>
    <div class="bg-white rounded-xl border border-slate-200">
        <div class="p-6 border-b border-slate-100">
            <div class="text-lg font-semibold text-slate-900">
                Thiết lập nhanh
            </div>
            <div class="text-sm text-slate-500 mt-1">
                Chỉ vài bước cài đặt để quản lý nhân viên hiệu quả, tối ưu vận
                hành và tính lương chính xác
            </div>
        </div>

        <div class="p-6">
            <div v-if="loading" class="flex items-center gap-2 text-slate-600">
                <div
                    class="animate-spin rounded-full h-5 w-5 border-b-2 border-blue-500"
                ></div>
                <span>Đang tải...</span>
            </div>

            <div v-else class="divide-y divide-slate-100">
                <div class="py-4 flex items-center justify-between">
                    <div class="flex items-start gap-3">
                        <div
                            class="mt-0.5"
                            :class="
                                overview.employees_total > 0
                                    ? 'text-green-600'
                                    : 'text-slate-300'
                            "
                        >
                            ✔
                        </div>
                        <div>
                            <div class="font-medium text-slate-900">
                                Thêm nhân viên
                            </div>
                            <div class="text-sm text-slate-500">
                                Cửa hàng đang có
                                {{ overview.employees_total }} nhân viên.
                            </div>
                        </div>
                    </div>
                    <button
                        class="px-3 py-2 rounded-lg border border-slate-300 text-sm hover:bg-slate-50"
                        type="button"
                        @click="$emit('navigate', 'quick')"
                    >
                        Xem
                    </button>
                </div>

                <div class="py-4 flex items-center justify-between">
                    <div class="flex items-start gap-3">
                        <div
                            class="mt-0.5"
                            :class="
                                overview.shifts_total > 0
                                    ? 'text-green-600'
                                    : 'text-slate-300'
                            "
                        >
                            ✔
                        </div>
                        <div>
                            <div class="font-medium text-slate-900">
                                Tạo ca làm việc
                            </div>
                            <div class="text-sm text-slate-500">
                                Cửa hàng đang có {{ overview.shifts_total }} ca
                                làm việc.
                            </div>
                        </div>
                    </div>
                    <button
                        class="px-3 py-2 rounded-lg border border-slate-300 text-sm hover:bg-slate-50"
                        type="button"
                        @click="$emit('navigate', 'shifts')"
                    >
                        Tạo ca
                    </button>
                </div>

                <div class="py-4 flex items-center justify-between">
                    <div class="flex items-start gap-3">
                        <div
                            class="mt-0.5"
                            :class="
                                overview.employees_scheduled_distinct > 0
                                    ? 'text-green-600'
                                    : 'text-slate-300'
                            "
                        >
                            ✔
                        </div>
                        <div>
                            <div class="font-medium text-slate-900">
                                Xếp lịch làm việc
                            </div>
                            <div class="text-sm text-slate-500">
                                Đã xếp lịch cho
                                {{ overview.employees_scheduled_distinct }}/{{
                                    overview.employees_total
                                }}
                                nhân viên.
                            </div>
                        </div>
                    </div>
                    <a
                        class="px-3 py-2 rounded-lg border border-slate-300 text-sm hover:bg-slate-50"
                        href="/employees/schedules"
                        >Xếp lịch</a
                    >
                </div>

                <div class="py-4 flex items-center justify-between">
                    <div class="flex items-start gap-3">
                        <div
                            class="mt-0.5"
                            :class="
                                overview.devices_total > 0
                                    ? 'text-green-600'
                                    : 'text-slate-300'
                            "
                        >
                            ✔
                        </div>
                        <div>
                            <div class="font-medium text-slate-900">
                                Hình thức chấm công
                            </div>
                            <div class="text-sm text-slate-500">
                                Cửa hàng đang có
                                {{ overview.devices_total }} máy chấm công.
                            </div>
                        </div>
                    </div>
                    <button
                        class="px-3 py-2 rounded-lg border border-slate-300 text-sm hover:bg-slate-50"
                        type="button"
                        @click="$emit('navigate', 'timekeeping')"
                    >
                        Thiết lập
                    </button>
                </div>

                <div class="py-4 flex items-center justify-between">
                    <div class="flex items-start gap-3">
                        <div
                            class="mt-0.5"
                            :class="
                                overview.salary_configs_total > 0
                                    ? 'text-green-600'
                                    : 'text-slate-300'
                            "
                        >
                            ✔
                        </div>
                        <div>
                            <div class="font-medium text-slate-900">
                                Thiết lập lương
                            </div>
                            <div class="text-sm text-slate-500">
                                Đã thiết lập lương cho
                                {{ overview.salary_configs_total }}/{{
                                    overview.employees_total
                                }}
                                nhân viên.
                            </div>
                        </div>
                    </div>
                    <a
                        class="px-3 py-2 rounded-lg border border-slate-300 text-sm hover:bg-slate-50"
                        href="/employees"
                        >Xem</a
                    >
                </div>

                <div class="py-4 flex items-center justify-between">
                    <div class="flex items-start gap-3">
                        <div
                            class="mt-0.5"
                            :class="
                                overview.payroll_sheets_total > 0
                                    ? 'text-green-600'
                                    : 'text-slate-300'
                            "
                        >
                            ✔
                        </div>
                        <div>
                            <div class="font-medium text-slate-900">
                                Thiết lập bảng lương
                            </div>
                            <div class="text-sm text-slate-500">
                                Đang có {{ overview.payroll_sheets_total }} bảng
                                lương.
                            </div>
                        </div>
                    </div>
                    <a
                        class="px-3 py-2 rounded-lg border border-slate-300 text-sm hover:bg-slate-50"
                        href="/employees/payroll"
                        >Tạo bảng lương</a
                    >
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { onMounted, ref } from "vue";
import employeeApi from "@/api/employeeApi";

export default {
    name: "SetupQuick",
    emits: ["navigate"],
    setup() {
        const loading = ref(false);
        const overview = ref({
            employees_total: 0,
            shifts_total: 0,
            schedules_total: 0,
            devices_total: 0,
            salary_configs_total: 0,
            payroll_sheets_total: 0,
            employees_scheduled_distinct: 0,
        });

        const load = async () => {
            loading.value = true;
            try {
                const res = await employeeApi.getEmployeeSetupOverview();
                overview.value = res?.data?.data || overview.value;
            } catch {
                // ignore
            } finally {
                loading.value = false;
            }
        };

        onMounted(load);

        return { loading, overview };
    },
};
</script>
