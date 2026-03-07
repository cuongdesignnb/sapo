<template>
    <div class="bg-white min-h-screen">
        <!-- Header -->
        <div class="p-6 border-b bg-white sticky top-0 z-10">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900">
                        Tạo đơn đặt hàng (Sắp nhập)
                    </h1>
                    <div class="text-sm text-gray-500 mt-1">
                        <span>Trang chủ</span> / <span>Đơn đặt hàng</span> /
                        <span class="text-gray-900">Tạo mới</span>
                    </div>
                </div>
                <div class="flex space-x-3">
                    <button
                        @click="goBack"
                        class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
                    >
                        ← Quay lại
                    </button>
                    <button
                        @click="saveDraft"
                        class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
                        :disabled="loading"
                    >
                        💾 Lưu nháp
                    </button>
                    <button
                        @click="saveAndSubmit"
                        class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600"
                        :disabled="loading"
                    >
                        📤 Lưu và gửi duyệt
                    </button>
                </div>
            </div>
        </div>

        <div class="flex">
            <!-- Main Content -->
            <div class="flex-1 p-6">
                <!-- Supplier Info -->
                <div class="bg-white border border-gray-200 rounded-lg mb-6">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2
                            class="text-lg font-medium text-gray-900 flex items-center"
                        >
                            🏢 Thông tin nhà cung cấp
                        </h2>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-2 gap-6">
                            <div>
                                <label
                                    class="block text-sm font-medium text-gray-700 mb-2"
                                >
                                    Nhà cung cấp
                                    <span class="text-red-500">*</span>
                                </label>
                                <select
                                    v-model="form.supplier_id"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    required
                                    @change="onSupplierChange"
                                >
                                    <option value="">Chọn nhà cung cấp</option>
                                    <option
                                        v-for="supplier in suppliers"
                                        :key="supplier.id"
                                        :value="supplier.id"
                                    >
                                        {{ supplier.name }}
                                    </option>
                                </select>
                                <div
                                    v-if="selectedSupplier"
                                    class="mt-2 text-sm text-gray-500"
                                >
                                    📞 {{ selectedSupplier.phone }}
                                    <span
                                        v-if="selectedSupplier.email"
                                        class="ml-3"
                                    >
                                        ✉️ {{ selectedSupplier.email }}
                                    </span>
                                </div>
                            </div>
                            <div>
                                <label
                                    class="block text-sm font-medium text-gray-700 mb-2"
                                >
                                    Kho nhập <span class="text-red-500">*</span>
                                </label>
                                <select
                                    v-model="form.warehouse_id"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    required
                                >
                                    <option value="">Chọn kho</option>
                                    <option
                                        v-for="warehouse in warehouses"
                                        :key="warehouse.id"
                                        :value="warehouse.id"
                                    >
                                        {{ warehouse.name }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-6 mt-6">
                            <div>
                                <label
                                    class="block text-sm font-medium text-gray-700 mb-2"
                                    >Ngày dự kiến nhập</label
                                >
                                <input
                                    v-model="form.expected_at"
                                    type="date"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    :min="todayDate"
                                />
                            </div>
                            <div>
                                <label
                                    class="block text-sm font-medium text-gray-700 mb-2"
                                    >Người liên hệ giao hàng</label
                                >
                                <input
                                    v-model="form.delivery_contact"
                                    type="text"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    placeholder="Tên người liên hệ"
                                />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-6 mt-6">
                            <div>
                                <label
                                    class="block text-sm font-medium text-gray-700 mb-2"
                                    >Số điện thoại</label
                                >
                                <input
                                    v-model="form.delivery_phone"
                                    type="tel"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    placeholder="Số điện thoại"
                                />
                            </div>
                            <div>
                                <label
                                    class="block text-sm font-medium text-gray-700 mb-2"
                                    >Địa chỉ giao hàng</label
                                >
                                <input
                                    v-model="form.delivery_address"
                                    type="text"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    placeholder="Địa chỉ giao hàng"
                                />
                            </div>
                        </div>

                        <div class="mt-6">
                            <label
                                class="block text-sm font-medium text-gray-700 mb-2"
                                >Ghi chú</label
                            >
                            <textarea
                                v-model="form.note"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                rows="3"
                                placeholder="Ghi chú cho đơn nhập hàng..."
                            ></textarea>
                        </div>
                    </div>
                </div>

                <!-- Products -->
                <div class="bg-white border border-gray-200 rounded-lg">
                    <div
                        class="px-6 py-4 border-b border-gray-200 flex justify-between items-center"
                    >
                        <h2
                            class="text-lg font-medium text-gray-900 flex items-center"
                        >
                            📦 Danh sách sản phẩm
                        </h2>
                        <button
                            @click="addProduct"
                            class="px-3 py-1 text-sm bg-blue-500 text-white rounded hover:bg-blue-600"
                        >
                            + Thêm sản phẩm
                        </button>
                    </div>
                    <div class="p-6">
                        <div
                            v-if="form.items.length === 0"
                            class="text-center py-12"
                        >
                            <div class="text-6xl mb-4">📦</div>
                            <h3 class="text-lg font-medium text-gray-900 mb-2">
                                Chưa có sản phẩm nào
                            </h3>
                            <p class="text-gray-500 mb-4">
                                Hãy thêm sản phẩm vào đơn nhập hàng
                            </p>
                            <button
                                @click="addProduct"
                                class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600"
                            >
                                + Thêm sản phẩm đầu tiên
                            </button>
                        </div>

                        <div v-else>
                            <div class="overflow-x-auto">
                                <table class="w-full">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th
                                                class="text-left p-3 text-sm font-medium text-gray-600"
                                                style="width: 35%"
                                            >
                                                Sản phẩm
                                            </th>
                                            <th
                                                class="text-left p-3 text-sm font-medium text-gray-600"
                                                style="width: 12%"
                                            >
                                                Số lượng
                                            </th>
                                            <th
                                                class="text-left p-3 text-sm font-medium text-gray-600"
                                                style="width: 15%"
                                            >
                                                Đơn giá
                                            </th>
                                            <th
                                                class="text-left p-3 text-sm font-medium text-gray-600"
                                                style="width: 15%"
                                            >
                                                Thành tiền
                                            </th>
                                            <th
                                                class="text-left p-3 text-sm font-medium text-gray-600"
                                                style="width: 18%"
                                            >
                                                Ghi chú
                                            </th>
                                            <th
                                                class="text-left p-3 text-sm font-medium text-gray-600"
                                                style="width: 5%"
                                            ></th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        <tr
                                            v-for="(item, index) in form.items"
                                            :key="index"
                                        >
                                            <td class="p-3">
                                                <select
                                                    v-model="item.product_id"
                                                    class="w-full px-2 py-1 text-sm border border-gray-300 rounded"
                                                    @change="
                                                        updateProductInfo(index)
                                                    "
                                                >
                                                    <option value="">
                                                        Chọn sản phẩm
                                                    </option>
                                                    <option
                                                        v-for="product in products"
                                                        :key="product.id"
                                                        :value="product.id"
                                                    >
                                                        {{ product.name }} ({{
                                                            product.sku
                                                        }})
                                                    </option>
                                                </select>
                                                <div
                                                    v-if="
                                                        getSelectedProduct(
                                                            item.product_id
                                                        )
                                                    "
                                                    class="mt-1 text-xs text-gray-500"
                                                >
                                                    SKU:
                                                    {{
                                                        getSelectedProduct(
                                                            item.product_id
                                                        ).sku
                                                    }}
                                                </div>
                                            </td>
                                            <td class="p-3">
                                                <input
                                                    v-model.number="
                                                        item.quantity
                                                    "
                                                    type="number"
                                                    class="w-full px-2 py-1 text-sm border border-gray-300 rounded"
                                                    min="1"
                                                    @input="
                                                        calculateTotal(index)
                                                    "
                                                />
                                            </td>
                                            <td class="p-3">
                                                <input
                                                    v-model.number="item.price"
                                                    type="number"
                                                    class="w-full px-2 py-1 text-sm border border-gray-300 rounded"
                                                    min="0"
                                                    step="1000"
                                                    @input="
                                                        calculateTotal(index)
                                                    "
                                                />
                                            </td>
                                            <td class="p-3">
                                                <div
                                                    class="font-medium text-blue-600"
                                                >
                                                    {{
                                                        formatCurrency(
                                                            item.total
                                                        )
                                                    }}
                                                </div>
                                            </td>
                                            <td class="p-3">
                                                <input
                                                    v-model="item.note"
                                                    type="text"
                                                    class="w-full px-2 py-1 text-sm border border-gray-300 rounded"
                                                    placeholder="Ghi chú sản phẩm..."
                                                />
                                            </td>
                                            <td class="p-3 text-center">
                                                <button
                                                    @click="
                                                        removeProduct(index)
                                                    "
                                                    class="text-red-600 hover:text-red-800"
                                                >
                                                    🗑️
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="flex justify-end mt-4">
                                <button
                                    @click="addProduct"
                                    class="px-4 py-2 border border-gray-300 rounded text-gray-700 hover:bg-gray-50"
                                >
                                    + Thêm sản phẩm
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="w-80 p-6 border-l border-gray-200">
                <!-- Summary -->
                <div class="bg-white border border-gray-200 rounded-lg mb-6">
                    <div class="px-4 py-3 border-b border-gray-200">
                        <h3
                            class="text-sm font-medium text-gray-900 flex items-center"
                        >
                            🧮 Tóm tắt đơn đặt hàng
                        </h3>
                    </div>
                    <div class="p-4">
                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div class="text-center">
                                <div class="text-2xl font-bold text-blue-600">
                                    {{ form.items.length }}
                                </div>
                                <div class="text-xs text-gray-500">
                                    Loại sản phẩm
                                </div>
                            </div>
                            <div class="text-center">
                                <div class="text-2xl font-bold text-green-600">
                                    {{ totalQuantity }}
                                </div>
                                <div class="text-xs text-gray-500">
                                    Tổng số lượng
                                </div>
                            </div>
                        </div>

                        <div class="border-t pt-4">
                            <div class="flex justify-between text-sm mb-2">
                                <span>Tạm tính:</span>
                                <span class="font-medium">{{
                                    formatCurrency(subtotal)
                                }}</span>
                            </div>
                            <div class="flex justify-between text-sm mb-2">
                                <span>Thuế VAT:</span>
                                <span>{{ formatCurrency(0) }}</span>
                            </div>
                            <div class="flex justify-between text-sm mb-2">
                                <span>Phí vận chuyển:</span>
                                <span>{{ formatCurrency(0) }}</span>
                            </div>

                            <div class="border-t pt-2 mt-2">
                                <div
                                    class="flex justify-between text-lg font-bold"
                                >
                                    <span>Tổng cộng:</span>
                                    <span class="text-blue-600">{{
                                        formatCurrency(subtotal)
                                    }}</span>
                                </div>
                            </div>
                            <div
                                class="mt-4 p-3 rounded bg-indigo-50 text-[11px] text-indigo-700 leading-relaxed"
                            >
                                Đây là đơn đặt hàng dùng để lập kế hoạch. Chưa
                                phát sinh nhập kho hay công nợ. Sau khi được
                                duyệt bạn có thể chuyển sang đơn nhập thực tế để
                                tạo phiếu nhập và ghi nhận công nợ.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="bg-white border border-gray-200 rounded-lg mb-6">
                    <div class="px-4 py-3 border-b border-gray-200">
                        <h3
                            class="text-sm font-medium text-gray-900 flex items-center"
                        >
                            ⚡ Thao tác nhanh (Sắp nhập)
                        </h3>
                    </div>
                    <div class="p-4 space-y-3">
                        <button
                            @click="saveDraft"
                            class="w-full px-4 py-2 border border-gray-300 rounded text-gray-700 hover:bg-gray-50"
                            :disabled="loading || !canSave"
                        >
                            💾 <span v-if="loading">Đang lưu...</span
                            ><span v-else>Lưu nháp</span>
                        </button>
                        <button
                            @click="saveAndSubmit"
                            class="w-full px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600"
                            :disabled="loading || !canSave"
                        >
                            📤 <span v-if="loading">Đang xử lý...</span
                            ><span v-else>Lưu và gửi duyệt</span>
                        </button>
                        <button
                            @click="goBack"
                            class="w-full px-4 py-2 border border-gray-300 rounded text-gray-700 hover:bg-gray-50"
                        >
                            ❌ Hủy bỏ
                        </button>
                    </div>
                </div>

                <!-- Tips -->
                <div class="bg-white border border-gray-200 rounded-lg">
                    <div class="px-4 py-3 border-b border-gray-200">
                        <h3
                            class="text-sm font-medium text-gray-900 flex items-center"
                        >
                            💡 Gợi ý (Đơn đặt hàng)
                        </h3>
                    </div>
                    <div class="p-4">
                        <ul class="text-xs space-y-2">
                            <li class="flex items-start">
                                <span class="text-green-500 mr-2">✅</span>
                                <span
                                    >Kiểm tra thông tin nhà cung cấp trước khi
                                    tạo đơn</span
                                >
                            </li>
                            <li class="flex items-start">
                                <span class="text-green-500 mr-2">✅</span>
                                <span
                                    >Đảm bảo kho có đủ không gian chứa
                                    hàng</span
                                >
                            </li>
                            <li class="flex items-start">
                                <span class="text-green-500 mr-2">✅</span>
                                <span
                                    >Liên hệ trước với nhà cung cấp về thời gian
                                    giao hàng</span
                                >
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Toast Notification -->
        <div v-if="notification.show" class="fixed top-4 right-4 z-50">
            <div
                class="p-4 rounded-lg shadow-lg max-w-sm"
                :class="{
                    'bg-green-100 border border-green-400 text-green-700':
                        notification.type === 'success',
                    'bg-red-100 border border-red-400 text-red-700':
                        notification.type === 'error',
                }"
            >
                <div class="flex items-center">
                    <span class="mr-2">{{
                        notification.type === "success" ? "✅" : "❌"
                    }}</span>
                    <span>{{ notification.message }}</span>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, reactive, computed, onMounted } from "vue";
