<template>
    <div class="bg-white min-h-screen">
        <div v-if="loading" class="flex justify-center items-center min-h-96">
            <div
                class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"
            ></div>
            <span class="ml-2 text-gray-600">Đang tải...</span>
        </div>

        <div v-else-if="receipt">
            <!-- Header -->
            <div class="p-6 border-b">
                <div class="flex justify-between items-center">
                    <div>
                        <h1 class="text-2xl font-semibold text-gray-900">
                            {{ receipt.code }}
                        </h1>
                        <nav class="mt-1">
                            <ol
                                class="flex items-center space-x-2 text-sm text-gray-500"
                            >
                                <li>
                                    <a
                                        href="/dashboard"
                                        class="hover:text-gray-700"
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
                                <li class="text-gray-900">
                                    {{ receipt.code }}
                                </li>
                            </ol>
                        </nav>
                    </div>
                    <div class="flex items-center space-x-3">
                        <span
                            :class="getStatusClass(receipt.status)"
                            class="px-3 py-1 text-sm font-medium rounded-full"
                        >
                            {{ getStatusText(receipt.status) }}
                        </span>
                        <div class="flex space-x-2">
                            <button
                                @click="goBack"
                                class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
                            >
                                ← Quay lại
                            </button>
                            <button
                                @click="printReceipt"
                                class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
                            >
                                🖨️ In phiếu
                            </button>
                            <button
                                v-if="canEdit"
                                @click="editReceipt"
                                class="px-4 py-2 border border-green-300 text-green-700 rounded-md hover:bg-green-50"
                            >
                                ✏️ Sửa
                            </button>
                            <button
                                v-if="canCancel"
                                @click="cancelReceipt"
                                class="px-4 py-2 border border-red-300 text-red-700 rounded-md hover:bg-red-50"
                            >
                                ❌ Hủy
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Receipt Info -->
            <div class="p-6">
                <div class="grid grid-cols-12 gap-6">
                    <div class="col-span-8">
                        <!-- Basic Info -->
                        <div
                            class="bg-white rounded-lg border border-gray-200 mb-6"
                        >
                            <div class="px-6 py-4 border-b border-gray-200">
                                <h3 class="text-lg font-medium text-gray-900">
                                    ℹ️ Thông tin phiếu nhập kho
                                </h3>
                            </div>
                            <div class="p-6">
                                <div class="grid grid-cols-2 gap-6">
                                    <div>
                                        <div class="mb-4">
                                            <label
                                                class="block text-sm text-gray-500 mb-1"
                                                >Mã phiếu nhập</label
                                            >
                                            <div
                                                class="font-medium text-gray-900"
                                            >
                                                {{ receipt.code }}
                                            </div>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="mb-4">
                                            <label
                                                class="block text-sm text-gray-500 mb-1"
                                                >Đơn nhập hàng</label
                                            >
                                            <div class="font-medium">
                                                <template
                                                    v-if="
                                                        receipt.purchase_order
                                                    "
                                                >
                                                    <a
                                                        :href="`/purchase-orders/${receipt.purchase_order.id}`"
                                                        class="text-blue-600 hover:text-blue-800"
                                                    >
                                                        {{
                                                            receipt
                                                                .purchase_order
                                                                .code
                                                        }}
                                                    </a>
                                                </template>
                                                <template v-else>
                                                    <span
                                                        class="inline-block px-2 py-0.5 text-xs rounded-full bg-emerald-100 text-emerald-700"
                                                        >Độc lập</span
                                                    >
                                                </template>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-6">
                                    <div>
                                        <div class="mb-4">
                                            <label
                                                class="block text-sm text-gray-500 mb-1"
                                                >Nhà cung cấp</label
                                            >
                                            <div
                                                class="font-medium text-gray-900"
                                            >
                                                {{
                                                    receipt.purchase_order
                                                        ?.supplier?.name ||
                                                    receipt.supplier?.name
                                                }}
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                📞
                                                {{
                                                    receipt.purchase_order
                                                        ?.supplier?.phone ||
                                                    receipt.supplier?.phone
                                                }}
                                            </div>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="mb-4">
                                            <label
                                                class="block text-sm text-gray-500 mb-1"
                                                >Kho nhập</label
                                            >
                                            <div
                                                class="font-medium text-gray-900"
                                            >
                                                {{ receipt.warehouse?.name }}
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                📍
                                                {{ receipt.warehouse?.address }}
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-6">
                                    <div>
                                        <div class="mb-4">
                                            <label
                                                class="block text-sm text-gray-500 mb-1"
                                                >Ngày nhập</label
                                            >
                                            <div>
                                                {{
                                                    formatDateTime(
                                                        receipt.received_at,
                                                    )
                                                }}
                                            </div>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="mb-4">
                                            <label
                                                class="block text-sm text-gray-500 mb-1"
                                                >Người nhập</label
                                            >
                                            <div>
                                                {{ receipt.receiver?.name }}
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-6">
                                    <div>
                                        <div class="mb-4">
                                            <label
                                                class="block text-sm text-gray-500 mb-1"
                                                >Ngày tạo</label
                                            >
                                            <div>
                                                {{
                                                    formatDateTime(
                                                        receipt.created_at,
                                                    )
                                                }}
                                            </div>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="mb-4">
                                            <label
                                                class="block text-sm text-gray-500 mb-1"
                                                >Cập nhật lần cuối</label
                                            >
                                            <div>
                                                {{
                                                    formatDateTime(
                                                        receipt.updated_at,
                                                    )
                                                }}
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <label
                                        class="block text-sm text-gray-500 mb-1"
                                        >Ghi chú</label
                                    >
                                    <div>
                                        {{ receipt.note || "Không có ghi chú" }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Items -->
                        <div class="bg-white rounded-lg border border-gray-200">
                            <div
                                class="px-6 py-4 border-b border-gray-200 flex justify-between items-center"
                            >
                                <h3 class="text-lg font-medium text-gray-900">
                                    📦 Danh sách sản phẩm đã nhập ({{
                                        receipt.items?.length || 0
                                    }})
                                </h3>
                                <div class="text-sm text-gray-500">
                                    Tổng: {{ totalQuantity }} sản phẩm
                                </div>
                            </div>
                            <div class="p-6">
                                <div class="overflow-x-auto">
                                    <table
                                        class="w-full border border-gray-200"
                                    >
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th
                                                    class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-[5%]"
                                                >
                                                    STT
                                                </th>
                                                <th
                                                    class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-[25%]"
                                                >
                                                    Sản phẩm
                                                </th>
                                                <th
                                                    class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-[10%]"
                                                >
                                                    Đặt hàng
                                                </th>
                                                <th
                                                    class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-[10%]"
                                                >
                                                    Số lượng nhập
                                                </th>
                                                <th
                                                    class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-[12%]"
                                                >
                                                    Đơn giá
                                                </th>
                                                <th
                                                    class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-[12%]"
                                                >
                                                    Thành tiền
                                                </th>
                                                <th
                                                    class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-[10%]"
                                                >
                                                    Tình trạng
                                                </th>
                                                <th
                                                    class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-[16%]"
                                                >
                                                    Thông tin bổ sung
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody
                                            class="bg-white divide-y divide-gray-200"
                                        >
                                            <template
                                                v-for="(
                                                    item, index
                                                ) in receipt.items"
                                                :key="item.id"
                                            >
                                                <tr>
                                                    <td
                                                        class="px-4 py-4 text-center"
                                                    >
                                                        {{ index + 1 }}
                                                    </td>
                                                    <td class="px-4 py-4">
                                                        <div
                                                            class="font-medium text-gray-900"
                                                        >
                                                            {{
                                                                item.product
                                                                    ?.name
                                                            }}
                                                        </div>
                                                        <div
                                                            class="text-sm text-gray-500"
                                                        >
                                                            SKU:
                                                            {{
                                                                item.product
                                                                    ?.sku
                                                            }}
                                                        </div>
                                                    </td>
                                                    <td
                                                        class="px-4 py-4 text-center"
                                                    >
                                                        <span
                                                            class="inline-block px-2 py-1 text-xs font-medium bg-gray-100 text-gray-800 rounded-full"
                                                        >
                                                            {{
                                                                item
                                                                    .purchase_order_item
                                                                    ?.quantity ||
                                                                0
                                                            }}
                                                        </span>
                                                    </td>
                                                    <td
                                                        class="px-4 py-4 text-center"
                                                    >
                                                        <span
                                                            class="inline-block px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full"
                                                        >
                                                            {{
                                                                item.quantity_received
                                                            }}
                                                        </span>
                                                    </td>
                                                    <td
                                                        class="px-4 py-4 text-right"
                                                    >
                                                        {{
                                                            formatCurrency(
                                                                item.unit_cost,
                                                            )
                                                        }}
                                                    </td>
                                                    <td
                                                        class="px-4 py-4 text-right font-medium"
                                                    >
                                                        {{
                                                            formatCurrency(
                                                                item.total_cost,
                                                            )
                                                        }}
                                                    </td>
                                                    <td
                                                        class="px-4 py-4 text-center"
                                                    >
                                                        <span
                                                            :class="
                                                                getConditionClass(
                                                                    item.condition_status,
                                                                )
                                                            "
                                                            class="inline-block px-2 py-1 text-xs font-medium rounded-full"
                                                        >
                                                            {{
                                                                getConditionText(
                                                                    item.condition_status,
                                                                )
                                                            }}
                                                        </span>
                                                    </td>
                                                    <td class="px-4 py-4">
                                                        <div
                                                            v-if="
                                                                item.lot_number
                                                            "
                                                            class="text-sm"
                                                        >
                                                            <strong
                                                                >Số lô:</strong
                                                            >
                                                            {{
                                                                item.lot_number
                                                            }}
                                                        </div>
                                                        <div
                                                            v-if="
                                                                item.expiry_date
                                                            "
                                                            class="text-sm"
                                                        >
                                                            <strong
                                                                >HSD:</strong
                                                            >
                                                            {{
                                                                formatDate(
                                                                    item.expiry_date,
                                                                )
                                                            }}
                                                        </div>
                                                        <div
                                                            v-if="item.note"
                                                            class="text-sm text-gray-500"
                                                        >
                                                            📝 {{ item.note }}
                                                        </div>
                                                    </td>
                                                </tr>
                                                <!-- Serial/IMEI row -->
                                                <tr
                                                    v-if="
                                                        item.serials &&
                                                        item.serials.length > 0
                                                    "
                                                    :key="'serial-' + item.id"
                                                    class="bg-blue-50"
                                                >
                                                    <td></td>
                                                    <td
                                                        colspan="7"
                                                        class="px-4 py-3"
                                                    >
                                                        <div
                                                            class="flex items-start gap-3"
                                                        >
                                                            <span
                                                                class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-blue-100 text-blue-800 flex-shrink-0 mt-0.5"
                                                            >
                                                                📱 Serial/IMEI
                                                                ({{
                                                                    item.serials
                                                                        .length
                                                                }})
                                                            </span>
                                                            <div
                                                                class="flex flex-wrap gap-1.5"
                                                            >
                                                                <span
                                                                    v-for="serial in item.serials"
                                                                    :key="
                                                                        serial.id
                                                                    "
                                                                    class="inline-flex items-center px-2 py-0.5 rounded text-xs font-mono border"
                                                                    :class="
                                                                        serial.status ===
                                                                        'in_stock'
                                                                            ? 'bg-green-50 border-green-300 text-green-800'
                                                                            : serial.status ===
                                                                                'sold'
                                                                              ? 'bg-blue-50 border-blue-300 text-blue-800'
                                                                              : 'bg-gray-50 border-gray-300 text-gray-700'
                                                                    "
                                                                >
                                                                    {{
                                                                        serial.serial_number
                                                                    }}
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            </template>
                                        </tbody>
                                        <tfoot class="bg-gray-50">
                                            <tr>
                                                <td
                                                    colspan="5"
                                                    class="px-4 py-4 text-right font-medium"
                                                >
                                                    Tổng cộng:
                                                </td>
                                                <td
                                                    class="px-4 py-4 text-right font-bold text-blue-600"
                                                >
                                                    {{
                                                        formatCurrency(
                                                            receipt.total_amount,
                                                        )
                                                    }}
                                                </td>
                                                <td colspan="2"></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Related Purchase Order -->
                        <div
                            class="bg-white rounded-lg border border-gray-200 mt-6"
                            v-if="receipt.purchase_order"
                        >
                            <div class="px-6 py-4 border-b border-gray-200">
                                <h3 class="text-lg font-medium text-gray-900">
                                    🔗 Thông tin đơn nhập hàng liên quan
                                </h3>
                            </div>
                            <div class="p-6">
                                <div class="grid grid-cols-2 gap-6">
                                    <div>
                                        <div class="mb-3">
                                            <strong>Mã đơn hàng:</strong>
                                            <a
                                                :href="`/purchase-orders/${receipt.purchase_order?.id}`"
                                                class="text-blue-600 hover:text-blue-800 ml-2"
                                            >
                                                {{
                                                    receipt.purchase_order.code
                                                }}
                                            </a>
                                        </div>
                                        <div class="mb-3">
                                            <strong
                                                >Trạng thái đơn hàng:</strong
                                            >
                                            <span
                                                :class="
                                                    getOrderStatusClass(
                                                        receipt.purchase_order
                                                            .status,
                                                    )
                                                "
                                                class="inline-block px-2 py-1 text-xs font-medium rounded-full ml-2"
                                            >
                                                {{
                                                    getOrderStatusText(
                                                        receipt.purchase_order
                                                            .status,
                                                    )
                                                }}
                                            </span>
                                        </div>
                                        <div class="mb-3">
                                            <strong>Ngày tạo đơn:</strong>
                                            {{
                                                formatDate(
                                                    receipt.purchase_order
                                                        .created_at,
                                                )
                                            }}
                                        </div>
                                    </div>
                                    <div>
                                        <div class="mb-3">
                                            <strong>Tổng tiền đơn hàng:</strong>
                                            {{
                                                formatCurrency(
                                                    receipt.purchase_order
                                                        .total,
                                                )
                                            }}
                                        </div>
                                        <div class="mb-3">
                                            <strong>Người tạo:</strong>
                                            {{
                                                receipt.purchase_order.creator
                                                    ?.name
                                            }}
                                        </div>
                                        <div class="mb-3">
                                            <strong>Ngày dự kiến:</strong>
                                            {{
                                                formatDate(
                                                    receipt.purchase_order
                                                        .expected_at,
                                                )
                                            }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div class="col-span-4 space-y-6">
                        <!-- Summary -->
                        <div class="bg-white rounded-lg border border-gray-200">
                            <div class="px-6 py-4 border-b border-gray-200">
                                <h3 class="text-lg font-medium text-gray-900">
                                    📊 Tóm tắt phiếu nhập
                                </h3>
                            </div>
                            <div class="p-6">
                                <div class="grid grid-cols-2 gap-4 mb-4">
                                    <div
                                        class="text-center border-r border-gray-200"
                                    >
                                        <div
                                            class="text-2xl font-bold text-blue-600"
                                        >
                                            {{ receipt.items?.length || 0 }}
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            Loại sản phẩm
                                        </div>
                                    </div>
                                    <div class="text-center">
                                        <div
                                            class="text-2xl font-bold text-green-600"
                                        >
                                            {{ totalQuantity }}
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            Tổng số lượng
                                        </div>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-4 mb-4">
                                    <div
                                        class="text-center border-r border-gray-200"
                                    >
                                        <div
                                            class="text-2xl font-bold text-blue-600"
                                        >
                                            {{ goodItems }}
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            Sản phẩm tốt
                                        </div>
                                    </div>
                                    <div class="text-center">
                                        <div
                                            class="text-2xl font-bold text-yellow-600"
                                        >
                                            {{ damagedItems }}
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            Hư hỏng
                                        </div>
                                    </div>
                                </div>

                                <hr class="my-4" />

                                <div
                                    class="flex justify-between items-center text-lg font-bold"
                                >
                                    <span>Tổng giá trị:</span>
                                    <span class="text-blue-600">{{
                                        formatCurrency(receipt.total_amount)
                                    }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Status History -->
                        <div class="bg-white rounded-lg border border-gray-200">
                            <div class="px-6 py-4 border-b border-gray-200">
                                <h3 class="text-lg font-medium text-gray-900">
                                    📈 Lịch sử trạng thái
                                </h3>
                            </div>
                            <div class="p-6">
                                <div class="space-y-4">
                                    <div class="flex items-start space-x-3">
                                        <div
                                            class="w-3 h-3 bg-blue-500 rounded-full mt-1"
                                        ></div>
                                        <div class="flex-1">
                                            <div class="font-medium">
                                                Phiếu nhập được tạo
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                {{
                                                    formatDateTime(
                                                        receipt.created_at,
                                                    )
                                                }}
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                Bởi:
                                                {{ receipt.receiver?.name }}
                                            </div>
                                        </div>
                                    </div>
                                    <div
                                        v-if="receipt.status === 'completed'"
                                        class="flex items-start space-x-3"
                                    >
                                        <div
                                            class="w-3 h-3 bg-green-500 rounded-full mt-1"
                                        ></div>
                                        <div class="flex-1">
                                            <div class="font-medium">
                                                Nhập kho hoàn thành
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                {{
                                                    formatDateTime(
                                                        receipt.received_at,
                                                    )
                                                }}
                                            </div>
                                        </div>
                                    </div>
                                    <div
                                        v-if="receipt.status === 'cancelled'"
                                        class="flex items-start space-x-3"
                                    >
                                        <div
                                            class="w-3 h-3 bg-red-500 rounded-full mt-1"
                                        ></div>
                                        <div class="flex-1">
                                            <div class="font-medium">
                                                Phiếu nhập bị hủy
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                {{
                                                    formatDateTime(
                                                        receipt.updated_at,
                                                    )
                                                }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="bg-white rounded-lg border border-gray-200">
                            <div class="px-6 py-4 border-b border-gray-200">
                                <h3 class="text-lg font-medium text-gray-900">
                                    ⚙️ Thao tác
                                </h3>
                            </div>
                            <div class="p-6 space-y-3">
                                <button
                                    @click="printReceipt"
                                    class="w-full px-4 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50"
                                >
                                    🖨️ In phiếu nhập
                                </button>
                                <button
                                    v-if="canEdit"
                                    @click="editReceipt"
                                    class="w-full px-4 py-2 border border-green-300 text-green-700 rounded-md hover:bg-green-50"
                                >
                                    ✏️ Chỉnh sửa
                                </button>
                                <button
                                    v-if="canCancel"
                                    @click="cancelReceipt"
                                    class="w-full px-4 py-2 border border-red-300 text-red-700 rounded-md hover:bg-red-50"
                                >
                                    ❌ Hủy phiếu nhập
                                </button>
                                <button
                                    @click="viewPurchaseOrder"
                                    class="w-full px-4 py-2 border border-blue-300 text-blue-700 rounded-md hover:bg-blue-50"
                                >
                                    👁️ Xem đơn nhập hàng
                                </button>
                            </div>
                        </div>

                        <!-- Warehouse Info -->
                        <div class="bg-white rounded-lg border border-gray-200">
                            <div class="px-6 py-4 border-b border-gray-200">
                                <h3 class="text-lg font-medium text-gray-900">
                                    🏬 Thông tin kho
                                </h3>
                            </div>
                            <div class="p-6 space-y-3">
                                <div>
                                    <strong>Tên kho:</strong>
                                    {{ receipt.warehouse?.name }}
                                </div>
                                <div>
                                    <strong>Mã kho:</strong>
                                    {{ receipt.warehouse?.code }}
                                </div>
                                <div>
                                    <strong>Địa chỉ:</strong>
                                    {{ receipt.warehouse?.address }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-else class="text-center py-12">
            <div class="text-6xl mb-4">⚠️</div>
            <h3 class="text-lg font-medium text-gray-900 mb-2">
                Không tìm thấy phiếu nhập kho
            </h3>
            <p class="text-gray-500 mb-4">
                Phiếu nhập kho không tồn tại hoặc bạn không có quyền truy cập.
            </p>
            <button
                @click="goBack"
                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
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
import { purchaseReceiptApi } from "../api/purchaseReceiptApi";

export default {
    name: "PurchaseReceiptDetail",
    setup() {
        const loading = ref(true);
        const receipt = ref(null);
        const receiptId = ref(null);

        const notification = ref({
            show: false,
            type: "success",
            message: "",
        });

        const totalQuantity = computed(() => {
            return (
                receipt.value?.items?.reduce(
                    (sum, item) => sum + (item.quantity_received || 0),
                    0,
                ) || 0
            );
        });

        const goodItems = computed(() => {
            return (
                receipt.value?.items?.filter(
                    (item) => item.condition_status === "good",
                ).length || 0
            );
        });

        const damagedItems = computed(() => {
            return (
                receipt.value?.items?.filter(
                    (item) => item.condition_status !== "good",
                ).length || 0
            );
        });

        const canEdit = computed(() => {
            return ["pending", "partial"].includes(receipt.value?.status);
        });

        const canCancel = computed(() => {
            return ["pending", "partial"].includes(receipt.value?.status);
        });

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

        const fetchReceipt = async () => {
            try {
                const response = await purchaseReceiptApi.show(receiptId.value);
                if (response.success) {
                    receipt.value = response.data;
                }
            } catch (error) {
                console.error("Error fetching receipt:", error);
                showNotification("Lỗi khi tải thông tin phiếu nhập", "error");
            } finally {
                loading.value = false;
            }
        };

        const cancelReceipt = async () => {
            const reason = prompt("Vui lòng nhập lý do hủy phiếu nhập:");
            if (!reason) return;

            try {
                const response = await purchaseReceiptApi.cancel(
                    receiptId.value,
                    { reason },
                );
                if (response.success) {
                    showNotification("Hủy phiếu nhập thành công");
                    fetchReceipt();
                }
            } catch (error) {
                console.error("Error cancelling receipt:", error);
                showNotification("Có lỗi xảy ra khi hủy phiếu nhập", "error");
            }
        };

        const editReceipt = () => {
            window.location.href = `/purchase-receipts/${receiptId.value}/edit`;
        };

        const printReceipt = () => {
            window.print();
        };

        const viewPurchaseOrder = () => {
            window.location.href = `/purchase-orders/${receipt.value.purchase_order.id}`;
        };

        const goBack = () => {
            window.location.href = "/purchase-receipts";
        };

        const getStatusClass = (status) => {
            const classes = {
                pending: "bg-yellow-100 text-yellow-800",
                partial: "bg-blue-100 text-blue-800",
                completed: "bg-green-100 text-green-800",
                cancelled: "bg-red-100 text-red-800",
            };
            return classes[status] || "bg-gray-100 text-gray-800";
        };

        const getStatusText = (status) => {
            const texts = {
                pending: "Chờ xử lý",
                partial: "Nhập một phần",
                completed: "Hoàn thành",
                cancelled: "Đã hủy",
            };
            return texts[status] || status;
        };

        const getConditionClass = (condition) => {
            const classes = {
                good: "bg-green-100 text-green-800",
                damaged: "bg-yellow-100 text-yellow-800",
                expired: "bg-red-100 text-red-800",
            };
            return classes[condition] || "bg-gray-100 text-gray-800";
        };

        const getConditionText = (condition) => {
            const texts = {
                good: "Tốt",
                damaged: "Hư hỏng",
                expired: "Hết hạn",
            };
            return texts[condition] || condition;
        };

        const getOrderStatusClass = (status) => {
            const classes = {
                approved: "bg-blue-100 text-blue-800",
                ordered: "bg-purple-100 text-purple-800",
                partial_received: "bg-yellow-100 text-yellow-800",
                received: "bg-green-100 text-green-800",
                completed: "bg-green-100 text-green-800",
            };
            return classes[status] || "bg-gray-100 text-gray-800";
        };

        const getOrderStatusText = (status) => {
            const texts = {
                approved: "Đã duyệt",
                ordered: "Đã đặt hàng",
                partial_received: "Nhập một phần",
                received: "Đã nhập kho",
                completed: "Hoàn thành",
            };
            return texts[status] || status;
        };

        const formatDate = (date) => {
            if (!date) return "";
            return new Date(date).toLocaleDateString("vi-VN");
        };

        const formatDateTime = (date) => {
            if (!date) return "";
            return new Date(date).toLocaleDateString("vi-VN", {
                year: "numeric",
                month: "2-digit",
                day: "2-digit",
                hour: "2-digit",
                minute: "2-digit",
            });
        };

        const formatCurrency = (amount) => {
            return new Intl.NumberFormat("vi-VN", {
                style: "currency",
                currency: "VND",
            }).format(amount || 0);
        };

        onMounted(() => {
            const element = document.getElementById(
                "purchase-receipt-detail-app",
            );
            receiptId.value =
                element?.dataset?.id ||
                window.location.pathname.split("/").pop();
            if (receiptId.value) {
                fetchReceipt();
            }
        });

        return {
            loading,
            receipt,
            notification,
            totalQuantity,
            goodItems,
            damagedItems,
            canEdit,
            canCancel,
            cancelReceipt,
            editReceipt,
            printReceipt,
            viewPurchaseOrder,
            goBack,
            getStatusClass,
            getStatusText,
            getConditionClass,
            getConditionText,
            getOrderStatusClass,
            getOrderStatusText,
            formatDate,
            formatDateTime,
            formatCurrency,
        };
    },
};
</script>
