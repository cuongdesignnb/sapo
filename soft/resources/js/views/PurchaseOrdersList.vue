<template>
    <div class="bg-white">
        <!-- Header -->
        <div class="p-6 border-b">
            <h1 class="text-2xl font-semibold text-gray-900">
                Danh sách đơn đặt hàng
            </h1>
        </div>

        <!-- Action Buttons -->
        <div class="p-6 border-b bg-gray-50">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <button
                        class="flex items-center space-x-2 text-gray-600 hover:text-gray-800"
                        @click="exportData"
                        :disabled="loading"
                    >
                        <span>⬇</span>
                        <span>Xuất file</span>
                    </button>
                </div>
                <a
                    href="/purchase-orders/create"
                    class="bg-blue-500 text-white px-4 py-2 rounded flex items-center space-x-2 hover:bg-blue-600"
                >
                    <span>+</span>
                    <span>Tạo đơn đặt hàng</span>
                </a>
            </div>
        </div>

        <!-- Filters -->
        <div class="p-6 border-b">
            <div class="mb-4">
                <button class="text-blue-500 border-b-2 border-blue-500 pb-2">
                    Tất cả đơn đặt hàng ({{ pagination.total || 0 }})
                </button>
            </div>

            <div class="grid grid-cols-12 gap-4 items-center">
                <!-- Search -->
                <div class="col-span-3">
                    <div class="relative">
                        <input
                            type="text"
                            placeholder="Tìm mã đơn, nhà cung cấp..."
                            class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md"
                            v-model="filters.search"
                            @input="debouncedSearch"
                        />
                        <span
                            class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"
                            >🔍</span
                        >
                    </div>
                </div>

                <!-- Supplier Filter -->
                <div class="col-span-2">
                    <select
                        class="w-full px-3 py-2 border border-gray-300 rounded-md"
                        v-model="filters.supplier_id"
                        @change="applyFilters"
                    >
                        <option value="">Nhà cung cấp</option>
                        <option
                            v-for="supplier in suppliers"
                            :key="supplier.id"
                            :value="supplier.id"
                        >
                            {{ supplier.name }}
                        </option>
                    </select>
                </div>

                <!-- Warehouse Filter -->
                <div class="col-span-2">
                    <select
                        class="w-full px-3 py-2 border border-gray-300 rounded-md"
                        v-model="filters.warehouse_id"
                        @change="applyFilters"
                    >
                        <option value="">Kho</option>
                        <option
                            v-for="warehouse in warehouses"
                            :key="warehouse.id"
                            :value="warehouse.id"
                        >
                            {{ warehouse.name }}
                        </option>
                    </select>
                </div>

                <!-- Status Filter -->
                <div class="col-span-2">
                    <select
                        class="w-full px-3 py-2 border border-gray-300 rounded-md"
                        v-model="filters.status"
                        @change="applyFilters"
                    >
                        <option value="">Trạng thái</option>
                        <option value="draft">Nháp</option>
                        <option value="pending">Chờ duyệt</option>
                        <option value="approved">Đã duyệt</option>
                        <option value="ordered">Đã đặt hàng</option>
                        <option value="partial_received">Nhập một phần</option>
                        <option value="received">Đã nhập kho</option>
                        <option value="completed">Hoàn thành</option>
                        <option value="cancelled">Đã hủy</option>
                    </select>
                </div>

                <!-- Type Filter -->
                <div class="col-span-2">
                    <select
                        class="w-full px-3 py-2 border border-gray-300 rounded-md"
                        v-model="filters.type"
                        @change="applyFilters"
                    >
                        <option value="planned">Sắp nhập</option>
                        <option value="actual">Actual</option>
                        <option value="all">Tất cả</option>
                    </select>
                </div>

                <!-- Reset Button -->
                <div class="col-span-2">
                    <button
                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-gray-600 hover:bg-gray-50"
                        @click="resetFilters"
                    >
                        🔄 Reset
                    </button>
                </div>

                <!-- More Actions -->
                <div class="col-span-1">
                    <button
                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-gray-600 hover:bg-gray-50"
                    >
                        ⚙️
                    </button>
                </div>
            </div>
        </div>

        <!-- Loading State -->
        <div
            v-if="loading && orders.length === 0"
            class="flex justify-center items-center py-12"
        >
            <div
                class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"
            ></div>
            <span class="ml-2 text-gray-600">Đang tải dữ liệu...</span>
        </div>

        <!-- Table -->
        <div v-else class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th
                            class="text-left p-4 text-sm font-medium text-gray-600"
                        >
                            Mã đơn
                        </th>
                        <th
                            class="text-left p-4 text-sm font-medium text-gray-600"
                        >
                            Nhà cung cấp
                        </th>
                        <th
                            class="text-left p-4 text-sm font-medium text-gray-600"
                        >
                            Kho
                        </th>
                        <th
                            class="text-left p-4 text-sm font-medium text-gray-600"
                        >
                            Trạng thái
                        </th>
                        <th
                            class="text-left p-4 text-sm font-medium text-gray-600"
                        >
                            Tổng tiền
                        </th>
                        <th
                            class="text-left p-4 text-sm font-medium text-gray-600"
                        >
                            Thanh toán
                        </th>
                        <th
                            class="text-left p-4 text-sm font-medium text-gray-600"
                        >
                            Người tạo
                        </th>
                        <th
                            class="text-left p-4 text-sm font-medium text-gray-600 cursor-pointer"
                            @click="sortBy('created_at')"
                        >
                            Ngày tạo
                            <span class="ml-1">↕</span>
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
                        v-for="order in orders"
                        :key="order.id"
                        class="hover:bg-gray-50 cursor-pointer"
                        @click="viewOrder(order.id)"
                    >
                        <td class="p-4">
                            <div
                                class="text-blue-500 hover:text-blue-700 font-medium"
                            >
                                {{ order.code }}
                            </div>
                            <div class="text-xs text-gray-500">
                                {{
                                    purchaseOrderApi.formatDate(
                                        order.created_at
                                    )
                                }}
                            </div>
                        </td>
                        <td class="p-4">
                            <div class="font-medium">
                                {{ order.supplier?.name }}
                            </div>
                            <div class="text-xs text-gray-500">
                                {{ order.supplier?.phone }}
                            </div>
                        </td>
                        <td class="p-4 text-sm text-gray-600">
                            {{ order.warehouse?.name }}
                        </td>
                        <td class="p-4">
                            <span
                                :class="getStatusBadgeClass(order.status)"
                                class="px-2 py-1 text-xs rounded-full"
                            >
                                {{
                                    purchaseOrderApi.getStatusText(order.status)
                                }}
                            </span>
                        </td>
                        <td class="p-4 text-sm font-medium text-gray-900">
                            <div class="flex items-center space-x-2">
                                <span>{{
                                    purchaseOrderApi.formatCurrency(order.total)
                                }}</span>
                                <span
                                    v-if="order.is_order_only"
                                    class="px-2 py-0.5 text-[10px] rounded bg-indigo-100 text-indigo-700 uppercase tracking-wide"
                                    >Sắp nhập</span
                                >
                            </div>
                        </td>
                        <td class="p-4">
                            <template v-if="!order.is_order_only">
                                <div class="text-sm font-medium text-gray-900">
                                    {{
                                        purchaseOrderApi.formatCurrency(
                                            order.paid
                                        )
                                    }}
                                    /
                                    {{
                                        purchaseOrderApi.formatCurrency(
                                            order.total
                                        )
                                    }}
                                </div>
                                <div
                                    class="text-xs"
                                    :class="
                                        getPaymentStatusClass(
                                            order.payment_status
                                        )
                                    "
                                >
                                    {{
                                        getPaymentStatusText(
                                            order.payment_status
                                        )
                                    }}
                                </div>
                            </template>
                            <template v-else>
                                <div class="text-xs text-gray-500 italic">
                                    (Đơn đặt hàng - chưa tính công nợ)
                                </div>
                            </template>
                        </td>
                        <td class="p-4 text-sm text-gray-600">
                            {{ order.creator?.name }}
                        </td>
                        <td class="p-4 text-sm text-gray-600">
                            {{ purchaseOrderApi.formatDate(order.created_at) }}
                        </td>
                        <td class="p-4" @click.stop>
                            <div class="flex space-x-2">
                                <button
                                    @click="viewOrder(order.id)"
                                    class="text-blue-600 hover:text-blue-800"
                                    title="Xem chi tiết"
                                >
                                    👁️
                                </button>
                                <button
                                    v-if="purchaseOrderApi.canEdit(order)"
                                    @click="editOrder(order.id)"
                                    class="text-green-600 hover:text-green-800"
                                    title="Sửa"
                                >
                                    ✏️
                                </button>
                                <button
                                    v-if="canApprove(order)"
                                    @click="approveOrder(order.id)"
                                    class="text-green-600 hover:text-green-800"
                                    title="Duyệt đơn"
                                >
                                    ✅
                                </button>
                                <button
                                    v-if="
                                        order.is_order_only &&
                                        order.status === 'approved'
                                    "
                                    @click="convertOrder(order)"
                                    class="text-indigo-600 hover:text-indigo-800"
                                    title="Chuyển thành đơn nhập thực tế"
                                >
                                    🔄
                                </button>
                                <button
                                    v-else-if="
                                        purchaseOrderApi.canCreateReceipt(order)
                                    "
                                    @click="createReceipt(order.id)"
                                    class="text-purple-600 hover:text-purple-800"
                                    title="Tạo phiếu nhập"
                                >
                                    📦
                                </button>
                                <button
                                    v-if="purchaseOrderApi.canDelete(order)"
                                    @click="deleteOrder(order.id)"
                                    class="text-red-600 hover:text-red-800"
                                    title="Xóa"
                                >
                                    🗑️
                                </button>
                                <button
                                    v-if="canMakePayment(order)"
                                    @click="openPaymentModal(order)"
                                    class="text-green-600 hover:text-green-800"
                                    title="Thanh toán"
                                >
                                    💰
                                </button>
                                <button
                                    v-if="hasPaymentHistory(order)"
                                    @click="viewPaymentHistory(order)"
                                    class="text-blue-600 hover:text-blue-800"
                                    title="Lịch sử thanh toán"
                                >
                                    📊
                                </button>
                            </div>
                        </td>
                    </tr>

                    <!-- Empty State -->
                    <tr v-if="orders.length === 0 && !loading">
                        <td colspan="8" class="text-center py-12 text-gray-500">
                            <div class="text-4xl mb-4">📋</div>
                            <div>Không có đơn nhập hàng nào</div>
                            <div class="mt-4">
                                <a
                                    href="/purchase-orders/create"
                                    class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600"
                                >
                                    Tạo đơn nhập hàng đầu tiên
                                </a>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div
            v-if="pagination.total > 0"
            class="px-6 py-4 border-t flex justify-between items-center"
        >
            <div class="text-sm text-gray-700">
                Hiển thị {{ pagination.from }}-{{ pagination.to }} của
                {{ pagination.total }} đơn nhập hàng
            </div>
            <div class="flex space-x-2">
                <button
                    class="px-3 py-1 border rounded text-sm"
                    :disabled="pagination.current_page <= 1"
                    @click="goToPage(pagination.current_page - 1)"
                >
                    Trước
                </button>

                <template v-for="page in visiblePages" :key="page">
                    <button
                        class="px-3 py-1 border rounded text-sm"
                        :class="
                            page === pagination.current_page
                                ? 'bg-blue-500 text-white'
                                : 'bg-white'
                        "
                        @click="goToPage(page)"
                    >
                        {{ page }}
                    </button>
                </template>

                <button
                    class="px-3 py-1 border rounded text-sm"
                    :disabled="pagination.current_page >= pagination.last_page"
                    @click="goToPage(pagination.current_page + 1)"
                >
                    Tiếp
                </button>
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
    <!-- Payment Modal -->
    <PaymentModal
        :show="showPaymentModal"
        :order="selectedOrder"
        @close="closePaymentModal"
        @payment-success="onPaymentSuccess"
    />
    <PaymentHistoryModal
        :show="showPaymentHistoryModal"
        :order="selectedOrder"
        @close="() => (showPaymentHistoryModal = false)"
    />
