<template>
    <div class="bg-white">
        <!-- Header -->
        <div class="p-6 border-b">
            <h1 class="text-2xl font-semibold text-gray-900">
                Quản lý vận chuyển
            </h1>
        </div>

        <!-- Shipping Statistics -->
        <div class="bg-white rounded-lg shadow border p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-900">
                    📦 Chi phí vận chuyển tháng này
                </h2>
                <div class="flex items-center space-x-2">
                    <select
                        v-model="statsFilter.month"
                        @change="loadStats"
                        class="text-sm border border-gray-300 rounded-md px-2 py-1"
                    >
                        <option
                            v-for="month in months"
                            :key="month.value"
                            :value="month.value"
                        >
                            {{ month.label }}
                        </option>
                    </select>
                </div>
            </div>

            <div v-if="statsLoading" class="text-center py-4">
                <div
                    class="inline-block animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600"
                ></div>
                <span class="ml-2 text-gray-600">Đang tải thống kê...</span>
            </div>

            <div
                v-else-if="shippingStats"
                class="grid grid-cols-1 md:grid-cols-4 gap-4"
            >
                <!-- Total Cost -->
                <div class="bg-blue-50 rounded-lg p-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div
                                class="w-8 h-8 bg-blue-500 rounded-lg flex items-center justify-center"
                            >
                                <span class="text-white text-sm font-bold"
                                    >💰</span
                                >
                            </div>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-blue-600">
                                Tổng chi phí
                            </p>
                            <p class="text-lg font-bold text-blue-900">
                                {{
                                    formatCurrency(
                                        shippingStats.total?.total_cost || 0
                                    )
                                }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Total Orders -->
                <div class="bg-green-50 rounded-lg p-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div
                                class="w-8 h-8 bg-green-500 rounded-lg flex items-center justify-center"
                            >
                                <span class="text-white text-sm font-bold"
                                    >📋</span
                                >
                            </div>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-green-600">
                                Tổng đơn
                            </p>
                            <p class="text-lg font-bold text-green-900">
                                {{ shippingStats.total?.total_orders || 0 }} đơn
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Delivered -->
                <div class="bg-purple-50 rounded-lg p-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div
                                class="w-8 h-8 bg-purple-500 rounded-lg flex items-center justify-center"
                            >
                                <span class="text-white text-sm font-bold"
                                    >✅</span
                                >
                            </div>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-purple-600">
                                Đã giao
                            </p>
                            <p class="text-lg font-bold text-purple-900">
                                {{
                                    shippingStats.total?.delivered_orders || 0
                                }}
                                đơn
                            </p>
                        </div>
                    </div>
                </div>

                <!-- In Progress -->
                <div class="bg-orange-50 rounded-lg p-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div
                                class="w-8 h-8 bg-orange-500 rounded-lg flex items-center justify-center"
                            >
                                <span class="text-white text-sm font-bold"
                                    >🚚</span
                                >
                            </div>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-orange-600">
                                Đang giao
                            </p>
                            <p class="text-lg font-bold text-orange-900">
                                {{
                                    shippingStats.total?.in_progress_orders || 0
                                }}
                                đơn
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Provider Breakdown -->
            <div v-if="shippingStats?.by_provider?.length > 0" class="mt-6">
                <h3 class="text-md font-medium text-gray-900 mb-3">
                    Chi phí theo đơn vị vận chuyển
                </h3>
                <div
                    class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3"
                >
                    <div
                        v-for="provider in shippingStats.by_provider"
                        :key="provider.provider_name"
                        class="bg-gray-50 rounded-lg p-3"
                    >
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-gray-900">
                                {{ provider.provider_name }}
                            </p>
                            <span
                                class="text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded"
                                >{{ provider.order_count }} đơn</span
                            >
                        </div>
                        <p class="text-lg font-bold text-gray-700 mt-1">
                            {{ formatCurrency(provider.total_cost) }}
                        </p>
                        <p class="text-xs text-gray-500">
                            Tỷ lệ giao: {{ provider.delivery_rate }}%
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="p-6 border-b bg-gray-50">
            <div class="grid grid-cols-12 gap-4 items-center">
                <!-- Search -->
                <div class="col-span-4">
                    <div class="relative">
                        <input
                            type="text"
                            placeholder="Tìm theo mã vận đơn, mã đơn hàng, tên khách hàng..."
                            class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md"
                            v-model="searchQuery"
                            @input="debouncedSearch"
                        />
                        <span
                            class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"
                            >🔍</span
                        >
                    </div>
                </div>

                <!-- Status Filter -->
                <div class="col-span-2">
                    <select
                        class="w-full px-3 py-2 border border-gray-300 rounded-md"
                        v-model="filters.status"
                        @change="applyFilters"
                    >
                        <option value="">Tất cả trạng thái</option>
                        <option value="pending">Chờ lấy hàng</option>
                        <option value="picked_up">Đã lấy hàng</option>
                        <option value="in_transit">Đang vận chuyển</option>
                        <option value="delivered">Đã giao hàng</option>
                        <option value="failed">Giao hàng thất bại</option>
                    </select>
                </div>

                <!-- Provider Filter -->
                <div class="col-span-2">
                    <select
                        class="w-full px-3 py-2 border border-gray-300 rounded-md"
                        v-model="filters.provider_id"
                        @change="applyFilters"
                    >
                        <option value="">Tất cả đơn vị</option>
                        <option
                            v-for="provider in providers"
                            :key="provider.id"
                            :value="provider.id"
                        >
                            {{ provider.name }}
                        </option>
                    </select>
                </div>

                <!-- Date Filter -->
                <div class="col-span-2">
                    <select
                        class="w-full px-3 py-2 border border-gray-300 rounded-md"
                        v-model="filters.date_range"
                        @change="applyFilters"
                    >
                        <option value="7">7 ngày gần nhất</option>
                        <option value="30">30 ngày gần nhất</option>
                        <option value="90">90 ngày gần nhất</option>
                    </select>
                </div>

                <!-- Reset -->
                <div class="col-span-2">
                    <button
                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-gray-600 hover:bg-gray-50"
                        @click="resetFilters"
                    >
                        Đặt lại
                    </button>
                </div>
            </div>
        </div>

        <!-- Stats -->
        <div class="p-6 border-b">
            <div class="grid grid-cols-5 gap-4">
                <div class="text-center">
                    <div class="text-2xl font-bold text-yellow-600">
                        {{ stats.pending || 0 }}
                    </div>
                    <div class="text-sm text-gray-600">Chờ lấy hàng</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-blue-600">
                        {{ stats.picked_up || 0 }}
                    </div>
                    <div class="text-sm text-gray-600">Đã lấy hàng</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-purple-600">
                        {{ stats.in_transit || 0 }}
                    </div>
                    <div class="text-sm text-gray-600">Đang vận chuyển</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-green-600">
                        {{ stats.delivered || 0 }}
                    </div>
                    <div class="text-sm text-gray-600">Đã giao hàng</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-red-600">
                        {{ stats.failed || 0 }}
                    </div>
                    <div class="text-sm text-gray-600">Giao hàng thất bại</div>
                </div>
            </div>
        </div>

        <!-- Bulk Actions -->
        <div
            v-if="selectedShippings.length > 0"
            class="p-4 bg-blue-50 border-b flex items-center justify-between"
        >
            <span class="text-sm font-medium text-blue-900">
                Đã chọn {{ selectedShippings.length }} vận đơn
            </span>
            <div class="flex space-x-2">
                <button
                    @click="printSelectedShippings"
                    class="px-3 py-1 bg-green-600 text-white text-sm rounded hover:bg-green-700"
                >
                    In nhiều vận đơn
                </button>
                <button
                    @click="selectedShippings = []"
                    class="px-3 py-1 bg-gray-600 text-white text-sm rounded hover:bg-gray-700"
                >
                    Bỏ chọn
                </button>
            </div>
        </div>

        <!-- Loading State -->
        <div
            v-if="loading && shippings.length === 0"
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
                            <input
                                type="checkbox"
                                @change="toggleAllShippings"
                                :checked="
                                    selectedShippings.length ===
                                        shippings.length && shippings.length > 0
                                "
                                class="rounded border-gray-300"
                            />
                        </th>
                        <th
                            class="text-left p-4 text-sm font-medium text-gray-600"
                        >
                            Mã vận đơn
                        </th>
                        <th
                            class="text-left p-4 text-sm font-medium text-gray-600"
                        >
                            Đơn hàng
                        </th>
                        <th
                            class="text-left p-4 text-sm font-medium text-gray-600"
                        >
                            Khách hàng
                        </th>
                        <th
                            class="text-left p-4 text-sm font-medium text-gray-600"
                        >
                            Đơn vị vận chuyển
                        </th>
                        <th
                            class="text-left p-4 text-sm font-medium text-gray-600"
                        >
                            Người nhận
                        </th>
                        <th
                            class="text-left p-4 text-sm font-medium text-gray-600"
                        >
                            Trạng thái
                        </th>
                        <th
                            class="text-left p-4 text-sm font-medium text-gray-600"
                        >
                            Ngày tạo
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
                        v-for="shipping in shippings"
                        :key="shipping.id"
                        class="hover:bg-gray-50"
                    >
                        <td class="p-4">
                            <input
                                type="checkbox"
                                :value="shipping.id"
                                v-model="selectedShippings"
                                class="rounded border-gray-300"
                            />
                        </td>
                        <td class="p-4">
                            <div class="text-blue-500 font-medium">
                                {{ shipping.tracking_number || "—" }}
                            </div>
                        </td>
                        <td class="p-4">
                            <div class="font-medium">
                                {{ shipping.order?.code }}
                            </div>
                            <div class="text-sm text-gray-500">
                                {{ formatCurrency(shipping.order?.total) }}
                            </div>
                        </td>
                        <td class="p-4">
                            <div>{{ shipping.order?.customer?.name }}</div>
                            <div class="text-sm text-gray-500">
                                {{ shipping.order?.customer?.phone }}
                            </div>
                        </td>
                        <td class="p-4">
                            <div>
                                {{
                                    shipping.provider?.name || shipping.carrier
                                }}
                            </div>
                            <div class="text-sm text-gray-500">
                                {{ shipping.shipping_method }}
                            </div>
                        </td>
                        <td class="p-4">
                            <div>
                                {{
                                    shipping.delivery_contact ||
                                    shipping.order?.customer?.name ||
                                    "—"
                                }}
                            </div>
                            <div class="text-sm text-gray-500">
                                {{
                                    shipping.delivery_phone ||
                                    shipping.order?.customer?.phone ||
                                    ""
                                }}
                            </div>
                        </td>
                        <td class="p-4">
                            <span
                                class="px-2 py-1 rounded-full text-xs font-medium"
                                :class="getStatusClass(shipping.status)"
                            >
                                {{ getStatusText(shipping.status) }}
                            </span>
                        </td>
                        <td class="p-4 text-sm text-gray-600">
                            {{ formatDate(shipping.created_at) }}
                        </td>
                        <td class="p-4">
                            <div class="flex space-x-2">
                                <button
                                    @click="viewDetail(shipping)"
                                    class="text-blue-600 hover:text-blue-800 text-sm font-medium"
                                >
                                    Xem
                                </button>
                                <button
                                    @click="printShipping(shipping)"
                                    class="text-green-600 hover:text-green-800 text-sm font-medium"
                                >
                                    In
                                </button>
                            </div>
                        </td>
                    </tr>

                    <!-- Empty State -->
                    <tr v-if="shippings.length === 0 && !loading">
                        <td colspan="9" class="text-center py-12 text-gray-500">
                            <div class="text-4xl mb-4">📦</div>
                            <div>Không có đơn vận chuyển nào</div>
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
                {{ pagination.total }} đơn vận chuyển
            </div>
            <div class="flex space-x-2">
                <button
                    class="px-3 py-1 border rounded text-sm"
                    :disabled="pagination.current_page <= 1"
                    @click="changePage(pagination.current_page - 1)"
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
                        @click="changePage(page)"
                    >
                        {{ page }}
                    </button>
                </template>

                <button
                    class="px-3 py-1 border rounded text-sm"
                    :disabled="pagination.current_page >= pagination.last_page"
                    @click="changePage(pagination.current_page + 1)"
                >
                    Tiếp
                </button>
            </div>
        </div>

        <!-- Detail Modal -->
        <div
            v-if="selectedShipping"
            class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4"
        >
            <div
                class="bg-white rounded-lg w-full max-w-4xl max-h-screen overflow-y-auto"
            >
                <div class="flex items-center justify-between p-6 border-b">
                    <h3 class="text-lg font-semibold">Chi tiết vận chuyển</h3>
                    <button
                        @click="selectedShipping = null"
                        class="text-gray-400 hover:text-gray-600"
                    >
                        ×
                    </button>
                </div>

                <div class="p-6">
                    <ShippingTracking
                        :order-id="selectedShipping.order_id"
                        :can-update="true"
                        @updated="loadShippings"
                    />
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, computed, onMounted } from "vue";
import { shippingApi } from "../api/shippingApi";
import ShippingTracking from "../components/ShippingTracking.vue";

