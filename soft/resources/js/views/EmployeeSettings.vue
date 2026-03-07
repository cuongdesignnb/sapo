<template>
    <div class="bg-white">
        <div class="p-6 border-b">
            <h1 class="text-2xl font-semibold text-gray-900">
                Thiết lập nhân viên
            </h1>
            <p class="text-sm text-gray-500 mt-1">
                Quản lý máy chấm công và cấu hình kết nối LAN
            </p>
        </div>

        <div class="p-6 border-b bg-white flex justify-end">
            <button
                class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600"
                @click="openCreate"
            >
                + Thêm máy chấm công
            </button>
        </div>

        <div v-if="loading" class="flex justify-center items-center py-12">
            <div
                class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"
            ></div>
            <span class="ml-2 text-gray-600">Đang tải...</span>
        </div>

        <div v-else class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th
                            class="text-left p-4 text-sm font-medium text-gray-600"
                        >
                            Chi nhánh
                        </th>
                        <th
                            class="text-left p-4 text-sm font-medium text-gray-600"
                        >
                            Tên máy
                        </th>
                        <th
                            class="text-left p-4 text-sm font-medium text-gray-600"
                        >
                            Model
                        </th>
                        <th
                            class="text-left p-4 text-sm font-medium text-gray-600"
                        >
                            Seri
                        </th>
                        <th
                            class="text-left p-4 text-sm font-medium text-gray-600"
                        >
                            IP
                        </th>
                        <th
                            class="text-left p-4 text-sm font-medium text-gray-600"
                        >
                            TCP Port
                        </th>
                        <th
                            class="text-left p-4 text-sm font-medium text-gray-600"
                        >
                            Mã kết nối
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
                        v-for="d in devices"
                        :key="d.id"
                        class="hover:bg-gray-50"
                    >
                        <td class="p-4">{{ d.warehouse?.name || "-" }}</td>
                        <td class="p-4 font-medium">{{ d.name }}</td>
                        <td class="p-4 text-sm text-gray-600">
                            {{ d.model || "-" }}
                        </td>
                        <td class="p-4 text-sm text-gray-600">
                            {{ d.serial_number || "-" }}
                        </td>
                        <td class="p-4 text-sm text-gray-600">
                            {{ d.ip_address }}
                        </td>
                        <td class="p-4 text-sm text-gray-600">
                            {{ d.tcp_port }}
                        </td>
                        <td class="p-4 text-sm text-gray-600">
                            {{ d.comm_key }}
                        </td>
                        <td class="p-4">
                            <div class="flex items-center gap-2">
                                <button
                                    class="text-blue-600 hover:text-blue-800 text-sm"
                                    @click="openEdit(d)"
                                >
                                    Cập nhật
                                </button>
                                <button
                                    class="text-slate-600 hover:text-slate-800 text-sm"
                                    @click="testConn(d)"
                                >
                                    Test
                                </button>
                                <button
                                    class="text-slate-600 hover:text-slate-800 text-sm"
                                    @click="sync(d)"
                                >
                                    Đồng bộ
                                </button>
                                <button
                                    class="text-red-600 hover:text-red-800 text-sm"
                                    @click="remove(d)"
                                >
                                    Xóa
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="devices.length === 0">
                        <td colspan="8" class="p-8 text-center text-gray-500">
                            Chưa có máy chấm công
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Modal giống hình #2 -->
        <div
            v-if="showModal"
            class="fixed inset-0 bg-black/40 flex items-center justify-center z-50"
        >
            <div class="bg-white rounded-lg w-full max-w-3xl p-6">
                <div class="flex items-center justify-between mb-2">
                    <h2 class="text-lg font-semibold">
                        {{
                            editing
                                ? "Cập nhật máy chấm công"
                                : "Thêm máy chấm công"
                        }}
                    </h2>
                    <button
                        class="text-gray-500 hover:text-gray-700"
                        @click="close"
                    >
                        ✕
                    </button>
                </div>

                <div class="grid grid-cols-2 gap-4 mt-4">
                    <div>
                        <label
                            class="block text-sm font-medium text-gray-700 mb-1"
                            >Chi nhánh</label
                        >
                        <select
                            v-model="form.warehouse_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md"
                        >
                            <option :value="null">-</option>
                            <option
                                v-for="w in warehouses"
                                :key="w.id"
                                :value="w.id"
                            >
                                {{ w.name }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label
                            class="block text-sm font-medium text-gray-700 mb-1"
                            >Tên máy *</label
                        >
                        <input
                            v-model="form.name"
                            type="text"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md"
                        />
                    </div>

                    <div>
                        <label
                            class="block text-sm font-medium text-gray-700 mb-1"
                            >Model máy</label
                        >
                        <input
                            v-model="form.model"
                            type="text"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md"
                        />
                    </div>
                    <div>
                        <label
                            class="block text-sm font-medium text-gray-700 mb-1"
                            >Seri máy</label
                        >
                        <input
                            v-model="form.serial_number"
                            type="text"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md"
                        />
                    </div>

                    <div class="col-span-2">
                        <div class="text-sm font-semibold text-gray-700 mt-2">
                            Thông tin kết nối máy chấm công qua LAN
                        </div>
                    </div>

                    <div>
                        <label
                            class="block text-sm font-medium text-gray-700 mb-1"
                            >Địa chỉ IP *</label
                        >
                        <input
                            v-model="form.ip_address"
                            type="text"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md"
                        />
                    </div>
                    <div>
                        <label
                            class="block text-sm font-medium text-gray-700 mb-1"
                            >Cổng liên kết TCP</label
                        >
                        <input
                            v-model.number="form.tcp_port"
                            type="number"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md"
                        />
                    </div>

                    <div>
                        <label
                            class="block text-sm font-medium text-gray-700 mb-1"
                            >Mã kết nối</label
                        >
                        <input
                            v-model.number="form.comm_key"
                            type="number"
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
                    <button class="px-4 py-2 rounded border" @click="close">
                        Bỏ qua
                    </button>
                    <button
                        class="px-4 py-2 rounded bg-green-600 text-white hover:bg-green-700"
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
                        toast.type === "success" ? "✅" : "❌"
                    }}</span>
                    <span>{{ toast.message }}</span>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, onMounted } from "vue";
