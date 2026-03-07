<template>
    <div class="bg-white">
        <!-- Header -->
        <div class="p-6 border-b">
            <h1 class="text-2xl font-semibold text-gray-900">
                Danh sách đơn trả hàng
            </h1>
            <!-- Debug Info -->
            <div class="mt-2 text-sm text-gray-500">
                Loading: {{ loading }} | Error: {{ error }} | Data count:
                {{ orderReturns.length }}
            </div>
        </div>

        <!-- Filters -->
        <div class="p-6 border-b bg-gray-50">
            <div class="grid grid-cols-12 gap-4 items-center">
                <!-- Search -->
                <div class="col-span-3">
                    <input
                        v-model="searchQuery"
                        @input="debouncedSearch"
                        type="text"
                        placeholder="Tìm theo mã đơn, khách hàng..."
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500"
                    />
                </div>

                <!-- Status Filter -->
                <div class="col-span-2">
                    <select
                        v-model="filters.status"
                        @change="applyFilters"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500"
                    >
                        <option value="">Tất cả trạng thái</option>
                        <option value="pending">Chưa nhận hàng</option>
                        <option value="received">Đã nhận hàng</option>
                        <option value="warehoused">Đã nhập kho</option>
                        <option value="refunded">Đã hoàn tiền</option>
                        <option value="cancelled">Đã hủy</option>
                    </select>
                </div>

                <!-- Date From -->
                <div class="col-span-2">
                    <input
                        v-model="filters.date_from"
                        @change="applyFilters"
                        type="date"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500"
                    />
                </div>

                <!-- Date To -->
                <div class="col-span-2">
                    <input
                        v-model="filters.date_to"
                        @change="applyFilters"
                        type="date"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500"
                    />
                </div>

                <!-- Reset Button -->
                <div class="col-span-1">
                    <button
                        @click="resetFilters"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
                    >
                        Reset
                    </button>
                </div>

                <!-- Export Button -->
                <div class="col-span-2">
                    <button
                        @click="exportData"
                        class="w-full px-3 py-2 bg-green-600 text-white rounded-md hover:bg-green-700"
                    >
                        Xuất Excel
                    </button>
                </div>
            </div>
        </div>

        <!-- Stats -->
        <div class="p-6 border-b">
            <div class="grid grid-cols-5 gap-4">
                <div class="text-center p-4 bg-yellow-50 rounded-lg">
                    <div class="text-2xl font-bold text-yellow-600">
                        {{ stats.pending || 0 }}
                    </div>
                    <div class="text-sm text-yellow-600">Chờ xử lý</div>
                </div>
                <div class="text-center p-4 bg-blue-50 rounded-lg">
                    <div class="text-2xl font-bold text-blue-600">
                        {{ stats.received || 0 }}
                    </div>
                    <div class="text-sm text-blue-600">Đã nhận hàng</div>
                </div>
                <div class="text-center p-4 bg-purple-50 rounded-lg">
                    <div class="text-2xl font-bold text-purple-600">
                        {{ stats.warehoused || 0 }}
                    </div>
                    <div class="text-sm text-purple-600">Đã nhập kho</div>
                </div>
                <div class="text-center p-4 bg-green-50 rounded-lg">
                    <div class="text-2xl font-bold text-green-600">
                        {{ stats.refunded || 0 }}
                    </div>
                    <div class="text-sm text-green-600">Đã hoàn tiền</div>
                </div>
                <div class="text-center p-4 bg-red-50 rounded-lg">
                    <div class="text-2xl font-bold text-red-600">
                        {{ stats.cancelled || 0 }}
                    </div>
                    <div class="text-sm text-red-600">Đã hủy</div>
                </div>
                <div class="text-center p-4 bg-gray-50 rounded-lg">
                    <div class="text-2xl font-bold text-gray-600">
                        {{ formatCurrency(stats.total_amount || 0) }}
                    </div>
                    <div class="text-sm text-gray-600">Tổng tiền trả</div>
                </div>
            </div>
        </div>

        <!-- Error State -->
        <div
            v-if="!loading && error"
            class="flex justify-center items-center py-12"
        >
            <div class="text-center">
                <div class="text-red-500 text-lg mb-2">
                    ⚠️ Có lỗi xảy ra khi tải dữ liệu
                </div>
                <button
                    @click="loadOrderReturns"
                    class="px-4 py-2 bg-orange-600 text-white rounded-md hover:bg-orange-700"
                >
                    Thử lại
                </button>
            </div>
        </div>

        <!-- Loading State -->
        <div
            v-else-if="loading && orderReturns.length === 0"
            class="flex justify-center items-center py-12"
        >
            <div
                class="animate-spin rounded-full h-8 w-8 border-b-2 border-orange-500"
            ></div>
            <span class="ml-2 text-gray-600">Đang tải dữ liệu...</span>
        </div>

        <!-- Table -->
        <div v-else class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                        >
                            Mã đơn trả
                        </th>
                        <th
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                        >
                            Đơn hàng gốc
                        </th>
                        <th
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                        >
                            Khách hàng
                        </th>
                        <th
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                        >
                            Trạng thái
                        </th>
                        <th
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                        >
                            Tổng tiền
                        </th>
                        <th
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                        >
                            Đã hoàn
                        </th>
                        <th
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                        >
                            Ngày trả
                        </th>
                        <th
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                        >
                            Thao tác
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-if="orderReturns.length === 0">
                        <td
                            colspan="8"
                            class="px-6 py-4 text-center text-gray-500"
                        >
                            Không có dữ liệu
                        </td>
                    </tr>
                    <tr
                        v-for="orderReturn in orderReturns"
                        :key="orderReturn.id"
                        class="hover:bg-gray-50"
                    >
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900">
                                {{ orderReturn.code }}
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div
                                class="text-sm text-blue-600 hover:text-blue-800 cursor-pointer"
                            >
                                {{ orderReturn.order?.code || "N/A" }}
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900">
                                {{ orderReturn.customer?.name || "N/A" }}
                            </div>
                            <div class="text-sm text-gray-500">
                                {{ orderReturn.customer?.phone || "" }}
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span
                                :class="getStatusClass(orderReturn.status)"
                                class="inline-flex px-2 py-1 text-xs font-semibold rounded-full"
                            >
                                {{ getStatusText(orderReturn.status) }}
                            </span>
                        </td>
                        <td
                            class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"
                        >
                            {{ formatCurrency(orderReturn.total || 0) }}
                        </td>
                        <td
                            class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"
                        >
                            {{ formatCurrency(orderReturn.refunded || 0) }}
                        </td>
                        <td
                            class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"
                        >
                            {{
                                formatDate(
                                    orderReturn.returned_at ||
                                        orderReturn.created_at
                                )
                            }}
                        </td>
                        <td
                            class="px-6 py-4 whitespace-nowrap text-sm font-medium"
                        >
                            <div class="flex space-x-2">
                                <button
                                    @click="viewDetail(orderReturn)"
                                    class="text-blue-600 hover:text-blue-900"
                                >
                                    Chi tiết
                                </button>
                                <button
                                    v-if="canReceive(orderReturn)"
                                    @click="receiveReturn(orderReturn)"
                                    class="text-green-600 hover:text-green-900"
                                >
                                    Nhận hàng
                                </button>
                                <button
                                    v-if="canWarehouse(orderReturn)"
                                    @click="warehouseReturn(orderReturn)"
                                    class="text-blue-600 hover:text-blue-900"
                                >
                                    Nhập kho
                                </button>
                                <button
                                    v-if="canRefund(orderReturn)"
                                    @click="showRefundModal(orderReturn)"
                                    class="text-purple-600 hover:text-purple-900"
                                >
                                    Hoàn tiền
                                </button>
                                <button
                                    v-if="canCancel(orderReturn)"
                                    @click="cancelReturn(orderReturn)"
                                    class="text-red-600 hover:text-red-900"
                                >
                                    Hủy
                                </button>
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
                Hiển thị {{ pagination.from }} - {{ pagination.to }} trong tổng
                số {{ pagination.total }} bản ghi
            </div>
            <div class="flex space-x-2">
                <button
                    v-for="page in visiblePages"
                    :key="page"
                    @click="changePage(page)"
                    :class="[
                        'px-3 py-2 rounded-md text-sm',
                        page === pagination.current_page
                            ? 'bg-orange-600 text-white'
                            : 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50',
                    ]"
                >
                    {{ page }}
                </button>
            </div>
        </div>

        <!-- Detail Modal -->
        <div
            v-if="selectedReturn"
            class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4"
        >
            <div
                class="bg-white rounded-lg w-full max-w-6xl max-h-screen overflow-y-auto"
            >
                <OrderReturnDetail
                    :order-return="selectedReturn"
                    @close="selectedReturn = null"
                    @updated="handleReturnUpdated"
                />
            </div>
        </div>

        <!-- Refund Modal -->
        <div
            v-if="showRefundForm"
            class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4"
        >
            <div class="bg-white rounded-lg w-full max-w-md">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">Hoàn tiền</h3>

                    <div class="mb-4">
                        <label
                            class="block text-sm font-medium text-gray-700 mb-2"
                        >
                            Số tiền hoàn
                        </label>
                        <input
                            v-model.number="refundForm.amount"
                            type="number"
                            :max="getRemainingRefund(refundTarget)"
                            min="0"
                            step="1000"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500"
                        />
                        <p class="text-sm text-gray-500 mt-1">
                            Còn lại:
                            {{
                                formatCurrency(getRemainingRefund(refundTarget))
                            }}
                        </p>
                    </div>

                    <div class="mb-4">
                        <label
                            class="block text-sm font-medium text-gray-700 mb-2"
                        >
                            Ghi chú
                        </label>
                        <textarea
                            v-model="refundForm.note"
                            rows="3"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500"
                            placeholder="Ghi chú về việc hoàn tiền..."
                        ></textarea>
                    </div>

                    <div class="flex justify-end space-x-3">
                        <button
                            @click="closeRefundModal"
                            class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
                        >
                            Hủy
                        </button>
                        <button
                            @click="processRefund"
                            :disabled="refundLoading || !refundForm.amount"
                            class="px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700 disabled:opacity-50"
                        >
                            {{ refundLoading ? "Đang xử lý..." : "Hoàn tiền" }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
console.log("📄 OrderReturnsList.vue script loading...");

import { ref, computed, onMounted } from "vue";
import { orderReturnApi, orderReturnHelpers } from "../api/orderReturnApi";
import OrderReturnDetail from "../components/OrderReturnDetail.vue";

console.log("📦 Imports loaded:", {
    orderReturnApi,
    orderReturnHelpers,
    OrderReturnDetail,
});

export default {
    name: "OrderReturnsList",
    components: {
        OrderReturnDetail,
    },
    setup() {
        console.log("🚀 OrderReturnsList component setup started");

        const loading = ref(false);
        const error = ref(false);
        const orderReturns = ref([]);
        const selectedReturn = ref(null);
        const searchQuery = ref("");

        const stats = ref({
            pending: 0,
            approved: 0,
            refunded: 0,
            cancelled: 0,
            total_amount: 0,
        });

        const filters = ref({
            status: "",
            date_from: "",
            date_to: "",
        });

        const pagination = ref({
            current_page: 1,
            last_page: 1,
            per_page: 20,
            total: 0,
            from: 0,
            to: 0,
        });

        // Refund modal
        const showRefundForm = ref(false);
        const refundTarget = ref(null);
        const refundLoading = ref(false);
        const refundForm = ref({
            amount: 0,
            note: "",
        });

        const visiblePages = computed(() => {
            const current = pagination.value.current_page;
            const last = pagination.value.last_page;
            const pages = [];

            for (
                let i = Math.max(1, current - 2);
                i <= Math.min(last, current + 2);
                i++
            ) {
                pages.push(i);
            }

            return pages;
        });

        const loadOrderReturns = async () => {
            console.log("📋 loadOrderReturns called");
            loading.value = true;
            error.value = false;

            try {
                const params = {
                    page: pagination.value.current_page,
                    per_page: pagination.value.per_page,
                    search: searchQuery.value,
                    ...filters.value,
                };

                console.log(
                    "📤 Calling orderReturnApi.getList with params:",
                    params
                );
                const response = await orderReturnApi.getList(params);
                console.log("📥 API response:", response);

                if (response && response.success) {
                    console.log("✅ Response successful, data:", response.data);
                    orderReturns.value = response.data || [];
                    pagination.value = response.pagination || {
                        current_page: 1,
                        last_page: 1,
                        per_page: 20,
                        total: 0,
                        from: 0,
                        to: 0,
                    };

                    // Calculate stats
                    calculateStats();
                    console.log("📊 Stats calculated:", stats.value);
                } else {
                    console.warn("⚠️ Invalid response from API:", response);
                    orderReturns.value = [];
                    error.value = true;
                }
            } catch (err) {
                console.error("❌ Error loading order returns:", err);
                error.value = true;
                // Set empty data on error
                orderReturns.value = [];
                pagination.value = {
                    current_page: 1,
                    last_page: 1,
                    per_page: 20,
                    total: 0,
                    from: 0,
                    to: 0,
                };
            } finally {
                loading.value = false;
                console.log(
                    "🏁 loadOrderReturns finished. Loading:",
                    loading.value,
                    "Error:",
                    error.value,
                    "Data count:",
                    orderReturns.value.length
                );
            }
        };

        const calculateStats = () => {
            stats.value = {
                pending: 0,
                approved: 0,
                received: 0,
                warehoused: 0,
                refunded: 0,
                cancelled: 0,
                total_amount: 0,
            };

            if (Array.isArray(orderReturns.value)) {
                orderReturns.value.forEach((orderReturn) => {
                    if (
                        orderReturn.status &&
                        stats.value.hasOwnProperty(orderReturn.status)
                    ) {
                        stats.value[orderReturn.status]++;
                    }
                    stats.value.total_amount += parseFloat(
                        orderReturn.total || 0
                    );
                });
            }
        };

        const applyFilters = () => {
            pagination.value.current_page = 1;
            loadOrderReturns();
        };

        const debouncedSearch = debounce(() => {
            applyFilters();
        }, 300);

        const resetFilters = () => {
            searchQuery.value = "";
            filters.value = {
                status: "",
                date_from: "",
                date_to: "",
            };
            applyFilters();
        };

        const changePage = (page) => {
            pagination.value.current_page = page;
            loadOrderReturns();
        };

        const viewDetail = (orderReturn) => {
            selectedReturn.value = orderReturn;
        };

        const approveReturn = async (orderReturn) => {
            if (!confirm("Bạn có chắc chắn muốn duyệt đơn trả hàng này?")) {
                return;
            }

            try {
                const response = await orderReturnApi.approve(orderReturn.id);
                if (response.success) {
                    alert("Duyệt đơn trả hàng thành công!");
                    loadOrderReturns();
                }
            } catch (error) {
                alert("Có lỗi xảy ra khi duyệt đơn trả hàng");
            }
        };

        const receiveReturn = async (orderReturn) => {
            if (!confirm("Bạn có chắc chắn muốn nhận hàng trả về này?")) {
                return;
            }

            try {
                const response = await orderReturnApi.receive(orderReturn.id);
                if (response.success) {
                    alert("Nhận hàng thành công!");
                    loadOrderReturns();
                }
            } catch (error) {
                alert("Có lỗi xảy ra khi nhận hàng");
            }
        };

        const warehouseReturn = async (orderReturn) => {
            if (!confirm("Bạn có chắc chắn muốn nhập kho hàng trả về này?")) {
                return;
            }

            try {
                const response = await orderReturnApi.warehouse(orderReturn.id);
                if (response.success) {
                    alert("Nhập kho thành công!");
                    loadOrderReturns();
                }
            } catch (error) {
                alert("Có lỗi xảy ra khi nhập kho");
            }
        };

        const showRefundModal = (orderReturn) => {
            refundTarget.value = orderReturn;
            refundForm.value.amount = getRemainingRefund(orderReturn);
            refundForm.value.note = "";
            showRefundForm.value = true;
        };

        const closeRefundModal = () => {
            showRefundForm.value = false;
            refundTarget.value = null;
            refundForm.value = { amount: 0, note: "" };
        };

        const processRefund = async () => {
            if (!refundTarget.value || !refundForm.value.amount) {
                return;
            }

            refundLoading.value = true;
            try {
                const response = await orderReturnApi.refund(
                    refundTarget.value.id,
                    refundForm.value
                );
                if (response.success) {
                    alert("Hoàn tiền thành công!");
                    closeRefundModal();
                    loadOrderReturns();
                }
            } catch (error) {
                alert("Có lỗi xảy ra khi hoàn tiền");
            } finally {
                refundLoading.value = false;
            }
        };

        const cancelReturn = async (orderReturn) => {
            if (!confirm("Bạn có chắc chắn muốn hủy đơn trả hàng này?")) {
                return;
            }

            try {
                const response = await orderReturnApi.cancel(orderReturn.id);
                if (response.success) {
                    alert("Hủy đơn trả hàng thành công!");
                    loadOrderReturns();
                }
            } catch (error) {
                alert("Có lỗi xảy ra khi hủy đơn trả hàng");
            }
        };

        const handleReturnUpdated = () => {
            loadOrderReturns();
            selectedReturn.value = null;
        };

        const exportData = () => {
            alert("Chức năng xuất Excel sẽ được triển khai sau");
        };

        // Helper methods
        const getStatusClass = orderReturnHelpers.getStatusColor;
        const getStatusText = orderReturnHelpers.getStatusText;
        const canReceive = orderReturnHelpers.canReceive;
        const canWarehouse = orderReturnHelpers.canWarehouse;
        const canRefund = orderReturnHelpers.canRefund;
        const canCancel = orderReturnHelpers.canCancel;
        const getRemainingRefund = orderReturnHelpers.getRemainingRefund;
        const formatCurrency = orderReturnHelpers.formatCurrency;
        const formatDate = orderReturnHelpers.formatDate;

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
            console.log("🎯 OrderReturnsList onMounted called");
            loadOrderReturns();
        });

        return {
            loading,
            error,
            orderReturns,
            selectedReturn,
            searchQuery,
            stats,
            filters,
            pagination,
            showRefundForm,
            refundTarget,
            refundLoading,
            refundForm,
            visiblePages,
            loadOrderReturns,
            applyFilters,
            debouncedSearch,
            resetFilters,
            changePage,
            viewDetail,
            receiveReturn,
            warehouseReturn,
            showRefundModal,
            closeRefundModal,
            processRefund,
            cancelReturn,
            handleReturnUpdated,
            exportData,
            getStatusClass,
            getStatusText,
            canReceive,
            canWarehouse,
            canRefund,
            canCancel,
            getRemainingRefund,
            formatCurrency,
            formatDate,
        };
    },
};
</script>