export default {
    name: "ShippingList",
    components: {
        ShippingTracking,
    },
    setup() {
        const loading = ref(false);
        const statsLoading = ref(false);
        const shippingStats = ref(null);
        const statsFilter = ref({
            month: new Date().toISOString().slice(0, 7), // Current month YYYY-MM
        });

        const shippings = ref([]);
        const providers = ref([]);
        const selectedShipping = ref(null);
        const searchQuery = ref("");
        const selectedShippings = ref([]);

        const stats = ref({
            pending: 0,
            picked_up: 0,
            in_transit: 0,
            delivered: 0,
            failed: 0,
        });

        const filters = ref({
            status: "",
            provider_id: "",
            date_range: "30",
        });

        const pagination = ref({
            current_page: 1,
            last_page: 1,
            per_page: 20,
            total: 0,
            from: 0,
            to: 0,
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

        const months = computed(() => {
            const currentDate = new Date();
            const months = [];

            for (let i = 5; i >= 0; i--) {
                const date = new Date(
                    currentDate.getFullYear(),
                    currentDate.getMonth() - i,
                    1
                );
                months.push({
                    value: date.toISOString().slice(0, 7),
                    label: `Tháng ${date.getMonth() + 1}/${date.getFullYear()}`,
                });
            }

            return months;
        });

        const loadShippings = async () => {
            loading.value = true;

            try {
                const params = {
                    search: searchQuery.value,
                    status: filters.value.status,
                    provider_id: filters.value.provider_id,
                    date_range: filters.value.date_range,
                    page: pagination.value.current_page,
                };

                const response = await shippingApi.getAll(params);

                if (response.success) {
                    shippings.value = response.data;
                    pagination.value = response.pagination;

                    // Update stats from current data
                    stats.value = {
                        pending: response.data.filter(
                            (s) => s.status === "pending"
                        ).length,
                        picked_up: response.data.filter(
                            (s) => s.status === "picked_up"
                        ).length,
                        in_transit: response.data.filter(
                            (s) => s.status === "in_transit"
                        ).length,
                        delivered: response.data.filter(
                            (s) => s.status === "delivered"
                        ).length,
                        failed: response.data.filter(
                            (s) => s.status === "failed"
                        ).length,
                    };
                }
            } catch (error) {
                console.error("Error loading shippings:", error);
            } finally {
                loading.value = false;
            }
        };

        const loadProviders = async () => {
            try {
                const response = await shippingApi.getProviders();
                if (response.success) {
                    providers.value = response.data;
                }
            } catch (error) {
                console.error("Error loading providers:", error);
            }
        };

        const loadStats = async () => {
            statsLoading.value = true;
            try {
                const [year, month] = statsFilter.value.month.split("-");
                const startDate = `${year}-${month}-01`;
                const endDate = new Date(year, month, 0)
                    .toISOString()
                    .slice(0, 10); // Last day of month

                const response = await shippingApi.getStats({
                    start_date: startDate,
                    end_date: endDate,
                });

                if (response.success) {
                    shippingStats.value = response.data;
                }
            } catch (error) {
                console.error("Error loading stats:", error);
            } finally {
                statsLoading.value = false;
            }
        };

        const applyFilters = () => {
            pagination.value.current_page = 1;
            loadShippings();
        };

        const debouncedSearch = debounce(() => {
            applyFilters();
        }, 300);

        const resetFilters = () => {
            searchQuery.value = "";
            filters.value = {
                status: "",
                provider_id: "",
                date_range: "30",
            };
            applyFilters();
        };

        const changePage = (page) => {
            pagination.value.current_page = page;
            loadShippings();
        };

        const viewDetail = (shipping) => {
            selectedShipping.value = shipping;
        };

        // Toggle all shippings selection
        const toggleAllShippings = (event) => {
            if (event.target.checked) {
                selectedShippings.value = shippings.value.map((s) => s.id);
            } else {
                selectedShippings.value = [];
            }
        };

        // Print shipping
        const printShipping = (shipping) => {
            const printUrl = `/api/shipping/${shipping.id}/print`;
            window.open(printUrl, "_blank");
        };

        // Bulk print function
        const printSelectedShippings = () => {
            if (selectedShippings.value.length === 0) {
                alert("Vui lòng chọn ít nhất một vận đơn để in");
                return;
            }

            // Create form and submit
            const form = document.createElement("form");
            form.method = "POST";
            form.action = "/api/shipping/print-bulk";
            form.target = "_blank";

            // Add CSRF token
            const csrfInput = document.createElement("input");
            csrfInput.type = "hidden";
            csrfInput.name = "_token";
            csrfInput.value =
                document
                    .querySelector('meta[name="csrf-token"]')
                    ?.getAttribute("content") || "";
            form.appendChild(csrfInput);

            // Add shipping IDs
            selectedShippings.value.forEach((id) => {
                const input = document.createElement("input");
                input.type = "hidden";
                input.name = "shipping_ids[]";
                input.value = id;
                form.appendChild(input);
            });

            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);

            // Clear selection
            selectedShippings.value = [];
        };

        const getStatusClass = (status) => {
            const classMap = {
                pending: "bg-yellow-100 text-yellow-800",
                picked_up: "bg-blue-100 text-blue-800",
                in_transit: "bg-purple-100 text-purple-800",
                delivered: "bg-green-100 text-green-800",
                failed: "bg-red-100 text-red-800",
            };
            return classMap[status] || "bg-gray-100 text-gray-800";
        };

        const getStatusText = (status) => {
            const statusMap = {
                pending: "Chờ lấy hàng",
                picked_up: "Đã lấy hàng",
                in_transit: "Đang vận chuyển",
                delivered: "Đã giao hàng",
                failed: "Giao hàng thất bại",
            };
            return statusMap[status] || status;
        };

        const formatDate = (dateString) => {
            return new Date(dateString).toLocaleString("vi-VN");
        };

        const formatCurrency = (amount) => {
            return new Intl.NumberFormat("vi-VN", {
                style: "currency",
                currency: "VND",
            }).format(amount);
        };

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

        onMounted(async () => {
            await Promise.all([loadShippings(), loadProviders(), loadStats()]);
        });

        return {
            loading,
            shippings,
            providers,
            selectedShipping,
            searchQuery,
            selectedShippings,
            stats,
            filters,
            pagination,
            visiblePages,
            statsLoading,
            shippingStats,
            statsFilter,
            months,
            loadShippings,
            loadStats,
            applyFilters,
            debouncedSearch,
            resetFilters,
            changePage,
            viewDetail,
            toggleAllShippings,
            printShipping,
            printSelectedShippings,
            getStatusClass,
            getStatusText,
            formatDate,
            formatCurrency,
        };
    },
};
</script>
