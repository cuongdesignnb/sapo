<template>
    <div class="bg-white min-h-screen">
        <!-- Header -->
        <div class="p-6 border-b bg-white sticky top-0 z-10">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900">
                        Tạo đơn trả hàng cho nhà cung cấp
                    </h1>
                    <div class="text-sm text-gray-500 mt-1">
                        <span>Trang chủ</span> / <span>Đơn trả hàng</span> /
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
                                    📞 {{ selectedSupplier.phone }} | 📧
                                    {{ selectedSupplier.email }}
                                </div>
                            </div>
                            <div>
                                <label
                                    class="block text-sm font-medium text-gray-700 mb-2"
                                >
                                    Kho trả hàng
                                    <span class="text-red-500">*</span>
                                </label>
                                <select
                                    v-model="form.warehouse_id"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    required
                                    @change="onWarehouseChange"
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
                    </div>
                </div>

                <!-- Order Details -->
                <div class="bg-white border border-gray-200 rounded-lg mb-6">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2
                            class="text-lg font-medium text-gray-900 flex items-center"
                        >
                            📋 Thông tin đơn trả hàng
                        </h2>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-3 gap-6">
                            <div>
                                <label
                                    class="block text-sm font-medium text-gray-700 mb-2"
                                    >Ngày trả hàng</label
                                >
                                <input
                                    v-model="form.returned_at"
                                    type="date"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    :max="todayDate"
                                />
                            </div>
                            <div>
                                <label
                                    class="block text-sm font-medium text-gray-700 mb-2"
                                    >Lý do trả hàng</label
                                >
                                <select
                                    v-model="form.return_reason"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                >
                                    <option value="">Chọn lý do</option>
                                    <option value="damaged">
                                        Hàng bị hỏng
                                    </option>
                                    <option value="expired">
                                        Hàng hết hạn
                                    </option>
                                    <option value="wrong_item">
                                        Giao sai hàng
                                    </option>
                                    <option value="excess">Giao thừa</option>
                                    <option value="defective">
                                        Lỗi sản xuất
                                    </option>
                                    <option value="other">Khác</option>
                                </select>
                            </div>
                            <div>
                                <label
                                    class="block text-sm font-medium text-gray-700 mb-2"
                                    >Trạng thái</label
                                >
                                <span
                                    class="inline-flex items-center px-3 py-2 rounded-md text-sm font-medium bg-yellow-100 text-yellow-800"
                                >
                                    🕐 Chờ duyệt
                                </span>
                            </div>
                        </div>
                        <div class="mt-6">
                            <label
                                class="block text-sm font-medium text-gray-700 mb-2"
                                >Ghi chú</label
                            >
                            <textarea
                                v-model="form.note"
                                rows="3"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="Ghi chú thêm về đơn trả hàng..."
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
                            📦 Sản phẩm trả hàng
                        </h2>
                        <button
                            @click="showReceiptModal = true"
                            class="px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600"
                            :disabled="!form.supplier_id"
                        >
                            ➕ Chọn từ phiếu nhập
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
                                Vui lòng chọn nhà cung cấp, sau đó chọn sản phẩm
                                từ phiếu nhập để trả.
                            </p>
                        </div>

                        <div v-else class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th
                                            class="text-left p-3 text-sm font-medium text-gray-600"
                                            style="width: 5%"
                                        >
                                            STT
                                        </th>
                                        <th
                                            class="text-left p-3 text-sm font-medium text-gray-600"
                                            style="width: 30%"
                                        >
                                            Sản phẩm
                                        </th>
                                        <th
                                            class="text-left p-3 text-sm font-medium text-gray-600"
                                            style="width: 15%"
                                        >
                                            Phiếu nhập
                                        </th>
                                        <th
                                            class="text-left p-3 text-sm font-medium text-gray-600"
                                            style="width: 10%"
                                        >
                                            Có thể trả
                                        </th>
                                        <th
                                            class="text-left p-3 text-sm font-medium text-gray-600"
                                            style="width: 10%"
                                        >
                                            SL trả
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
                                            style="width: 5%"
                                        >
                                            Thao tác
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <tr
                                        v-for="(item, index) in form.items"
                                        :key="index"
                                    >
                                        <td class="p-3 text-center text-sm">
                                            {{ index + 1 }}
                                        </td>
                                        <td class="p-3">
                                            <div class="font-medium">
                                                {{ item.product?.name }}
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                SKU: {{ item.product?.sku }}
                                            </div>
                                            <div class="mt-1">
                                                <select
                                                    v-model="
                                                        item.condition_status
                                                    "
                                                    class="text-xs border border-gray-300 rounded px-2 py-1"
                                                >
                                                    <option value="good">
                                                        Tốt
                                                    </option>
                                                    <option value="damaged">
                                                        Hỏng
                                                    </option>
                                                    <option value="expired">
                                                        Hết hạn
                                                    </option>
                                                    <option value="wrong_item">
                                                        Sai hàng
                                                    </option>
                                                    <option value="excess">
                                                        Thừa
                                                    </option>
                                                    <option value="defective">
                                                        Lỗi sản xuất
                                                    </option>
                                                </select>
                                            </div>
                                        </td>
                                        <td class="p-3 text-sm">
                                            <div class="font-medium">
                                                {{ item.receipt_code }}
                                            </div>
                                            <div class="text-gray-500">
                                                {{
                                                    formatDate(
                                                        item.receipt_date
                                                    )
                                                }}
                                            </div>
                                        </td>
                                        <td class="p-3 text-center">
                                            <span
                                                class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded"
                                            >
                                                {{ item.returnable_quantity }}
                                            </span>
                                        </td>
                                        <td class="p-3">
                                            <input
                                                v-model.number="item.quantity"
                                                @input="
                                                    calculateItemTotal(item)
                                                "
                                                type="number"
                                                min="1"
                                                :max="item.returnable_quantity"
                                                class="w-full px-2 py-1 border border-gray-300 rounded text-center focus:outline-none focus:ring-2 focus:ring-blue-500"
                                                :class="{
                                                    'border-red-500 bg-red-50':
                                                        item.quantity >
                                                        item.returnable_quantity,
                                                }"
                                            />
                                            <div
                                                v-if="
                                                    item.quantity >
                                                    item.returnable_quantity
                                                "
                                                class="text-xs text-red-500 mt-1"
                                            >
                                                Vượt quá số lượng có thể trả!
                                            </div>
                                        </td>
                                        <td class="p-3">
                                            <input
                                                v-model.number="item.price"
                                                @input="
                                                    calculateItemTotal(item)
                                                "
                                                type="number"
                                                min="0"
                                                step="1000"
                                                class="w-full px-2 py-1 border border-gray-300 rounded text-right focus:outline-none focus:ring-2 focus:ring-blue-500"
                                            />
                                        </td>
                                        <td class="p-3 text-right font-medium">
                                            {{
                                                formatCurrency(item.total || 0)
                                            }}
                                        </td>
                                        <td class="p-3 text-center">
                                            <button
                                                @click="removeItem(index)"
                                                class="text-red-500 hover:text-red-700"
                                            >
                                                🗑️
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Summary Sidebar -->
            <div class="w-80 p-6 bg-gray-50 border-l">
                <div class="bg-white rounded-lg p-6 shadow-sm">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">
                        📊 Tổng kết đơn hàng
                    </h3>

                    <div class="space-y-3">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Tổng sản phẩm:</span>
                            <span class="font-medium"
                                >{{ form.items.length }} loại</span
                            >
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Tổng số lượng:</span>
                            <span class="font-medium">{{ totalQuantity }}</span>
                        </div>
                        <div class="border-t pt-3">
                            <div
                                class="flex justify-between text-lg font-medium"
                            >
                                <span>Tổng tiền:</span>
                                <span class="text-blue-600">{{
                                    formatCurrency(totalAmount)
                                }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 pt-6 border-t">
                        <div class="text-sm text-gray-600 mb-2">
                            Trạng thái validation:
                        </div>
                        <div class="space-y-2">
                            <div class="flex items-center text-sm">
                                <span
                                    class="w-3 h-3 rounded-full mr-2"
                                    :class="
                                        form.supplier_id
                                            ? 'bg-green-500'
                                            : 'bg-red-500'
                                    "
                                ></span>
                                <span
                                    :class="
                                        form.supplier_id
                                            ? 'text-green-700'
                                            : 'text-red-700'
                                    "
                                >
                                    {{
                                        form.supplier_id
                                            ? "Đã chọn nhà cung cấp"
                                            : "Chưa chọn nhà cung cấp"
                                    }}
                                </span>
                            </div>
                            <div class="flex items-center text-sm">
                                <span
                                    class="w-3 h-3 rounded-full mr-2"
                                    :class="
                                        form.items.length > 0
                                            ? 'bg-green-500'
                                            : 'bg-red-500'
                                    "
                                ></span>
                                <span
                                    :class="
                                        form.items.length > 0
                                            ? 'text-green-700'
                                            : 'text-red-700'
                                    "
                                >
                                    {{
                                        form.items.length > 0
                                            ? "Đã có sản phẩm"
                                            : "Chưa có sản phẩm"
                                    }}
                                </span>
                            </div>
                            <div class="flex items-center text-sm">
                                <span
                                    class="w-3 h-3 rounded-full mr-2"
                                    :class="
                                        isValidQuantities
                                            ? 'bg-green-500'
                                            : 'bg-red-500'
                                    "
                                ></span>
                                <span
                                    :class="
                                        isValidQuantities
                                            ? 'text-green-700'
                                            : 'text-red-700'
                                    "
                                >
                                    {{
                                        isValidQuantities
                                            ? "Số lượng hợp lệ"
                                            : "Số lượng không hợp lệ"
                                    }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Receipt Selection Modal -->
        <div
            v-if="showReceiptModal"
            class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
        >
            <div
                class="bg-white rounded-lg max-w-6xl w-full max-h-[90vh] overflow-hidden"
            >
                <div class="p-6 border-b border-gray-200">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-medium text-gray-900">
                            Chọn phiếu nhập và sản phẩm trả hàng
                        </h3>
                        <button
                            @click="showReceiptModal = false"
                            class="text-gray-400 hover:text-gray-600"
                        >
                            ✕
                        </button>
                    </div>
                </div>

                <div class="p-6 overflow-y-auto max-h-[calc(90vh-120px)]">
                    <!-- Step 1: Select Receipt -->
                    <div v-if="!selectedReceipt" class="space-y-4">
                        <div class="text-sm text-gray-600 mb-4">
                            <span class="font-medium">Bước 1:</span> Chọn phiếu
                            nhập hàng từ nhà cung cấp
                            <strong>{{ selectedSupplier?.name }}</strong>
                        </div>

                        <div
                            v-if="availableReceipts.length === 0"
                            class="text-center py-8"
                        >
                            <div class="text-gray-500">
                                Không có phiếu nhập nào có thể trả hàng từ nhà
                                cung cấp này
                            </div>
                        </div>

                        <div v-else class="grid gap-4">
                            <div
                                v-for="receipt in availableReceipts"
                                :key="receipt.id"
                                @click="selectReceipt(receipt)"
                                class="border border-gray-200 rounded-lg p-4 hover:border-blue-500 hover:bg-blue-50 cursor-pointer transition-colors"
                            >
                                <div class="flex justify-between items-start">
                                    <div>
                                        <div class="font-medium">
                                            {{ receipt.code }}
                                        </div>
                                        <div class="text-sm text-gray-600">
                                            Ngày nhập:
                                            {{
                                                formatDate(receipt.received_at)
                                            }}
                                        </div>
                                        <div class="text-sm text-gray-600">
                                            Kho: {{ receipt.warehouse?.name }}
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-sm text-blue-600">
                                            {{ receipt.items?.length || 0 }} sản
                                            phẩm
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            Có thể trả
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Select Items -->
                    <div v-else class="space-y-4">
                        <div class="flex items-center justify-between">
                            <div class="text-sm text-gray-600">
                                <span class="font-medium">Bước 2:</span> Chọn
                                sản phẩm từ phiếu
                                <strong>{{ selectedReceipt.code }}</strong>
                            </div>
                            <button
                                @click="selectedReceipt = null"
                                class="text-blue-600 hover:text-blue-800 text-sm"
                            >
                                ← Chọn phiếu khác
                            </button>
                        </div>

                        <div
                            v-if="returnableItems.length === 0"
                            class="text-center py-8"
                        >
                            <div class="text-gray-500">
                                Không có sản phẩm nào có thể trả từ phiếu này
                            </div>
                        </div>

                        <div v-else class="overflow-x-auto">
                            <table
                                class="w-full border border-gray-200 rounded-lg"
                            >
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th
                                            class="text-left p-3 text-sm font-medium text-gray-600"
                                        >
                                            Chọn
                                        </th>
                                        <th
                                            class="text-left p-3 text-sm font-medium text-gray-600"
                                        >
                                            Sản phẩm
                                        </th>
                                        <th
                                            class="text-left p-3 text-sm font-medium text-gray-600"
                                        >
                                            Đã nhập
                                        </th>
                                        <th
                                            class="text-left p-3 text-sm font-medium text-gray-600"
                                        >
                                            Có thể trả
                                            <span
                                                class="ml-1 text-xs text-gray-400"
                                                title="= Đã nhập - ĐÃ TRẢ ĐÃ DUYỆT"
                                                >ⓘ</span
                                            >
                                        </th>
                                        <th
                                            class="text-left p-3 text-sm font-medium text-gray-600"
                                        >
                                            Đơn giá
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <tr
                                        v-for="item in returnableItems"
                                        :key="item.id"
                                        :class="{
                                            'bg-blue-50': isItemSelected(
                                                item.id
                                            ),
                                        }"
                                        class="hover:bg-gray-50"
                                    >
                                        <td class="p-3">
                                            <input
                                                type="checkbox"
                                                :checked="
                                                    isItemSelected(item.id)
                                                "
                                                @change="
                                                    toggleItemSelection(item)
                                                "
                                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                            />
                                        </td>
                                        <td class="p-3">
                                            <div class="font-medium">
                                                {{ item.product?.name }}
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                SKU: {{ item.product?.sku }}
                                            </div>
                                        </td>
                                        <td class="p-3 text-center">
                                            {{ item.quantity_received }}
                                        </td>
                                        <td class="p-3 text-center">
                                            <span
                                                class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded"
                                            >
                                                {{ item.returnable_quantity }}
                                            </span>
                                        </td>
                                        <td class="p-3 text-right">
                                            {{ formatCurrency(item.unit_cost) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="flex justify-end space-x-3 pt-4 border-t">
                            <button
                                @click="showReceiptModal = false"
                                class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
                            >
                                Hủy
                            </button>
                            <button
                                @click="addSelectedItems"
                                class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600"
                                :disabled="selectedItems.length === 0"
                            >
                                Thêm {{ selectedItems.length }} sản phẩm
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Notification -->
        <div v-if="notification.show" class="fixed top-4 right-4 z-50">
            <div
                class="bg-white border border-gray-200 rounded-lg shadow-lg p-4 max-w-sm"
            >
                <div class="flex items-center space-x-3">
                    <span class="text-2xl">
                        {{
                            notification.type === "success"
                                ? "✅"
                                : notification.type === "warning"
                                ? "⚠️"
                                : "❌"
                        }}
                    </span>
                    <span>{{ notification.message }}</span>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, computed, onMounted } from "vue";
import { purchaseReturnOrderApi } from "../api/purchaseReturnOrderApi";

export default {
    name: "PurchaseReturnOrderCreate",
    setup() {
        const loading = ref(false);
        const showReceiptModal = ref(false);

        const suppliers = ref([]);
        const warehouses = ref([]);
        const availableReceipts = ref([]);
        const returnableItems = ref([]);
        const selectedReceipt = ref(null);
        const selectedItems = ref([]);
        const selectedSupplier = ref(null);

        // Form data
        const form = ref({
            supplier_id: "",
            warehouse_id: "",
            returned_at: "",
            return_reason: "",
            note: "",
            items: [],
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

        const totalQuantity = computed(() => {
            return form.value.items.reduce(
                (sum, item) => sum + (item.quantity || 0),
                0
            );
        });

        const totalAmount = computed(() => {
            return form.value.items.reduce(
                (sum, item) => sum + (item.total || 0),
                0
            );
        });

        const isValidQuantities = computed(() => {
            return form.value.items.every(
                (item) =>
                    item.quantity > 0 &&
                    item.quantity <= item.returnable_quantity
            );
        });

        const showNotification = (message, type = "success") => {
            notification.value = { show: true, type, message };
            setTimeout(() => {
                notification.value.show = false;
            }, 3000);
        };

        const formatCurrency = (amount) => {
            return new Intl.NumberFormat("vi-VN", {
                style: "currency",
                currency: "VND",
            }).format(amount);
        };

        const formatDate = (date) => {
            if (!date) return "";
            return new Date(date).toLocaleDateString("vi-VN");
        };

        onMounted(() => {
            fetchSuppliers();
            fetchWarehouses();
        });

        const fetchSuppliers = async () => {
            try {
                const response = await purchaseReturnOrderApi.getSuppliers();
                suppliers.value = response.data || response;
            } catch (error) {
                console.error("Error fetching suppliers:", error);
            }
        };

        const fetchWarehouses = async () => {
            try {
                const response = await purchaseReturnOrderApi.getWarehouses();
                warehouses.value = response.data.data || response;
            } catch (error) {
                console.error("Error fetching warehouses:", error);
            }
        };

        const onSupplierChange = async () => {
            selectedSupplier.value = suppliers.value.find(
                (s) => s.id == form.value.supplier_id
            );
            form.value.items = []; // Clear items when supplier changes
            availableReceipts.value = [];

            if (form.value.supplier_id) {
                await fetchReceiptsBySupplier();
            }
        };

        const onWarehouseChange = () => {
            form.value.items = []; // Clear items when warehouse changes
        };

        const fetchReceiptsBySupplier = async () => {
            if (!form.value.supplier_id) return;

            try {
                const response =
                    await purchaseReturnOrderApi.getReceiptsBySupplier(
                        form.value.supplier_id
                    );
                availableReceipts.value = response.data || response;
            } catch (error) {
                console.error("Error fetching receipts:", error);
                showNotification("Lỗi khi tải danh sách phiếu nhập", "error");
            }
        };

        const selectReceipt = async (receipt) => {
            selectedReceipt.value = receipt;
            selectedItems.value = [];

            try {
                const response =
                    await purchaseReturnOrderApi.getReturnableItems(receipt.id);
                returnableItems.value = response.data || response;
            } catch (error) {
                console.error("Error fetching returnable items:", error);
                showNotification("Lỗi khi tải sản phẩm có thể trả", "error");
            }
        };

        const isItemSelected = (itemId) => {
            return selectedItems.value.some((item) => item.id === itemId);
        };

        const toggleItemSelection = (item) => {
            const index = selectedItems.value.findIndex(
                (selected) => selected.id === item.id
            );
            if (index >= 0) {
                selectedItems.value.splice(index, 1);
            } else {
                selectedItems.value.push(item);
            }
        };

        const addSelectedItems = () => {
            selectedItems.value.forEach((item) => {
                const existingItem = form.value.items.find(
                    (formItem) => formItem.purchase_receipt_item_id === item.id
                );

                if (!existingItem) {
                    form.value.items.push({
                        purchase_receipt_item_id: item.id,
                        product_id: item.product_id,
                        product: item.product,
                        quantity: 1,
                        returnable_quantity: item.returnable_quantity,
                        price: item.unit_cost,
                        total: item.unit_cost,
                        receipt_code: selectedReceipt.value.code,
                        receipt_date: selectedReceipt.value.received_at,
                        condition_status: "good",
                        return_reason: "",
                    });
                }
            });

            showReceiptModal.value = false;
            selectedReceipt.value = null;
            selectedItems.value = [];
            showNotification(`Đã thêm ${selectedItems.value.length} sản phẩm`);
        };

        const removeItem = (index) => {
            form.value.items.splice(index, 1);
        };

        const calculateItemTotal = (item) => {
            item.total = (item.quantity || 0) * (item.price || 0);
        };

        const validateForm = () => {
            if (!form.value.supplier_id) {
                showNotification("Vui lòng chọn nhà cung cấp", "error");
                return false;
            }

            if (!form.value.warehouse_id) {
                showNotification("Vui lòng chọn kho trả hàng", "error");
                return false;
            }

            if (form.value.items.length === 0) {
                showNotification("Vui lòng thêm ít nhất một sản phẩm", "error");
                return false;
            }

            if (!isValidQuantities.value) {
                showNotification(
                    "Vui lòng kiểm tra lại số lượng các sản phẩm",
                    "error"
                );
                return false;
            }

            return true;
        };

        const saveDraft = async () => {
            if (!validateForm()) return;

            try {
                loading.value = true;
                const response =
                    await purchaseReturnOrderApi.createPurchaseReturnOrder({
                        ...form.value,
                        status: "draft",
                    });

                if (response.success) {
                    showNotification("Lưu nháp thành công");
                    setTimeout(() => {
                        window.location.href = "/purchase-return-orders";
                    }, 1000);
                }
            } catch (error) {
                console.error("Error saving draft:", error);
                showNotification("Lỗi khi lưu nháp", "error");
            } finally {
                loading.value = false;
            }
        };

        const saveAndSubmit = async () => {
            if (!validateForm()) return;

            try {
                loading.value = true;
                const response =
                    await purchaseReturnOrderApi.createPurchaseReturnOrder({
                        ...form.value,
                        status: "pending",
                    });

                if (response.success) {
                    showNotification("Tạo đơn trả hàng thành công");
                    setTimeout(() => {
                        window.location.href = "/purchase-return-orders";
                    }, 1000);
                }
            } catch (error) {
                console.error("Error creating order:", error);
                showNotification("Lỗi khi tạo đơn trả hàng", "error");
            } finally {
                loading.value = false;
            }
        };

        const goBack = () => {
            window.history.back();
        };

        return {
            loading,
            showReceiptModal,
            suppliers,
            warehouses,
            availableReceipts,
            returnableItems,
            selectedReceipt,
            selectedItems,
            selectedSupplier,
            form,
            notification,
            todayDate,
            totalQuantity,
            totalAmount,
            isValidQuantities,
            showNotification,
            formatCurrency,
            formatDate,
            fetchSuppliers,
            fetchWarehouses,
            onSupplierChange,
            onWarehouseChange,
            fetchReceiptsBySupplier,
            selectReceipt,
            isItemSelected,
            toggleItemSelection,
            addSelectedItems,
            removeItem,
            calculateItemTotal,
            validateForm,
            saveDraft,
            saveAndSubmit,
            goBack,
        };
    },
};
</script>

<style scoped>
/* Custom styles for this component */
.border-red-500 {
    border-color: #ef4444;
}

.bg-red-50 {
    background-color: #fef2f2;
}

.text-red-500 {
    color: #ef4444;
}

.text-red-700 {
    color: #b91c1c;
}

.text-green-700 {
    color: #15803d;
}

.bg-green-500 {
    background-color: #22c55e;
}

.bg-blue-50 {
    background-color: #eff6ff;
}

.text-blue-600 {
    color: #2563eb;
}

.text-blue-800 {
    color: #1e40af;
}

.hover\:text-blue-800:hover {
    color: #1e40af;
}

.hover\:border-blue-500:hover {
    border-color: #3b82f6;
}

.hover\:bg-blue-50:hover {
    background-color: #eff6ff;
}

.hover\:bg-blue-600:hover {
    background-color: #2563eb;
}

.hover\:bg-green-600:hover {
    background-color: #16a34a;
}

.hover\:bg-gray-50:hover {
    background-color: #f9fafb;
}

.hover\:text-red-700:hover {
    color: #b91c1c;
}

.focus\:ring-2:focus {
    --tw-ring-offset-shadow: var(--tw-ring-inset) 0 0 0
        var(--tw-ring-offset-width) var(--tw-ring-offset-color);
    --tw-ring-shadow: var(--tw-ring-inset) 0 0 0
        calc(2px + var(--tw-ring-offset-width)) var(--tw-ring-color);
    box-shadow: var(--tw-ring-offset-shadow), var(--tw-ring-shadow),
        var(--tw-shadow, 0 0 #0000);
}

.focus\:ring-blue-500:focus {
    --tw-ring-opacity: 1;
    --tw-ring-color: rgb(59 130 246 / var(--tw-ring-opacity));
}

.transition-colors {
    transition-property: color, background-color, border-color,
        text-decoration-color, fill, stroke;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
    transition-duration: 150ms;
}

.max-h-\[90vh\] {
    max-height: 90vh;
}

.max-h-\[calc\(90vh-120px\)\] {
    max-height: calc(90vh - 120px);
}

.overflow-y-auto {
    overflow-y: auto;
}

.overflow-x-auto {
    overflow-x: auto;
}

.z-50 {
    z-index: 50;
}

.z-10 {
    z-index: 10;
}

.fixed {
    position: fixed;
}

.sticky {
    position: sticky;
}

.inset-0 {
    top: 0px;
    right: 0px;
    bottom: 0px;
    left: 0px;
}

.top-0 {
    top: 0px;
}

.top-4 {
    top: 1rem;
}

.right-4 {
    right: 1rem;
}

.bg-black {
    background-color: rgb(0 0 0);
}

.bg-opacity-50 {
    background-color: rgb(0 0 0 / 0.5);
}

.items-center {
    align-items: center;
}

.justify-center {
    justify-content: center;
}

.justify-between {
    justify-content: space-between;
}

.justify-end {
    justify-content: flex-end;
}

.flex {
    display: flex;
}

.grid {
    display: grid;
}

.space-x-3 > :not([hidden]) ~ :not([hidden]) {
    --tw-space-x-reverse: 0;
    margin-right: calc(0.75rem * var(--tw-space-x-reverse));
    margin-left: calc(0.75rem * calc(1 - var(--tw-space-x-reverse)));
}

.space-y-2 > :not([hidden]) ~ :not([hidden]) {
    --tw-space-y-reverse: 0;
    margin-top: calc(0.5rem * calc(1 - var(--tw-space-y-reverse)));
    margin-bottom: calc(0.5rem * var(--tw-space-y-reverse));
}

.space-y-3 > :not([hidden]) ~ :not([hidden]) {
    --tw-space-y-reverse: 0;
    margin-top: calc(0.75rem * calc(1 - var(--tw-space-y-reverse)));
    margin-bottom: calc(0.75rem * var(--tw-space-y-reverse));
}

.space-y-4 > :not([hidden]) ~ :not([hidden]) {
    --tw-space-y-reverse: 0;
    margin-top: calc(1rem * calc(1 - var(--tw-space-y-reverse)));
    margin-bottom: calc(1rem * var(--tw-space-y-reverse));
}

.gap-4 {
    gap: 1rem;
}

.gap-6 {
    gap: 1.5rem;
}

.grid-cols-2 {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.grid-cols-3 {
    grid-template-columns: repeat(3, minmax(0, 1fr));
}

.w-full {
    width: 100%;
}

.w-80 {
    width: 20rem;
}

.w-3 {
    width: 0.75rem;
}

.h-3 {
    height: 0.75rem;
}

.max-w-6xl {
    max-width: 72rem;
}

.max-w-sm {
    max-width: 24rem;
}

.min-h-screen {
    min-height: 100vh;
}

.rounded-lg {
    border-radius: 0.5rem;
}

.rounded-md {
    border-radius: 0.375rem;
}

.rounded-full {
    border-radius: 9999px;
}

.rounded {
    border-radius: 0.25rem;
}

.border {
    border-width: 1px;
}

.border-b {
    border-bottom-width: 1px;
}

.border-l {
    border-left-width: 1px;
}

.border-t {
    border-top-width: 1px;
}

.border-gray-200 {
    border-color: rgb(229 231 235);
}

.border-gray-300 {
    border-color: rgb(209 213 219);
}

.bg-white {
    background-color: rgb(255 255 255);
}

.bg-gray-50 {
    background-color: rgb(249 250 251);
}

.bg-blue-500 {
    background-color: rgb(59 130 246);
}

.bg-yellow-100 {
    background-color: rgb(254 249 195);
}

.bg-blue-100 {
    background-color: rgb(219 234 254);
}

.bg-green-100 {
    background-color: rgb(220 252 231);
}

.text-white {
    color: rgb(255 255 255);
}

.text-gray-400 {
    color: rgb(156 163 175);
}

.text-gray-500 {
    color: rgb(107 114 128);
}

.text-gray-600 {
    color: rgb(75 85 99);
}

.text-gray-700 {
    color: rgb(55 65 81);
}

.text-gray-900 {
    color: rgb(17 24 39);
}

.text-yellow-800 {
    color: rgb(133 77 14);
}

.text-green-800 {
    color: rgb(22 101 52);
}

.shadow-sm {
    box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);
}

.shadow-lg {
    box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1),
        0 4px 6px -2px rgb(0 0 0 / 0.05);
}

.cursor-pointer {
    cursor: pointer;
}

.select-none {
    user-select: none;
}

.text-xs {
    font-size: 0.75rem;
    line-height: 1rem;
}

.text-sm {
    font-size: 0.875rem;
    line-height: 1.25rem;
}

.text-lg {
    font-size: 1.125rem;
    line-height: 1.75rem;
}

.text-xl {
    font-size: 1.25rem;
    line-height: 1.75rem;
}

.text-2xl {
    font-size: 1.5rem;
    line-height: 2rem;
}

.font-medium {
    font-weight: 500;
}

.font-semibold {
    font-weight: 600;
}

.text-center {
    text-align: center;
}

.text-left {
    text-align: left;
}

.text-right {
    text-align: right;
}

.p-3 {
    padding: 0.75rem;
}

.p-4 {
    padding: 1rem;
}

.p-6 {
    padding: 1.5rem;
}

.px-2 {
    padding-left: 0.5rem;
    padding-right: 0.5rem;
}

.px-3 {
    padding-left: 0.75rem;
    padding-right: 0.75rem;
}

.px-4 {
    padding-left: 1rem;
    padding-right: 1rem;
}

.py-1 {
    padding-top: 0.25rem;
    padding-bottom: 0.25rem;
}

.py-2 {
    padding-top: 0.5rem;
    padding-bottom: 0.5rem;
}

.py-4 {
    padding-top: 1rem;
    padding-bottom: 1rem;
}

.py-8 {
    padding-top: 2rem;
    padding-bottom: 2rem;
}

.py-12 {
    padding-top: 3rem;
    padding-bottom: 3rem;
}

.pt-3 {
    padding-top: 0.75rem;
}

.pt-4 {
    padding-top: 1rem;
}

.pt-6 {
    padding-top: 1.5rem;
}

.pb-3 {
    padding-bottom: 0.75rem;
}

.mb-1 {
    margin-bottom: 0.25rem;
}

.mb-2 {
    margin-bottom: 0.5rem;
}

.mb-4 {
    margin-bottom: 1rem;
}

.mb-6 {
    margin-bottom: 1.5rem;
}

.mt-1 {
    margin-top: 0.25rem;
}

.mt-2 {
    margin-top: 0.5rem;
}

.mt-6 {
    margin-top: 1.5rem;
}

.mr-2 {
    margin-right: 0.5rem;
}

.divide-y > :not([hidden]) ~ :not([hidden]) {
    border-top-width: 1px;
}

.divide-gray-200 > :not([hidden]) ~ :not([hidden]) {
    border-color: rgb(229 231 235);
}

.focus\:outline-none:focus {
    outline: 2px solid transparent;
    outline-offset: 2px;
}

.disabled\:opacity-50:disabled {
    opacity: 0.5;
}

.disabled\:cursor-not-allowed:disabled {
    cursor: not-allowed;
}

/* Hide spinner for number inputs */
input[type="number"]::-webkit-outer-spin-button,
input[type="number"]::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

input[type="number"] {
    -moz-appearance: textfield;
}
</style>