</template>

<script>
import { ref, reactive, computed, onMounted } from "vue";
import { purchaseOrderApi } from "../api/purchaseOrderApi";
import supplierApi from "../api/supplierApi";
import warehouseApi from "../api/warehouseApi";
import PaymentModal from "../components/PaymentModal.vue";
import PaymentHistoryModal from "../components/PaymentHistoryModal.vue";

export default {
    name: "PurchaseOrdersList",
    components: {
        PaymentModal,
        PaymentHistoryModal,
    },
    setup() {
        const loading = ref(false);
        const orders = ref([]);
        const suppliers = ref([]);
        const warehouses = ref([]);
        const showPaymentModal = ref(false);
        const selectedOrder = ref(null);
        const showPaymentHistoryModal = ref(false);

        const filters = reactive({
            search: "",
            supplier_id: "",
            warehouse_id: "",
            status: "",
            type: "planned",
        });

        const pagination = reactive({
            current_page: 1,
            last_page: 1,
            per_page: 20,
            total: 0,
            from: 0,
            to: 0,
        });

        // Notification
        const notification = ref({
            show: false,
            type: "success",
            message: "",
        });

        const visiblePages = computed(() => {
            const pages = [];
            const start = Math.max(1, pagination.current_page - 2);
            const end = Math.min(
                pagination.last_page,
                pagination.current_page + 2
            );

            for (let i = start; i <= end; i++) {
                pages.push(i);
            }

            return pages;
        });

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

        const fetchOrders = async (page = 1) => {
            loading.value = true;
            try {
                const params = {
                    page: page,
                    per_page: pagination.per_page,
                    ...Object.fromEntries(
                        Object.entries(filters).filter(([_, v]) => v !== "")
                    ),
                };

                const response = await purchaseOrderApi.getPurchaseOrders(
                    params
                );

                if (response.success) {
                    orders.value = response.data;
                    Object.assign(pagination, response.pagination);
                }
            } catch (error) {
                console.error("Error fetching orders:", error);
                showNotification("Có lỗi xảy ra khi tải dữ liệu", "error");
            } finally {
                loading.value = false;
            }
        };

        const fetchSuppliers = async () => {
            try {
                const response = await supplierApi.getSupplierOptions();
                const data = response.data;
                if (data.success) {
                    suppliers.value = data.data.map((item) => ({
                        id: item.value,
                        name: item.label,
                        code: item.code,
                        phone: item.phone,
                        email: item.email,
                    }));
                }
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

        const debouncedSearch = debounce(() => {
            fetchOrders(1);
        }, 500);

        const applyFilters = () => {
            fetchOrders(1);
        };

        const resetFilters = () => {
            filters.search = "";
            filters.supplier_id = "";
            filters.warehouse_id = "";
            filters.status = "";
            filters.type = "planned";
            applyFilters();
        };

        const sortBy = (field) => {
            // Add sorting logic here if needed
            console.log("Sort by:", field);
        };

        const goToPage = (page) => {
            if (page >= 1 && page <= pagination.last_page) {
                fetchOrders(page);
            }
        };

        const viewOrder = (id) => {
            window.location.href = `/purchase-orders/${id}`;
        };

        const editOrder = (id) => {
            window.location.href = `/purchase-orders/${id}/edit`;
        };

        const createReceipt = (id) => {
            window.location.href = `/purchase-receipts/create?order_id=${id}`;
        };

        const deleteOrder = async (id) => {
            if (!confirm("Bạn có chắc chắn muốn xóa đơn nhập hàng này?"))
                return;

            try {
                const response = await purchaseOrderApi.deletePurchaseOrder(id);

                if (response.success) {
                    showNotification("Xóa đơn nhập hàng thành công");
                    fetchOrders(pagination.current_page);
                } else {
                    showNotification(
                        response.message || "Có lỗi xảy ra",
                        "error"
                    );
                }
            } catch (error) {
                console.error("Error deleting order:", error);
                showNotification("Có lỗi xảy ra", "error");
            }
        };

        const exportData = async () => {
            try {
                const params = Object.fromEntries(
                    Object.entries(filters).filter(([_, v]) => v !== "")
                );

                const response = await purchaseOrderApi.exportPurchaseOrders(
                    params
                );

                // Create blob and download
                const blob = new Blob([response.data], {
                    type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
                });
                const url = window.URL.createObjectURL(blob);
                const link = document.createElement("a");
                link.href = url;
                link.setAttribute(
                    "download",
                    `purchase-orders-${
                        new Date().toISOString().split("T")[0]
                    }.xlsx`
                );
                document.body.appendChild(link);
                link.click();
                link.remove();
                window.URL.revokeObjectURL(url);

                showNotification("Xuất file thành công!");
            } catch (error) {
                console.error("Error exporting data:", error);
                showNotification("Có lỗi xảy ra khi xuất dữ liệu", "error");
            }
        };

        const getStatusBadgeClass = (status) => {
            const classes = {
                draft: "bg-gray-100 text-gray-800",
                pending: "bg-yellow-100 text-yellow-800",
                approved: "bg-blue-100 text-blue-800",
                ordered: "bg-purple-100 text-purple-800",
                partial_received: "bg-orange-100 text-orange-800",
                received: "bg-green-100 text-green-800",
                completed: "bg-green-100 text-green-800",
                cancelled: "bg-red-100 text-red-800",
            };
            return classes[status] || "bg-gray-100 text-gray-800";
        };
        const getPaymentStatusText = (status) => {
            const texts = {
                unpaid: "Chưa thanh toán",
                partial: "Thanh toán một phần",
                paid: "Đã thanh toán",
            };
            return texts[status] || status;
        };

        const getPaymentStatusClass = (status) => {
            const classes = {
                unpaid: "text-red-600",
                partial: "text-yellow-600",
                paid: "text-green-600",
            };
            return classes[status] || "text-gray-600";
        };

        const canMakePayment = (order) => {
            if (!order || order.is_order_only) return false;
            return (
                [
                    "approved",
                    "ordered",
                    "partial_received",
                    "received",
                ].includes(order.status) && order.need_pay > 0
            );
        };

        const hasPaymentHistory = (order) => {
            return !order.is_order_only && order.paid > 0;
        };

        const openPaymentModal = (order) => {
            selectedOrder.value = order;
            showPaymentModal.value = true;
        };

        const viewPaymentHistory = (order) => {
            selectedOrder.value = order;
            showPaymentHistoryModal.value = true;
        };
        const closePaymentModal = () => {
            showPaymentModal.value = false;
            selectedOrder.value = null;
        };

        const onPaymentSuccess = (paymentData) => {
            // Refresh order data after payment
            fetchOrders(pagination.current_page);
            showNotification("Thanh toán thành công!");
        };
        const canApprove = (order) => {
            return ["pending", "draft"].includes(order.status);
        };

        const convertOrder = async (order) => {
            if (
                !confirm(
                    "Chuyển đơn đặt hàng này thành đơn nhập thực tế? Sau khi chuyển có thể tạo phiếu nhập & công nợ sẽ được ghi nhận."
                )
            )
                return;
            try {
                const response = await purchaseOrderApi.convertToActual(
                    order.id
                );
                if (response.success) {
                    showNotification("Chuyển đổi thành công");
                    fetchOrders(pagination.current_page);
                } else {
                    showNotification(
                        response.message || "Có lỗi xảy ra",
                        "error"
                    );
                }
            } catch (e) {
                console.error(e);
                showNotification("Có lỗi xảy ra", "error");
            }
        };

        const approveOrder = async (id) => {
            if (!confirm("Bạn có chắc chắn muốn duyệt đơn nhập hàng này?"))
                return;

            try {
                const response =
                    await purchaseOrderApi.updatePurchaseOrderStatus(
                        id,
                        "approved"
                    );

                if (response.success) {
                    showNotification("Duyệt đơn nhập hàng thành công");
                    fetchOrders(pagination.current_page);
                } else {
                    showNotification(
                        response.message || "Có lỗi xảy ra",
                        "error"
                    );
                }
            } catch (error) {
                console.error("Error approving order:", error);
                showNotification("Có lỗi xảy ra khi duyệt đơn", "error");
            }
        };

        // Debounce helper function
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        onMounted(() => {
            fetchOrders();
            fetchSuppliers();
            fetchWarehouses();
        });

        return {
            loading,
            orders,
            suppliers,
            warehouses,
            filters,
            pagination,
            notification,
            visiblePages,
            showNotification,
            debouncedSearch,
            applyFilters,
            resetFilters,
            sortBy,
            goToPage,
            viewOrder,
            editOrder,
            createReceipt,
            deleteOrder,
            exportData,
            getStatusBadgeClass,
            purchaseOrderApi,
            getPaymentStatusText,
            getPaymentStatusClass,
            canMakePayment,
            hasPaymentHistory,
            openPaymentModal,
            viewPaymentHistory,
            showPaymentModal,
            selectedOrder,
            closePaymentModal,
            onPaymentSuccess,
            showPaymentHistoryModal,
            canApprove,
            approveOrder,
            convertOrder,
        };
    },
};
</script>
