<template>
    <div class="bg-gray-50 min-h-screen">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div class="grid grid-cols-12 gap-6">
                <aside class="col-span-12 lg:col-span-3">
                    <div class="bg-white rounded-lg border overflow-hidden">
                        <div class="p-4 border-b">
                            <div class="text-sm font-semibold text-gray-900">
                                Thiết lập nhân viên
                            </div>
                        </div>
                        <nav class="p-2">
                            <button
                                v-for="m in menu"
                                :key="m.key"
                                type="button"
                                @click="navigate(m.key)"
                                class="w-full flex items-center gap-3 px-3 py-2 rounded text-left"
                                :class="
                                    current === m.key
                                        ? 'bg-blue-50 text-blue-700'
                                        : 'hover:bg-gray-50 text-gray-700'
                                "
                            >
                                <span
                                    class="h-6 w-6 flex items-center justify-center rounded"
                                    :class="
                                        current === m.key
                                            ? 'bg-blue-100'
                                            : 'bg-gray-100'
                                    "
                                >
                                    <span class="text-sm">{{ m.icon }}</span>
                                </span>
                                <span class="font-medium">{{ m.label }}</span>
                            </button>
                        </nav>
                    </div>
                </aside>

                <main class="col-span-12 lg:col-span-9">
                    <EmployeeSetupOverview
                        v-if="current === 'overview'"
                        @navigate="navigate"
                    />
                    <ShiftManagement v-else-if="current === 'shifts'" />

                    <div
                        v-else-if="current === 'timekeeping'"
                        class="space-y-6"
                    >
                        <EmployeeSettings />
                    </div>

                    <div
                        v-else
                        class="bg-white rounded-lg border p-6 text-gray-600"
                    >
                        Đang triển khai...
                    </div>
                </main>
            </div>
        </div>
    </div>
</template>

<script>
import { computed, onMounted, ref } from "vue";
import EmployeeSetupOverview from "./EmployeeSetupOverview.vue";
import ShiftManagement from "./ShiftManagement.vue";
import EmployeeSettings from "@/views/EmployeeSettings.vue";

export default {
    name: "EmployeeSettingsApp",
    components: { EmployeeSetupOverview, ShiftManagement, EmployeeSettings },
    setup() {
        const menu = computed(() => [
            { key: "overview", label: "Khởi tạo", icon: "⚙" },
            { key: "shifts", label: "Ca làm việc", icon: "🕒" },
            { key: "timekeeping", label: "Chấm công", icon: "🗓" },
            { key: "payroll-settings", label: "Tính lương", icon: "₫" },
            { key: "workdays", label: "Ngày làm & Nghỉ", icon: "📅" },
        ]);

        const current = ref("overview");

        const navigate = (key) => {
            current.value = key;
            const url = new URL(window.location.href);
            url.searchParams.set("tab", key);
            window.history.replaceState({}, "", url.toString());
            window.scrollTo({ top: 0, behavior: "smooth" });
        };

        onMounted(() => {
            const url = new URL(window.location.href);
            const tab = url.searchParams.get("tab");
            if (tab) current.value = tab;
        });

        return { menu, current, navigate };
    },
};
</script>
