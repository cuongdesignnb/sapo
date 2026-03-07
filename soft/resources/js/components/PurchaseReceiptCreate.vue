<template>
    <div class="bg-white">
        <!-- Header + Mode Toggle -->
        <div class="p-6 border-b">
            <div class="flex justify-between items-center flex-wrap gap-4">
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900">
                        Tạo phiếu nhập kho
                    </h1>
                    <nav class="mt-1">
                        <ol
                            class="flex items-center space-x-2 text-sm text-gray-500"
                        >
                            <li>
                                <a href="/dashboard" class="hover:text-gray-700"
                                    >Trang chủ</a
                                >
                            </li>
                            <li>•</li>
                            <li>
                                <a
                                    href="/purchase-receipts"
                                    class="hover:text-gray-700"
                                    >Phiếu nhập kho</a
                                >
                            </li>
                            <li>•</li>
                            <li class="text-gray-900">Tạo mới</li>
                        </ol>
                    </nav>
                </div>
                <div class="flex items-center gap-3">
                    <div
                        class="flex items-center gap-2 bg-gray-50 px-3 py-2 rounded-md border"
                    >
                        <span class="text-sm text-gray-600">Chế độ:</span>
                        <button
                            type="button"
                            @click="toggleMode('order')"
                            :class="[
                                'px-3 py-1 rounded text-sm font-medium',
                                isOrderMode
                                    ? 'bg-blue-600 text-white shadow'
                                    : 'bg-white text-gray-700 border',
                            ]"
                        >
                            Theo đơn
                        </button>
                        <button
                            type="button"
                            @click="toggleMode('manual')"
                            :class="[
                                'px-3 py-1 rounded text-sm font-medium',
                                !isOrderMode
                                    ? 'bg-emerald-600 text-white shadow'
                                    : 'bg-white text-gray-700 border',
                            ]"
                        >
                            Độc lập
                        </button>
                    </div>
                    <button
                        @click="goBack"
                        class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
                    >
                        ← Quay lại
                    </button>
                    <button
                        @click="saveReceipt"
                        :disabled="loading || !canSave"
                        class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50"
                    >
                        <span v-if="loading">Đang lưu...</span>
                        <span v-else>💾 Lưu phiếu nhập</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Selection Section -->
        <div class="p-6 border-b">
            <div class="bg-white rounded-lg border border-gray-200">
                <div
                    class="px-6 py-4 border-b flex items-center justify-between gap-4"
                >
                    <h3 class="text-lg font-medium text-gray-900">
                        🔍 Khai báo thông tin
                        <span v-if="isOrderMode" class="text-blue-600"
                            >(Theo đơn)</span
                        >
                        <span v-else class="text-emerald-600">(Độc lập)</span>
                    </h3>
                </div>
                <div class="p-6 space-y-6">
                    <!-- Order Mode Inputs -->
                    <div v-if="isOrderMode" class="grid grid-cols-2 gap-6">
                        <div>
                            <label
                                class="block text-sm font-medium text-gray-700 mb-2"
                                >Đơn nhập hàng</label
                            >
                            <div class="relative">
                                <input
                                    v-model="orderSearch"
                                    @focus="searchOrders"
                                    @input="onOrderSearchInput"
                                    placeholder="Tìm mã đơn hoặc NCC..."
                                    class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500"
                                />
                                <div
                                    v-if="showOrderDropdown"
                                    class="absolute z-10 w-full mt-1 bg-white border rounded-md shadow max-h-60 overflow-y-auto"
                                >
                                    <template v-if="filteredOrders.length">
                                        <div
                                            v-for="o in filteredOrders"
                                            :key="o.id"
                                            @click="selectOrder(o)"
                                            class="px-3 py-2 cursor-pointer hover:bg-gray-100 border-b last:border-b-0"
                                        >
                                            <div
                                                class="flex items-center justify-between"
                                            >
                                                <div
                                                    class="text-sm font-medium"
                                                >
                                                    {{ o.code }}
                                                </div>
                                                <span
                                                    :class="
                                                        getOrderStatusClass(
                                                            o.status,
                                                        )
                                                    "
                                                    class="text-xs px-2 py-0.5 rounded-full"
                                                    >{{
                                                        getOrderStatusText(
                                                            o.status,
                                                        )
                                                    }}</span
                                                >
                                            </div>
                                            <div class="text-xs text-gray-600">
                                                {{ o.supplier?.name || "—" }}
                                            </div>
                                            <div
                                                class="text-xs text-gray-500 flex items-center gap-3"
                                            >
                                                <span
                                                    >Ngày:
                                                    {{
                                                        formatDate(
                                                            o.expected_at ||
                                                                o.created_at,
                                                        )
                                                    }}</span
                                                >
                                                <span
                                                    >Giá trị:
                                                    {{
                                                        formatCurrency(
                                                            o.total ||
                                                                o.total_amount ||
                                                                0,
                                                        )
                                                    }}</span
                                                >
                                            </div>
                                        </div>
                                    </template>
                                    <div
                                        v-else
                                        class="px-3 py-2 text-gray-500 text-center"
                                    >
                                        Không tìm thấy
                                    </div>
                                </div>
                            </div>
                            <div
                                v-if="selectedOrder"
                                class="mt-2 text-xs text-gray-600 flex items-center gap-3 flex-wrap"
                            >
                                <span
                                    >Nhà cung cấp:
                                    <strong>{{
                                        selectedOrder.supplier?.name
                                    }}</strong></span
                                >
                                <span
                                    >Ngày:
                                    {{
                                        formatDate(
                                            selectedOrder.expected_at ||
                                                selectedOrder.created_at,
                                        )
                                    }}</span
                                >
                                <span
                                    >Giá trị:
                                    <strong>{{
                                        formatCurrency(selectedOrder.total)
                                    }}</strong></span
                                >
                                <span class="inline-flex items-center gap-1">
                                    Trạng thái:
                                    <span
                                        :class="
                                            getOrderStatusClass(
                                                selectedOrder.status,
                                            )
                                        "
                                        class="px-2 py-0.5 rounded-full"
                                        >{{
                                            getOrderStatusText(
                                                selectedOrder.status,
                                            )
                                        }}</span
                                    >
                                </span>
                            </div>
                        </div>
                        <div>
                            <label
                                class="block text-sm font-medium text-gray-700 mb-2"
                                >Kho nhập
                                <span class="text-red-500">*</span></label
                            >
                            <div class="relative">
                                <input
                                    v-model="warehouseSearch"
                                    @focus="searchWarehouses"
                                    placeholder="Tìm kho..."
                                    class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500"
                                />
                                <div
                                    v-if="showWarehouseDropdown"
                                    class="absolute z-10 w-full mt-1 bg-white border rounded-md shadow max-h-48 overflow-y-auto"
                                >
                                    <template v-if="filteredWarehouses.length">
                                        <div
                                            v-for="w in filteredWarehouses"
                                            :key="w.id"
                                            @click="selectWarehouse(w)"
                                            class="px-3 py-2 cursor-pointer hover:bg-gray-100 border-b last:border-b-0"
                                        >
                                            <div class="font-medium text-sm">
                                                {{ w.name }}
                                            </div>
                                            <div class="text-xs text-gray-600">
                                                {{
                                                    w.address ||
                                                    "Chưa có địa chỉ"
                                                }}
                                            </div>
                                        </div>
                                    </template>
                                    <div
                                        v-else
                                        class="px-3 py-2 text-gray-500 text-center"
                                    >
                                        Không tìm thấy kho
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Manual Mode Inputs -->
                    <div v-else class="grid grid-cols-3 gap-6">
                        <div>
                            <label
                                class="block text-sm font-medium text-gray-700 mb-2"
                                >Nhà cung cấp
                                <span class="text-red-500">*</span></label
                            >
                            <div class="relative">
                                <input
                                    v-model="supplierSearch"
                                    @focus="searchSuppliers"
                                    placeholder="Tìm tên hoặc mã NCC..."
                                    class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-1 focus:ring-emerald-500"
                                />
                                <div
                                    v-if="showSupplierDropdown"
                                    class="absolute z-10 w-full mt-1 bg-white border rounded-md shadow max-h-60 overflow-y-auto"
                                >
                                    <template v-if="filteredSuppliers.length">
                                        <div
                                            v-for="s in filteredSuppliers"
                                            :key="s.id"
                                            @click="selectSupplier(s)"
                                            class="px-3 py-2 cursor-pointer hover:bg-gray-100 border-b last:border-b-0"
                                        >
                                            <div class="font-medium text-sm">
                                                {{ s.name }}
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                Mã: {{ s.code || s.id }}
                                            </div>
                                        </div>
                                    </template>
                                    <div
                                        v-else
                                        class="px-3 py-2 text-gray-500 text-center"
                                    >
                                        Không tìm thấy
                                    </div>
                                </div>
                            </div>
                            <p
                                v-if="selectedSupplier"
                                class="mt-2 text-xs text-emerald-600"
                            >
                                Đã chọn: {{ selectedSupplier.name }}
                            </p>
                        </div>
                        <div>
                            <label
                                class="block text-sm font-medium text-gray-700 mb-2"
                                >Kho nhập
                                <span class="text-red-500">*</span></label
                            >
                            <div class="relative">
                                <input
                                    v-model="warehouseSearch"
                                    @focus="searchWarehouses"
                                    placeholder="Tìm kho..."
                                    class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-1 focus:ring-emerald-500"
                                />
                                <div
                                    v-if="showWarehouseDropdown"
                                    class="absolute z-10 w-full mt-1 bg-white border rounded-md shadow max-h-48 overflow-y-auto"
                                >
                                    <template v-if="filteredWarehouses.length">
                                        <div
                                            v-for="w in filteredWarehouses"
                                            :key="w.id"
                                            @click="selectWarehouse(w)"
                                            class="px-3 py-2 cursor-pointer hover:bg-gray-100 border-b last:border-b-0"
                                        >
                                            <div class="font-medium text-sm">
                                                {{ w.name }}
                                            </div>
                                            <div class="text-xs text-gray-600">
                                                {{
                                                    w.address ||
                                                    "Chưa có địa chỉ"
                                                }}
                                            </div>
                                        </div>
                                    </template>
                                    <div
                                        v-else
                                        class="px-3 py-2 text-gray-500 text-center"
                                    >
                                        Không tìm thấy kho
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div>
                            <label
                                class="block text-sm font-medium text-gray-700 mb-2"
                                >Thêm sản phẩm</label
                            >
                            <div class="relative">
                                <input
                                    v-model="productSearch"
                                    @focus="searchProducts"
                                    placeholder="Tìm tên hoặc SKU sản phẩm..."
                                    class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-1 focus:ring-emerald-500"
                                />
                                <div
                                    v-if="showProductDropdown"
                                    class="absolute z-10 w-full mt-1 bg-white border rounded-md shadow max-h-60 overflow-y-auto"
                                >
                                    <template v-if="filteredProducts.length">
                                        <div
                                            v-for="p in filteredProducts"
                                            :key="p.id"
                                            @click="addProduct(p)"
                                            class="px-3 py-2 cursor-pointer hover:bg-gray-100 border-b last:border-b-0"
                                        >
                                            <div class="font-medium text-sm">
                                                {{ p.name }}
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                SKU: {{ p.sku }} | Giá:
                                                {{
                                                    formatCurrency(
                                                        p.cost_price ||
                                                            p.import_price ||
                                                            p.price ||
                                                            0,
                                                    )
                                                }}
                                            </div>
                                        </div>
                                    </template>
                                    <div
                                        v-else
                                        class="px-3 py-2 text-gray-500 text-center"
                                    >
                                        Không tìm thấy
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Order Info Panel -->
                    <div
                        v-if="isOrderMode && selectedOrder"
                        class="p-4 bg-blue-50 border border-blue-200 rounded-lg"
                    >
                        <div class="grid grid-cols-3 gap-4 text-sm">
                            <div>
                                <p>
                                    <strong>Mã đơn:</strong>
                                    {{ selectedOrder.code }}
                                </p>
                                <p>
                                    <strong>Nhà cung cấp:</strong>
                                    {{ selectedOrder.supplier?.name }}
                                </p>
                            </div>
                            <div>
                                <p>
                                    <strong>Ngày tạo:</strong>
                                    {{ formatDate(selectedOrder.created_at) }}
                                </p>
                                <p>
                                    <strong>Ngày dự kiến:</strong>
                                    {{ formatDate(selectedOrder.expected_at) }}
                                </p>
                            </div>
                            <div>
                                <p>
                                    <strong>Tổng tiền:</strong>
                                    {{ formatCurrency(selectedOrder.total) }}
                                </p>
                                <p>
                                    <strong>Trạng thái:</strong>
                                    <span
                                        :class="
                                            getOrderStatusClass(
                                                selectedOrder.status,
                                            )
                                        "
                                        class="inline-block px-2 py-1 text-xs font-medium rounded-full ml-1"
                                        >{{
                                            getOrderStatusText(
                                                selectedOrder.status,
                                            )
                                        }}</span
                                    >
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Receipt Form -->
        <div v-if="!isOrderMode || selectedOrder" class="p-6">
            <div class="grid grid-cols-12 gap-6">
                <div class="col-span-8">
                    <div class="bg-white rounded-lg border border-gray-200">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900">
                                📦 Nhập sản phẩm
                            </h3>
                        </div>
                        <div class="p-6">
                            <div class="grid grid-cols-2 gap-4 mb-6">
                                <div>
                                    <label
                                        class="block text-sm font-medium text-gray-700 mb-2"
                                        >Số lô</label
                                    >
                                    <input
                                        v-model="form.lot_number"
                                        type="text"
                                        class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500"
                                        placeholder="Nhập số lô (tùy chọn)"
                                    />
                                </div>
                                <div>
                                    <label
                                        class="block text-sm font-medium text-gray-700 mb-2"
                                        >Hạn sử dụng</label
                                    >
                                    <input
                                        v-model="form.expiry_date"
                                        type="date"
                                        :min="todayDate"
                                        class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500"
                                    />
                                </div>
                            </div>

                            <div class="mb-6">
                                <label
                                    class="block text-sm font-medium text-gray-700 mb-2"
                                    >Ghi chú</label
                                >
                                <textarea
                                    v-model="form.note"
                                    rows="3"
                                    class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500"
                                    placeholder="Ghi chú về phiếu nhập..."
                                ></textarea>
                            </div>

                            <div
                                v-if="form.items.length"
                                class="overflow-x-auto"
                            >
                                <table
                                    class="min-w-full divide-y divide-gray-200"
                                >
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th
                                                class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase"
                                            >
                                                Sản phẩm
                                            </th>
                                            <th
                                                class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase"
                                            >
                                                Đã đặt
                                            </th>
                                            <th
                                                class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase"
                                            >
                                                Đã nhập
                                            </th>
                                            <th
                                                class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase"
                                            >
                                                Còn lại
                                            </th>
                                            <th
                                                class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase"
                                            >
                                                Nhập lần này
                                            </th>
                                            <th
                                                class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase"
                                            >
                                                Đơn giá
                                            </th>
                                            <th
                                                class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase"
                                            >
                                                Thành tiền
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody
                                        class="bg-white divide-y divide-gray-200"
                                    >
                                        <tr
                                            v-for="(item, index) in form.items"
                                            :key="index"
                                            class="hover:bg-gray-50"
                                        >
                                            <td class="px-4 py-3">
                                                <div
                                                    class="text-sm font-medium text-gray-900"
                                                >
                                                    {{ item.product?.name }}
                                                </div>
                                                <div
                                                    class="text-xs text-gray-500"
                                                >
                                                    {{ item.product?.sku }}
                                                </div>
                                            </td>
                                            <td
                                                class="px-4 py-3 text-center text-sm"
                                            >
                                                {{ item.quantity || 0 }}
                                            </td>
                                            <td
                                                class="px-4 py-3 text-center text-sm text-gray-600"
                                            >
                                                {{
                                                    (item.quantity || 0) -
                                                    (item.remaining_quantity ||
                                                        item.quantity ||
                                                        0)
                                                }}
                                            </td>
                                            <td
                                                class="px-4 py-3 text-center text-sm text-gray-600"
                                            >
                                                {{
                                                    item.remaining_quantity ||
                                                    item.quantity ||
                                                    0
                                                }}
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <input
                                                    v-model.number="
                                                        item.quantity_received
                                                    "
                                                    @input="
                                                        calculateItemTotal(item)
                                                    "
                                                    type="number"
                                                    min="0"
                                                    :max="
                                                        item.remaining_quantity ||
                                                        item.quantity
                                                    "
                                                    :readonly="
                                                        item.product
                                                            ?.track_serial
                                                    "
                                                    :class="[
                                                        'w-20 px-2 py-1 text-center border rounded focus:outline-none focus:ring-1 focus:ring-blue-500',
                                                        item.product
                                                            ?.track_serial
                                                            ? 'bg-gray-100 cursor-not-allowed'
                                                            : '',
                                                    ]"
                                                    :title="
                                                        item.product
                                                            ?.track_serial
                                                            ? 'Số lượng tự động theo serial'
                                                            : ''
                                                    "
                                                />
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <input
                                                    v-model.number="
                                                        item.unit_cost
                                                    "
                                                    @input="
                                                        calculateItemTotal(item)
                                                    "
                                                    type="number"
                                                    min="0"
                                                    step="0.01"
                                                    class="w-24 px-2 py-1 text-center border rounded focus:outline-none focus:ring-1 focus:ring-blue-500"
                                                />
                                            </td>
                                            <td
                                                class="px-4 py-3 text-center text-sm font-medium"
                                            >
                                                {{
                                                    formatCurrency(
                                                        item.total_cost || 0,
                                                    )
                                                }}
                                            </td>
                                        </tr>
                                        <!-- Serial/IMEI input row -->
                                        <tr
                                            v-if="item.product?.track_serial"
                                            :key="'serial-' + index"
                                            class="bg-blue-50"
                                        >
                                            <td colspan="7" class="px-4 py-3">
                                                <div
                                                    class="flex items-start gap-3"
                                                >
                                                    <div
                                                        class="flex-shrink-0 mt-1"
                                                    >
                                                        <span
                                                            class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-blue-100 text-blue-800"
                                                        >
                                                            📱 Serial/IMEI
                                                        </span>
                                                    </div>
                                                    <div class="flex-1">
                                                        <textarea
                                                            v-model="
                                                                item.serial_numbers_text
                                                            "
                                                            @input="
                                                                onSerialInput(
                                                                    item,
                                                                )
                                                            "
                                                            rows="3"
                                                            class="w-full px-3 py-2 border border-blue-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                                            placeholder="Nhập số Serial/IMEI, mỗi dòng một số. Số lượng nhập sẽ tự cập nhật theo số serial."
                                                        ></textarea>
                                                        <div
                                                            class="flex items-center justify-between mt-1"
                                                        >
                                                            <p
                                                                class="text-xs text-gray-500"
                                                            >
                                                                Mỗi dòng 1
                                                                serial. Số
                                                                lượng:
                                                                <strong
                                                                    class="text-blue-700"
                                                                    >{{
                                                                        getSerialCount(
                                                                            item,
                                                                        )
                                                                    }}</strong
                                                                >
                                                            </p>
                                                            <p
                                                                v-if="
                                                                    getSerialDuplicates(
                                                                        item,
                                                                    ).length > 0
                                                                "
                                                                class="text-xs text-red-600"
                                                            >
                                                                ⚠ Trùng:
                                                                {{
                                                                    getSerialDuplicates(
                                                                        item,
                                                                    ).join(", ")
                                                                }}
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div v-else class="text-center py-8 text-gray-500">
                                <div class="text-4xl mb-2">📦</div>
                                <p>
                                    {{
                                        isOrderMode
                                            ? "Chọn đơn nhập hàng để bắt đầu nhập sản phẩm"
                                            : "Chọn sản phẩm để nhập"
                                    }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Summary Panel -->
                <div class="col-span-4">
                    <div
                        class="bg-white rounded-lg border border-gray-200 sticky top-6"
                    >
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900">
                                📊 Tổng quan
                            </h3>
                        </div>
                        <div class="p-6">
                            <div v-if="selectedOrder" class="mb-6">
                                <div
                                    class="flex justify-between text-sm text-gray-600 mb-2"
                                >
                                    <span>Tiến độ nhập hàng</span>
                                    <span>{{ progressPercent }}%</span>
                                </div>
                                <div
                                    class="w-full bg-gray-200 rounded-full h-2"
                                >
                                    <div
                                        class="bg-blue-500 h-2 rounded-full"
                                        :style="`width: ${progressPercent}%`"
                                    ></div>
                                </div>
                            </div>

                            <div class="space-y-4">
                                <div
                                    class="flex justify-between py-2 border-b border-gray-100"
                                >
                                    <span class="text-gray-600"
                                        >Số mặt hàng:</span
                                    ><span class="font-medium">{{
                                        totalItems
                                    }}</span>
                                </div>
                                <div
                                    class="flex justify-between py-2 border-b border-gray-100"
                                >
                                    <span class="text-gray-600"
                                        >Tổng số lượng:</span
                                    ><span class="font-medium">{{
                                        totalQuantity
                                    }}</span>
                                </div>
                                <div
                                    class="flex justify-between py-2 border-b border-gray-100"
                                >
                                    <span class="text-gray-600">Tổng tiền:</span
                                    ><span class="font-medium text-lg">{{
                                        formatCurrency(totalAmount)
                                    }}</span>
                                </div>
                            </div>

                            <!-- Payment Form -->
                            <div
                                class="mt-6 p-4 bg-gray-50 border border-gray-200 rounded-md"
                            >
                                <div
                                    class="text-sm font-medium text-gray-900 mb-3"
                                >
                                    💳 Thanh toán
                                </div>
                                <div class="flex gap-2 mb-3">
                                    <button
                                        type="button"
                                        @click="onPaymentTypeChange('full')"
                                        :class="[
                                            'px-3 py-1 rounded text-sm',
                                            form.payment_type === 'full'
                                                ? 'bg-blue-600 text-white'
                                                : 'bg-white border',
                                        ]"
                                    >
                                        Thanh toán toàn bộ
                                    </button>
                                    <button
                                        type="button"
                                        @click="onPaymentTypeChange('partial')"
                                        :class="[
                                            'px-3 py-1 rounded text-sm',
                                            form.payment_type === 'partial'
                                                ? 'bg-blue-600 text-white'
                                                : 'bg-white border',
                                        ]"
                                    >
                                        Thanh toán một phần
                                    </button>
                                    <button
                                        type="button"
                                        @click="onPaymentTypeChange('debt')"
                                        :class="[
                                            'px-3 py-1 rounded text-sm',
                                            form.payment_type === 'debt'
                                                ? 'bg-blue-600 text-white'
                                                : 'bg-white border',
                                        ]"
                                    >
                                        Lưu công nợ
                                    </button>
                                </div>

                                <div
                                    v-if="form.payment_type === 'partial'"
                                    class="mb-3"
                                >
                                    <label
                                        class="block text-sm text-gray-700 mb-1"
                                        >Số tiền đã thanh toán</label
                                    >
                                    <input
                                        type="number"
                                        min="0"
                                        :max="totalAmount"
                                        step="0.01"
                                        v-model.number="form.paid"
                                        class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500"
                                        placeholder="Nhập số tiền đã thanh toán"
                                    />
                                    <div
                                        v-if="
                                            form.paid > totalAmount ||
                                            form.paid < 0
                                        "
                                        class="mt-1 text-xs text-red-600"
                                    >
                                        Số tiền thanh toán không hợp lệ
                                    </div>
                                </div>

                                <div
                                    class="text-sm text-gray-700 flex justify-between"
                                >
                                    <span>Còn nợ sau phiếu này:</span>
                                    <span
                                        class="font-semibold"
                                        :class="
                                            needPay > 0
                                                ? 'text-red-600'
                                                : 'text-emerald-600'
                                        "
                                        >{{ formatCurrency(needPay) }}</span
                                    >
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="mt-6 space-y-3">
                                <button
                                    @click="saveReceipt"
                                    :disabled="loading || !canSave"
                                    class="w-full px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    <span v-if="loading">Đang lưu...</span>
                                    <span v-else>💾 Lưu phiếu nhập</span>
                                </button>
                                <button
                                    @click="goBack"
                                    class="w-full px-4 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50"
                                >
                                    ← Quay lại danh sách
                                </button>
                            </div>

                            <!-- Validation Messages -->
                            <div
                                v-if="!canSave && form.items.length"
                                class="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded-md text-sm text-yellow-800"
                            >
                                <div class="font-medium mb-1">
                                    Chưa thể lưu phiếu nhập:
                                </div>
                                <ul class="list-disc list-inside space-y-1">
                                    <li v-if="!form.warehouse_id">
                                        Chưa chọn kho nhập
                                    </li>
                                    <li
                                        v-if="
                                            !form.items.some(
                                                (i) => i.quantity_received > 0,
                                            )
                                        "
                                    >
                                        Chưa nhập số lượng cho sản phẩm nào
                                    </li>
                                    <li
                                        v-if="
                                            form.items.some(
                                                (i) =>
                                                    i.quantity_received >
                                                    (i.remaining_quantity ||
                                                        i.quantity),
                                            )
                                        "
                                    >
                                        Số lượng nhập vượt quá số lượng còn lại
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Empty State -->
        <div v-else class="p-12 text-center">
            <div class="text-6xl mb-4">🔍</div>
            <h3 class="text-xl font-medium text-gray-900 mb-2">
                Chọn đơn nhập hàng
            </h3>
            <p class="text-gray-600">
                Vui lòng chọn một đơn nhập hàng để bắt đầu tạo phiếu nhập kho
            </p>
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
                    <span class="mr-2">{{
                        notification.type === "success"
                            ? "✅"
                            : notification.type === "error"
                              ? "❌"
                              : "⚠️"
                    }}</span>
                    <span>{{ notification.message }}</span>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, reactive, computed, onMounted, onUnmounted } from "vue";
