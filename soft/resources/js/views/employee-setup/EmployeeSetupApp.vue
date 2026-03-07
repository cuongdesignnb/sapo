<template>
    <div class="min-h-screen bg-slate-50">
        <div class="max-w-[1400px] mx-auto px-4 py-6">
            <div class="flex gap-6">
                <!-- Sidebar -->
                <aside class="w-64 shrink-0">
                    <div
                        class="bg-white rounded-xl border border-slate-200 overflow-hidden"
                    >
                        <div class="px-4 py-4 border-b border-slate-100">
                            <div class="text-sm text-slate-500">
                                Thiết lập nhân viên
                            </div>
                        </div>
                        <nav class="p-2">
                            <button
                                v-for="item in menu"
                                :key="item.key"
                                class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-left text-sm"
                                :class="
                                    activeKey === item.key
                                        ? 'bg-blue-50 text-blue-700'
                                        : 'text-slate-700 hover:bg-slate-50'
                                "
                                type="button"
                                @click="activeKey = item.key"
                            >
                                <span
                                    class="w-6 text-center"
                                    aria-hidden="true"
                                    >{{ item.icon }}</span
                                >
                                <span class="font-medium">{{
                                    item.label
                                }}</span>
                            </button>
                        </nav>
                    </div>
                </aside>

                <!-- Content -->
                <main class="flex-1">
                    <component :is="activeComponent" @navigate="onNavigate" />
                </main>
            </div>
        </div>
    </div>
</template>

<script>
import { computed, ref } from "vue";
import SetupQuick from "./SetupQuick.vue";
import ShiftSettings from "./ShiftSettings.vue";
import TimekeepingSettings from "./TimekeepingSettings.vue";
import PayrollSettings from "./PayrollSettings.vue";
import WorkdaySettings from "./WorkdaySettings.vue";

export default {
    name: "EmployeeSetupApp",
    components: {
        SetupQuick,
        ShiftSettings,
        TimekeepingSettings,
        PayrollSettings,
        WorkdaySettings,
    },
    setup() {
        const menu = [
            { key: "quick", label: "Khởi tạo", icon: "⚙" },
            { key: "shifts", label: "Ca làm việc", icon: "🕒" },
            { key: "timekeeping", label: "Chấm công", icon: "🗓" },
            { key: "payroll", label: "Tính lương", icon: "💰" },
            { key: "workday", label: "Ngày làm & Nghỉ", icon: "📅" },
        ];

        const activeKey = ref("quick");

        const activeComponent = computed(() => {
            switch (activeKey.value) {
                case "shifts":
                    return "ShiftSettings";
                case "timekeeping":
                    return "TimekeepingSettings";
                case "payroll":
                    return "PayrollSettings";
                case "workday":
                    return "WorkdaySettings";
                case "quick":
                default:
                    return "SetupQuick";
            }
        });

        const onNavigate = (key) => {
            if (menu.some((m) => m.key === key)) {
                activeKey.value = key;
            }
        };

        return { menu, activeKey, activeComponent, onNavigate };
    },
};
</script>
