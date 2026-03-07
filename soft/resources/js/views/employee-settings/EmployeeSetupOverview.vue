<template>
    <div class="bg-white rounded-lg border">
        <div class="p-5 border-b">
            <h2 class="text-lg font-semibold text-gray-900">Thiết lập nhanh</h2>
            <p class="text-sm text-gray-500 mt-1">
                Chỉ vài bước cài đặt để quản lý nhân viên hiệu quả, tối ưu vận
                hành và tính lương chính xác
            </p>
        </div>

        <div v-if="loading" class="p-6 flex items-center gap-2 text-gray-600">
            <div
                class="animate-spin rounded-full h-5 w-5 border-b-2 border-blue-500"
            ></div>
            <span>Đang tải...</span>
        </div>

        <div v-else class="divide-y">
            <div
                v-for="item in items"
                :key="item.key"
                class="p-5 flex items-center justify-between"
            >
                <div class="flex items-start gap-3">
                    <div
                        class="mt-0.5 h-6 w-6 rounded-full flex items-center justify-center"
                        :class="
                            item.done
                                ? 'bg-green-50 text-green-700'
                                : 'bg-gray-100 text-gray-500'
                        "
                    >
                        <span v-if="item.done">✓</span>
                        <span v-else>•</span>
                    </div>
                    <div>
                        <div class="font-medium text-gray-900">
                            {{ item.title }}
                        </div>
                        <div class="text-sm text-gray-500">
                            {{ item.subtitle }}
                        </div>
                        <button
                            v-if="item.linkText"
                            class="text-sm text-blue-600 hover:text-blue-800 mt-1"
                            type="button"
                            @click="$emit('navigate', item.target)"
                        >
                            {{ item.linkText }}
                        </button>
                    </div>
                </div>
                <button
                    v-if="item.actionText"
                    class="px-4 py-2 rounded border hover:bg-gray-50"
                    type="button"
                    @click="$emit('navigate', item.target)"
                >
                    {{ item.actionText }}
                </button>
            </div>
        </div>
    </div>
</template>

<script>
import { computed, onMounted, ref } from "vue";
import employeeApi from "@/api/employeeApi";

export default {
    name: "EmployeeSetupOverview",
    emits: ["navigate"],
    setup() {
        const loading = ref(false);
        const metrics = ref({
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
                metrics.value = res?.data?.data || metrics.value;
            } finally {
                loading.value = false;
            }
        };

        const items = computed(() => {
            const m = metrics.value;
            return [
                {
                    key: "employees",
                    title: "Thêm nhân viên",
                    subtitle: `Cửa hàng đang có ${m.employees_total} nhân viên.`,
                    done: m.employees_total > 0,
                    linkText: "Xem danh sách",
                    actionText: "Thêm nhân viên",
                    target: "employees",
                },
                {
                    key: "shifts",
                    title: "Tạo ca làm việc",
                    subtitle: `Cửa hàng đang có ${m.shifts_total} ca làm việc.`,
                    done: m.shifts_total > 0,
                    linkText: "Xem danh sách",
                    actionText: "Tạo ca",
                    target: "shifts",
                },
                {
                    key: "schedules",
                    title: "Xếp lịch làm việc",
                    subtitle: `Đã xếp lịch cho ${m.employees_scheduled_distinct}/${m.employees_total} nhân viên trong cửa hàng.`,
                    done:
                        m.employees_total > 0 &&
                        m.employees_scheduled_distinct > 0,
                    linkText: "Xem lịch",
                    actionText: "Xếp lịch",
                    target: "schedules",
                },
                {
                    key: "timekeeping",
                    title: "Hình thức chấm công",
                    subtitle: `Cửa hàng đã thiết lập ${m.devices_total} máy chấm công.`,
                    done: m.devices_total > 0,
                    linkText: "Xem chi tiết",
                    actionText: "Thiết lập",
                    target: "timekeeping",
                },
                {
                    key: "salary",
                    title: "Thiết lập lương",
                    subtitle: `Đã thiết lập lương cho ${m.salary_configs_total}/${m.employees_total} nhân viên.`,
                    done: m.employees_total > 0 && m.salary_configs_total > 0,
                    linkText: "Xem chi tiết",
                    actionText: "Thiết lập",
                    target: "payroll-settings",
                },
                {
                    key: "payroll_sheets",
                    title: "Thiết lập bảng lương",
                    subtitle:
                        "Theo dõi chính xác và tự động tính lương của nhân viên.",
                    done: m.payroll_sheets_total > 0,
                    linkText: "Xem danh sách",
                    actionText: "Tạo bảng lương",
                    target: "payroll",
                },
            ];
        });

        onMounted(load);

        return { loading, metrics, items };
    },
};
</script>