import { purchaseOrderApi } from "../api/purchaseOrderApi";
import warehouseApi from "../api/warehouseApi";
import { productApi } from "../api/productApi";

export default {
    name: "PurchaseOrderCreate",
    setup() {
        const loading = ref(false);
        const suppliers = ref([]);
        const warehouses = ref([]);
        const products = ref([]);

        const form = reactive({
            supplier_id: "",
            warehouse_id: "",
            expected_at: "",
            delivery_contact: "",
            delivery_phone: "",
            delivery_address: "",
            note: "",
            items: [],
            // Removed immediate payment fields for planned orders
        });

        // Notification
        const notification = ref({
            show: false,
            type: "success",
            message: "",
        });

        const todayDate = computed(() => {
            return new Date().toISOString().split("T")[0];
        });

        const selectedSupplier = computed(() => {
            return suppliers.value.find((s) => s.id == form.supplier_id);
        });

        const subtotal = computed(() => {
            return form.items.reduce((sum, item) => sum + (item.total || 0), 0);
        });

        const totalQuantity = computed(() => {
            return form.items.reduce(
                (sum, item) => sum + (item.quantity || 0),
                0
            );
        });

        const canSave = computed(() => {
            return (
                form.supplier_id && form.warehouse_id && form.items.length > 0
            );
        });

        // Removed immediate payment reactive helpers (planned orders do not pay yet)
        // Notification helper
        const showNotification = (message, type = "success") => {
            notification.value = {
                show: true,
                type,
                message,
            };

            setTimeout(() => {
                notification.value.show = false;
            }, 3000);
        };

        const fetchSuppliers = async () => {
            try {
                const response = await purchaseOrderApi.getSuppliers();
                suppliers.value =
                    response.data?.data || response.data || response;
            } catch (error) {
                console.error("Error fetching suppliers:", error);
            }
        };

        const fetchWarehouses = async () => {
            try {
                const response = await warehouseApi.getWarehouses();
                const data = response;
                if (data.success) {
                    warehouses.value = data.data.data || [];
                }
            } catch (error) {
                console.error("Error fetching warehouses:", error);
            }
        };

        const fetchProducts = async () => {
            try {
                const response = await productApi.getAll({
                    per_page: 1000,
                    status: "active",
                });
                const data = response;
                if (data.success) {
                    products.value = data.data || [];
                }
            } catch (error) {
                console.error("Error fetching products:", error);
            }
        };

        const onSupplierChange = () => {
            if (selectedSupplier.value) {
                form.delivery_contact = selectedSupplier.value.name;
                form.delivery_phone = selectedSupplier.value.phone;
            }
        };

        const addProduct = () => {
            form.items.push({
                product_id: "",
                quantity: 1,
                price: 0,
                total: 0,
                note: "",
            });
        };

        const removeProduct = (index) => {
            if (confirm("Bạn có chắc chắn muốn xóa sản phẩm này?")) {
                form.items.splice(index, 1);
            }
        };

        const getSelectedProduct = (productId) => {
            return products.value.find((p) => p.id == productId);
        };

        const updateProductInfo = (index) => {
            const product = getSelectedProduct(form.items[index].product_id);
            if (product) {
                form.items[index].price = product.cost_price || 0;
                calculateTotal(index);
            }
        };

        const calculateTotal = (index) => {
            const item = form.items[index];
            item.total = (item.quantity || 0) * (item.price || 0);
        };

        const validateForm = () => {
            if (!form.supplier_id) {
                showNotification("Vui lòng chọn nhà cung cấp", "error");
                return false;
            }
            if (!form.warehouse_id) {
                showNotification("Vui lòng chọn kho nhập hàng", "error");
                return false;
            }
            if (form.items.length === 0) {
                showNotification("Vui lòng thêm ít nhất một sản phẩm", "error");
                return false;
            }

            for (let i = 0; i < form.items.length; i++) {
                const item = form.items[i];
                if (!item.product_id) {
                    showNotification(
                        `Vui lòng chọn sản phẩm cho dòng ${i + 1}`,
                        "error"
                    );
                    return false;
                }
                if (!item.quantity || item.quantity <= 0) {
                    showNotification(
                        `Vui lòng nhập số lượng hợp lệ cho dòng ${i + 1}`,
                        "error"
                    );
                    return false;
                }
                if (!item.price || item.price <= 0) {
                    showNotification(
                        `Vui lòng nhập đơn giá hợp lệ cho dòng ${i + 1}`,
                        "error"
                    );
                    return false;
                }
            }
            return true;
        };

        const saveDraft = async () => {
            if (!validateForm()) return;

            loading.value = true;
            try {
                const payload = { ...form };
                delete payload.immediate_payment;
                delete payload.payment_method;
                const response = await purchaseOrderApi.createPurchaseOrder(
                    payload
                );

                if (response.success) {
                    showNotification("Lưu nháp thành công");
                    window.location.href = "/purchase-orders";
                } else {
                    showNotification(
                        response.message || "Có lỗi xảy ra",
                        "error"
                    );
                }
            } catch (error) {
                console.error("Error saving draft:", error);
                showNotification("Có lỗi xảy ra khi lưu nháp", "error");
            } finally {
                loading.value = false;
            }
        };

        const saveAndSubmit = async () => {
            if (!validateForm()) return;

            if (!confirm("Bạn có chắc chắn muốn gửi đơn hàng này để duyệt?"))
                return;

            loading.value = true;
            try {
                const payload = { ...form, submit_for_approval: true };
                delete payload.immediate_payment;
                delete payload.payment_method;
                const response = await purchaseOrderApi.createPurchaseOrder(
                    payload
                );

                if (response.success) {
                    showNotification(
                        "Tạo đơn nhập hàng thành công và đã gửi duyệt"
                    );
                    window.location.href = "/purchase-orders";
                } else {
                    showNotification(
                        response.message || "Có lỗi xảy ra",
                        "error"
                    );
                }
            } catch (error) {
                console.error("Error saving order:", error);
                showNotification("Có lỗi xảy ra khi tạo đơn hàng", "error");
            } finally {
                loading.value = false;
            }
        };

        const goBack = () => {
            if (form.items.length > 0) {
                if (confirm("Bạn có chắc chắn muốn hủy? Dữ liệu sẽ bị mất.")) {
                    window.location.href = "/purchase-orders";
                }
            } else {
                window.location.href = "/purchase-orders";
            }
        };

        const formatCurrency = (amount) => {
            return new Intl.NumberFormat("vi-VN", {
                style: "currency",
                currency: "VND",
            }).format(amount || 0);
        };

        onMounted(() => {
            fetchSuppliers();
            fetchWarehouses();
            fetchProducts();
        });

        return {
            loading,
            suppliers,
            warehouses,
            products,
            form,
            notification,
            todayDate,
            selectedSupplier,
            subtotal,
            totalQuantity,
            canSave,
            showNotification,
            onSupplierChange,
            addProduct,
            removeProduct,
            getSelectedProduct,
            updateProductInfo,
            calculateTotal,
            saveDraft,
            saveAndSubmit,
            goBack,
            formatCurrency,
            // removed payment bindings
        };
    },
};
</script>
