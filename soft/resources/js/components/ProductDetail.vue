<template>
    <!-- Modal Overlay -->
    <div
        class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-start justify-center pt-4"
    >
        <div
            class="bg-white rounded-lg shadow-xl w-full max-w-6xl mx-4 max-h-screen overflow-y-auto"
        >
            <!-- Header -->
            <div class="flex items-center justify-between p-6 border-b">
                <div class="flex items-center space-x-4">
                    <button
                        @click="$emit('close')"
                        class="text-gray-500 hover:text-gray-700"
                    >
                        ← Quay lại danh sách sản phẩm
                    </button>
                </div>
                <div class="text-center">
                    <h2 class="text-xl font-semibold text-red-500">
                        CHI TIẾT SẢN PHẨM
                    </h2>
                </div>
                <div class="flex items-center space-x-3">
                    <button
                        @click="$emit('close')"
                        class="px-4 py-2 border border-gray-300 rounded text-gray-600 hover:bg-gray-50"
                    >
                        Thoát
                    </button>
                    <button
                        @click="$emit('delete', product.id)"
                        class="px-4 py-2 border border-red-300 rounded text-red-600 hover:bg-red-50"
                    >
                        Xoá
                    </button>
                    <button
                        @click="editProduct"
                        class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600"
                    >
                        Sửa sản phẩm
                    </button>
                </div>
            </div>

            <!-- Content -->
            <div class="p-6">
                <!-- Tabs -->
                <div class="flex space-x-8 border-b mb-6">
                    <button
                        :class="[
                            'pb-3',
                            activeTab === 'info'
                                ? 'border-b-2 border-blue-500 text-blue-500'
                                : 'text-gray-600',
                        ]"
                        @click="activeTab = 'info'"
                    >
                        Thông tin sản phẩm
                    </button>
                    <button
                        :class="[
                            'pb-3',
                            activeTab === 'history'
                                ? 'border-b-2 border-blue-500 text-blue-500'
                                : 'text-gray-600',
                        ]"
                        @click="activeTab = 'history'"
                    >
                        Lịch sử kho
                    </button>
                    <button
                        v-if="product.track_serial"
                        :class="[
                            'pb-3',
                            activeTab === 'serials'
                                ? 'border-b-2 border-blue-500 text-blue-500'
                                : 'text-gray-600',
                        ]"
                        @click="activeTab = 'serials'"
                    >
                        Serial/IMEI
                        <span
                            v-if="serialCount > 0"
                            class="ml-1 px-2 py-0.5 text-xs rounded-full bg-blue-100 text-blue-600"
                            >{{ serialCount }}</span
                        >
                    </button>
                </div>

                <!-- Tab Content: Product Info -->
                <div
                    v-if="activeTab === 'info'"
                    class="grid grid-cols-1 lg:grid-cols-3 gap-8"
                >
                    <!-- Left Column - Product Info -->
                    <div class="lg:col-span-2 space-y-6">
                        <!-- Basic Info -->
                        <div class="grid grid-cols-2 gap-x-8 gap-y-4 text-sm">
                            <div class="flex">
                                <span class="w-32 text-gray-600">Mã SKU</span>
                                <span class="text-gray-900"
                                    >: {{ product.sku || "N/A" }}</span
                                >
                            </div>
                            <div class="flex">
                                <span class="w-32 text-gray-600"
                                    >Loại sản phẩm</span
                                >
                                <span class="text-gray-900"
                                    >:
                                    {{ product.category_name || "N/A" }}</span
                                >
                            </div>

                            <div class="flex">
                                <span class="w-32 text-gray-600"
                                    >Mã barcode</span
                                >
                                <span class="text-gray-900"
                                    >: {{ product.barcode || "N/A" }}</span
                                >
                            </div>
                            <div class="flex">
                                <span class="w-32 text-gray-600"
                                    >Nhãn hiệu</span
                                >
                                <span class="text-gray-900"
                                    >: {{ product.brand_name || "N/A" }}</span
                                >
                            </div>

                            <div class="flex">
                                <span class="w-32 text-gray-600"
                                    >Khối lượng</span
                                >
                                <span class="text-gray-900"
                                    >: {{ product.weight || "N/A" }}</span
                                >
                            </div>
                            <div class="flex">
                                <span class="w-32 text-gray-600">Tags</span>
                                <span class="text-gray-900"
                                    >: {{ product.tags || "N/A" }}</span
                                >
                            </div>

                            <div class="flex">
                                <span class="w-32 text-gray-600"
                                    >Đơn vị tính</span
                                >
                                <span class="text-gray-900"
                                    >: {{ product.unit || "Chai" }}</span
                                >
                            </div>
                            <div class="flex">
                                <span class="w-32 text-gray-600">Ngày tạo</span>
                                <span class="text-gray-900"
                                    >:
                                    {{
                                        formatDateTime(product.created_at)
                                    }}</span
                                >
                            </div>

                            <div class="flex">
                                <span class="w-32 text-gray-600"
                                    >Phân loại</span
                                >
                                <span class="text-gray-900"
                                    >: {{ product.status || "active" }}</span
                                >
                            </div>
                            <div class="flex">
                                <span class="w-32 text-gray-600"
                                    >Ngày cập nhật cuối</span
                                >
                                <span class="text-gray-900"
                                    >:
                                    {{
                                        formatDateTime(product.updated_at)
                                    }}</span
                                >
                            </div>
                        </div>

                        <div
                            class="text-blue-500 cursor-pointer hover:underline"
                        >
                            Xem mô tả: {{ product.note || "Không có ghi chú" }}
                        </div>

                        <!-- Pricing Section -->
                        <div class="border-t pt-6">
                            <h3 class="text-lg font-semibold mb-4">
                                Giá sản phẩm
                            </h3>
                            <div
                                class="grid grid-cols-2 gap-x-8 gap-y-4 text-sm"
                            >
                                <div class="flex">
                                    <span class="w-32 text-gray-600"
                                        >Giá bán lẻ</span
                                    >
                                    <span class="text-gray-900"
                                        >:
                                        {{
                                            formatCurrency(product.retail_price)
                                        }}</span
                                    >
                                </div>
                                <div class="flex">
                                    <span class="w-32 text-gray-600"
                                        >Giá bán buôn</span
                                    >
                                    <span class="text-gray-900"
                                        >:
                                        {{
                                            formatCurrency(
                                                product.wholesale_price,
                                            )
                                        }}</span
                                    >
                                </div>

                                <div class="flex">
                                    <span class="w-32 text-gray-600"
                                        >Giá vốn</span
                                    >
                                    <span class="text-gray-900"
                                        >:
                                        {{
                                            formatCurrency(product.cost_price)
                                        }}</span
                                    >
                                </div>
                                <div class="flex">
                                    <span class="w-32 text-gray-600"
                                        >Số lượng</span
                                    >
                                    <span class="text-gray-900"
                                        >: {{ product.quantity || 0 }}</span
                                    >
                                </div>
                            </div>
                        </div>

                        <!-- Inventory Section -->
                        <div class="border-t pt-6">
                            <div class="flex space-x-8 border-b">
                                <button
                                    class="pb-3 border-b-2 border-blue-500 text-blue-500"
                                >
                                    Tồn kho
                                </button>
                                <button class="pb-3 text-gray-600">
                                    Lịch sử kho
                                </button>
                            </div>

                            <div class="mt-4 overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th
                                                class="text-left p-3 text-gray-600"
                                            >
                                                Chi nhánh
                                            </th>
                                            <th
                                                class="text-left p-3 text-gray-600"
                                            >
                                                Tồn kho
                                            </th>
                                            <th
                                                class="text-left p-3 text-gray-600"
                                            >
                                                Giá vốn
                                            </th>
                                            <th
                                                class="text-left p-3 text-gray-600"
                                            >
                                                Có thể bán
                                            </th>
                                            <th
                                                class="text-left p-3 text-gray-600"
                                            >
                                                Đang giao dịch
                                            </th>
                                            <th
                                                class="text-left p-3 text-gray-600"
                                            >
                                                Hàng đang về
                                            </th>
                                            <th
                                                class="text-left p-3 text-gray-600"
                                            >
                                                Hàng đang giao
                                            </th>
                                            <th
                                                class="text-left p-3 text-gray-600"
                                            >
                                                Tồn tối thiểu
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        <tr v-if="!hasWarehouseData">
                                            <td
                                                colspan="8"
                                                class="p-3 text-center text-gray-500"
                                            >
                                                Sản phẩm chưa có trong kho nào
                                            </td>
                                        </tr>
                                        <tr
                                            v-for="wp in product.warehouse_products"
                                            :key="wp.warehouse_id"
                                        >
                                            <td class="p-3">
                                                {{
                                                    wp.warehouse?.name || "N/A"
                                                }}
                                            </td>
                                            <td class="p-3">
                                                {{ wp.quantity || 0 }}
                                            </td>
                                            <td class="p-3">
                                                {{ formatCurrency(wp.cost) }}
                                            </td>
                                            <td class="p-3">
                                                {{
                                                    (wp.quantity || 0) -
                                                    (wp.reserved_quantity || 0)
                                                }}
                                            </td>
                                            <td class="p-3">
                                                {{ wp.reserved_quantity || 0 }}
                                            </td>
                                            <td class="p-3">0</td>
                                            <td class="p-3">0</td>
                                            <td class="p-3">
                                                {{ wp.min_stock || "---" }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column - Image & Additional Info -->
                    <div class="space-y-6">
                        <!-- Product Image -->
                        <div class="border rounded-lg p-6 text-center">
                            <div
                                class="w-32 h-32 bg-gray-100 rounded-lg mx-auto flex items-center justify-center mb-4"
                            >
                                <span class="text-4xl text-gray-400">📷</span>
                            </div>
                            <p class="text-gray-500 text-sm">
                                Sản phẩm chưa có ảnh tải lên
                            </p>
                        </div>

                        <!-- Additional Info -->
                        <div class="border rounded-lg p-6">
                            <h4 class="font-semibold mb-4">Thông tin thêm</h4>
                            <div class="space-y-3 text-sm">
                                <label class="flex items-center">
                                    <input
                                        type="checkbox"
                                        class="mr-2"
                                        :checked="product.status === 'active'"
                                        readonly
                                    />
                                    <span>Cho phép bán</span>
                                    <span class="ml-2 text-gray-400">ℹ️</span>
                                </label>
                                <label class="flex items-center">
                                    <input
                                        type="checkbox"
                                        class="mr-2"
                                        :checked="false"
                                        readonly
                                    />
                                    <span>Áp dụng thuế</span>
                                    <span class="ml-2 text-gray-400">ℹ️</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab Content: Stock History -->
                <div v-if="activeTab === 'history'" class="space-y-4">
                    <!-- History Summary -->
                    <div
                        v-if="stockHistory.summary"
                        class="grid grid-cols-4 gap-4 mb-6"
                    >
                        <div class="bg-blue-50 p-4 rounded-lg">
                            <div class="text-2xl font-bold text-blue-600">
                                {{ stockHistory.summary.total_imports || 0 }}
                            </div>
                            <div class="text-sm text-gray-600">
                                Lần nhập kho
                            </div>
                        </div>
                        <div class="bg-red-50 p-4 rounded-lg">
                            <div class="text-2xl font-bold text-red-600">
                                {{ stockHistory.summary.total_exports || 0 }}
                            </div>
                            <div class="text-sm text-gray-600">
                                Lần xuất bán
                            </div>
                        </div>
                        <div class="bg-yellow-50 p-4 rounded-lg">
                            <div class="text-2xl font-bold text-yellow-600">
                                {{ stockHistory.summary.total_returns || 0 }}
                            </div>
                            <div class="text-sm text-gray-600">
                                Lần trả hàng
                            </div>
                        </div>
                        <div class="bg-green-50 p-4 rounded-lg">
                            <div class="text-2xl font-bold text-green-600">
                                {{ stockHistory.summary.current_balance || 0 }}
                            </div>
                            <div class="text-sm text-gray-600">
                                Tồn kho hiện tại
                            </div>
                        </div>
                    </div>

                    <!-- History Filters -->
                    <div class="flex space-x-4 mb-4">
                        <select
                            v-model="historyFilter.type"
                            @change="loadStockHistory"
                            class="border rounded px-3 py-2"
                        >
                            <option value="all">Tất cả</option>
                            <option value="import">Nhập kho</option>
                            <option value="export">Xuất bán</option>
                            <option value="return">Trả hàng</option>
                        </select>

                        <select
                            v-model="historyFilter.warehouse_id"
                            @change="loadStockHistory"
                            class="border rounded px-3 py-2"
                        >
                            <option value="">Tất cả kho</option>
                            <!-- TODO: Load warehouses -->
                        </select>

                        <button
                            @click="loadStockHistory"
                            class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600"
                        >
                            Tải lại
                        </button>
                    </div>

                    <!-- History Table -->
                    <div v-if="loadingHistory" class="text-center py-8">
                        <div class="text-gray-500">Đang tải lịch sử...</div>
                    </div>

                    <div
                        v-else-if="
                            stockHistory.history &&
                            stockHistory.history.length > 0
                        "
                        class="overflow-x-auto"
                    >
                        <table class="w-full text-sm border border-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="text-left p-3 border-b">Ngày</th>
                                    <th class="text-left p-3 border-b">Loại</th>
                                    <th class="text-left p-3 border-b">Kho</th>
                                    <th class="text-right p-3 border-b">
                                        Số lượng
                                    </th>
                                    <th class="text-right p-3 border-b">
                                        Đơn giá
                                    </th>
                                    <th class="text-right p-3 border-b">
                                        Tồn kho
                                    </th>
                                    <th class="text-left p-3 border-b">
                                        Tham chiếu
                                    </th>
                                    <th class="text-left p-3 border-b">
                                        Đối tác
                                    </th>
                                    <th class="text-left p-3 border-b">
                                        Ghi chú
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr
                                    v-for="item in stockHistory.history"
                                    :key="item.id"
                                    class="hover:bg-gray-50"
                                >
                                    <td class="p-3">
                                        {{ formatDateTime(item.date) }}
                                    </td>
                                    <td class="p-3">
                                        <span
                                            :class="getTypeColor(item.type)"
                                            class="px-2 py-1 rounded text-xs font-medium"
                                        >
                                            {{ item.type_text }}
                                        </span>
                                    </td>
                                    <td class="p-3">{{ item.warehouse }}</td>
                                    <td
                                        class="p-3 text-right"
                                        :class="
                                            item.quantity > 0
                                                ? 'text-green-600'
                                                : 'text-red-600'
                                        "
                                    >
                                        {{ item.quantity > 0 ? "+" : ""
                                        }}{{ item.quantity }}
                                    </td>
                                    <td class="p-3 text-right">
                                        {{ formatCurrency(item.price) }}
                                    </td>
                                    <td class="p-3 text-right font-medium">
                                        {{ item.running_balance }}
                                    </td>
                                    <td class="p-3">
                                        <span
                                            class="text-blue-600 hover:underline cursor-pointer"
                                            >{{ item.reference_code }}</span
                                        >
                                    </td>
                                    <td class="p-3">{{ item.partner }}</td>
                                    <td class="p-3">
                                        <span
                                            v-if="item.note"
                                            class="text-gray-600"
                                            >{{ item.note }}</span
                                        >
                                        <span
                                            v-if="item.return_reason"
                                            class="text-red-600 text-xs"
                                            >({{ item.return_reason }})</span
                                        >
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-else class="text-center py-12 text-gray-500">
                        <p>Chưa có lịch sử nhập/xuất kho</p>
                    </div>

                    <!-- Pagination -->
                    <div
                        v-if="
                            stockHistory.history &&
                            stockHistory.history.length > 0
                        "
                        class="flex justify-between items-center mt-4"
                    >
                        <div class="text-sm text-gray-600">
                            Hiển thị {{ stockHistory.history.length }} /
                            {{ historyPagination.total || 0 }} bản ghi
                        </div>
                        <div class="flex space-x-2">
                            <button
                                v-if="historyPagination.current_page > 1"
                                @click="
                                    loadHistoryPage(
                                        historyPagination.current_page - 1,
                                    )
                                "
                                class="px-3 py-1 border rounded hover:bg-gray-50"
                            >
                                Trước
                            </button>
                            <button
                                v-if="
                                    historyPagination.current_page <
                                    historyPagination.last_page
                                "
                                @click="
                                    loadHistoryPage(
                                        historyPagination.current_page + 1,
                                    )
                                "
                                class="px-3 py-1 border rounded hover:bg-gray-50"
                            >
                                Sau
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Tab Content: Serials/IMEI -->
                <div v-if="activeTab === 'serials'" class="space-y-4">
                    <!-- Serial Filters -->
                    <div class="flex items-center gap-4 flex-wrap">
                        <input
                            v-model="serialFilter.search"
                            @input="loadSerials"
                            type="text"
                            class="border rounded px-3 py-2 text-sm w-64"
                            placeholder="Tìm serial/IMEI..."
                        />
                        <select
                            v-model="serialFilter.status"
                            @change="loadSerials"
                            class="border rounded px-3 py-2 text-sm"
                        >
                            <option value="">Tất cả trạng thái</option>
                            <option value="in_stock">Trong kho</option>
                            <option value="sold">Đã bán</option>
                            <option value="returned">Đã trả</option>
                            <option value="defective">Lỗi/Hỏng</option>
                        </select>
                        <select
                            v-model="serialFilter.warehouse_id"
                            @change="loadSerials"
                            class="border rounded px-3 py-2 text-sm"
                        >
                            <option value="">Tất cả kho</option>
                            <option
                                v-for="wp in product.warehouse_products || []"
                                :key="wp.warehouse_id"
                                :value="wp.warehouse_id"
                            >
                                {{
                                    wp.warehouse?.name ||
                                    "Kho #" + wp.warehouse_id
                                }}
                            </option>
                        </select>
                        <button
                            @click="loadSerials"
                            class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 text-sm"
                        >
                            Tải lại
                        </button>
                    </div>

                    <!-- Serial Stats -->
                    <div class="grid grid-cols-4 gap-4">
                        <div class="bg-green-50 p-3 rounded-lg text-center">
                            <div class="text-xl font-bold text-green-600">
                                {{ serialStats.in_stock || 0 }}
                            </div>
                            <div class="text-xs text-gray-600">Trong kho</div>
                        </div>
                        <div class="bg-blue-50 p-3 rounded-lg text-center">
                            <div class="text-xl font-bold text-blue-600">
                                {{ serialStats.sold || 0 }}
                            </div>
                            <div class="text-xs text-gray-600">Đã bán</div>
                        </div>
                        <div class="bg-yellow-50 p-3 rounded-lg text-center">
                            <div class="text-xl font-bold text-yellow-600">
                                {{ serialStats.returned || 0 }}
                            </div>
                            <div class="text-xs text-gray-600">Đã trả</div>
                        </div>
                        <div class="bg-red-50 p-3 rounded-lg text-center">
                            <div class="text-xl font-bold text-red-600">
                                {{ serialStats.defective || 0 }}
                            </div>
                            <div class="text-xs text-gray-600">Lỗi/Hỏng</div>
                        </div>
                    </div>

                    <!-- Serial Loading -->
                    <div v-if="loadingSerials" class="text-center py-8">
                        <div class="text-gray-500">
                            Đang tải danh sách serial...
                        </div>
                    </div>

                    <!-- Serial Table -->
                    <div v-else-if="serials.length > 0" class="overflow-x-auto">
                        <table class="w-full text-sm border border-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="text-left p-3 border-b">
                                        Serial/IMEI
                                    </th>
                                    <th class="text-left p-3 border-b">
                                        Trạng thái
                                    </th>
                                    <th class="text-left p-3 border-b">Kho</th>
                                    <th class="text-right p-3 border-b">
                                        Giá nhập
                                    </th>
                                    <th class="text-left p-3 border-b">
                                        Ngày nhập
                                    </th>
                                    <th class="text-left p-3 border-b">
                                        Ghi chú
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr
                                    v-for="serial in serials"
                                    :key="serial.id"
                                    class="hover:bg-gray-50"
                                >
                                    <td class="p-3 font-mono font-medium">
                                        {{ serial.serial_number }}
                                    </td>
                                    <td class="p-3">
                                        <span
                                            :class="
                                                getSerialStatusClass(
                                                    serial.status,
                                                )
                                            "
                                            class="px-2 py-1 rounded text-xs font-medium"
                                        >
                                            {{
                                                getSerialStatusText(
                                                    serial.status,
                                                )
                                            }}
                                        </span>
                                    </td>
                                    <td class="p-3">
                                        {{ serial.warehouse?.name || "N/A" }}
                                    </td>
                                    <td class="p-3 text-right">
                                        {{ formatCurrency(serial.cost_price) }}
                                    </td>
                                    <td class="p-3">
                                        {{ formatDateTime(serial.created_at) }}
                                    </td>
                                    <td class="p-3 text-gray-500">
                                        {{ serial.note || "-" }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-else class="text-center py-12 text-gray-500">
                        <p>Chưa có serial/IMEI nào cho sản phẩm này</p>
                    </div>

                    <!-- Serial Pagination -->
                    <div
                        v-if="serials.length > 0"
                        class="flex justify-between items-center mt-4"
                    >
                        <div class="text-sm text-gray-600">
                            Hiển thị {{ serials.length }} /
                            {{ serialPagination.total || 0 }} serial
                        </div>
                        <div class="flex space-x-2">
                            <button
                                v-if="serialPagination.current_page > 1"
                                @click="
                                    loadSerialsPage(
                                        serialPagination.current_page - 1,
                                    )
                                "
                                class="px-3 py-1 border rounded hover:bg-gray-50"
                            >
                                Trước
                            </button>
                            <button
                                v-if="
                                    serialPagination.current_page <
                                    serialPagination.last_page
                                "
                                @click="
                                    loadSerialsPage(
                                        serialPagination.current_page + 1,
                                    )
                                "
                                class="px-3 py-1 border rounded hover:bg-gray-50"
                            >
                                Sau
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { productApi } from "../api/productApi.js";
import { productSerialApi } from "../api/productSerialApi.js";

export default {
    name: "ProductDetail",
    props: {
        product: {
            type: Object,
            required: true,
        },
    },
    emits: ["close", "save", "delete", "edit"],
    data() {
        return {
            activeTab: "info",
            loadingHistory: false,
            stockHistory: {
                history: [],
                summary: null,
            },
            historyPagination: {},
            historyFilter: {
                type: "all",
                warehouse_id: "",
            },
            // Serial data
            loadingSerials: false,
            serials: [],
            serialPagination: {},
            serialFilter: {
                search: "",
                status: "",
                warehouse_id: "",
            },
            serialStats: {},
            serialCount: 0,
        };
    },
    computed: {
        hasWarehouseData() {
            return (
                this.product.warehouse_products &&
                this.product.warehouse_products.length > 0
            );
        },
    },
    methods: {
        editProduct() {
            this.$emit("edit", this.product);
        },

        // ========== SERIAL METHODS ==========
        async loadSerials(page = 1) {
            this.loadingSerials = true;
            try {
                const params = {
                    product_id: this.product.id,
                    per_page: 20,
                    page: page,
                };
                if (this.serialFilter.search)
                    params.search = this.serialFilter.search;
                if (this.serialFilter.status)
                    params.status = this.serialFilter.status;
                if (this.serialFilter.warehouse_id)
                    params.warehouse_id = this.serialFilter.warehouse_id;

                const response = await productSerialApi.getAll(params);
                if (response.success) {
                    this.serials = response.data;
                    this.serialPagination = response.pagination;
                }
            } catch (error) {
                console.error("Error loading serials:", error);
            } finally {
                this.loadingSerials = false;
            }
        },

        async loadSerialsPage(page) {
            await this.loadSerials(page);
        },

        async loadSerialStats() {
            try {
                // Load counts by status
                const statuses = ["in_stock", "sold", "returned", "defective"];
                const stats = {};
                let total = 0;
                for (const status of statuses) {
                    const response = await productSerialApi.getAll({
                        product_id: this.product.id,
                        status: status,
                        per_page: 1,
                    });
                    if (response.success) {
                        stats[status] = response.pagination?.total || 0;
                        total += stats[status];
                    }
                }
                this.serialStats = stats;
                this.serialCount = total;
            } catch (error) {
                console.error("Error loading serial stats:", error);
            }
        },

        getSerialStatusText(status) {
            const map = {
                in_stock: "Trong kho",
                sold: "Đã bán",
                returned: "Đã trả",
                defective: "Lỗi/Hỏng",
                transferred: "Đang chuyển",
            };
            return map[status] || status;
        },

        getSerialStatusClass(status) {
            const map = {
                in_stock: "bg-green-100 text-green-800",
                sold: "bg-blue-100 text-blue-800",
                returned: "bg-yellow-100 text-yellow-800",
                defective: "bg-red-100 text-red-800",
                transferred: "bg-purple-100 text-purple-800",
            };
            return map[status] || "bg-gray-100 text-gray-800";
        },

        // ========== EXISTING METHODS ==========
        async loadStockHistory() {
            if (this.activeTab !== "history") return;

            this.loadingHistory = true;
            try {
                const params = {
                    type: this.historyFilter.type,
                    warehouse_id: this.historyFilter.warehouse_id,
                    per_page: 20,
                    page: 1,
                };

                const response = await productApi.getStockHistory(
                    this.product.id,
                    params,
                );

                if (response.success) {
                    this.stockHistory = response.data;
                    this.historyPagination = response.pagination;
                }
            } catch (error) {
                console.error("Error loading stock history:", error);
                alert(
                    "Lỗi khi tải lịch sử kho: " +
                        (error.message || "Vui lòng thử lại"),
                );
            } finally {
                this.loadingHistory = false;
            }
        },

        async loadHistoryPage(page) {
            this.loadingHistory = true;
            try {
                const params = {
                    type: this.historyFilter.type,
                    warehouse_id: this.historyFilter.warehouse_id,
                    per_page: 20,
                    page: page,
                };

                const response = await productApi.getStockHistory(
                    this.product.id,
                    params,
                );

                if (response.success) {
                    this.stockHistory = response.data;
                    this.historyPagination = response.pagination;
                }
            } catch (error) {
                console.error("Error loading history page:", error);
            } finally {
                this.loadingHistory = false;
            }
        },

        getTypeColor(type) {
            const colors = {
                import: "bg-green-100 text-green-800",
                export: "bg-red-100 text-red-800",
                return: "bg-yellow-100 text-yellow-800",
            };
            return colors[type] || "bg-gray-100 text-gray-800";
        },

        formatCurrency(amount) {
            if (!amount) return "0";
            return new Intl.NumberFormat("vi-VN").format(amount);
        },

        formatDateTime(dateString) {
            if (!dateString) return "N/A";
            const date = new Date(dateString);
            return date.toLocaleString("vi-VN", {
                day: "2-digit",
                month: "2-digit",
                year: "numeric",
                hour: "2-digit",
                minute: "2-digit",
            });
        },
    },

    watch: {
        activeTab(newTab) {
            if (
                newTab === "history" &&
                this.stockHistory.history.length === 0
            ) {
                this.loadStockHistory();
            }
            if (newTab === "serials" && this.serials.length === 0) {
                this.loadSerials();
            }
        },
    },

    mounted() {
        console.log("🔍 Product data:", this.product);
        console.log("🏪 Warehouse products:", this.product.warehouse_products);
        console.log("📊 Has warehouse data:", this.hasWarehouseData);

        // Auto-load serial stats if product has track_serial
        if (this.product.track_serial) {
            this.loadSerialStats();
        }
    },
};
</script>
