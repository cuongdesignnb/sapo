<template>
    <div class="bg-white min-h-screen">
        <div v-if="loading" class="flex justify-center items-center py-12">
            <div
                class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"
            ></div>
            <span class="ml-2 text-gray-600">Đang tải...</span>
        </div>

        <div v-else-if="order">
            <!-- Header -->
            <div class="p-6 border-b bg-white sticky top-0 z-10">
                <div class="flex justify-between items-center">
                    <div>
                        <h1 class="text-2xl font-semibold text-gray-900">
                            {{ order.code }}
                        </h1>
                        <div class="text-sm text-gray-500 mt-1">
                            <span>Trang chủ</span> /
                            <span>Đơn nhập hàng</span> /
                            <span class="text-gray-900">{{ order.code }}</span>
                        </div>
                    </div>
                    <div class="flex items-center space-x-3">
                        <span
                            :class="getStatusBadgeClass(order.status)"
                            class="px-3 py-1 text-sm rounded-full flex items-center space-x-2"
                        >
                            <span>{{
                                purchaseOrderApi.getStatusText(order.status)
                            }}</span>
                            <span
                                v-if="order.is_order_only"
                                class="ml-2 px-2 py-0.5 text-[10px] rounded bg-indigo-100 text-indigo-700 uppercase"
                                >Sắp nhập</span
                            >
                        </span>
                        <div class="flex space-x-2">
                            <button
                                @click="goBack"
                                class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
                            >
                                ← Quay lại
                            </button>
                            <button
                                v-if="canEdit"
                                @click="editOrder"
                                class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
                            >
                                ✏️ Sửa
                            </button>
                            <button
                                v-if="canApprove"
                                @click="approveOrder"
                                class="px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600"
                            >
                                ✅ Duyệt
                            </button>
                            <button
                                v-if="canReject"
                                @click="rejectOrder"
                                class="px-4 py-2 border border-red-300 text-red-700 rounded-md hover:bg-red-50"
                            >
                                ❌ Từ chối
                            </button>
                            <button
                                v-if="showConvertButton"
                                @click="convertOrder"
                                class="px-4 py-2 bg-indigo-500 text-white rounded-md hover:bg-indigo-600"
                            >
                                🔄 Chuyển thành đơn thực tế
                            </button>
                            <button
                                v-else-if="canCreateReceipt"
                                @click="createReceipt"
                                class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600"
                            >
                                📦 Tạo phiếu nhập
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex">
                <!-- Main Content -->
                <div class="flex-1 p-6">
                    <!-- Basic Info -->
                    <div
                        class="bg-white border border-gray-200 rounded-lg mb-6"
                    >
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h2
                                class="text-lg font-medium text-gray-900 flex items-center"
                            >
                                ℹ️ Thông tin đơn nhập hàng
                            </h2>
                        </div>
                        <div class="p-6">
                            <div class="grid grid-cols-2 gap-6">
                                <div>
                                    <label
                                        class="block text-sm font-medium text-gray-500 mb-1"
                                        >Nhà cung cấp</label
                                    >
                                    <div class="font-medium">
                                        {{ order.supplier?.name }}
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        📞 {{ order.supplier?.phone }}
                                        <span
                                            v-if="order.supplier?.email"
                                            class="ml-3"
                                        >
                                            ✉️ {{ order.supplier?.email }}
                                        </span>
                                    </div>
                                </div>
                                <div>
                                    <label
                                        class="block text-sm font-medium text-gray-500 mb-1"
                                        >Kho nhập</label
                                    >
                                    <div class="font-medium">
                                        {{ order.warehouse?.name }}
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        📍 {{ order.warehouse?.address }}
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-6 mt-6">
                                <div>
                                    <label
                                        class="block text-sm font-medium text-gray-500 mb-1"
                                        >Ngày tạo</label
                                    >
                                    <div>
                                        {{
                                            purchaseOrderApi.formatDateTime(
                                                order.created_at
                                            )
                                        }}
                                    </div>
                                </div>
                                <div>
                                    <label
                                        class="block text-sm font-medium text-gray-500 mb-1"
                                        >Ngày dự kiến nhập</label
                                    >
                                    <div>
                                        {{
                                            purchaseOrderApi.formatDate(
                                                order.expected_at
                                            ) || "Chưa xác định"
                                        }}
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-6 mt-6">
                                <div>
                                    <label
                                        class="block text-sm font-medium text-gray-500 mb-1"
                                        >Người tạo</label
                                    >
                                    <div>{{ order.creator?.name }}</div>
                                </div>
                                <div>
                                    <label
                                        class="block text-sm font-medium text-gray-500 mb-1"
                                        >Cập nhật lần cuối</label
                                    >
                                    <div>
                                        {{
                                            purchaseOrderApi.formatDateTime(
                                                order.updated_at
                                            )
                                        }}
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-6 mt-6">
                                <div>
                                    <label
                                        class="block text-sm font-medium text-gray-500 mb-1"
                                        >Người liên hệ giao hàng</label
                                    >
                                    <div>
                                        {{
                                            order.delivery_contact || "Chưa có"
                                        }}
                                    </div>
                                </div>
                                <div>
                                    <label
                                        class="block text-sm font-medium text-gray-500 mb-1"
                                        >Số điện thoại liên hệ</label
                                    >
                                    <div>
                                        {{ order.delivery_phone || "Chưa có" }}
                                    </div>
                                </div>
                            </div>

                            <div class="mt-6">
                                <label
                                    class="block text-sm font-medium text-gray-500 mb-1"
                                    >Địa chỉ giao hàng</label
                                >
                                <div>
                                    {{ order.delivery_address || "Chưa có" }}
                                </div>
                            </div>

                            <div class="mt-6">
                                <label
                                    class="block text-sm font-medium text-gray-500 mb-1"
                                    >Ghi chú</label
                                >
                                <div>
                                    {{ order.note || "Không có ghi chú" }}
                                </div>
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
                                📦 Danh sách sản phẩm ({{
                                    order.items?.length || 0
                                }})
                            </h2>
                            <div class="text-sm text-gray-500">
                                Tổng: {{ totalQuantity }} sản phẩm
                            </div>
                        </div>
                        <div class="p-6">
                            <!-- Product Search -->
                            <div class="mb-4 flex items-center justify-between">
                                <div class="w-1/2">
                                    <input
                                        v-model="productSearch"
                                        type="text"
                                        placeholder="Tìm sản phẩm theo tên hoặc SKU..."
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm"
                                    />
                                </div>
                                <div
                                    class="text-xs text-gray-500"
                                    v-if="productSearch"
                                >
                                    Hiển thị {{ filteredItems.length }} /
                                    {{ order.items?.length || 0 }} sản phẩm phù
                                    hợp
                                </div>
                            </div>
                            <div class="overflow-x-auto">
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
                                                style="width: 10%"
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
                                                style="width: 10%"
                                            >
                                                Đã nhập
                                            </th>
                                            <th
                                                class="text-left p-3 text-sm font-medium text-gray-600"
                                                style="width: 10%"
                                            >
                                                Còn lại
                                            </th>
                                            <th
                                                class="text-left p-3 text-sm font-medium text-gray-600"
                                                style="width: 5%"
                                            >
                                                Tiến độ
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        <tr
                                            v-for="(
                                                item, index
                                            ) in filteredItems"
                                            :key="item.id || index"
                                        >
                                            <td class="p-3 text-center text-sm">
                                                {{ index + 1 }}
                                            </td>
                                            <td class="p-3">
                                                <div class="font-medium">
                                                    {{ item.product?.name }}
                                                </div>
                                                <div
                                                    class="text-xs text-gray-500"
                                                >
                                                    SKU: {{ item.product?.sku }}
                                                </div>
                                                <div
                                                    v-if="item.note"
                                                    class="text-xs text-gray-500 mt-1"
                                                >
                                                    📝 {{ item.note }}
                                                </div>
                                            </td>
                                            <td class="p-3 text-center">
                                                <span
                                                    class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded"
                                                    >{{ item.quantity }}</span
                                                >
                                            </td>
                                            <td class="p-3 text-right">
                                                {{
                                                    purchaseOrderApi.formatCurrency(
                                                        item.price
                                                    )
                                                }}
                                            </td>
                                            <td
                                                class="p-3 text-right font-medium"
                                            >
                                                {{
                                                    purchaseOrderApi.formatCurrency(
                                                        item.total
                                                    )
                                                }}
                                            </td>
                                            <td class="p-3 text-center">
                                                <span
                                                    :class="
                                                        item.received_quantity >
                                                        0
                                                            ? 'bg-green-100 text-green-800'
                                                            : 'bg-gray-100 text-gray-800'
                                                    "
                                                    class="px-2 py-1 text-xs rounded"
                                                >
                                                    {{
                                                        item.received_quantity ||
                                                        0
                                                    }}
                                                </span>
                                            </td>
                                            <td class="p-3 text-center">
                                                <span
                                                    :class="
                                                        item.remaining_quantity >
                                                        0
                                                            ? 'bg-yellow-100 text-yellow-800'
                                                            : 'bg-green-100 text-green-800'
                                                    "
                                                    class="px-2 py-1 text-xs rounded"
                                                >
                                                    {{
                                                        item.remaining_quantity ||
                                                        0
                                                    }}
                                                </span>
                                            </td>
                                            <td class="p-3 text-center">
                                                <div
                                                    class="w-full bg-gray-200 rounded-full h-2"
                                                >
                                                    <div
                                                        :class="
                                                            getProgressBarClass(
                                                                item
                                                            )
                                                        "
                                                        class="h-2 rounded-full transition-all duration-300"
                                                        :style="{
                                                            width:
                                                                getProgressPercent(
                                                                    item
                                                                ) + '%',
                                                        }"
                                                    ></div>
                                                </div>
                                                <div
                                                    class="text-xs text-gray-500 mt-1"
                                                >
                                                    {{
                                                        getProgressPercent(
                                                            item
                                                        )
                                                    }}%
                                                </div>
                                            </td>
                                        </tr>
                                        <tr v-if="filteredItems.length === 0">
                                            <td
                                                colspan="8"
                                                class="p-6 text-center text-sm text-gray-500"
                                            >
                                                Không tìm thấy sản phẩm nào phù
                                                hợp với từ khóa "{{
                                                    productSearch
                                                }}".
                                            </td>
                                        </tr>
                                    </tbody>
                                    <tfoot class="bg-gray-50">
                                        <tr>
                                            <td
                                                colspan="4"
                                                class="p-3 text-right font-medium"
                                            >
                                                Tổng cộng:
                                            </td>
                                            <td
                                                class="p-3 text-right font-bold text-blue-600"
                                            >
                                                {{
                                                    purchaseOrderApi.formatCurrency(
                                                        order.total
                                                    )
                                                }}
                                            </td>
                                            <td colspan="3"></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Receipts -->
                    <div
                        v-if="order.receipts?.length > 0"
                        class="bg-white border border-gray-200 rounded-lg mt-6"
                    >
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h2
                                class="text-lg font-medium text-gray-900 flex items-center"
                            >
                                🧾 Phiếu nhập kho ({{ order.receipts.length }})
                            </h2>
                        </div>
                        <div class="p-6">
                            <div class="overflow-x-auto">
                                <table class="w-full">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th
                                                class="text-left p-3 text-sm font-medium text-gray-600"
                                            >
                                                Mã phiếu
                                            </th>
                                            <th
                                                class="text-left p-3 text-sm font-medium text-gray-600"
                                            >
                                                Ngày nhập
                                            </th>
                                            <th
                                                class="text-left p-3 text-sm font-medium text-gray-600"
                                            >
                                                Tổng tiền
                                            </th>
                                            <th
                                                class="text-left p-3 text-sm font-medium text-gray-600"
                                            >
                                                Trạng thái
                                            </th>
                                            <th
                                                class="text-left p-3 text-sm font-medium text-gray-600"
                                            >
                                                Thao tác
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        <tr
                                            v-for="receipt in order.receipts"
                                            :key="receipt.id"
                                        >
                                            <td class="p-3">
                                                {{ receipt.code }}
                                            </td>
                                            <td class="p-3">
                                                {{
                                                    purchaseOrderApi.formatDateTime(
                                                        receipt.received_at
                                                    )
                                                }}
                                            </td>
                                            <td class="p-3">
                                                {{
                                                    purchaseOrderApi.formatCurrency(
                                                        receipt.total_amount
                                                    )
                                                }}
                                            </td>
                                            <td class="p-3">
                                                <span
                                                    :class="
                                                        getReceiptStatusClass(
                                                            receipt.status
                                                        )
                                                    "
                                                    class="px-2 py-1 text-xs rounded-full"
                                                >
                                                    {{
                                                        getReceiptStatusText(
                                                            receipt.status
                                                        )
                                                    }}
                                                </span>
                                            </td>
                                            <td class="p-3">
                                                <button
                                                    @click="
                                                        viewReceipt(receipt.id)
                                                    "
                                                    class="text-blue-600 hover:text-blue-800"
                                                >
                                                    👁️ Xem
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="w-80 p-6 border-l border-gray-200">
                    <!-- Summary -->
                    <div
                        class="bg-white border border-gray-200 rounded-lg mb-6"
                    >
                        <div class="px-4 py-3 border-b border-gray-200">
                            <h3
                                class="text-sm font-medium text-gray-900 flex items-center"
                            >
                                📊 Tóm tắt đơn hàng
                            </h3>
                        </div>
                        <div class="p-4">
                            <div class="grid grid-cols-2 gap-4 mb-4">
                                <div class="text-center border-r">
                                    <div
                                        class="text-2xl font-bold text-blue-600"
                                    >
                                        {{ order.items?.length || 0 }}
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        Loại sản phẩm
                                    </div>
                                </div>
                                <div class="text-center">
                                    <div
                                        class="text-2xl font-bold text-green-600"
                                    >
                                        {{ totalQuantity }}
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        Tổng số lượng
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4 mb-4">
                                <div class="text-center border-r">
                                    <div
                                        class="text-2xl font-bold text-blue-500"
                                    >
                                        {{ totalReceived }}
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        Đã nhập
                                    </div>
                                </div>
                                <div class="text-center">
                                    <div
                                        class="text-2xl font-bold text-yellow-500"
                                    >
                                        {{ totalRemaining }}
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        Còn lại
                                    </div>
                                </div>
                            </div>

                            <div class="border-t pt-4">
                                <div class="flex justify-between text-sm mb-2">
                                    <span>Tạm tính:</span>
                                    <span class="font-medium">{{
                                        purchaseOrderApi.formatCurrency(
                                            order.total
                                        )
                                    }}</span>
                                </div>
                                <div class="flex justify-between text-sm mb-2">
                                    <span>Thuế VAT:</span>
                                    <span>{{
                                        purchaseOrderApi.formatCurrency(
                                            order.tax || 0
                                        )
                                    }}</span>
                                </div>
                                <div class="flex justify-between text-sm mb-2">
                                    <span>Phí khác:</span>
                                    <span>{{
                                        purchaseOrderApi.formatCurrency(
                                            order.additional_fee || 0
                                        )
                                    }}</span>
                                </div>

                                <div class="border-t pt-2 mt-2">
                                    <div
                                        class="flex justify-between text-lg font-bold"
                                    >
                                        <span>Tổng cộng:</span>
                                        <span class="text-blue-600">{{
                                            purchaseOrderApi.formatCurrency(
                                                order.need_pay || order.total
                                            )
                                        }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Progress -->
                    <div
                        class="bg-white border border-gray-200 rounded-lg mb-6"
                    >
                        <div class="px-4 py-3 border-b border-gray-200">
                            <h3
                                class="text-sm font-medium text-gray-900 flex items-center"
                            >
                                📈 Tiến độ nhập hàng
                            </h3>
                        </div>
                        <div class="p-4">
                            <div class="mb-4">
                                <div class="flex justify-between text-sm mb-1">
                                    <span>Tiến độ tổng thể</span>
                                    <span>{{ overallProgress }}%</span>
                                </div>
                                <div
                                    class="w-full bg-gray-200 rounded-full h-3"
                                >
                                    <div
                                        :class="
                                            overallProgress === 100
                                                ? 'bg-green-500'
                                                : 'bg-blue-500'
                                        "
                                        class="h-3 rounded-full transition-all duration-300"
                                        :style="{
                                            width: overallProgress + '%',
                                        }"
                                    ></div>
                                </div>
                            </div>

                            <div class="text-xs text-gray-500 space-y-1">
                                <div class="flex justify-between">
                                    <span>Đã nhập:</span>
                                    <span
                                        >{{ totalReceived }}/{{
                                            totalQuantity
                                        }}</span
                                    >
                                </div>
                                <div class="flex justify-between">
                                    <span>Còn lại:</span>
                                    <span>{{ totalRemaining }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="bg-white border border-gray-200 rounded-lg">
                        <div class="px-4 py-3 border-b border-gray-200">
                            <h3
                                class="text-sm font-medium text-gray-900 flex items-center"
                            >
                                ⚙️ Thao tác
                            </h3>
                        </div>
                        <div class="p-4 space-y-3">
                            <button
                                v-if="canEdit"
                                @click="editOrder"
                                class="w-full px-4 py-2 border border-gray-300 rounded text-gray-700 hover:bg-gray-50"
                            >
                                ✏️ Chỉnh sửa
                            </button>
                            <button
                                v-if="canApprove"
                                @click="approveOrder"
                                class="w-full px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600"
                            >
                                ✅ Duyệt đơn hàng
                            </button>
                            <button
                                v-if="canReject"
                                @click="rejectOrder"
                                class="w-full px-4 py-2 border border-red-300 text-red-700 rounded hover:bg-red-50"
                            >
                                ❌ Từ chối
                            </button>
                            <button
                                v-if="showConvertButton"
                                @click="convertOrder"
                                class="w-full px-4 py-2 bg-indigo-500 text-white rounded hover:bg-indigo-600"
                            >
                                🔄 Chuyển thành đơn thực tế
                            </button>
                            <button
                                v-else-if="canCreateReceipt"
                                @click="createReceipt"
                                class="w-full px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600"
                            >
                                📦 Tạo phiếu nhập
                            </button>
                            <button
                                @click="printOrder"
                                class="w-full px-4 py-2 border border-gray-300 rounded text-gray-700 hover:bg-gray-50"
                            >
                                🖨️ In đơn hàng
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-else class="text-center py-12">
            <div class="text-6xl mb-4">⚠️</div>
            <h2 class="text-xl font-medium text-gray-900 mb-2">
                Không tìm thấy đơn nhập hàng
            </h2>
            <p class="text-gray-500 mb-4">
                Đơn nhập hàng không tồn tại hoặc bạn không có quyền truy cập.
            </p>
            <button
                @click="goBack"
                class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600"
            >
                ← Quay lại
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
import { ref, computed, onMounted } from "vue";
import { purchaseOrderApi } from "../api/purchaseOrderApi";

export default {
    name: "PurchaseOrderDetail",
    setup() {
        const loading = ref(true);
        const order = ref(null);
        const orderId = ref(null);
        // Search state
        const productSearch = ref("");

        // Notification
        const notification = ref({
            show: false,
            type: "success",
            message: "",
        });

        const totalQuantity = computed(() => {
            return (
                order.value?.items?.reduce(
                    (sum, item) => sum + (item.quantity || 0),
                    0
                ) || 0
            );
        });
        // Filter items by product name or SKU
        const filteredItems = computed(() => {
            if (!productSearch.value) return order.value?.items || [];
            const keyword = productSearch.value.trim().toLowerCase();
            return (order.value?.items || []).filter((it) => {
                const name = (it.product?.name || "").toLowerCase();
                const sku = (it.product?.sku || "").toLowerCase();
                return name.includes(keyword) || sku.includes(keyword);
            });
        });

        const totalReceived = computed(() => {
            return (
                order.value?.items?.reduce(
                    (sum, item) => sum + (item.received_quantity || 0),
                    0
                ) || 0
            );
        });

        const totalRemaining = computed(() => {
            return (
                order.value?.items?.reduce(
                    (sum, item) => sum + (item.remaining_quantity || 0),
                    0
                ) || 0
            );
        });

        const overallProgress = computed(() => {
            if (totalQuantity.value === 0) return 0;
            return Math.round(
                (totalReceived.value / totalQuantity.value) * 100
            );
        });

        const canEdit = computed(() => {
            return purchaseOrderApi.canEdit(order.value);
        });

        const canApprove = computed(() => {
            return purchaseOrderApi.canApprove(order.value);
        });

        const canReject = computed(() => {
            return purchaseOrderApi.canReject(order.value);
        });

        const canCreateReceipt = computed(() => {
            return purchaseOrderApi.canCreateReceipt(order.value);
        });

        const showConvertButton = computed(() => {
            if (!order.value) return false;
            return (
                order.value.is_order_only && order.value.status === "approved"
            );
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

        const fetchOrder = async () => {
            try {
                const response = await purchaseOrderApi.getPurchaseOrder(
                    orderId.value
                );
                if (response.success) {
                    order.value = response.data;
                }
            } catch (error) {
                console.error("Error fetching order:", error);
            } finally {
                loading.value = false;
            }
        };

        const approveOrder = async () => {
            if (!confirm("Bạn có chắc chắn muốn duyệt đơn hàng này?")) return;

            try {
                const response =
                    await purchaseOrderApi.updatePurchaseOrderStatus(
                        orderId.value,
                        "approved"
                    );

                if (response.success) {
                    showNotification("Duyệt đơn hàng thành công");
                    fetchOrder();
                } else {
                    showNotification(
                        response.message || "Có lỗi xảy ra",
                        "error"
                    );
                }
            } catch (error) {
                console.error("Error approving order:", error);
                showNotification("Có lỗi xảy ra", "error");
            }
        };

        const rejectOrder = async () => {
            const reason = prompt("Vui lòng nhập lý do từ chối:");
            if (!reason) return;

            try {
                const response =
                    await purchaseOrderApi.updatePurchaseOrderStatus(
                        orderId.value,
                        "cancelled",
                        reason
                    );

                if (response.success) {
                    showNotification("Từ chối đơn hàng thành công");
                    fetchOrder();
                } else {
                    showNotification(
                        response.message || "Có lỗi xảy ra",
                        "error"
                    );
                }
            } catch (error) {
                console.error("Error rejecting order:", error);
                showNotification("Có lỗi xảy ra", "error");
            }
        };

        const editOrder = () => {
            window.location.href = `/purchase-orders/${orderId.value}/edit`;
        };

        const convertOrder = async () => {
            if (!confirm("Chuyển đơn đặt hàng này thành đơn nhập thực tế?"))
                return;
            try {
                const response = await purchaseOrderApi.convertToActual(
                    orderId.value
                );
                if (response.success) {
                    showNotification("Chuyển đổi thành công");
                    fetchOrder();
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

        const createReceipt = () => {
            window.location.href = `/purchase-receipts/create?order_id=${orderId.value}`;
        };

        const viewReceipt = (receiptId) => {
            window.location.href = `/purchase-receipts/${receiptId}`;
        };

        const printOrder = () => {
            window.print();
        };

        const goBack = () => {
            window.location.href = "/purchase-orders";
        };

        const getProgressPercent = (item) => {
            if (item.quantity === 0) return 0;
            return Math.round((item.received_quantity / item.quantity) * 100);
        };

        const getProgressBarClass = (item) => {
            const percent = getProgressPercent(item);
            if (percent === 100) return "bg-green-500";
            if (percent > 0) return "bg-blue-500";
            return "bg-gray-300";
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

        const getReceiptStatusClass = (status) => {
            const classes = {
                pending: "bg-yellow-100 text-yellow-800",
                partial: "bg-blue-100 text-blue-800",
                completed: "bg-green-100 text-green-800",
                cancelled: "bg-red-100 text-red-800",
            };
            return classes[status] || "bg-gray-100 text-gray-800";
        };

        const getReceiptStatusText = (status) => {
            const texts = {
                pending: "Chờ xử lý",
                partial: "Nhập một phần",
                completed: "Hoàn thành",
                cancelled: "Đã hủy",
            };
            return texts[status] || status;
        };

        onMounted(() => {
            const element = document.getElementById(
                "purchase-order-detail-app"
            );
            orderId.value = element?.dataset?.id;
            if (orderId.value) {
                fetchOrder();
            }
        });

        return {
            loading,
            order,
            notification,
            totalQuantity,
            totalReceived,
            totalRemaining,
            overallProgress,
            canEdit,
            canApprove,
            canReject,
            canCreateReceipt,
            showNotification,
            approveOrder,
            rejectOrder,
            editOrder,
            createReceipt,
            viewReceipt,
            printOrder,
            goBack,
            getProgressPercent,
            getProgressBarClass,
            getStatusBadgeClass,
            getReceiptStatusClass,
            getReceiptStatusText,
            purchaseOrderApi,
            showConvertButton,
            convertOrder,
            productSearch,
            filteredItems,
        };
    },
};
</script>
