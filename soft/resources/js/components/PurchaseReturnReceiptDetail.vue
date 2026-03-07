<template>
    <div class="bg-gray-50 min-h-screen">
        <!-- Header -->
        <div class="bg-white border-b border-gray-200 px-6 py-4">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900">
                        🧾 Chi tiết phiếu trả hàng
                    </h1>
                    <nav class="text-sm text-gray-500 mt-1">
                        <a href="/" class="hover:text-gray-700">Trang chủ</a>
                        <span class="mx-2">/</span>
                        <a
                            href="/purchase-return-receipts"
                            class="hover:text-gray-700"
                            >Phiếu trả hàng</a
                        >
                        <span class="mx-2">/</span>
                        <span class="text-gray-900">{{
                            receipt?.code || "Chi tiết"
                        }}</span>
                    </nav>
                </div>
                <div class="flex space-x-3">
                    <button
                        v-if="canApprove"
                        @click="approveReceipt"
                        class="px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600"
                    >
                        ✅ Duyệt phiếu
                    </button>
                    <button
                        v-if="canEdit"
                        @click="editReceipt"
                        class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600"
                    >
                        ✏️ Chỉnh sửa
                    </button>
                    <button
                        @click="printReceipt"
                        class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
                    >
                        🖨️ In phiếu
                    </button>
                    <button
                        @click="goBack"
                        class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
                    >
                        ← Quay lại
                    </button>
                </div>
            </div>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="flex justify-center items-center py-12">
            <div
                class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"
            ></div>
            <span class="ml-2 text-gray-600">Đang tải...</span>
        </div>

        <!-- Content -->
        <div v-else-if="receipt" class="p-6">
            <div class="max-w-6xl mx-auto">
                <!-- Receipt Header -->
                <div
                    class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6"
                >
                    <div class="px-6 py-4 border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <h2 class="text-lg font-medium text-gray-900">
                                📋 Thông tin phiếu trả hàng
                            </h2>
                            <span
                                :class="getStatusBadgeClass(receipt.status)"
                                class="px-3 py-1 text-sm font-medium rounded-full"
                            >
                                {{ getStatusText(receipt.status) }}
                            </span>
                        </div>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-4 gap-6">
                            <div>
                                <div class="text-sm text-gray-600">
                                    Mã phiếu
                                </div>
                                <div class="font-medium text-gray-900">
                                    {{ receipt.code }}
                                </div>
                            </div>
                            <div>
                                <div class="text-sm text-gray-600">
                                    Ngày tạo
                                </div>
                                <div class="font-medium text-gray-900">
                                    {{ formatDateTime(receipt.created_at) }}
                                </div>
                            </div>
                            <div>
                                <div class="text-sm text-gray-600">
                                    Ngày trả hàng
                                </div>
                                <div class="font-medium text-gray-900">
                                    {{ formatDateTime(receipt.returned_at) }}
                                </div>
                            </div>
                            <div>
                                <div class="text-sm text-gray-600">
                                    Người tạo
                                </div>
                                <div class="font-medium text-gray-900">
                                    {{ receipt.creator?.name || "N/A" }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Return Order Info -->
                <div
                    v-if="receipt.purchase_return_order"
                    class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6"
                >
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-medium text-gray-900">
                            📦 Thông tin đơn trả hàng
                        </h2>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-3 gap-6">
                            <div>
                                <div class="text-sm text-gray-600">
                                    Mã đơn trả hàng
                                </div>
                                <div class="font-medium text-blue-600">
                                    <a
                                        :href="`/purchase-return-orders/${receipt.purchase_return_order.id}`"
                                        class="hover:underline"
                                    >
                                        {{ receipt.purchase_return_order.code }}
                                    </a>
                                </div>
                            </div>
                            <div>
                                <div class="text-sm text-gray-600">
                                    Trạng thái đơn
                                </div>
                                <div class="font-medium text-gray-900">
                                    {{
                                        getReturnOrderStatusText(
                                            receipt.purchase_return_order.status
                                        )
                                    }}
                                </div>
                            </div>
                            <div>
                                <div class="text-sm text-gray-600">
                                    Ngày tạo đơn
                                </div>
                                <div class="font-medium text-gray-900">
                                    {{
                                        formatDateTime(
                                            receipt.purchase_return_order
                                                .created_at
                                        )
                                    }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Supplier & Warehouse Info -->
                <div class="grid grid-cols-2 gap-6 mb-6">
                    <!-- Supplier Info -->
                    <div
                        class="bg-white rounded-lg shadow-sm border border-gray-200"
                    >
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h2 class="text-lg font-medium text-gray-900">
                                🏢 Thông tin nhà cung cấp
                            </h2>
                        </div>
                        <div class="p-6">
                            <div class="space-y-3">
                                <div>
                                    <div class="text-sm text-gray-600">
                                        Tên nhà cung cấp
                                    </div>
                                    <div class="font-medium text-gray-900">
                                        {{ receipt.supplier?.name || "N/A" }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-sm text-gray-600">
                                        Mã nhà cung cấp
                                    </div>
                                    <div class="font-medium text-gray-900">
                                        {{ receipt.supplier?.code || "N/A" }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-sm text-gray-600">
                                        Điện thoại
                                    </div>
                                    <div class="font-medium text-gray-900">
                                        {{ receipt.supplier?.phone || "N/A" }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-sm text-gray-600">
                                        Email
                                    </div>
                                    <div class="font-medium text-gray-900">
                                        {{ receipt.supplier?.email || "N/A" }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Warehouse Info -->
                    <div
                        class="bg-white rounded-lg shadow-sm border border-gray-200"
                    >
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h2 class="text-lg font-medium text-gray-900">
                                🏭 Thông tin kho trả hàng
                            </h2>
                        </div>
                        <div class="p-6">
                            <div class="space-y-3">
                                <div>
                                    <div class="text-sm text-gray-600">
                                        Tên kho
                                    </div>
                                    <div class="font-medium text-gray-900">
                                        {{ receipt.warehouse?.name || "N/A" }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-sm text-gray-600">
                                        Mã kho
                                    </div>
                                    <div class="font-medium text-gray-900">
                                        {{ receipt.warehouse?.code || "N/A" }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-sm text-gray-600">
                                        Địa chỉ
                                    </div>
                                    <div class="font-medium text-gray-900">
                                        {{
                                            receipt.warehouse?.address || "N/A"
                                        }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-sm text-gray-600">
                                        Người quản lý
                                    </div>
                                    <div class="font-medium text-gray-900">
                                        {{
                                            receipt.warehouse?.manager || "N/A"
                                        }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Items -->
                <div
                    class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6"
                >
                    <div
                        class="px-6 py-4 border-b border-gray-200 flex items-center justify-between"
                    >
                        <h2 class="text-lg font-medium text-gray-900">
                            📋 Danh sách sản phẩm trả
                        </h2>
                        <span
                            v-if="
                                ['approved', 'completed'].includes(
                                    receipt.status
                                )
                            "
                            class="text-xs px-2 py-1 rounded bg-green-50 text-green-600 border border-green-200"
                            title="Tồn kho đã giảm và công nợ nhà cung cấp đã được ghi nhận giảm."
                        >
                            ĐÃ CẬP NHẬT TỒN KHO & CÔNG NỢ
                        </span>
                        <span
                            v-else
                            class="text-xs px-2 py-1 rounded bg-yellow-50 text-yellow-700 border border-yellow-200"
                            title="Chưa ảnh hưởng tồn kho/công nợ cho đến khi duyệt."
                        >
                            Chưa ảnh hưởng kho/công nợ
                        </span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th
                                        class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider"
                                    >
                                        STT
                                    </th>
                                    <th
                                        class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider"
                                    >
                                        Sản phẩm
                                    </th>
                                    <th
                                        class="text-center px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider"
                                    >
                                        Số lượng trả
                                    </th>
                                    <th
                                        class="text-right px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider"
                                    >
                                        Đơn giá
                                    </th>
                                    <th
                                        class="text-right px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider"
                                    >
                                        Thành tiền
                                    </th>
                                    <th
                                        class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider"
                                    >
                                        Lý do trả
                                    </th>
                                    <th
                                        class="text-center px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider"
                                    >
                                        Tình trạng
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr
                                    v-for="(item, index) in receipt.items"
                                    :key="item.id"
                                    class="hover:bg-gray-50"
                                >
                                    <td
                                        class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"
                                    >
                                        {{ index + 1 }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="font-medium text-gray-900">
                                            {{ item.product?.name }}
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            SKU: {{ item.product?.sku }}
                                        </div>
                                        <div
                                            v-if="item.lot_number"
                                            class="text-sm text-gray-500"
                                        >
                                            Lô: {{ item.lot_number }}
                                        </div>
                                    </td>
                                    <td
                                        class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-900"
                                    >
                                        {{ item.quantity }}
                                        <div class="text-xs text-gray-500">
                                            {{ item.product?.unit || "cái" }}
                                        </div>
                                    </td>
                                    <td
                                        class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium text-gray-900"
                                    >
                                        {{ formatCurrency(item.unit_price) }}
                                    </td>
                                    <td
                                        class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium text-gray-900"
                                    >
                                        {{ formatCurrency(item.total_amount) }}
                                    </td>
                                    <td
                                        class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"
                                    >
                                        {{ item.return_reason || "N/A" }}
                                    </td>
                                    <td
                                        class="px-6 py-4 whitespace-nowrap text-center"
                                    >
                                        <span
                                            :class="
                                                getConditionBadgeClass(
                                                    item.condition_status
                                                )
                                            "
                                            class="px-2 py-1 text-xs font-medium rounded-full"
                                        >
                                            {{
                                                getConditionText(
                                                    item.condition_status
                                                )
                                            }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot class="bg-gray-50">
                                <tr>
                                    <td
                                        colspan="4"
                                        class="px-6 py-4 text-right font-medium text-gray-900"
                                    >
                                        Tổng cộng:
                                    </td>
                                    <td
                                        class="px-6 py-4 text-right font-bold text-lg text-red-600"
                                    >
                                        {{
                                            formatCurrency(receipt.total_amount)
                                        }}
                                    </td>
                                    <td colspan="2"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- Notes & Additional Info -->
                <div
                    class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6"
                >
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-medium text-gray-900">
                            📝 Ghi chú và thông tin bổ sung
                        </h2>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-2 gap-6">
                            <div>
                                <div class="text-sm text-gray-600 mb-2">
                                    Ghi chú
                                </div>
                                <div
                                    class="bg-gray-50 rounded-lg p-3 text-sm text-gray-900"
                                >
                                    {{ receipt.note || "Không có ghi chú" }}
                                </div>
                            </div>
                            <div>
                                <div class="text-sm text-gray-600 mb-2">
                                    Lý do trả hàng
                                </div>
                                <div
                                    class="bg-gray-50 rounded-lg p-3 text-sm text-gray-900"
                                >
                                    {{
                                        receipt.return_reason ||
                                        "Không có lý do cụ thể"
                                    }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- History -->
                <div
                    class="bg-white rounded-lg shadow-sm border border-gray-200"
                >
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-medium text-gray-900">
                            📅 Lịch sử thay đổi
                        </h2>
                    </div>
                    <div class="p-6">
                        <div class="space-y-4">
                            <div class="flex items-start space-x-3">
                                <div class="flex-shrink-0">
                                    <div
                                        class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center"
                                    >
                                        <span class="text-green-600 text-sm"
                                            >✅</span
                                        >
                                    </div>
                                </div>
                                <div class="flex-1">
                                    <div class="font-medium text-gray-900">
                                        Phiếu trả hàng được tạo
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        {{
                                            formatDateTime(receipt.created_at)
                                        }}
                                        - Bởi {{ receipt.creator?.name }}
                                    </div>
                                </div>
                            </div>

                            <div
                                v-if="receipt.approved_at"
                                class="flex items-start space-x-3"
                            >
                                <div class="flex-shrink-0">
                                    <div
                                        class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center"
                                    >
                                        <span class="text-blue-600 text-sm"
                                            >👍</span
                                        >
                                    </div>
                                </div>
                                <div class="flex-1">
                                    <div class="font-medium text-gray-900">
                                        Phiếu được duyệt
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        {{
                                            formatDateTime(receipt.approved_at)
                                        }}
                                        - Bởi {{ receipt.approver?.name }}
                                    </div>
                                </div>
                            </div>

                            <div
                                v-if="receipt.returned_at"
                                class="flex items-start space-x-3"
                            >
                                <div class="flex-shrink-0">
                                    <div
                                        class="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center"
                                    >
                                        <span class="text-purple-600 text-sm"
                                            >📦</span
                                        >
                                    </div>
                                </div>
                                <div class="flex-1">
                                    <div class="font-medium text-gray-900">
                                        Hàng được trả
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        {{
                                            formatDateTime(receipt.returned_at)
                                        }}
                                        - Tại {{ receipt.warehouse?.name }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Error State -->
        <div
            v-else-if="error"
            class="flex flex-col items-center justify-center py-12"
        >
            <div class="text-6xl mb-4">❌</div>
            <h3 class="text-lg font-medium text-gray-900 mb-2">
                Có lỗi xảy ra
            </h3>
            <p class="text-gray-500 mb-4">{{ error }}</p>
            <button
                @click="goBack"
                class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600"
            >
                ← Quay lại danh sách
            </button>
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
                    'bg-yellow-100 border border-yellow-400 text-yellow-700':
                        notification.type === 'warning',
                }"
            >
                <div class="flex items-center">
                    <span class="mr-2">
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
import { purchaseReturnReceiptApi } from "../api/purchaseReturnReceiptApi";

export default {
    name: "PurchaseReturnReceiptDetail",
    setup() {
        const loading = ref(true);
        const receipt = ref(null);
        const error = ref("");
        const receiptId = ref(null);

        // Notification
        const notification = ref({
            show: false,
            type: "success",
            message: "",
        });

        // Get receipt ID from DOM data attribute
        onMounted(() => {
            const appElement = document.getElementById(
                "purchase-return-receipt-detail-app"
            );
            const id = appElement?.getAttribute("data-receipt-id");

            if (id) {
                receiptId.value = id;
                fetchReceipt();
            } else {
                error.value = "Không tìm thấy ID phiếu trả hàng";
                loading.value = false;
            }
        });

        // Computed properties
        const canEdit = computed(() => {
            return (
                receipt.value &&
                ["draft", "pending"].includes(receipt.value.status)
            );
        });

        const canApprove = computed(() => {
            return receipt.value && receipt.value.status === "pending";
        });

        // Methods
        const showNotification = (message, type = "success") => {
            notification.value = { show: true, type, message };
            setTimeout(() => {
                notification.value.show = false;
            }, 3000);
        };

        const fetchReceipt = async () => {
            if (!receiptId.value) return;

            try {
                loading.value = true;
                const response =
                    await purchaseReturnReceiptApi.getPurchaseReturnReceipt(
                        receiptId.value
                    );

                if (response.success) {
                    receipt.value = response.data;
                } else {
                    error.value =
                        response.message || "Không tìm thấy phiếu trả hàng";
                }
            } catch (err) {
                console.error("Error fetching receipt:", err);
                error.value = "Có lỗi xảy ra khi tải dữ liệu";
            } finally {
                loading.value = false;
            }
        };

        const approveReceipt = async () => {
            if (!receipt.value) return;

            if (
                !confirm(
                    `Duyệt phiếu trả hàng ${
                        receipt.value.code
                    }?\n\nSẽ tự động giảm ${formatCurrency(
                        receipt.value.total_amount
                    )} từ công nợ nhà cung cấp.`
                )
            )
                return;

            try {
                const response = await purchaseReturnReceiptApi.approve(
                    receipt.value.id
                );

                if (response.success) {
                    showNotification("Duyệt phiếu trả hàng thành công!");
                    fetchReceipt(); // Reload data
                } else {
                    showNotification(
                        response.message || "Có lỗi xảy ra",
                        "error"
                    );
                }
            } catch (error) {
                console.error("Error approving receipt:", error);
                showNotification("Có lỗi xảy ra khi duyệt", "error");
            }
        };

        const editReceipt = () => {
            if (!receiptId.value) return;
            window.location.href = `/purchase-return-receipts/${receiptId.value}/edit`;
        };

        const printReceipt = () => {
            if (!receiptId.value) return;
            window.open(
                `/purchase-return-receipts/${receiptId.value}/print`,
                "_blank"
            );
        };

        const goBack = () => {
            window.location.href = "/purchase-return-receipts";
        };

        // Helper functions
        const formatCurrency = (amount) => {
            return new Intl.NumberFormat("vi-VN", {
                style: "currency",
                currency: "VND",
            }).format(amount || 0);
        };

        const formatDateTime = (datetime) => {
            if (!datetime) return "N/A";
            return new Date(datetime).toLocaleString("vi-VN");
        };

        const getStatusText = (status) => {
            const texts = {
                draft: "Nháp",
                pending: "Chờ duyệt",
                approved: "Đã duyệt",
                returned: "Đã trả hàng",
                completed: "Hoàn tất",
                cancelled: "Đã hủy",
            };
            return texts[status] || status;
        };

        const getStatusBadgeClass = (status) => {
            const baseClasses = "px-3 py-1 text-sm font-medium rounded-full";
            const statusClasses = {
                draft: "bg-gray-100 text-gray-800",
                pending: "bg-yellow-100 text-yellow-800",
                approved: "bg-blue-100 text-blue-800",
                returned: "bg-purple-100 text-purple-800",
                completed: "bg-green-100 text-green-800",
                cancelled: "bg-red-100 text-red-800",
            };
            return `${baseClasses} ${
                statusClasses[status] || "bg-gray-100 text-gray-800"
            }`;
        };

        const getReturnOrderStatusText = (status) => {
            const texts = {
                pending: "Chờ duyệt",
                approved: "Đã duyệt",
                returned: "Đã trả hàng",
                completed: "Hoàn thành",
                cancelled: "Đã hủy",
            };
            return texts[status] || status;
        };

        const getConditionText = (condition) => {
            const texts = {
                good: "Tốt",
                damaged: "Hư hỏng",
                expired: "Hết hạn",
                defective: "Lỗi sản xuất",
            };
            return texts[condition] || condition;
        };

        const getConditionBadgeClass = (condition) => {
            const baseClasses = "px-2 py-1 text-xs font-medium rounded-full";
            const conditionClasses = {
                good: "bg-green-100 text-green-800",
                damaged: "bg-red-100 text-red-800",
                expired: "bg-orange-100 text-orange-800",
                defective: "bg-yellow-100 text-yellow-800",
            };
            return `${baseClasses} ${
                conditionClasses[condition] || "bg-gray-100 text-gray-800"
            }`;
        };

        return {
            loading,
            receipt,
            error,
            notification,
            canEdit,
            canApprove,
            showNotification,
            approveReceipt,
            editReceipt,
            printReceipt,
            goBack,
            formatCurrency,
            formatDateTime,
            getStatusText,
            getStatusBadgeClass,
            getReturnOrderStatusText,
            getConditionText,
            getConditionBadgeClass,
        };
    },
};
</script>
