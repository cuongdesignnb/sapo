<template>
    <div class="bg-gray-50 min-h-screen">
        <!-- Header -->
        <div class="bg-white border-b border-gray-200 px-6 py-4">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900">
                        📋 Chi tiết đơn trả hàng
                    </h1>
                    <nav class="text-sm text-gray-500 mt-1">
                        <a href="/" class="hover:text-gray-700">Trang chủ</a>
                        <span class="mx-2">/</span>
                        <a
                            href="/purchase-return-orders"
                            class="hover:text-gray-700"
                            >Đơn trả hàng</a
                        >
                        <span class="mx-2">/</span>
                        <span class="text-gray-900">{{
                            order?.code || "Chi tiết"
                        }}</span>
                    </nav>
                </div>
                <div class="flex space-x-3">
                    <button
                        v-if="canApprove"
                        @click="approveOrder"
                        class="px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600"
                    >
                        ✅ Duyệt đơn
                    </button>
                    <button
                        v-if="canEdit"
                        @click="editOrder"
                        class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600"
                    >
                        ✏️ Chỉnh sửa
                    </button>
                    <button
                        @click="printOrder"
                        class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
                    >
                        🖨️ In đơn
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
        <div v-else-if="order" class="p-6">
            <div class="max-w-6xl mx-auto">
                <!-- Order Header -->
                <div
                    class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6"
                >
                    <div class="px-6 py-4 border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <h2 class="text-lg font-medium text-gray-900">
                                📋 Thông tin đơn trả hàng
                            </h2>
                            <span
                                :class="getStatusBadgeClass(order.status)"
                                class="px-3 py-1 text-sm font-medium rounded-full"
                            >
                                {{ getStatusText(order.status) }}
                            </span>
                        </div>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-4 gap-6">
                            <div>
                                <div class="text-sm text-gray-600">Mã đơn</div>
                                <div class="font-medium text-gray-900">
                                    {{ order.code }}
                                </div>
                            </div>
                            <div>
                                <div class="text-sm text-gray-600">
                                    Ngày tạo
                                </div>
                                <div class="font-medium text-gray-900">
                                    {{ formatDateTime(order.created_at) }}
                                </div>
                            </div>
                            <div>
                                <div class="text-sm text-gray-600">
                                    Ngày trả hàng
                                </div>
                                <div class="font-medium text-gray-900">
                                    {{ formatDateTime(order.returned_at) }}
                                </div>
                            </div>
                            <div>
                                <div class="text-sm text-gray-600">
                                    Người tạo
                                </div>
                                <div class="font-medium text-gray-900">
                                    {{ order.creator?.name || "N/A" }}
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
                                        {{ order.supplier?.name || "N/A" }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-sm text-gray-600">
                                        Mã nhà cung cấp
                                    </div>
                                    <div class="font-medium text-gray-900">
                                        {{ order.supplier?.code || "N/A" }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-sm text-gray-600">
                                        Điện thoại
                                    </div>
                                    <div class="font-medium text-gray-900">
                                        {{ order.supplier?.phone || "N/A" }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-sm text-gray-600">
                                        Email
                                    </div>
                                    <div class="font-medium text-gray-900">
                                        {{ order.supplier?.email || "N/A" }}
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
                                🏭 Thông tin kho
                            </h2>
                        </div>
                        <div class="p-6">
                            <div class="space-y-3">
                                <div>
                                    <div class="text-sm text-gray-600">
                                        Tên kho
                                    </div>
                                    <div class="font-medium text-gray-900">
                                        {{ order.warehouse?.name || "N/A" }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-sm text-gray-600">
                                        Mã kho
                                    </div>
                                    <div class="font-medium text-gray-900">
                                        {{ order.warehouse?.code || "N/A" }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-sm text-gray-600">
                                        Địa chỉ
                                    </div>
                                    <div class="font-medium text-gray-900">
                                        {{ order.warehouse?.address || "N/A" }}
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
                    <div class="px-6 py-4 border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <h2 class="text-lg font-medium text-gray-900">
                                📋 Danh sách sản phẩm trả
                            </h2>
                            <span
                                class="text-xs px-2 py-1 rounded bg-blue-50 text-blue-600 border border-blue-200"
                                title="Số lượng ở đây chỉ là dự kiến. Tồn kho & công nợ chỉ thay đổi khi Phiếu trả hàng được duyệt."
                            >
                                Dự kiến • Chưa ảnh hưởng kho/công nợ
                            </span>
                        </div>
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
                                        Số lượng
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
                                        Lý do
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr
                                    v-for="(item, index) in order.items"
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
                                    </td>
                                    <td
                                        class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-900"
                                    >
                                        {{ item.quantity }}
                                    </td>
                                    <td
                                        class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium text-gray-900"
                                    >
                                        {{ formatCurrency(item.price) }}
                                    </td>
                                    <td
                                        class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium text-gray-900"
                                    >
                                        {{ formatCurrency(item.total) }}
                                    </td>
                                    <td
                                        class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"
                                    >
                                        {{ item.return_reason || "N/A" }}
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
                                        {{ formatCurrency(order.total) }}
                                    </td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- Notes -->
                <div
                    class="bg-white rounded-lg shadow-sm border border-gray-200"
                >
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-medium text-gray-900">
                            📝 Ghi chú
                        </h2>
                    </div>
                    <div class="p-6">
                        <div
                            class="bg-gray-50 rounded-lg p-3 text-sm text-gray-900"
                        >
                            {{ order.note || "Không có ghi chú" }}
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
import purchaseReturnOrderApi from "../api/purchaseReturnOrderApi";

export default {
    name: "PurchaseReturnOrderDetail",
    setup() {
        const loading = ref(true);
        const order = ref(null);
        const error = ref("");
        const orderId = ref(null);

        // Notification
        const notification = ref({
            show: false,
            type: "success",
            message: "",
        });

        // Get order ID from DOM data attribute
        onMounted(() => {
            const appElement = document.getElementById(
                "purchase-return-order-detail-app"
            );
            const id = appElement?.getAttribute("data-order-id");

            if (id) {
                orderId.value = id;
                fetchOrder();
            } else {
                error.value = "Không tìm thấy ID đơn trả hàng";
                loading.value = false;
            }
        });

        // Computed properties
        const canEdit = computed(() => {
            return (
                order.value && ["draft", "pending"].includes(order.value.status)
            );
        });

        const canApprove = computed(() => {
            return order.value && order.value.status === "pending";
        });

        // Methods
        const showNotification = (message, type = "success") => {
            notification.value = { show: true, type, message };
            setTimeout(() => {
                notification.value.show = false;
            }, 3000);
        };

        const fetchOrder = async () => {
            if (!orderId.value) return;

            try {
                loading.value = true;
                const response = await purchaseReturnOrderApi.show(
                    orderId.value
                );

                if (response.success) {
                    order.value = response.data;
                } else {
                    error.value =
                        response.message || "Không tìm thấy đơn trả hàng";
                }
            } catch (err) {
                console.error("Error fetching order:", err);
                error.value = "Có lỗi xảy ra khi tải dữ liệu";
            } finally {
                loading.value = false;
            }
        };

        const approveOrder = async () => {
            if (!order.value) return;

            if (!confirm(`Duyệt đơn trả hàng ${order.value.code}?`)) return;

            try {
                const response = await purchaseReturnOrderApi.approve(
                    order.value.id
                );

                if (response.success) {
                    showNotification("Duyệt đơn trả hàng thành công!");
                    fetchOrder();
                } else {
                    showNotification(
                        response.message || "Có lỗi xảy ra",
                        "error"
                    );
                }
            } catch (error) {
                console.error("Error approving order:", error);
                showNotification("Có lỗi xảy ra khi duyệt", "error");
            }
        };

        const editOrder = () => {
            if (!orderId.value) return;
            window.location.href = `/purchase-return-orders/${orderId.value}/edit`;
        };

        const printOrder = () => {
            if (!orderId.value) return;
            window.open(
                `/purchase-return-orders/${orderId.value}/print`,
                "_blank"
            );
        };

        const goBack = () => {
            window.location.href = "/purchase-return-orders";
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
                completed: "Hoàn thành",
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

        return {
            loading,
            order,
            error,
            notification,
            canEdit,
            canApprove,
            showNotification,
            approveOrder,
            editOrder,
            printOrder,
            goBack,
            formatCurrency,
            formatDateTime,
            getStatusText,
            getStatusBadgeClass,
        };
    },
};
</script>