import employeeApi from "@/api/employeeApi";
import warehouseApi from "@/api/warehouseApi";

export default {
    name: "EmployeeSettings",
    setup() {
        const loading = ref(false);
        const saving = ref(false);
        const devices = ref([]);
        const warehouses = ref([]);

        const showModal = ref(false);
        const editing = ref(null);
        const form = ref({
            warehouse_id: null,
            name: "",
            model: "",
            serial_number: "",
            ip_address: "",
            tcp_port: 4370,
            comm_key: 0,
            notes: "",
        });

        const toast = ref({ show: false, type: "success", message: "" });
        const showToast = (message, type = "success") => {
            toast.value = { show: true, type, message };
            setTimeout(() => (toast.value.show = false), 3000);
        };

        const loadWarehouses = async () => {
            try {
                const res = await warehouseApi.getWarehouses({ per_page: 200 });
                warehouses.value = res?.data?.data || res?.data || [];
            } catch {
                warehouses.value = [];
            }
        };

        const load = async () => {
            loading.value = true;
            try {
                const res = await employeeApi.getDevices();
                devices.value = res.data?.data || [];
            } catch {
                showToast("Lỗi khi tải danh sách máy chấm công", "error");
            } finally {
                loading.value = false;
            }
        };

        const openCreate = () => {
            editing.value = null;
            form.value = {
                warehouse_id: null,
                name: "",
                model: "",
                serial_number: "",
                ip_address: "",
                tcp_port: 4370,
                comm_key: 0,
                notes: "",
            };
            showModal.value = true;
        };

        const openEdit = (d) => {
            editing.value = d;
            form.value = {
                warehouse_id: d.warehouse_id ?? null,
                name: d.name,
                model: d.model || "",
                serial_number: d.serial_number || "",
                ip_address: d.ip_address,
                tcp_port: d.tcp_port || 4370,
                comm_key: d.comm_key ?? 0,
                notes: d.notes || "",
            };
            showModal.value = true;
        };

        const close = () => (showModal.value = false);

        const save = async () => {
            saving.value = true;
            try {
                if (editing.value) {
                    await employeeApi.updateDevice(
                        editing.value.id,
                        form.value
                    );
                    showToast("Đã cập nhật máy chấm công");
                } else {
                    await employeeApi.createDevice(form.value);
                    showToast("Đã thêm máy chấm công");
                }
                showModal.value = false;
                await load();
            } catch {
                showToast("Lỗi khi lưu máy chấm công", "error");
            } finally {
                saving.value = false;
            }
        };

        const testConn = async (d) => {
            try {
                const res = await employeeApi.testDeviceConnection(d.id);
                showToast(res.data?.message || "Test kết nối thành công");
            } catch (e) {
                showToast(
                    e?.response?.data?.message || "Không kết nối được",
                    "error"
                );
            }
        };

        const sync = async (d) => {
            try {
                const res = await employeeApi.syncDevice(d.id);
                showToast(res.data?.message || "Đã đồng bộ");
                await load();
            } catch (e) {
                const resp = e?.response?.data;
                const hint = resp?.data?.hint;
                const detail = resp?.data?.exception?.message;
                const msg = [resp?.message || "Đồng bộ thất bại", hint, detail]
                    .filter(Boolean)
                    .join(" | ");
                showToast(msg, "error");
            }
        };

        const remove = async (d) => {
            if (!confirm(`Xóa máy chấm công ${d.name}?`)) return;
            try {
                await employeeApi.deleteDevice(d.id);
                showToast("Đã xóa máy chấm công");
                await load();
            } catch {
                showToast("Lỗi khi xóa máy chấm công", "error");
            }
        };

        onMounted(async () => {
            await loadWarehouses();
            await load();
        });

        return {
            loading,
            saving,
            devices,
            warehouses,
            showModal,
            editing,
            form,
            openCreate,
            openEdit,
            close,
            save,
            testConn,
            sync,
            remove,
            toast,
        };
    },
};
</script>
