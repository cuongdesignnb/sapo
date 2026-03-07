<template>
    <div class="container mx-auto px-4 py-6">
        <!-- Header -->
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">
                    Quản lý đơn hàng
                </h1>
                <p class="text-gray-600 mt-1">
                    Quy trình mới 5 bước đơn giản hóa
                </p>
            </div>
            <div class="flex space-x-3">
                <button
                    @click="exportOrders"
                    class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50"
                >
                    <i class="fas fa-download mr-2"></i>
                    Xuất Excel
                </button>
                <a
                    href="/orders/create"
                    class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 inline-flex items-center"
                >
                    <i class="fas fa-plus mr-2"></i>
                    Tạo đơn hàng
                </a>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow-sm border p-4">
                <div class="flex items-center">
                    <div
                        class="w-10 h-10 bg-yellow-100 rounded-full flex items-center justify-center"
                    >
                        <i class="fas fa-shopping-cart text-yellow-600"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-gray-600">Đặt hàng</p>
                        <p class="text-lg font-semibold">
                            {{ stats.ordered || 0 }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm border p-4">
                <div class="flex items-center">
                    <div
                        class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center"
                    >
                        <i class="fas fa-check-circle text-blue-600"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-gray-600">Đã duyệt</p>
                        <p class="text-lg font-semibold">
                            {{ stats.approved || 0 }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm border p-4">
                <div class="flex items-center">
                    <div
                        class="w-10 h-10 bg-purple-100 rounded-full flex items-center justify-center"
                    >
                        <i class="fas fa-truck text-purple-600"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-gray-600">Vận chuyển</p>
                        <p class="text-lg font-semibold">
                            {{ stats.shipping_created || 0 }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm border p-4">
                <div class="flex items-center">
                    <div
                        class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center"
                    >
                        <i class="fas fa-box text-green-600"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-gray-600">Đã giao</p>
                        <p class="text-lg font-semibold">
                            {{ stats.delivered || 0 }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm border p-4">
                <div class="flex items-center">
                    <div
                        class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center"
                    >
                        <i class="fas fa-check-double text-green-600"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-gray-600">Hoàn thành</p>
                        <p class="text-lg font-semibold">
                            {{ stats.completed || 0 }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow-sm border p-4 mb-6">
            <div class="flex flex-col md:flex-row md:items-end gap-4">
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-1"
                        >Tìm kiếm</label
                    >
                    <input
                        v-model="filters.search"
                        @input="searchOrders"
                        type="text"
                        placeholder="Mã đơn, khách hàng, SĐT..."
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                </div>

                <div class="flex gap-3">
                    <button
                        type="button"
                        @click="openFilterDrawer"
                        class="px-4 py-2 border border-gray-300 rounded-md hover:bg-gray-50 inline-flex items-center"
                        title="Bộ lọc"
                    >
                        <FunnelIcon class="h-5 w-5 mr-2 text-gray-600" />
                        Bộ lọc
                    </button>
                    <button
                        type="button"
                        @click="resetFilters"
                        class="px-4 py-2 border border-gray-300 rounded-md hover:bg-gray-50"
                        title="Reset"
                    >
                        <i class="fas fa-undo mr-2"></i>
                        Reset
                    </button>
                </div>
            </div>
        </div>

        <!-- Orders Table -->
        <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex justify-between items-center">
                    <h3 class="text-lg font-medium">Danh sách đơn hàng</h3>
                    <span class="text-sm text-gray-500">
                        {{ pagination.total || 0 }} đơn hàng
                    </span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                            >
                                Mã đơn hàng
                            </th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                            >
                                Khách hàng
                            </th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                            >
                                Trạng thái & Tiến trình
                            </th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                            >
                                Tổng tiền
                            </th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                            >
                                Thanh toán
                            </th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                            >
                                Ngày tạo
                            </th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                            >
                                Hành động
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-if="loading">
                            <td
                                colspan="7"
                                class="px-6 py-4 text-center text-gray-500"
                            >
                                <i class="fas fa-spinner fa-spin mr-2"></i>
                                Đang tải...
                            </td>
                        </tr>
                        <tr v-else-if="orders.length === 0">
                            <td
                                colspan="7"
                                class="px-6 py-4 text-center text-gray-500"
                            >
                                Không có đơn hàng nào
                            </td>
                        </tr>
                        <tr
                            v-else
                            v-for="order in orders"
                            :key="order.id"
                            class="hover:bg-gray-50 cursor-pointer"
                            @dblclick="goToOrderDetail(order)"
                        >
                            <!-- Mã đơn hàng -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div>
                                        <a
                                            class="text-sm font-medium text-blue-600 hover:underline"
                                            :href="`/orders/${order.id}`"
                                            title="Bấm để mở trang chi tiết"
                                        >
                                            {{
                                                order.code ||
                                                order.order_code ||
                                                order.invoice_code ||
                                                `#${order.id}`
                                            }}
                                        </a>
                                        <div class="text-sm text-gray-500">
                                            {{
                                                order.warehouse?.name ||
                                                order.warehouse_name ||
                                                "N/A"
                                            }}
                                        </div>
                                    </div>
                                    <button
                                        class="ml-3 text-xs text-gray-500 hover:text-blue-600"
                                        title="Mở trang chi tiết"
                                        @click.stop="goToOrderDetail(order)"
                                    >
                                        ↗
                                    </button>
                                </div>
                            </td>

                            <!-- Khách hàng -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div>
                                    <div
                                        class="text-sm font-medium text-gray-900"
                                    >
                                        {{ order.customer?.name }}
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        {{ order.customer?.phone }}
                                    </div>
                                </div>
                            </td>

                            <!-- Trạng thái & Tiến trình -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex flex-col space-y-2">
                                    <span
                                        :class="getStatusClass(order.status)"
                                        class="inline-flex px-2 py-1 text-xs font-medium rounded-full"
                                    >
                                        {{ getStatusText(order.status) }}
                                    </span>
                                    <!-- Progress bar -->
                                    <div
                                        class="w-full bg-gray-200 rounded-full h-1.5"
                                    >
                                        <div
                                            class="bg-blue-600 h-1.5 rounded-full transition-all duration-300"
                                            :style="{
                                                width:
                                                    getProgressPercentage(
                                                        order.status
                                                    ) + '%',
                                            }"
                                        ></div>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        {{ getProgressText(order.status) }}
                                    </div>
                                </div>
                            </td>

                            <!-- Tổng tiền -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">
                                    {{ formatCurrency(order.total) }}
                                </div>
                            </td>

                            <!-- Thanh toán -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex flex-col">
                                    <span class="text-sm text-green-600">{{
                                        formatCurrency(order.paid)
                                    }}</span>
                                    <span
                                        v-if="order.debt > 0"
                                        class="text-sm text-red-600"
                                    >
                                        Nợ: {{ formatCurrency(order.debt) }}
                                    </span>
                                </div>
                            </td>

                            <!-- Ngày tạo -->
                            <td
                                class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"
                            >
                                {{ formatDateTime(order.created_at) }}
                            </td>

                            <!-- Hành động -->
                            <td
                                class="px-6 py-4 whitespace-nowrap text-sm font-medium"
                            >
                                <div class="flex space-x-2">
                                    <button
                                        @click="goToOrderDetail(order)"
                                        class="text-gray-600 hover:text-gray-900"
                                        title="Mở trang chi tiết"
                                    >
                                        <i class="fas fa-external-link-alt"></i>
                                    </button>
                                    <button
                                        v-if="order.status === 'ordered'"
                                        @click="approveOrder(order)"
                                        class="text-green-600 hover:text-green-900"
                                        title="Duyệt đơn hàng"
                                    >
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <button
                                        v-if="order.status === 'approved'"
                                        @click="createShipping(order)"
                                        class="text-purple-600 hover:text-purple-900"
                                        title="Tạo vận chuyển (tự giao hàng)"
                                    >
                                        <i class="fas fa-truck"></i>
                                    </button>
                                    <button
                                        v-if="
                                            order.status === 'shipping_created'
                                        "
                                        @click="exportStock(order)"
                                        class="text-orange-600 hover:text-orange-900"
                                        title="Xuất kho"
                                    >
                                        <i class="fas fa-box"></i>
                                    </button>
                                    <button
                                        v-if="
                                            order.status === 'delivered' &&
                                            order.debt > 0
                                        "
                                        @click="makePayment(order)"
                                        class="text-green-600 hover:text-green-900"
                                        title="Thanh toán"
                                    >
                                        <i class="fas fa-credit-card"></i>
                                    </button>
                                    <button
                                        @click="printOrder(order)"
                                        class="text-gray-600 hover:text-gray-900"
                                        title="In đơn hàng"
                                    >
                                        <i class="fas fa-print"></i>
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
                class="px-6 py-4 border-t border-gray-200"
            >
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-700">
                        Hiển thị {{ pagination.from }} - {{ pagination.to }} của
                        {{ pagination.total }} kết quả
                    </div>
                    <div class="flex space-x-2">
                        <button
                            @click="loadOrders(pagination.current_page - 1)"
                            :disabled="pagination.current_page <= 1"
                            class="px-3 py-1 border border-gray-300 rounded disabled:opacity-50"
                        >
                            Trước
                        </button>
                        <span class="px-3 py-1 text-sm">
                            {{ pagination.current_page }} /
                            {{ pagination.last_page }}
                        </span>
                        <button
                            @click="loadOrders(pagination.current_page + 1)"
                            :disabled="
                                pagination.current_page >= pagination.last_page
                            "
                            class="px-3 py-1 border border-gray-300 rounded disabled:opacity-50"
                        >
                            Sau
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- No popups: all actions are inline -->

        <!-- Filter Drawer (slide-over from right) -->
        <TransitionRoot as="template" :show="isFilterDrawerOpen">
            <Dialog as="div" class="relative z-50" @close="closeFilterDrawer">
                <TransitionChild
                    as="template"
                    enter="ease-in-out duration-300"
                    enter-from="opacity-0"
                    enter-to="opacity-100"
                    leave="ease-in-out duration-300"
                    leave-from="opacity-100"
                    leave-to="opacity-0"
                >
                    <div class="fixed inset-0 bg-gray-500/50" />
                </TransitionChild>

                <div class="fixed inset-0 overflow-hidden">
                    <div class="absolute inset-0 overflow-hidden">
                        <div
                            class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10"
                        >
                            <TransitionChild
                                as="template"
                                enter="transform transition ease-in-out duration-300"
                                enter-from="translate-x-full"
                                enter-to="translate-x-0"
                                leave="transform transition ease-in-out duration-300"
                                leave-from="translate-x-0"
                                leave-to="translate-x-full"
                            >
                                <DialogPanel
                                    class="pointer-events-auto w-screen max-w-md"
                                >
                                    <div
                                        class="flex h-full flex-col bg-white border-l border-gray-200"
                                    >
                                        <div
                                            class="flex items-center justify-between px-4 py-4 border-b"
                                        >
                                            <DialogTitle
                                                class="text-base font-semibold text-gray-900"
                                                >Bộ lọc</DialogTitle
                                            >
                                            <button
                                                type="button"
                                                class="rounded-md p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-50"
                                                @click="closeFilterDrawer"
                                                title="Đóng"
                                            >
                                                <XMarkIcon class="h-5 w-5" />
                                            </button>
                                        </div>

                                        <div
                                            class="flex-1 overflow-y-auto px-4 py-4 space-y-4"
                                        >
                                            <div
                                                v-if="filterDataLoading"
                                                class="text-sm text-gray-500"
                                            >
                                                Đang tải dữ liệu bộ lọc...
                                            </div>

                                            <div>
                                                <label
                                                    class="block text-sm font-medium text-gray-700 mb-1"
                                                    >Chi nhánh / Kho tạo
                                                    đơn</label
                                                >
                                                <select
                                                    v-model="
                                                        drawerFilters.warehouse_id
                                                    "
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                                >
                                                    <option value="">
                                                        Tất cả kho
                                                    </option>
                                                    <option
                                                        v-for="warehouse in warehouses"
                                                        :key="warehouse.id"
                                                        :value="warehouse.id"
                                                    >
                                                        {{ warehouse.name }}
                                                    </option>
                                                </select>
                                            </div>

                                            <div>
                                                <label
                                                    class="block text-sm font-medium text-gray-700 mb-1"
                                                    >Trạng thái đơn hàng</label
                                                >
                                                <select
                                                    v-model="
                                                        drawerFilters.status
                                                    "
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                                >
                                                    <option value="">
                                                        Tất cả
                                                    </option>
                                                    <option value="ordered">
                                                        Đặt hàng
                                                    </option>
                                                    <option value="approved">
                                                        Đã duyệt
                                                    </option>
                                                    <option
                                                        value="shipping_created"
                                                    >
                                                        Đã tạo vận chuyển
                                                    </option>
                                                    <option value="delivered">
                                                        Đã giao hàng
                                                    </option>
                                                    <option value="completed">
                                                        Hoàn thành
                                                    </option>
                                                    <option value="cancelled">
                                                        Đã hủy
                                                    </option>
                                                </select>
                                            </div>

                                            <div>
                                                <label
                                                    class="block text-sm font-medium text-gray-700 mb-1"
                                                    >Trạng thái thanh
                                                    toán</label
                                                >
                                                <select
                                                    v-model="
                                                        drawerFilters.payment_status
                                                    "
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                                >
                                                    <option value="">
                                                        Tất cả
                                                    </option>
                                                    <option value="unpaid">
                                                        Chưa thanh toán
                                                    </option>
                                                    <option value="partial">
                                                        Thanh toán một phần
                                                    </option>
                                                    <option value="paid">
                                                        Đã thanh toán
                                                    </option>
                                                </select>
                                            </div>

                                            <div>
                                                <label
                                                    class="block text-sm font-medium text-gray-700 mb-1"
                                                    >Trạng thái giao hàng</label
                                                >
                                                <select
                                                    v-model="
                                                        drawerFilters.shipping_status
                                                    "
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                                >
                                                    <option value="">
                                                        Tất cả
                                                    </option>
                                                    <option value="pending">
                                                        Chờ lấy hàng
                                                    </option>
                                                    <option value="picked_up">
                                                        Đã lấy hàng
                                                    </option>
                                                    <option value="in_transit">
                                                        Đang vận chuyển
                                                    </option>
                                                    <option value="delivered">
                                                        Đã giao hàng
                                                    </option>
                                                    <option value="failed">
                                                        Giao hàng thất bại
                                                    </option>
                                                </select>
                                            </div>

                                            <div>
                                                <label
                                                    class="block text-sm font-medium text-gray-700 mb-1"
                                                    >Đối tác vận chuyển</label
                                                >
                                                <select
                                                    v-model="
                                                        drawerFilters.shipping_provider_id
                                                    "
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                                >
                                                    <option value="">
                                                        Tất cả
                                                    </option>
                                                    <option
                                                        v-for="provider in shippingProviders"
                                                        :key="provider.id"
                                                        :value="provider.id"
                                                    >
                                                        {{ provider.name }}
                                                    </option>
                                                </select>
                                            </div>

                                            <div>
                                                <label
                                                    class="block text-sm font-medium text-gray-700 mb-1"
                                                    >Khách hàng</label
                                                >
                                                <select
                                                    v-model="
                                                        drawerFilters.customer_id
                                                    "
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                                >
                                                    <option value="">
                                                        Tất cả
                                                    </option>
                                                    <option
                                                        v-for="c in customers"
                                                        :key="c.id"
                                                        :value="c.id"
                                                    >
                                                        {{ c.name
                                                        }}{{
                                                            c.phone
                                                                ? ` (${c.phone})`
                                                                : ""
                                                        }}
                                                    </option>
                                                </select>
                                            </div>

                                            <div>
                                                <label
                                                    class="block text-sm font-medium text-gray-700 mb-1"
                                                    >Sản phẩm</label
                                                >
                                                <select
                                                    v-model="
                                                        drawerFilters.product_id
                                                    "
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                                >
                                                    <option value="">
                                                        Tất cả
                                                    </option>
                                                    <option
                                                        v-for="p in products"
                                                        :key="p.id"
                                                        :value="p.id"
                                                    >
                                                        {{
                                                            p.sku
                                                                ? `${p.sku} - `
                                                                : ""
                                                        }}{{ p.name }}
                                                    </option>
                                                </select>
                                            </div>

                                            <div>
                                                <label
                                                    class="block text-sm font-medium text-gray-700 mb-1"
                                                    >Nhân viên tạo đơn</label
                                                >
                                                <select
                                                    v-model="
                                                        drawerFilters.created_by
                                                    "
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                                >
                                                    <option value="">
                                                        Tất cả
                                                    </option>
                                                    <option
                                                        v-for="u in users"
                                                        :key="u.id"
                                                        :value="u.id"
                                                    >
                                                        {{ u.name }}
                                                    </option>
                                                </select>
                                            </div>

                                            <div>
                                                <label
                                                    class="block text-sm font-medium text-gray-700 mb-1"
                                                    >Nhân viên phụ trách</label
                                                >
                                                <select
                                                    v-model="
                                                        drawerFilters.cashier_id
                                                    "
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                                >
                                                    <option value="">
                                                        Tất cả
                                                    </option>
                                                    <option
                                                        v-for="u in users"
                                                        :key="u.id"
                                                        :value="u.id"
                                                    >
                                                        {{ u.name }}
                                                    </option>
                                                </select>
                                            </div>

                                            <div>
                                                <label
                                                    class="block text-sm font-medium text-gray-700 mb-1"
                                                    >Từ ngày</label
                                                >
                                                <input
                                                    v-model="
                                                        drawerFilters.date_from
                                                    "
                                                    type="date"
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                                />
                                            </div>

                                            <div>
                                                <label
                                                    class="block text-sm font-medium text-gray-700 mb-1"
                                                    >Đến ngày</label
                                                >
                                                <input
                                                    v-model="
                                                        drawerFilters.date_to
                                                    "
                                                    type="date"
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                                />
                                            </div>
                                        </div>

                                        <div
                                            class="px-4 py-4 border-t flex gap-3"
                                        >
                                            <button
                                                type="button"
                                                class="flex-1 px-4 py-2 border border-gray-300 rounded-md hover:bg-gray-50 text-gray-700"
                                                @click="clearDrawerFilters"
                                            >
                                                Xóa bộ lọc
                                            </button>
                                            <button
                                                type="button"
                                                class="flex-1 px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600"
                                                @click="applyDrawerFilters"
                                            >
                                                Lọc
                                            </button>
                                        </div>
                                    </div>
                                </DialogPanel>
                            </TransitionChild>
                        </div>
                    </div>
                </div>
            </Dialog>
        </TransitionRoot>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from "vue";
import {
    Dialog,
    DialogPanel,
    DialogTitle,
    TransitionChild,
    TransitionRoot,
} from "@headlessui/vue";
import { FunnelIcon, XMarkIcon } from "@heroicons/vue/24/outline";
import { orderApi, workflowHelpers } from "../api/orderApi";

const orders = ref([]);
const stats = ref({});
const warehouses = ref([]);
const users = ref([]);
const customers = ref([]);
const products = ref([]);
const shippingProviders = ref([]);
const filterDataLoaded = ref(false);
const filterDataLoading = ref(false);
const loading = ref(false);
// No popup flow; keep everything inline

const pagination = reactive({
    current_page: 1,
    last_page: 1,
    per_page: 20,
    total: 0,
    from: 0,
    to: 0,
});

const filters = reactive({
    search: "",
    status: "",
    warehouse_id: "",
    customer_id: "",
    product_id: "",
    created_by: "",
    cashier_id: "",
    shipping_provider_id: "",
    shipping_status: "",
    payment_status: "",
    date_from: "",
    date_to: "",
});

const isFilterDrawerOpen = ref(false);

const drawerFilters = reactive({
    status: "",
    warehouse_id: "",
    customer_id: "",
    product_id: "",
    created_by: "",
    cashier_id: "",
    shipping_provider_id: "",
    shipping_status: "",
    payment_status: "",
    date_from: "",
    date_to: "",
});

const ensureFilterDataLoaded = async () => {
    if (filterDataLoaded.value || filterDataLoading.value) return;
    filterDataLoading.value = true;
    try {
        const response = await orderApi.getFilterData({
            limit: 200,
            only_active: 1,
        });
        const data = response?.data || {};
        warehouses.value = data.warehouses || [];
        users.value = data.users || [];
        customers.value = data.customers || [];
        products.value = data.products || [];
        shippingProviders.value = data.shipping_providers || [];
        filterDataLoaded.value = true;
    } catch (error) {
        console.error("❌ Error loading filter data:", error);
        // Keep arrays empty; UI still usable
        warehouses.value = warehouses.value || [];
        users.value = users.value || [];
        customers.value = customers.value || [];
        products.value = products.value || [];
        shippingProviders.value = shippingProviders.value || [];
    } finally {
        filterDataLoading.value = false;
    }
};

const openFilterDrawer = () => {
    drawerFilters.status = filters.status;
    drawerFilters.warehouse_id = filters.warehouse_id;
    drawerFilters.customer_id = filters.customer_id;
    drawerFilters.product_id = filters.product_id;
    drawerFilters.created_by = filters.created_by;
    drawerFilters.cashier_id = filters.cashier_id;
    drawerFilters.shipping_provider_id = filters.shipping_provider_id;
    drawerFilters.shipping_status = filters.shipping_status;
    drawerFilters.payment_status = filters.payment_status;
    drawerFilters.date_from = filters.date_from;
    drawerFilters.date_to = filters.date_to;
    isFilterDrawerOpen.value = true;
    // Load dropdown data on-demand
    ensureFilterDataLoaded();
};

const closeFilterDrawer = () => {
    isFilterDrawerOpen.value = false;
};

const applyDrawerFilters = async () => {
    filters.status = drawerFilters.status;
    filters.warehouse_id = drawerFilters.warehouse_id;
    filters.customer_id = drawerFilters.customer_id;
    filters.product_id = drawerFilters.product_id;
    filters.created_by = drawerFilters.created_by;
    filters.cashier_id = drawerFilters.cashier_id;
    filters.shipping_provider_id = drawerFilters.shipping_provider_id;
    filters.shipping_status = drawerFilters.shipping_status;
    filters.payment_status = drawerFilters.payment_status;
    filters.date_from = drawerFilters.date_from;
    filters.date_to = drawerFilters.date_to;
    await loadOrders(1);
    closeFilterDrawer();
};

const clearDrawerFilters = async () => {
    drawerFilters.status = "";
    drawerFilters.warehouse_id = "";
    drawerFilters.customer_id = "";
    drawerFilters.product_id = "";
    drawerFilters.created_by = "";
    drawerFilters.cashier_id = "";
    drawerFilters.shipping_provider_id = "";
    drawerFilters.shipping_status = "";
    drawerFilters.payment_status = "";
    drawerFilters.date_from = "";
    drawerFilters.date_to = "";

    filters.status = "";
    filters.warehouse_id = "";
    filters.customer_id = "";
    filters.product_id = "";
    filters.created_by = "";
    filters.cashier_id = "";
    filters.shipping_provider_id = "";
    filters.shipping_status = "";
    filters.payment_status = "";
    filters.date_from = "";
    filters.date_to = "";
    await loadOrders(1);
};

// Load orders
const loadOrders = async (page = 1) => {
    loading.value = true;
    try {
        console.log("🔄 Loading orders...", { page, filters });

        const params = {
            page,
            per_page: pagination.per_page,
            ...filters,
        };

        const response = await orderApi.getAll(params);
        console.log("📦 Orders response:", response);

        if (response && response.data) {
            // API trả về structure: {success: true, data: Array(20), pagination: {...}}
            orders.value = response.data || [];

            // Update pagination
            if (response.pagination) {
                Object.assign(pagination, response.pagination);
            }

            console.log("✅ Orders loaded:", orders.value.length);
        }

        // Update stats
        await loadStats();
    } catch (error) {
        console.error("❌ Error loading orders:", error);

        // Nếu lỗi auth, hiển thị thông báo
        if (error.response?.status === 401) {
            alert("Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.");
        } else {
            alert("Không thể tải danh sách đơn hàng. Vui lòng thử lại.");
        }

        // Set empty data on error
        orders.value = [];
        stats.value = {};
    } finally {
        loading.value = false;
    }
};

// Load stats
const loadStats = async () => {
    try {
        console.log("📊 Loading stats...");
        const response = await orderApi.getStats(filters);
        console.log("📈 Stats response:", response);

        if (response && response.data) {
            stats.value = response.data.data || {};
            console.log("✅ Stats loaded:", stats.value);
        }
    } catch (error) {
        console.error("❌ Error loading stats:", error);
        // Set default stats on error
        stats.value = {
            ordered: 0,
            approved: 0,
            shipping_created: 0,
            delivered: 0,
            completed: 0,
        };
    }
};

// Search orders with debounce
let searchTimeout;
const searchOrders = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        loadOrders(1);
    }, 500);
};

// Reset filters
const resetFilters = () => {
    Object.assign(filters, {
        search: "",
        status: "",
        warehouse_id: "",
        date_from: "",
        date_to: "",
    });
    loadOrders(1);
};

// No quick-view modal

// Quick actions
const approveOrder = async (order) => {
    try {
        if (!confirm(`Duyệt đơn hàng ${order.code || "#" + order.id}?`)) return;
        await orderApi.approveOrder(order.id);
        await loadOrders(pagination.current_page);
    } catch (error) {
        console.error("Error approving order:", error);
        alert(error?.message || "Không duyệt được đơn hàng");
    }
};

const createShipping = async (order) => {
    try {
        // Không mở popup: mặc định tự giao hàng (self_delivery)
        await orderApi.createShipping(order.id, {
            shipping_method: "self_delivery",
        });
        await loadOrders(pagination.current_page);
    } catch (error) {
        console.error("Error creating shipping:", error);
        alert(error?.message || "Không tạo được đơn vận chuyển");
    }
};

const exportStock = async (order) => {
    try {
        await orderApi.exportStock(order.id);
        await loadOrders(pagination.current_page);
    } catch (error) {
        console.error("Error exporting stock:", error);
        alert(error?.message || "Không xuất kho được");
    }
};

const makePayment = async (order) => {
    try {
        const amount = Number(order.debt || 0);
        if (amount <= 0) return;
        await orderApi.completePayment(order.id, {
            payment_method: "cash",
            amount,
            note: "Thanh toán nhanh toàn bộ nợ",
        });
        await loadOrders(pagination.current_page);
    } catch (error) {
        console.error("Error completing payment:", error);
        alert(error?.message || "Thanh toán thất bại");
    }
};

const printOrder = (order) => {
    window.open(`/api/orders/${order.id}/print`, "_blank");
};

// Điều hướng sang trang chi tiết đầy đủ (nếu có route web)
const goToOrderDetail = (order) => {
    window.location.href = `/orders/${order.id}`;
};

const exportOrders = async () => {
    try {
        const response = await orderApi.export(filters);
        // Handle download
    } catch (error) {
        console.error("Error exporting orders:", error);
    }
};

// Utility functions
const getStatusText = (status) => workflowHelpers.getStatusText(status);
const getStatusColor = (status) => workflowHelpers.getStatusColor(status);

const getStatusClass = (status) => {
    const color = getStatusColor(status);
    const classMap = {
        yellow: "bg-yellow-100 text-yellow-800",
        blue: "bg-blue-100 text-blue-800",
        purple: "bg-purple-100 text-purple-800",
        green: "bg-green-100 text-green-800",
        red: "bg-red-100 text-red-800",
        gray: "bg-gray-100 text-gray-800",
    };
    return classMap[color] || classMap.gray;
};

const getProgressPercentage = (status) => {
    const progressMap = {
        ordered: 20,
        approved: 40,
        shipping_created: 60,
        delivered: 80,
        completed: 100,
        cancelled: 0,
    };
    return progressMap[status] || 0;
};

const getProgressText = (status) => {
    const progressMap = {
        ordered: "Bước 1/5",
        approved: "Bước 2/5",
        shipping_created: "Bước 3/5",
        delivered: "Bước 4/5",
        completed: "Hoàn thành",
        cancelled: "Đã hủy",
    };
    return progressMap[status] || "";
};

const formatCurrency = (amount) => {
    return new Intl.NumberFormat("vi-VN", {
        style: "currency",
        currency: "VND",
    }).format(amount || 0);
};

const formatDateTime = (dateString) => {
    return new Date(dateString).toLocaleString("vi-VN");
};

// Initialize with test data if API fails
const initializeWithTestData = () => {
    console.log("🧪 Initializing with test data...");

    // Test orders data
    orders.value = [
        {
            id: 1,
            code: "DH-001",
            status: "ordered",
            total: 1500000,
            paid: 0,
            debt: 1500000,
            created_at: new Date().toISOString(),
            customer: {
                name: "Nguyễn Văn A",
                phone: "0123456789",
            },
            warehouse: {
                name: "Kho Hà Nội",
            },
        },
        {
            id: 2,
            code: "DH-002",
            status: "approved",
            total: 2500000,
            paid: 1000000,
            debt: 1500000,
            created_at: new Date().toISOString(),
            customer: {
                name: "Trần Thị B",
                phone: "0987654321",
            },
            warehouse: {
                name: "Kho TP.HCM",
            },
        },
        {
            id: 3,
            code: "DH-003",
            status: "shipping_created",
            total: 3000000,
            paid: 3000000,
            debt: 0,
            created_at: new Date().toISOString(),
            customer: {
                name: "Lê Văn C",
                phone: "0901234567",
            },
            warehouse: {
                name: "Kho Đà Nẵng",
            },
        },
    ];

    // Test stats
    stats.value = {
        ordered: 5,
        approved: 3,
        shipping_created: 2,
        delivered: 8,
        completed: 12,
    };

    // Test pagination
    Object.assign(pagination, {
        current_page: 1,
        last_page: 1,
        per_page: 20,
        total: 3,
        from: 1,
        to: 3,
    });

    console.log("✅ Test data initialized");
};

// Initialize
onMounted(async () => {
    try {
        await loadOrders();
        // Không cần fallback vì API đã hoạt động
        console.log("🎉 Orders loaded successfully from API");
    } catch (error) {
        console.log("🔄 API failed, falling back to test data");
        initializeWithTestData();
    }
});
</script>

<style scoped>
/* Custom styles for better visual */
.container {
    max-width: 1400px;
}

.hover\:bg-gray-50:hover {
    background-color: #f9fafb;
}

/* Progress bar animation */
.transition-all {
    transition-property: all;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
    transition-duration: 300ms;
}

/* Custom scroll styles */
.overflow-x-auto::-webkit-scrollbar {
    height: 6px;
}

.overflow-x-auto::-webkit-scrollbar-track {
    background: #f1f1f1;
}

.overflow-x-auto::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 3px;
}

.overflow-x-auto::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}
</style>