import purchaseReceiptApi from "../api/purchaseReceiptApi";
import purchaseOrderApi from "../api/purchaseOrderApi";
import warehouseApi from "../api/warehouseApi";
import supplierApi from "../api/supplierApi";
import { productApi } from "../api/productApi";

export default {
    name: "PurchaseReceiptCreate",
    setup() {
        // Mode state
        const mode = ref("order"); // 'order' | 'manual'
        const isOrderMode = computed(() => mode.value === "order");

        // Reactive state
        const loading = ref(false);
        const availableOrders = ref([]);
        const warehouses = ref([]);
        const selectedOrderId = ref("");
        const selectedOrder = ref(null);

        // Supplier / product (manual mode)
        const suppliers = ref([]);
        const products = ref([]);
        const selectedSupplier = ref(null);

        // Search states
        const orderSearch = ref("");
        const warehouseSearch = ref("");
        const supplierSearch = ref("");
        const productSearch = ref("");
        const showOrderDropdown = ref(false);
        const showWarehouseDropdown = ref(false);
        const showSupplierDropdown = ref(false);
        const showProductDropdown = ref(false);

        const notification = ref({ show: false, type: "success", message: "" });

        const form = reactive({
            purchase_order_id: "",
            supplier_id: "",
            warehouse_id: "",
            note: "",
            lot_number: "",
            expiry_date: "",
            payment_type: "debt", // full | partial | debt
            paid: 0,
            items: [],
        });

        // Computed
        const todayDate = computed(
            () => new Date().toISOString().split("T")[0],
        );

        const filteredOrders = computed(() => {
            if (!orderSearch.value) return availableOrders.value.slice(0, 10);
            const s = orderSearch.value.toLowerCase();
            return availableOrders.value
                .filter(
                    (o) =>
                        o.code?.toLowerCase().includes(s) ||
                        o.supplier?.name?.toLowerCase().includes(s),
                )
                .slice(0, 10);
        });

        const filteredWarehouses = computed(() => {
            if (!warehouseSearch.value) return warehouses.value;
            const s = warehouseSearch.value.toLowerCase();
            return warehouses.value.filter(
                (w) =>
                    w.name?.toLowerCase().includes(s) ||
                    w.address?.toLowerCase().includes(s),
            );
        });

        const filteredSuppliers = computed(() => {
            if (!supplierSearch.value) return suppliers.value.slice(0, 15);
            const s = supplierSearch.value.toLowerCase();
            return suppliers.value
                .filter(
                    (sp) =>
                        sp.name?.toLowerCase().includes(s) ||
                        (sp.code || "").toLowerCase().includes(s),
                )
                .slice(0, 15);
        });

        const filteredProducts = computed(() => {
            if (!productSearch.value) return products.value.slice(0, 20);
            const s = productSearch.value.toLowerCase();
            return products.value
                .filter(
                    (p) =>
                        p.name?.toLowerCase().includes(s) ||
                        (p.sku || "").toLowerCase().includes(s),
                )
                .slice(0, 20);
        });

        const totalItems = computed(
            () => form.items.filter((i) => i.quantity_received > 0).length,
        );
        const totalQuantity = computed(() =>
            form.items.reduce((s, i) => s + (i.quantity_received || 0), 0),
        );
        const totalAmount = computed(() =>
            form.items.reduce(
                (s, i) => s + (i.quantity_received || 0) * (i.unit_cost || 0),
                0,
            ),
        );

        // Payment helpers
        const needPay = computed(() => {
            if (form.payment_type === "full") return 0;
            if (form.payment_type === "partial") {
                const paid = Number(form.paid) || 0;
                return Math.max(0, totalAmount.value - paid);
            }
            return totalAmount.value;
        });

        const onPaymentTypeChange = (type) => {
            form.payment_type = type;
            if (type === "full") form.paid = totalAmount.value;
            else if (type === "debt") form.paid = 0;
        };

        const progressPercent = computed(() => {
            if (!isOrderMode.value || !selectedOrder.value) return 0;
            const totalOrdered =
                selectedOrder.value.items?.reduce(
                    (sum, it) => sum + (it.quantity || 0),
                    0,
                ) || 0;
            const receiving = totalQuantity.value;
            return totalOrdered > 0
                ? Math.round((receiving / totalOrdered) * 100)
                : 0;
        });

        const canSave = computed(() => {
            if (!form.warehouse_id) return false;
            if (!form.items.length) return false;
            if (!form.items.some((i) => i.quantity_received > 0)) return false;
            if (isOrderMode.value) {
                if (!selectedOrderId.value && !selectedOrder.value)
                    return false;
                if (
                    form.items.some(
                        (i) =>
                            i.quantity_received >
                            (i.remaining_quantity || i.quantity),
                    )
                )
                    return false;
            } else {
                if (!form.supplier_id) return false;
            }
            if (form.payment_type === "partial") {
                if (form.paid < 0 || form.paid > totalAmount.value)
                    return false;
            }
            return true;
        });

        // Notifications
        const showNotification = (message, type = "success") => {
            notification.value = { show: true, type, message };
            setTimeout(() => (notification.value.show = false), 3000);
        };

        // Fetch methods
        const fetchAvailableOrders = async (searchTerm = "") => {
            try {
                // Only fetch orders eligible for receiving
                const params = {
                    per_page: 100,
                    status: "approved,ordered,partial_received",
                    type: "actual",
                };
                if (searchTerm && searchTerm.length >= 2)
                    params.search = searchTerm;

                let res = purchaseOrderApi.getAvailableOrders
                    ? await purchaseOrderApi.getAvailableOrders(params)
                    : await purchaseOrderApi.getAll(params);

                const raw = res?.data ?? res?.items ?? res ?? [];
                const list = Array.isArray(raw)
                    ? raw
                    : Array.isArray(res?.data?.data)
                      ? res.data.data
                      : [];
                let items = Array.isArray(list) ? list : [];

                // If nothing comes back, relax filters to avoid empty dropdown
                if ((!items || items.length === 0) && !searchTerm) {
                    const fb = await purchaseOrderApi.getAll({ per_page: 100 });
                    const fbRaw = fb?.data ?? fb?.items ?? fb ?? [];
                    items = Array.isArray(fbRaw)
                        ? fbRaw
                        : Array.isArray(fb?.data?.data)
                          ? fb.data.data
                          : [];
                }

                availableOrders.value = items;
            } catch (e) {
                console.error("fetchAvailableOrders error:", e);
            }
        };
        const fetchWarehouses = async () => {
            try {
                const r = await warehouseApi.getWarehouses();
                const list = r?.data?.data ?? r?.data ?? r?.items ?? r ?? [];
                warehouses.value = Array.isArray(list) ? list : [];
            } catch (e) {
                console.error("fetchWarehouses error:", e);
            }
        };
        const fetchSuppliers = async () => {
            try {
                const r = await supplierApi.getSuppliers({ per_page: 200 });
                const list = r?.data?.data ?? r?.data ?? r?.items ?? r ?? [];
                suppliers.value = Array.isArray(list) ? list : [];
            } catch (e) {
                console.error("fetchSuppliers error:", e);
            }
        };
        const fetchProducts = async () => {
            try {
                const r = await productApi.getAll({ per_page: 300 });
                const list = r?.data?.data ?? r?.data ?? r?.items ?? r ?? [];
                products.value = Array.isArray(list) ? list : [];
            } catch (e) {
                console.error("fetchProducts error:", e);
            }
        };

        // Search toggles
        const searchOrders = () => {
            showOrderDropdown.value = true;
            fetchAvailableOrders(orderSearch.value);
        };
        const searchWarehouses = () => (showWarehouseDropdown.value = true);
        const searchSuppliers = () => (showSupplierDropdown.value = true);
        const searchProducts = () => (showProductDropdown.value = true);

        // Mode toggle
        const toggleMode = (m) => {
            if (mode.value === m) return;
            mode.value = m;
            if (isOrderMode.value) {
                form.supplier_id = "";
                selectedSupplier.value = null;
                form.items = [];
            } else {
                form.purchase_order_id = "";
                selectedOrderId.value = "";
                selectedOrder.value = null;
                form.items = [];
            }
        };

        // Selectors
        const selectOrder = async (order) => {
            selectedOrderId.value = order.id;
            orderSearch.value = `${order.code} - ${order.supplier?.name}`;
            showOrderDropdown.value = false;
            await onOrderChange();
        };
        const selectWarehouse = (w) => {
            form.warehouse_id = w.id;
            warehouseSearch.value = w.name;
            showWarehouseDropdown.value = false;
        };
        const selectSupplier = (s) => {
            selectedSupplier.value = s;
            form.supplier_id = s.id;
            supplierSearch.value = `${s.name}`;
            showSupplierDropdown.value = false;
        };

        const addProduct = (prod) => {
            if (form.items.some((i) => i.product_id === prod.id)) return;
            form.items.push({
                product_id: prod.id,
                product: prod,
                quantity_received: 0,
                unit_cost:
                    prod.cost_price || prod.import_price || prod.price || 0,
                total_cost: 0,
                condition_status: "good",
                serial_numbers_text: "",
                serial_numbers: [],
            });
            productSearch.value = "";
            showProductDropdown.value = false;
        };
        const removeProduct = (idx) => {
            form.items.splice(idx, 1);
        };

        // Order change handler
        const onOrderChange = async () => {
            if (!selectedOrderId.value) {
                selectedOrder.value = null;
                form.items = [];
                form.purchase_order_id = "";
                return;
            }
            try {
                loading.value = true;
                const res = await purchaseOrderApi.show(selectedOrderId.value);
                const data = res?.data ?? res;
                selectedOrder.value = data;
                form.purchase_order_id = selectedOrderId.value;
                form.items = (data?.items || []).map((it) => ({
                    id: it.id,
                    purchase_order_item_id: it.id,
                    product_id: it.product_id,
                    product: it.product || {
                        id: it.product_id,
                        name: it.product_name || "SP",
                        sku: it.product_sku || "",
                    },
                    quantity: it.quantity,
                    remaining_quantity: it.remaining_quantity || it.quantity,
                    quantity_received: 0,
                    unit_cost: it.price || 0,
                    total_cost: 0,
                    condition_status: "good",
                    note: "",
                    serial_numbers_text: "",
                    serial_numbers: [],
                }));
            } catch (e) {
                console.error(e);
                showNotification("Lỗi tải đơn hàng", "error");
            } finally {
                loading.value = false;
            }
        };

        const calculateItemTotal = (item) => {
            const q = Number(item.quantity_received) || 0;
            const c = Number(item.unit_cost) || 0;
            item.total_cost = q * c;
        };

        // Serial/IMEI helpers
        const parseSerials = (text) => {
            if (!text) return [];
            return text
                .split("\n")
                .map((s) => s.trim())
                .filter((s) => s.length > 0);
        };

        const onSerialInput = (item) => {
            const serials = parseSerials(item.serial_numbers_text);
            item.serial_numbers = [...new Set(serials)]; // deduplicate
            item.quantity_received = item.serial_numbers.length;
            calculateItemTotal(item);
        };

        const getSerialCount = (item) => {
            return parseSerials(item.serial_numbers_text).length;
        };

        const getSerialDuplicates = (item) => {
            const serials = parseSerials(item.serial_numbers_text);
            const seen = new Set();
            const dupes = new Set();
            serials.forEach((s) => {
                if (seen.has(s)) dupes.add(s);
                seen.add(s);
            });
            return [...dupes];
        };

        const buildPayload = () => {
            const purchaseOrderId =
                isOrderMode.value &&
                (selectedOrderId.value || form.purchase_order_id)
                    ? Number(selectedOrderId.value || form.purchase_order_id)
                    : null;
            const supplierId =
                !isOrderMode.value && form.supplier_id
                    ? Number(form.supplier_id)
                    : null;

            const payload = {
                purchase_order_id: purchaseOrderId,
                ...(supplierId != null ? { supplier_id: supplierId } : {}),
                warehouse_id: Number(form.warehouse_id) || null,
                note: form.note,
                lot_number: form.lot_number,
                expiry_date: form.expiry_date,
                payment_type: form.payment_type,
                paid:
                    form.payment_type === "full"
                        ? totalAmount.value
                        : form.payment_type === "partial"
                          ? Number(form.paid) || 0
                          : 0,
                items: form.items
                    .filter((i) => (Number(i.quantity_received) || 0) > 0)
                    .map((i) => {
                        const item = {
                            quantity_received: Number(i.quantity_received) || 0,
                            unit_cost: Number(i.unit_cost) || 0,
                            condition_status: i.condition_status || "good",
                            note: i.note || "",
                        };
                        if (
                            i.product?.track_serial &&
                            i.serial_numbers?.length > 0
                        ) {
                            item.serial_numbers = i.serial_numbers;
                        }
                        if (isOrderMode.value)
                            item.purchase_order_item_id =
                                Number(i.purchase_order_item_id) || null;
                        else item.product_id = Number(i.product_id) || null;
                        return item;
                    }),
            };

            Object.keys(payload).forEach((k) => {
                if (payload[k] == null) delete payload[k];
            });
            return payload;
        };

        const saveReceipt = async () => {
            const clientPayload = buildPayload();
            const v = purchaseReceiptApi.validateReceiptData(clientPayload);
            if (!v.isValid) {
                const firstKey = Object.keys(v.errors)[0];
                const msg =
                    v.errors[firstKey] || "Vui lòng kiểm tra lại thông tin";
                showNotification(Array.isArray(msg) ? msg[0] : msg, "warning");
                return;
            }
            if (!canSave.value) {
                showNotification("Vui lòng kiểm tra lại thông tin", "warning");
                return;
            }
            try {
                loading.value = true;
                const res =
                    await purchaseReceiptApi.createPurchaseReceipt(
                        clientPayload,
                    );
                if (res.success) {
                    showNotification("Tạo phiếu nhập thành công (chờ duyệt)");
                    setTimeout(
                        () =>
                            (window.location.href = `/purchase-receipts/${res.data.id}`),
                        1000,
                    );
                } else throw new Error(res.message || "Lỗi tạo phiếu");
            } catch (e) {
                console.error(e);
                const beErrors = e?.errors;
                if (beErrors && typeof beErrors === "object") {
                    const key = Object.keys(beErrors)[0];
                    const msg = beErrors[key]?.[0] || e.message;
                    showNotification(msg || "Có lỗi khi tạo phiếu", "error");
                } else
                    showNotification(
                        e.message || "Có lỗi khi tạo phiếu",
                        "error",
                    );
            } finally {
                loading.value = false;
            }
        };

        const goBack = () => {
            if (form.items.some((i) => i.quantity_received > 0)) {
                if (
                    confirm(
                        "Bạn có thay đổi chưa lưu. Quay lại sẽ mất dữ liệu. Tiếp tục?",
                    )
                )
                    window.location.href = "/purchase-receipts";
            } else window.location.href = "/purchase-receipts";
        };

        // UI helpers
        const getOrderStatusClass = (status) =>
            ({
                approved: "bg-blue-100 text-blue-800",
                ordered: "bg-purple-100 text-purple-800",
                partial_received: "bg-yellow-100 text-yellow-800",
                received: "bg-green-100 text-green-800",
            })[status] || "bg-gray-100 text-gray-800";

        const getOrderStatusText = (status) =>
            ({
                approved: "Đã duyệt",
                ordered: "Đã đặt hàng",
                partial_received: "Nhập một phần",
                received: "Đã nhập kho",
            })[status] || status;

        const formatDate = (d) =>
            d ? new Date(d).toLocaleDateString("vi-VN") : "";
        const formatCurrency = (a) =>
            new Intl.NumberFormat("vi-VN", {
                style: "currency",
                currency: "VND",
            }).format(a || 0);

        // Events
        let orderSearchTimer = null;
        const onOrderSearchInput = () => {
            showOrderDropdown.value = true;
            if (orderSearchTimer) clearTimeout(orderSearchTimer);
            orderSearchTimer = setTimeout(() => {
                fetchAvailableOrders(orderSearch.value);
            }, 300);
        };
        const handleClickOutside = (e) => {
            if (!e.target.closest(".relative")) {
                showOrderDropdown.value = false;
                showWarehouseDropdown.value = false;
                showSupplierDropdown.value = false;
                showProductDropdown.value = false;
            }
        };
        const handleKeydown = (e) => {
            if (e.key === "Escape") {
                showOrderDropdown.value = false;
                showWarehouseDropdown.value = false;
                showSupplierDropdown.value = false;
                showProductDropdown.value = false;
            }
        };

        onMounted(async () => {
            await Promise.all([
                fetchAvailableOrders(),
                fetchWarehouses(),
                fetchSuppliers(),
                fetchProducts(),
            ]);
            document.addEventListener("click", handleClickOutside);
            document.addEventListener("keydown", handleKeydown);
            // Check if editing existing receipt
            const editRoot = document.getElementById(
                "purchase-receipt-edit-app",
            );
            if (editRoot && editRoot.dataset.id) {
                const id = editRoot.dataset.id;
                try {
                    loading.value = true;
                    const res = await purchaseReceiptApi.show(id);
                    if (res.success) {
                        const r = res.data;
                        // If linked to order switch to order mode
                        if (r.purchase_order_id) {
                            mode.value = "order";
                            // Preload selected order minimal fields
                            selectedOrder.value = r.purchase_order;
                            selectedOrderId.value = r.purchase_order_id;
                        } else {
                            mode.value = "manual";
                            form.supplier_id = r.supplier_id;
                        }
                        form.warehouse_id = r.warehouse_id;
                        form.note = r.note;
                        form.items = r.items.map((it) => ({
                            purchase_order_item_id: it.purchase_order_item_id,
                            product_id: it.product_id,
                            product: it.product, // keep reference for UI
                            quantity_received: it.quantity_received,
                            unit_cost: it.unit_cost,
                            total_cost: it.total_cost,
                            condition_status: it.condition_status,
                            lot_number: it.lot_number,
                            expiry_date: it.expiry_date,
                            note: it.note,
                        }));
                        recalcTotals();
                    }
                } catch (e) {
                    console.error("Failed to load receipt for edit", e);
                } finally {
                    loading.value = false;
                }
                return; // Skip create flow
            }
            const orderId = new URLSearchParams(window.location.search).get(
                "order_id",
            );
            if (orderId) {
                mode.value = "order";
                const order = availableOrders.value.find(
                    (o) => o.id == orderId,
                );
                if (order) await selectOrder(order);
            }
        });

        onUnmounted(() => {
            document.removeEventListener("click", handleClickOutside);
            document.removeEventListener("keydown", handleKeydown);
        });

        return {
            // Mode
            mode,
            isOrderMode,
            toggleMode,
            // State
            loading,
            availableOrders,
            warehouses,
            selectedOrderId,
            selectedOrder,
            suppliers,
            products,
            selectedSupplier,
            orderSearch,
            warehouseSearch,
            supplierSearch,
            productSearch,
            showOrderDropdown,
            showWarehouseDropdown,
            showSupplierDropdown,
            showProductDropdown,
            form,
            notification,
            // Computed
            todayDate,
            filteredOrders,
            filteredWarehouses,
            filteredSuppliers,
            filteredProducts,
            totalItems,
            totalQuantity,
            totalAmount,
            needPay,
            progressPercent,
            canSave,
            // Methods
            searchOrders,
            searchWarehouses,
            searchSuppliers,
            searchProducts,
            selectOrder,
            selectWarehouse,
            selectSupplier,
            addProduct,
            removeProduct,
            onOrderChange,
            calculateItemTotal,
            onSerialInput,
            getSerialCount,
            getSerialDuplicates,
            onPaymentTypeChange,
            saveReceipt,
            goBack,
            getOrderStatusClass,
            getOrderStatusText,
            formatDate,
            formatCurrency,
            showNotification,
            onOrderSearchInput,
        };
    },
};
</script>
