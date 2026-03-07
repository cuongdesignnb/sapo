<template>
    <!-- Full-page Order Detail (no overlay) -->
    <div class="container mx-auto px-4 py-6">
        <!-- Top bar: Back + Title + Status + Actions -->
        <div class="bg-white rounded-lg shadow-sm border p-4 mb-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <button
                        @click="backToList"
                        class="text-gray-600 hover:text-gray-800"
                    >
                        ← Quay lại danh sách đơn hàng
                    </button>
                    <span class="text-gray-300">|</span>
                    <h2 class="text-xl font-semibold">
                        {{ order?.code || "Đơn hàng" }}
                    </h2>
                    <span
                        :class="getStatusClass(displayStatus)"
                        class="px-3 py-1 rounded-full text-sm font-medium"
                    >
                        {{ getStatusText(displayStatus) }}
                    </span>
                </div>
                <div class="flex items-center space-x-3">
                    <button
                        v-if="canApproveOrder"
                        @click="approveOrder"
                        class="px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600"
                    >
                        Duyệt đơn hàng
                    </button>
                    <!-- Đã bỏ logic khoảng cách/km -->
                    <div
                        v-if="canCreateShipping"
                        class="relative"
                        @keyup.esc="shippingMenuOpen = false"
                    >
                        <button
                            @click="toggleShippingMenu"
                            class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 inline-flex items-center"
                        >
                            <span class="mr-2">🚚 Tạo đơn giao hàng</span>
                            <i class="fas fa-caret-down"></i>
                        </button>
                        <div
                            v-if="shippingMenuOpen"
                            class="absolute right-0 mt-2 w-72 bg-white border rounded-lg shadow-lg z-10"
                        >
                            <button
                                class="w-full text-left px-4 py-2 hover:bg-gray-50"
                                @click="chooseShipping('partner')"
                            >
                                Đẩy qua hãng vận chuyển
                                <div class="text-xs text-gray-500">
                                    Tạo đơn qua đơn vị đã cấu hình, tự động áp
                                    phí.
                                </div>
                            </button>
                            <button
                                class="w-full text-left px-4 py-2 hover:bg-gray-50"
                                @click="quickCreateShipping('self_delivery')"
                            >
                                Tự gọi shipper
                            </button>
                            <button
                                class="w-full text-left px-4 py-2 hover:bg-gray-50"
                                @click="quickCreateShipping('pickup')"
                            >
                                Nhận tại cửa hàng
                            </button>
                        </div>
                    </div>
                    <button
                        v-if="canExportStock"
                        @click="exportStock"
                        class="px-4 py-2 bg-orange-500 text-white rounded hover:bg-orange-600"
                        title="Xuất kho để trừ tồn và chuyển đơn sang trạng thái giao hàng"
                    >
                        Xuất kho
                    </button>
                    <button
                        v-if="canFinishNow"
                        @click="finishOrder"
                        class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700"
                        title="Thanh toán phần còn lại (nếu có) và chuyển đơn sang Hoàn thành"
                    >
                        ✅ Hoàn thành
                    </button>
                    <button
                        v-if="canReturn"
                        @click="openReturnModal"
                        class="px-4 py-2 bg-orange-600 text-white rounded hover:bg-orange-700"
                        title="Tạo đơn trả hàng cho đơn hàng này"
                    >
                        ↩️ Trả hàng
                    </button>
                    <button
                        @click="printOrder"
                        class="px-4 py-2 border border-purple-300 rounded text-purple-600 hover:bg-purple-50"
                    >
                        🖨️ In đơn hàng
                    </button>
                    <button
                        v-if="canEditOrder"
                        @click="editOrder"
                        class="px-4 py-2 border border-blue-300 rounded text-blue-600 hover:bg-blue-50"
                    >
                        Sửa đơn hàng
                    </button>
                    <button
                        v-if="canDeleteOrder"
                        @click="deleteOrder"
                        class="px-4 py-2 border border-red-300 rounded text-red-600 hover:bg-red-50"
                    >
                        🗑️ Xóa đơn
                    </button>
                </div>
            </div>
            <!-- Progress stepper (always visible) -->
            <div class="mt-4">
                <div class="flex items-center">
                    <template v-for="(step, idx) in steps" :key="step.key">
                        <div class="flex items-center">
                            <div
                                :class="[
                                    'w-8 h-8 rounded-full flex items-center justify-center text-sm font-semibold transition-colors duration-150',
                                    idx < currentStep
                                        ? 'bg-green-500 text-white'
                                        : idx === currentStep
                                          ? 'bg-blue-500 text-white'
                                          : 'bg-gray-200 text-gray-600',
                                ]"
                            >
                                {{ idx + 1 }}
                            </div>
                            <div
                                class="ml-2 mr-4 text-sm"
                                :class="[
                                    idx <= currentStep
                                        ? 'text-gray-900 font-medium'
                                        : 'text-gray-400',
                                ]"
                            >
                                {{ step.label }}
                            </div>
                        </div>
                        <div
                            v-if="idx < steps.length - 1"
                            class="flex-1 h-1 transition-colors duration-150"
                            :class="
                                idx < currentStep
                                    ? 'bg-green-500'
                                    : 'bg-gray-200'
                            "
                        ></div>
                    </template>
                </div>
            </div>

            <!-- Partner shipping form (only when choosing provider) -->
            <div
                v-if="showPartnerForm"
                class="mt-6 border rounded-lg p-4 bg-white"
            >
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-semibold">
                        Tạo đơn giao hàng (Chọn đơn vị đã cấu hình)
                    </h3>
                    <button
                        class="text-gray-500 hover:text-gray-700"
                        @click="cancelPartnerForm"
                        type="button"
                    >
                        ×
                    </button>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                    <div class="md:col-span-1">
                        <label class="text-gray-600 text-xs font-medium"
                            >Đơn vị vận chuyển</label
                        >
                        <select
                            v-model="partnerForm.provider_id"
                            class="mt-1 w-full px-3 py-2 border rounded"
                            :disabled="providersLoading"
                            @change="onProviderChange"
                        >
                            <option value="" disabled>-- Chọn đơn vị --</option>
                            <option
                                v-for="p in shippingProviders"
                                :key="p.id"
                                :value="p.id"
                            >
                                {{ p.name }} ({{ p.code }})
                            </option>
                        </select>
                        <div
                            v-if="
                                !providersLoading && !shippingProviders.length
                            "
                            class="text-xs text-red-500 mt-1"
                        >
                            Chưa có đơn vị vận chuyển Active. Vào quản lý vận
                            chuyển để tạo.
                        </div>
                        <div
                            v-else-if="
                                currentProvider &&
                                currentProvider.pricing_config &&
                                currentProvider.pricing_config.base_fee != null
                            "
                            class="text-[11px] text-gray-500 mt-1"
                        >
                            Phí cơ bản NCC:
                            <strong>{{
                                formatCurrency(
                                    Number(
                                        currentProvider.pricing_config.base_fee,
                                    ) || 0,
                                )
                            }}</strong>
                        </div>
                        <div
                            v-else-if="
                                partnerForm.provider_id && !providersLoading
                            "
                            class="text-[11px] text-amber-600 mt-1"
                        >
                            Không thấy base_fee trong pricing_config. Vui lòng
                            mở sửa đơn vị để thêm.
                        </div>
                    </div>
                    <div>
                        <label class="text-gray-600 text-xs font-medium"
                            >Hình thức</label
                        >
                        <select
                            v-model="partnerForm.shipping_method"
                            class="mt-1 w-full px-3 py-2 border rounded"
                        >
                            <option value="standard">Tiêu chuẩn</option>
                            <option value="express">Nhanh</option>
                            <option value="same_day">Trong ngày</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-gray-600 text-xs font-medium"
                            >Phí giao hàng</label
                        >
                        <input
                            type="number"
                            v-model.number="partnerForm.shipping_fee"
                            class="mt-1 w-full px-3 py-2 border rounded bg-gray-50"
                            min="0"
                            :readonly="true"
                            :title="'Tự động lấy từ Phí cơ bản của đơn vị vận chuyển'"
                        />
                        <p class="text-[11px] text-gray-500 mt-1">
                            Phí này chỉ để thống kê chi phí vận chuyển (không
                            cộng vào tổng đơn)
                        </p>
                        <div
                            v-if="feeBreakdown && feeBreakdown.base"
                            class="mt-1 text-[11px] text-gray-600 space-y-0.5"
                        >
                            <div>
                                • Cơ bản:
                                {{ formatCurrency(feeBreakdown.base || 0) }}
                            </div>
                            <div v-if="feeBreakdown.method_adj > 0">
                                • Phụ trội hình thức: +{{
                                    formatCurrency(feeBreakdown.method_adj || 0)
                                }}
                            </div>
                        </div>
                    </div>
                    <div>
                        <label class="text-gray-600 text-xs font-medium"
                            >Bên trả phí</label
                        >
                        <select
                            v-model="partnerForm.payment_by"
                            class="mt-1 w-full px-3 py-2 border rounded"
                        >
                            <option value="sender">Người gửi</option>
                            <option value="receiver">Người nhận</option>
                        </select>
                    </div>
                    <div class="md:col-span-3 pt-2 border-t"></div>
                    <div>
                        <label class="text-gray-600 text-xs font-medium"
                            >Người nhận</label
                        >
                        <input
                            v-model="partnerForm.delivery_contact"
                            class="mt-1 w-full px-3 py-2 border rounded"
                        />
                    </div>
                    <div>
                        <label class="text-gray-600 text-xs font-medium"
                            >SĐT nhận</label
                        >
                        <input
                            v-model="partnerForm.delivery_phone"
                            class="mt-1 w-full px-3 py-2 border rounded"
                        />
                    </div>
                    <div class="md:col-span-1">
                        <label class="text-gray-600 text-xs font-medium"
                            >Địa chỉ giao</label
                        >
                        <input
                            v-model="partnerForm.delivery_address"
                            class="mt-1 w-full px-3 py-2 border rounded"
                        />
                    </div>
                    <div class="md:col-span-3">
                        <label class="text-gray-600 text-xs font-medium"
                            >Ghi chú</label
                        >
                        <input
                            v-model="partnerForm.note"
                            class="mt-1 w-full px-3 py-2 border rounded"
                            placeholder="Ghi chú cho đơn vị vận chuyển"
                        />
                    </div>
                </div>
                <div class="mt-4 flex gap-3">
                    <button
                        @click="submitPartnerForm"
                        class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
                        :disabled="providersLoading"
                        type="button"
                    >
                        Tạo đơn giao hàng
                    </button>
                    <button
                        @click="cancelPartnerForm"
                        class="px-4 py-2 border rounded"
                        type="button"
                    >
                        Hủy
                    </button>
                </div>
            </div>
        </div>
        <!-- Content -->
        <div class="p-0">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Left Column - Order Info -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Skeleton while loading basic info -->
                    <div v-if="!orderLoaded" class="space-y-4">
                        <div
                            class="h-24 bg-gray-100 animate-pulse rounded"
                        ></div>
                        <div
                            class="h-48 bg-gray-100 animate-pulse rounded"
                        ></div>
                    </div>
                    <template v-else>
                        <!-- Customer Info -->
                        <div class="border rounded-lg p-4">
                            <h3 class="text-lg font-semibold mb-4">
                                Thông tin khách hàng
                            </h3>
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <span class="text-gray-600"
                                        >Tên khách hàng:</span
                                    >
                                    <span class="ml-2 font-medium">{{
                                        order.customer?.name || "N/A"
                                    }}</span>
                                </div>
                                <div>
                                    <span class="text-gray-600"
                                        >Số điện thoại:</span
                                    >
                                    <span class="ml-2">{{
                                        order.customer?.phone || "N/A"
                                    }}</span>
                                </div>
                                <div>
                                    <span class="text-gray-600">Email:</span>
                                    <span class="ml-2">{{
                                        order.customer?.email || "N/A"
                                    }}</span>
                                </div>
                                <div>
                                    <span class="text-gray-600"
                                        >Mã khách hàng:</span
                                    >
                                    <span class="ml-2">{{
                                        order.customer?.code || "N/A"
                                    }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Delivery Info -->
                        <div class="border rounded-lg p-4">
                            <h3 class="text-lg font-semibold mb-4">
                                Thông tin giao hàng
                            </h3>
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <span class="text-gray-600"
                                        >Địa chỉ giao hàng:</span
                                    >
                                    <span class="ml-2">{{
                                        order.delivery_address || "N/A"
                                    }}</span>
                                </div>
                                <div>
                                    <span class="text-gray-600"
                                        >Người nhận:</span
                                    >
                                    <span class="ml-2">{{
                                        order.delivery_contact || "N/A"
                                    }}</span>
                                </div>
                                <div>
                                    <span class="text-gray-600"
                                        >SĐT nhận hàng:</span
                                    >
                                    <span class="ml-2">{{
                                        order.delivery_phone || "N/A"
                                    }}</span>
                                </div>
                                <div>
                                    <span class="text-gray-600"
                                        >Ngày giao hàng:</span
                                    >
                                    <span class="ml-2">{{
                                        order.delivery_date
                                            ? formatDate(order.delivery_date)
                                            : "N/A"
                                    }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Products -->
                        <div class="border rounded-lg p-4">
                            <h3 class="text-lg font-semibold mb-4">
                                Thông tin sản phẩm
                            </h3>
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="text-left p-3">STT</th>
                                            <th class="text-left p-3">Ảnh</th>
                                            <th class="text-left p-3">
                                                Tên sản phẩm
                                            </th>
                                            <th class="text-left p-3">
                                                Số lượng
                                            </th>
                                            <th class="text-left p-3">
                                                Đơn giá
                                            </th>
                                            <th class="text-left p-3">
                                                Chiết khấu
                                            </th>
                                            <th class="text-left p-3">
                                                Thành tiền
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        <tr v-if="loading">
                                            <td
                                                colspan="7"
                                                class="p-3 text-center text-gray-500"
                                            >
                                                <i
                                                    class="fas fa-spinner fa-spin mr-2"
                                                ></i>
                                                Đang tải...
                                            </td>
                                        </tr>
                                        <tr
                                            v-else-if="
                                                !order.items ||
                                                order.items.length === 0
                                            "
                                        >
                                            <td
                                                colspan="7"
                                                class="p-3 text-center text-gray-500"
                                            >
                                                Không có sản phẩm nào
                                            </td>
                                        </tr>
                                        <tr
                                            v-else
                                            v-for="(item, index) in order.items"
                                            :key="item.id"
                                        >
                                            <td class="p-3">{{ index + 1 }}</td>
                                            <td class="p-3">
                                                <div
                                                    class="w-12 h-12 bg-gray-100 rounded flex items-center justify-center"
                                                >
                                                    <span class="text-gray-400"
                                                        >📷</span
                                                    >
                                                </div>
                                            </td>
                                            <td class="p-3">
                                                <div class="font-medium">
                                                    {{
                                                        item.product_name ||
                                                        item.product?.name
                                                    }}
                                                </div>
                                                <div class="text-gray-500">
                                                    {{
                                                        item.sku ||
                                                        item.product?.sku
                                                    }}
                                                </div>
                                            </td>
                                            <td class="p-3">
                                                {{ item.quantity }}
                                            </td>
                                            <td class="p-3">
                                                {{ formatCurrency(item.price) }}
                                            </td>
                                            <td class="p-3">
                                                {{
                                                    formatCurrency(
                                                        item.discount_amount ||
                                                            0,
                                                    )
                                                }}
                                            </td>
                                            <td class="p-3">
                                                {{ formatCurrency(item.total) }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Payment Status -->
                        <div class="border rounded-lg p-4">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-semibold">
                                    Đơn hàng chờ thanh toán
                                </h3>
                                <button
                                    @click="openPaymentModal"
                                    class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600"
                                >
                                    💰 Thanh toán
                                </button>
                            </div>
                            <div class="grid grid-cols-3 gap-4 text-sm">
                                <div class="text-center">
                                    <div class="text-2xl font-bold">
                                        {{
                                            formatCurrency(
                                                order.total - order.paid,
                                            )
                                        }}
                                    </div>
                                    <div class="text-gray-600">
                                        Khách phải trả
                                    </div>
                                </div>
                                <div class="text-center">
                                    <div class="text-2xl font-bold">
                                        {{ formatCurrency(order.paid) }}
                                    </div>
                                    <div class="text-gray-600">
                                        Đã thanh toán
                                    </div>
                                </div>
                                <div class="text-center">
                                    <div class="text-2xl font-bold">
                                        {{ formatCurrency(order.debt) }}
                                    </div>
                                    <div class="text-gray-600">
                                        Còn phải trả
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Shipping & Delivery -->
                        <div class="border rounded-lg p-4">
                            <h3 class="text-lg font-semibold mb-4">
                                Đóng gói và giao hàng
                            </h3>
                            <div
                                v-if="!order.shipping"
                                class="text-center text-gray-500 py-8"
                            >
                                <div class="text-4xl mb-2">📦</div>
                                <div>
                                    Chưa có thông tin đóng gói và giao hàng
                                </div>
                                <div
                                    class="flex justify-center mt-4"
                                    v-if="canCreateShipping"
                                >
                                    <div class="relative">
                                        <button
                                            @click="toggleShippingMenu"
                                            class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 inline-flex items-center"
                                        >
                                            <span class="mr-2"
                                                >🚚 Tạo đơn giao hàng</span
                                            >
                                            <i class="fas fa-caret-down"></i>
                                        </button>
                                        <div
                                            v-if="shippingMenuOpen"
                                            class="absolute left-1/2 -translate-x-1/2 mt-2 w-72 bg-white border rounded-lg shadow-lg z-10"
                                        >
                                            <button
                                                class="w-full text-left px-4 py-2 hover:bg-gray-50"
                                                @click="
                                                    chooseShipping('partner')
                                                "
                                            >
                                                Đẩy qua hãng vận chuyển
                                                <div
                                                    class="text-xs text-gray-500"
                                                >
                                                    Nhập thông tin và tạo vận
                                                    đơn
                                                </div>
                                            </button>
                                            <button
                                                class="w-full text-left px-4 py-2 hover:bg-gray-50"
                                                @click="
                                                    quickCreateShipping(
                                                        'self_delivery',
                                                    )
                                                "
                                            >
                                                Tự gọi shipper
                                            </button>
                                            <button
                                                class="w-full text-left px-4 py-2 hover:bg-gray-50"
                                                @click="
                                                    quickCreateShipping(
                                                        'pickup',
                                                    )
                                                "
                                            >
                                                Nhận tại cửa hàng
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="space-y-3">
                                <div class="flex justify-between">
                                    <span class="text-gray-600"
                                        >Mã vận đơn:</span
                                    >
                                    <span class="font-medium">{{
                                        order.shipping.tracking_number
                                    }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600"
                                        >Đơn vị vận chuyển:</span
                                    >
                                    <span>{{
                                        order.shipping.provider?.name
                                    }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600"
                                        >Phương thức:</span
                                    >
                                    <span>{{
                                        getShippingMethodText(
                                            order.shipping.shipping_method,
                                        )
                                    }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600"
                                        >Phí giao hàng:</span
                                    >
                                    <span>{{
                                        formatCurrency(
                                            order.shipping.shipping_fee,
                                        )
                                    }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600"
                                        >Trạng thái:</span
                                    >
                                    <span
                                        :class="
                                            getShippingStatusClass(
                                                order.shipping.status,
                                            )
                                        "
                                        class="px-2 py-1 rounded-full text-xs"
                                    >
                                        {{
                                            getShippingStatusText(
                                                order.shipping.status,
                                            )
                                        }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Right Column - Order Summary -->
                <div class="space-y-6">
                    <!-- Order Summary -->
                    <div class="border rounded-lg p-4">
                        <h3 class="text-lg font-semibold mb-4">
                            Thông tin đơn hàng
                        </h3>
                        <div class="space-y-3 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600"
                                    >Chính sách giá:</span
                                >
                                <span>Giá bán lẻ</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Bán tại:</span>
                                <span>{{
                                    order.warehouse?.name || "N/A"
                                }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Bán bởi:</span>
                                <span>{{ order.cashier?.name || "N/A" }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600"
                                    >Hạn giao hàng:</span
                                >
                                <span>{{
                                    order.delivery_date
                                        ? formatDate(order.delivery_date)
                                        : "N/A"
                                }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Đường dẫn:</span>
                                <span>{{ order.source || "Web" }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600"
                                    >Kênh bán hàng:</span
                                >
                                <span>{{ order.source || "Web" }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Ngày bán:</span>
                                <span>{{ formatDate(order.created_at) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Order Totals -->
                    <div class="border rounded-lg p-4">
                        <h3 class="text-lg font-semibold mb-4">Tổng tiền</h3>
                        <div class="space-y-3 text-sm">
                            <div class="flex justify-between">
                                <span
                                    >Tổng tiền ({{
                                        order.items?.length || 0
                                    }}
                                    sản phẩm)</span
                                >
                                <span>{{ formatCurrency(order.total) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Chiết khấu</span>
                                <span>{{
                                    formatCurrency(order.discount_amount || 0)
                                }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Phí giao hàng</span>
                                <span>{{
                                    formatCurrency(
                                        (order.shipping &&
                                            order.shipping.shipping_fee) ||
                                            order.shipping_fee ||
                                            0,
                                    )
                                }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Mã giảm giá</span>
                                <span>{{ formatCurrency(0) }}</span>
                            </div>
                            <div class="border-t pt-3">
                                <div class="flex justify-between font-bold">
                                    <span>Khách phải trả</span>
                                    <span>{{
                                        formatCurrency(order.total)
                                    }}</span>
                                </div>
                                <p
                                    class="mt-1 text-[11px] text-gray-500 italic"
                                >
                                    Lưu ý: Phí giao hàng không cộng vào tổng
                                    thanh toán; chỉ dùng để thống kê.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Return Information -->
                    <div v-if="hasReturns" class="border rounded-lg p-4">
                        <h3
                            class="text-lg font-semibold mb-4 flex items-center"
                        >
                            <span class="mr-2">↩️</span>
                            Thông tin trả hàng
                        </h3>
                        <div class="space-y-3">
                            <!-- Return Summary -->
                            <div class="bg-orange-50 p-3 rounded-lg">
                                <div
                                    class="flex justify-between items-center mb-2"
                                >
                                    <span class="font-medium text-orange-800"
                                        >Tổng quan trả hàng</span
                                    >
                                    <span class="text-sm text-orange-600">
                                        {{ totalReturnedQuantity }}/{{
                                            totalOrderQuantity
                                        }}
                                        sản phẩm
                                    </span>
                                </div>
                                <div class="text-sm text-orange-700">
                                    <span v-if="isFullyReturned"
                                        >✅ Đã trả hàng toàn bộ</span
                                    >
                                    <span v-else-if="isPartiallyReturned"
                                        >⚠️ Trả hàng một phần</span
                                    >
                                </div>
                            </div>

                            <!-- Return Orders List -->
                            <div class="space-y-2">
                                <div
                                    v-for="returnOrder in order.returns"
                                    :key="returnOrder.id"
                                    class="border border-gray-200 rounded p-3"
                                >
                                    <div
                                        class="flex justify-between items-start mb-2"
                                    >
                                        <div>
                                            <div class="font-medium">
                                                {{ returnOrder.code }}
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                {{
                                                    formatDate(
                                                        returnOrder.created_at,
                                                    )
                                                }}
                                            </div>
                                        </div>
                                        <span
                                            :class="
                                                getReturnStatusClass(
                                                    returnOrder.status,
                                                )
                                            "
                                            class="px-2 py-1 rounded text-xs font-medium"
                                        >
                                            {{
                                                getReturnStatusText(
                                                    returnOrder.status,
                                                )
                                            }}
                                        </span>
                                    </div>

                                    <!-- Return Items -->
                                    <div class="text-sm">
                                        <div class="text-gray-600 mb-1">
                                            Sản phẩm trả:
                                        </div>
                                        <div class="space-y-1">
                                            <div
                                                v-for="item in returnOrder.items"
                                                :key="item.id"
                                                class="flex justify-between"
                                            >
                                                <span>{{
                                                    item.product?.name || "N/A"
                                                }}</span>
                                                <span
                                                    >× {{ item.quantity }}</span
                                                >
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Return Amount -->
                                    <div
                                        class="mt-2 pt-2 border-t border-gray-100 flex justify-between"
                                    >
                                        <span class="text-sm text-gray-600"
                                            >Giá trị trả:</span
                                        >
                                        <span class="font-medium">{{
                                            formatCurrency(returnOrder.total)
                                        }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div class="border rounded-lg p-4">
                        <h3 class="text-lg font-semibold mb-4">Ghi chú</h3>
                        <div class="text-sm text-gray-600">
                            {{ order.note || "Chưa có ghi chú" }}
                        </div>
                    </div>

                    <!-- Tags -->
                    <div class="border rounded-lg p-4">
                        <h3 class="text-lg font-semibold mb-4">Tags</h3>
                        <div class="text-sm text-gray-600">
                            {{ order.tags || "Chưa có tags" }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment Modal -->
        <div
            v-if="showPaymentModal"
            class="fixed inset-0 bg-black bg-opacity-50 z-60 flex items-center justify-center"
        >
            <div class="bg-white rounded-lg p-6 w-full max-w-md">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium">Thanh toán đơn hàng</h3>
                    <button
                        @click="showPaymentModal = false"
                        class="text-gray-400 hover:text-gray-600"
                    >
                        ×
                    </button>
                </div>

                <div class="space-y-4">
                    <div>
                        <label
                            class="block text-sm font-medium text-gray-700 mb-2"
                            >Số tiền thanh toán</label
                        >
                        <input
                            type="number"
                            v-model="paymentForm.amount"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md"
                            :max="order.debt"
                            min="0"
                        />
                    </div>

                    <div>
                        <label
                            class="block text-sm font-medium text-gray-700 mb-2"
                            >Phương thức thanh toán</label
                        >
                        <select
                            v-model="paymentForm.payment_method"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md"
                        >
                            <option value="cash">Tiền mặt</option>
                            <option value="transfer">Chuyển khoản</option>
                            <option value="card">Thẻ tín dụng</option>
                            <option value="wallet">Ví điện tử</option>
                        </select>
                    </div>

                    <div>
                        <label
                            class="block text-sm font-medium text-gray-700 mb-2"
                            >Mã giao dịch</label
                        >
                        <input
                            type="text"
                            v-model="paymentForm.transaction_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md"
                            placeholder="Nhập mã giao dịch (nếu có)"
                        />
                    </div>

                    <div>
                        <label
                            class="block text-sm font-medium text-gray-700 mb-2"
                            >Ghi chú</label
                        >
                        <textarea
                            v-model="paymentForm.note"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md"
                            rows="3"
                            placeholder="Ghi chú thanh toán..."
                        ></textarea>
                    </div>
                </div>

                <div class="flex justify-end space-x-3 mt-6">
                    <button
                        @click="showPaymentModal = false"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300"
                    >
                        Hủy
                    </button>
                    <button
                        @click="submitPayment"
                        class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700"
                    >
                        Thanh toán
                    </button>
                </div>
            </div>
        </div>

        <!-- Return Modal -->
        <div
            v-if="showReturnModal"
            class="fixed inset-0 bg-black bg-opacity-50 z-60 flex items-center justify-center p-4"
        >
            <div
                class="bg-white rounded-lg w-full max-w-4xl max-h-screen overflow-y-auto"
            >
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold">Tạo đơn trả hàng</h3>
                        <button
                            @click="closeReturnModal"
                            class="text-gray-400 hover:text-gray-600"
                        >
                            <svg
                                class="w-6 h-6"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12"
                                ></path>
                            </svg>
                        </button>
                    </div>

                    <!-- Return Items -->
                    <div class="mb-6">
                        <h4 class="font-medium mb-3">
                            Chọn sản phẩm trả hàng:
                        </h4>
                        <div class="space-y-3 max-h-60 overflow-y-auto">
                            <div
                                v-for="item in returnForm.items"
                                :key="item.order_item_id"
                                class="border rounded-lg p-4"
                            >
                                <div class="flex items-center justify-between">
                                    <div class="flex-1">
                                        <div class="font-medium">
                                            {{ item.product_name }}
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            Đã mua: {{ item.max_quantity }} |
                                            Giá:
                                            {{ formatCurrency(item.price) }}
                                        </div>
                                    </div>
                                    <div class="flex items-center space-x-3">
                                        <div class="flex flex-col">
                                            <label
                                                class="text-xs text-gray-500 mb-1"
                                                >Số lượng trả</label
                                            >
                                            <input
                                                v-model.number="item.quantity"
                                                type="number"
                                                :max="item.max_quantity"
                                                min="0"
                                                class="w-20 px-2 py-1 border rounded text-center"
                                            />
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-3" v-if="item.quantity > 0">
                                    <label
                                        class="block text-xs text-gray-500 mb-1"
                                        >Lý do trả hàng:</label
                                    >
                                    <input
                                        v-model="item.return_reason"
                                        type="text"
                                        placeholder="Lỗi sản phẩm, không đúng mô tả..."
                                        class="w-full px-3 py-2 border rounded-md text-sm"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Return Details -->
                    <div class="space-y-4">
                        <div>
                            <label
                                class="block text-sm font-medium text-gray-700 mb-2"
                                >Lý do trả hàng chung:</label
                            >
                            <select
                                v-model="returnForm.return_reason"
                                class="w-full px-3 py-2 border rounded-md"
                            >
                                <option value="">Chọn lý do...</option>
                                <option value="defective">Sản phẩm lỗi</option>
                                <option value="wrong_item">
                                    Giao sai hàng
                                </option>
                                <option value="not_as_described">
                                    Không đúng mô tả
                                </option>
                                <option value="customer_change_mind">
                                    Khách đổi ý
                                </option>
                                <option value="other">Khác</option>
                            </select>
                        </div>

                        <div>
                            <label
                                class="block text-sm font-medium text-gray-700 mb-2"
                                >Ghi chú:</label
                            >
                            <textarea
                                v-model="returnForm.note"
                                rows="3"
                                class="w-full px-3 py-2 border rounded-md"
                                placeholder="Ghi chú thêm về đơn trả hàng..."
                            ></textarea>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-3 mt-6">
                        <button
                            @click="closeReturnModal"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300"
                        >
                            Hủy
                        </button>
                        <button
                            @click="submitReturn"
                            :disabled="loading"
                            class="px-4 py-2 text-sm font-medium text-white bg-orange-600 rounded-md hover:bg-orange-700 disabled:opacity-50"
                        >
                            {{ loading ? "Đang xử lý..." : "Tạo đơn trả hàng" }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, reactive, computed, onMounted, watch } from "vue";
import { orderApi, orderHelpers } from "../api/orderApi";
import { orderReturnApi } from "../api/orderReturnApi";
import { shippingProviderApi } from "../api/shippingProviderApi.js";

export default {
    name: "OrderDetail",
    props: {
        order: {
            type: Object,
            required: false,
            default: () => ({}),
        },
    },
    emits: [],
    setup(props, { emit }) {
        const loading = ref(false);
        const showPaymentModal = ref(false);
        const showReturnModal = ref(false);
        const shippingMenuOpen = ref(false);
        const showPartnerForm = ref(false);
        const providersLoading = ref(false);
        const shippingProviders = ref([]);
        // Local reactive order state (don't mutate props)
        const order = reactive({ ...(props.order || {}) });
        const partnerForm = ref({
            provider_id: null,
            shipping_method: "standard",
            shipping_fee: 0,
            payment_by: "sender",
            delivery_address: "",
            delivery_phone: "",
            delivery_contact: "",
            note: "",
        });
        const feeBreakdown = ref({ base: 0, method_adj: 0 });
        const paymentForm = ref({
            amount: 0,
            payment_method: "cash",
            transaction_id: "",
            note: "",
        });

        const returnForm = ref({
            items: [],
            return_reason: "",
            note: "",
        });

        // Status normalization for stepper and visibility
        const normalizeStatus = (status) => {
            const raw = status ?? "";
            const s = raw.toString().trim().toLowerCase();
            // Optional: support numeric statuses from backend
            if (/^\d+$/.test(s)) {
                switch (Number(s)) {
                    case 0:
                        return "ordered";
                    case 1:
                        return "approved";
                    case 2:
                        return "shipping"; // created / packing
                    case 3:
                        return "delivered"; // exported / on the way
                    case 4:
                        return "completed";
                    case 9:
                        return "cancelled";
                }
            }
            const map = {
                pending: "ordered",
                ordered: "ordered",
                new: "ordered",
                confirmed: "approved",
                confirm: "approved",
                approved: "approved",
                shipping_created: "shipping",
                "shipping-created": "shipping",
                created_shipping: "shipping",
                processing: "shipping",
                packed: "shipping",
                packing: "shipping",
                shipping: "shipping",
                exported: "delivered",
                out_for_delivery: "delivered",
                on_the_way: "delivered",
                in_transit: "delivered",
                delivered: "delivered",
                completed: "completed",
                done: "completed",
                closed: "completed",
                cancelled: "cancelled",
                canceled: "cancelled",
            };
            return map[s] || s;
        };

        // Steps for progress UI (Sapo-like labels)
        const steps = [
            { key: "ordered", label: "Đặt hàng" },
            { key: "approved", label: "Duyệt" },
            { key: "packing", label: "Đóng gói" },
            { key: "exported", label: "Xuất kho" },
            { key: "completed", label: "Hoàn thành" },
        ];

        // Helpers to detect shipping presence across payload shapes
        const hasShipping = (order) =>
            !!(
                order?.shipping ||
                order?.shipping_order ||
                order?.shipping_id ||
                order?.shipping_status ||
                (Array.isArray(order?.shipments) && order.shipments.length > 0)
            );

        // Map normalized status + order facts to progress step index
        const computeProgressStep = (order) => {
            const s = normalizeStatus(order?.status);
            // Step 5: Hoàn thành
            if (s === "completed") return 4;
            // Step 4: Xuất kho / Đã giao
            if (s === "delivered") return 3;
            // Step 3: Đóng gói / Đang xử lý vận chuyển
            if (s === "shipping") return 2;
            // Step 2: Duyệt (nếu đã tạo vận chuyển thì coi như đang ở bước Đóng gói)
            if (s === "approved") return hasShipping(order) ? 2 : 1;
            // Step 1: Đặt hàng
            if (s === "ordered") return 0;
            // Fallback: nếu đã có shipping record nhưng status không khớp
            if (hasShipping(order)) return 2;
            return 0;
        };

        const currentStep = computed(() => computeProgressStep(order));

        // Button visibility
        const canApproveOrder = computed(() => {
            const s = normalizeStatus(order?.status);
            return s === "ordered";
        });

        const canCreateShipping = computed(() => {
            const s = normalizeStatus(order?.status);
            return s === "approved" && !hasShipping(order);
        });

        const canExportStock = computed(() => {
            const s = normalizeStatus(order?.status);
            if (["delivered", "completed", "cancelled"].includes(s))
                return false;
            // Show when đã tạo vận chuyển/đóng gói (hasShipping) hoặc đang ở trạng thái shipping
            return hasShipping(order) || s === "shipping";
        });

        // Show return button after export/delivery (and also on completed)
        const canReturn = computed(() => {
            const s = normalizeStatus(order?.status);
            return ["delivered", "completed"].includes(s);
        });

        // Allow finishing when delivered but not yet completed
        const canFinishNow = computed(() => {
            const s = normalizeStatus(order?.status);
            return s === "delivered"; // completed already done; other states shouldn't allow finish
        });

        const canCancelOrder = computed(() => {
            const s = normalizeStatus(order?.status);
            return ["ordered", "approved"].includes(s);
        });

        const canEditOrder = computed(() => {
            const s = normalizeStatus(order?.status);
            return s === "ordered";
        });

        const canDeleteOrder = computed(() => {
            return true;
        });

        const remainingDebt = computed(() => {
            return Math.max(0, (order?.total || 0) - (order?.paid || 0));
        });

        // Computed properties for return status
        const hasReturns = computed(() => {
            return order?.returns && order.returns.length > 0;
        });

        const totalReturnedQuantity = computed(() => {
            if (!hasReturns.value) return 0;
            return order.returns.reduce((total, returnOrder) => {
                return (
                    total +
                    returnOrder.items.reduce(
                        (sum, item) => sum + item.quantity,
                        0,
                    )
                );
            }, 0);
        });

        const totalOrderQuantity = computed(() => {
            if (!order?.items) return 0;
            return order.items.reduce((sum, item) => sum + item.quantity, 0);
        });

        const isFullyReturned = computed(() => {
            return (
                hasReturns.value &&
                totalReturnedQuantity.value >= totalOrderQuantity.value
            );
        });

        const isPartiallyReturned = computed(() => {
            return (
                hasReturns.value &&
                totalReturnedQuantity.value > 0 &&
                totalReturnedQuantity.value < totalOrderQuantity.value
            );
        });

        // Enhanced status display with return information
        const displayStatus = computed(() => {
            if (isFullyReturned.value) {
                return "returned";
            } else if (isPartiallyReturned.value) {
                return "partially_returned";
            }
            return order?.status;
        });

        const orderLoaded = ref(false);

        const loadOrderDetail = async () => {
            if (!order?.id) return;

            loading.value = true;
            try {
                const res = await orderApi.getById(order.id);
                // axios interceptor returns response.data directly
                const data = res?.data ?? res;
                if (data) {
                    // Support structure { success: true, data: {...} }
                    const payload = data.success ? data.data : data;
                    Object.assign(order, payload);
                }
                orderLoaded.value = true;
                // Force a reactivity tick for computed step updates
                // no-op assignment to trigger watchers if needed
                order.status = order.status;
            } catch (error) {
                console.error("Error loading order detail:", error);
            } finally {
                loading.value = false;
            }
        };

        const editOrder = () => {
            if (!order?.id) return;
            window.location.href = `/orders/${order.id}/edit`;
        };

        const approveOrder = async () => {
            if (!confirm(`Bạn có chắc chắn muốn duyệt đơn hàng ${order.code}?`))
                return;

            try {
                loading.value = true;
                await orderApi.approveOrder(order.id);
                // Ở lại trang chi tiết và hiển thị CTA tạo đơn vận chuyển
                await loadOrderDetail();
            } catch (error) {
                console.error("Error approving order:", error);
            } finally {
                loading.value = false;
            }
        };

        const toggleShippingMenu = () => {
            shippingMenuOpen.value = !shippingMenuOpen.value;
        };

        const chooseShipping = (type) => {
            shippingMenuOpen.value = false;
            if (type === "partner") {
                openPartnerForm();
            }
        };

        const openPartnerForm = async () => {
            showPartnerForm.value = true;
            if (!shippingProviders.value.length) {
                await loadShippingProviders();
            }
            // Prefill delivery info from order
            partnerForm.value.delivery_address = order.delivery_address || "";
            partnerForm.value.delivery_phone =
                order.delivery_phone || order.customer?.phone || "";
            partnerForm.value.delivery_contact =
                order.delivery_contact || order.customer?.name || "";
        };

        const loadShippingProviders = async () => {
            try {
                providersLoading.value = true;
                const list = await orderApi.getShippingProviders();
                shippingProviders.value = Array.isArray(list) ? list : [];
                console.debug(
                    "[ShipFee] Loaded providers:",
                    shippingProviders.value,
                );
                // Prefetch detail for each provider missing pricing_config.base_fee
                for (const p of shippingProviders.value) {
                    if (
                        !p.pricing_config ||
                        p.pricing_config?.base_fee == null
                    ) {
                        try {
                            const detail = await shippingProviderApi.getById(
                                p.id,
                            );
                            if (detail?.data) {
                                const idx = shippingProviders.value.findIndex(
                                    (x) => x.id === p.id,
                                );
                                if (idx !== -1)
                                    shippingProviders.value[idx] = detail.data;
                            }
                        } catch (err) {
                            console.warn(
                                "[ShipFee] Prefetch detail failed",
                                p.id,
                                err,
                            );
                        }
                    }
                }
                console.debug(
                    "[ShipFee] After prefetch:",
                    shippingProviders.value,
                );
            } catch (e) {
                console.error("Failed to load shipping providers", e);
            } finally {
                providersLoading.value = false;
            }
        };

        const onProviderChange = () => {
            console.debug(
                "[ShipFee] provider changed ->",
                partnerForm.value.provider_id,
            );
            recomputeShippingFee();
        };

        const cancelPartnerForm = () => {
            showPartnerForm.value = false;
            partnerForm.value = {
                provider_id: null,
                shipping_method: "standard",
                shipping_fee: 0,
                payment_by: "sender",
                delivery_address: order.delivery_address || "",
                delivery_phone:
                    order.delivery_phone || order.customer?.phone || "",
                delivery_contact:
                    order.delivery_contact || order.customer?.name || "",
                note: "",
            };
        };

        const submitPartnerForm = async () => {
            try {
                loading.value = true;
                if (!partnerForm.value.provider_id) {
                    alert("Vui lòng chọn đơn vị vận chuyển");
                    return;
                }
                // Map UI shipping_method (standard/express/same_day) to backend allowed values
                // Backend (OrderController@createShipping) expects: third_party, self_delivery, pickup
                // For partner form we always use third_party
                const backendMethod = "third_party";
                const payload = {
                    provider_id: partnerForm.value.provider_id,
                    shipping_method: backendMethod,
                    shipping_fee: partnerForm.value.shipping_fee,
                    payment_by: partnerForm.value.payment_by,
                    delivery_address: partnerForm.value.delivery_address,
                    delivery_phone: partnerForm.value.delivery_phone,
                    delivery_contact: partnerForm.value.delivery_contact,
                    note: partnerForm.value.note,
                    // Provide a provider_name fallback if backend expects it for third_party variant
                    provider_name: currentProvider?.value?.name || undefined,
                    receiver_name: partnerForm.value.delivery_contact,
                    receiver_phone: partnerForm.value.delivery_phone,
                    receiver_address: partnerForm.value.delivery_address,
                };
                console.debug("[ShipFee] createShipping payload:", payload);
                await orderApi.createShipping(order.id, payload);
                await loadOrderDetail();
                showPartnerForm.value = false;
                alert("Tạo đơn giao hàng thành công");
            } catch (error) {
                console.error("Error creating shipping via partner:", error);
                alert(error?.message || "Không tạo được đơn vận chuyển");
            } finally {
                loading.value = false;
            }
        };

        const quickCreateShipping = async (method) => {
            try {
                loading.value = true;
                // Map quick methods to backend values
                // self_delivery -> self_delivery, pickup -> pickup, default -> self_delivery
                let backendMethod = "self_delivery";
                if (method === "pickup") backendMethod = "pickup";
                console.debug(
                    "[ShipFee] quickCreateShipping method mapped:",
                    method,
                    "->",
                    backendMethod,
                );
                await orderApi.createShipping(order.id, {
                    shipping_method: backendMethod,
                });
                await loadOrderDetail();
                alert("Đã tạo đơn giao hàng");
            } catch (error) {
                console.error("Error creating shipping:", error);
                alert(error?.message || "Không tạo được đơn vận chuyển");
            } finally {
                shippingMenuOpen.value = false;
                loading.value = false;
            }
        };

        const exportStock = async () => {
            try {
                loading.value = true;
                await orderApi.exportStock(order.id, {});
                await loadOrderDetail();
                alert("Xuất kho thành công");
            } catch (error) {
                console.error("Error exporting stock:", error);
                alert(error?.message || "Không xuất kho được");
            } finally {
                loading.value = false;
            }
        };

        // One-click finish: pay remaining and mark completed
        const finishOrder = async () => {
            try {
                loading.value = true;
                const remaining = Math.max(
                    0,
                    (order?.total || 0) - (order?.paid || 0),
                );
                if (remaining > 0) {
                    await orderApi.completePayment(order.id, {
                        payment_method: "cash",
                        amount: remaining,
                        note: "Hoàn tất đơn hàng",
                    });
                }
                // Ensure status is completed if backend hasn't flipped yet
                if (normalizeStatus(order?.status) !== "completed") {
                    await orderApi.updateStatus(order.id, {
                        status: "completed",
                        note: "Hoàn tất từ trang chi tiết",
                    });
                }
                await loadOrderDetail();
                alert("Đơn hàng đã hoàn thành");
            } catch (error) {
                console.error("Error finishing order:", error);
                alert(error?.message || "Không thể hoàn thành đơn hàng");
            } finally {
                loading.value = false;
            }
        };

        // Return modal methods
        const openReturnModal = () => {
            // Initialize return form with order items
            returnForm.value.items =
                order.items?.map((item) => ({
                    order_item_id: item.id,
                    product_name: item.product?.name || item.product_name,
                    quantity: 0,
                    max_quantity: item.quantity,
                    return_reason: "",
                    price: item.price,
                })) || [];
            returnForm.value.return_reason = "";
            returnForm.value.note = "";
            showReturnModal.value = true;
        };

        const closeReturnModal = () => {
            showReturnModal.value = false;
            returnForm.value = {
                items: [],
                return_reason: "",
                note: "",
            };
        };

        const submitReturn = async () => {
            try {
                // Filter only items with quantity > 0
                const itemsToReturn = returnForm.value.items.filter(
                    (item) => item.quantity > 0,
                );

                if (itemsToReturn.length === 0) {
                    alert("Vui lòng chọn ít nhất một sản phẩm để trả");
                    return;
                }

                loading.value = true;

                const payload = {
                    items: itemsToReturn.map((item) => ({
                        order_item_id: item.order_item_id,
                        quantity: item.quantity,
                        return_reason: item.return_reason,
                    })),
                    return_reason: returnForm.value.return_reason,
                    note: returnForm.value.note,
                };

                await orderReturnApi.create(order.id, payload);

                closeReturnModal();
                alert("Tạo đơn trả hàng thành công!");
                await loadOrderDetail();
            } catch (error) {
                console.error("Error creating return:", error);
                alert(
                    error?.response?.data?.message ||
                        "Có lỗi xảy ra khi tạo đơn trả hàng",
                );
            } finally {
                loading.value = false;
            }
        };

        const openPaymentModal = () => {
            paymentForm.value.amount = remainingDebt.value;
            showPaymentModal.value = true;
        };

        const closePaymentModal = () => {
            showPaymentModal.value = false;
            resetPaymentForm();
        };

        const resetPaymentForm = () => {
            paymentForm.value = {
                amount: 0,
                payment_method: "cash",
                transaction_id: "",
                note: "",
            };
        };

        const submitPayment = async () => {
            if (!validatePaymentForm()) return;

            try {
                loading.value = true;
                await orderApi.completePayment(order.id, {
                    payment_method: paymentForm.value.payment_method,
                    amount: paymentForm.value.amount,
                    transaction_id: paymentForm.value.transaction_id,
                    note: paymentForm.value.note,
                });
                await loadOrderDetail();
                closePaymentModal();
                alert("Thanh toán thành công!");
            } catch (error) {
                console.error("Error submitting payment:", error);
                alert("Có lỗi xảy ra khi thanh toán");
            } finally {
                loading.value = false;
            }
        };

        const validatePaymentForm = () => {
            if (!paymentForm.value.amount || paymentForm.value.amount <= 0) {
                alert("Vui lòng nhập số tiền thanh toán hợp lệ");
                return false;
            }

            if (paymentForm.value.amount > remainingDebt.value) {
                alert("Số tiền thanh toán không được vượt quá số tiền còn nợ");
                return false;
            }

            return true;
        };

        const cancelOrder = async () => {
            if (
                !confirm(
                    `Bạn có chắc chắn muốn hủy đơn hàng ${order.code}? Hành động này không thể hoàn tác.`,
                )
            )
                return;

            try {
                loading.value = true;
                await orderApi.updateStatus(order.id, {
                    status: "cancelled",
                    note: "Đơn hàng đã được hủy từ chi tiết đơn hàng",
                });
                await loadOrderDetail();
                alert("Hủy đơn hàng thành công!");
            } catch (error) {
                console.error("Error cancelling order:", error);
                alert("Có lỗi xảy ra khi hủy đơn hàng");
            } finally {
                loading.value = false;
            }
        };

        const deleteOrder = async () => {
            if (
                !confirm(
                    `Bạn có chắc chắn muốn XÓA đơn hàng ${order.code}?\n\nLưu ý:\n• Công nợ khách hàng sẽ được hoàn lại\n• Serial/IMEI sẽ trả về kho\n• Tồn kho sẽ được cập nhật\n\nHành động này KHÔNG THỂ hoàn tác.`,
                )
            )
                return;

            try {
                loading.value = true;
                const response = await orderApi.delete(order.id);
                if (response.success) {
                    alert(response.message || "Xóa đơn hàng thành công!");
                    window.location.href = "/orders";
                }
            } catch (error) {
                console.error("Error deleting order:", error);
                alert(error.message || "Có lỗi xảy ra khi xóa đơn hàng");
            } finally {
                loading.value = false;
            }
        };

        const printOrder = () => {
            window.open(`/api/orders/${order.id}/print`, "_blank");
        };

        // Utility functions
        const formatCurrency = (amount) => {
            return orderHelpers.formatCurrency(amount);
        };

        const formatDate = (dateString) => {
            return orderHelpers.formatDate(dateString);
        };

        // Localize more statuses to Vietnamese; fall back to orderHelpers
        const getStatusText = (status) => {
            const s = (status ?? "").toString().trim().toLowerCase();
            const map = {
                pending: "Đặt hàng",
                ordered: "Đặt hàng",
                new: "Đặt hàng",
                confirmed: "Đã duyệt",
                confirm: "Đã duyệt",
                approved: "Đã duyệt",
                shipping_created: "Đã tạo vận chuyển",
                "shipping-created": "Đã tạo vận chuyển",
                created_shipping: "Đã tạo vận chuyển",
                processing: "Đóng gói",
                packed: "Đóng gói",
                packing: "Đóng gói",
                shipping: "Đang giao hàng",
                exported: "Xuất kho",
                out_for_delivery: "Đang giao hàng",
                on_the_way: "Đang giao hàng",
                in_transit: "Đang giao hàng",
                delivered: "Đã giao hàng",
                completed: "Hoàn thành",
                done: "Hoàn thành",
                closed: "Hoàn thành",
                cancelled: "Đã hủy",
                canceled: "Đã hủy",
                // Trạng thái trả hàng
                partially_returned: "Trả hàng một phần",
                returned: "Đã trả hàng",
            };
            return map[s] || orderHelpers.getStatusText(s) || s;
        };

        const getStatusClass = (status) => {
            const s = (status ?? "").toString().trim().toLowerCase();
            const map = {
                pending: "bg-yellow-100 text-yellow-800",
                ordered: "bg-yellow-100 text-yellow-800",
                new: "bg-yellow-100 text-yellow-800",
                confirmed: "bg-blue-100 text-blue-800",
                confirm: "bg-blue-100 text-blue-800",
                approved: "bg-blue-100 text-blue-800",
                shipping_created: "bg-purple-100 text-purple-800",
                "shipping-created": "bg-purple-100 text-purple-800",
                created_shipping: "bg-purple-100 text-purple-800",
                processing: "bg-purple-100 text-purple-800",
                packed: "bg-purple-100 text-purple-800",
                packing: "bg-purple-100 text-purple-800",
                shipping: "bg-indigo-100 text-indigo-800",
                exported: "bg-indigo-100 text-indigo-800",
                out_for_delivery: "bg-indigo-100 text-indigo-800",
                on_the_way: "bg-indigo-100 text-indigo-800",
                in_transit: "bg-indigo-100 text-indigo-800",
                delivered: "bg-green-100 text-green-800",
                completed: "bg-green-100 text-green-800",
                done: "bg-green-100 text-green-800",
                closed: "bg-green-100 text-green-800",
                cancelled: "bg-red-100 text-red-800",
                canceled: "bg-red-100 text-red-800",
                // Trạng thái trả hàng
                partially_returned: "bg-orange-100 text-orange-800",
                returned: "bg-gray-100 text-gray-800",
            };
            return map[s] || orderHelpers.getStatusClass(s);
        };
        // Payment handled via in-page modal (openPaymentModal/submitPayment)

        // Return status helpers
        const getReturnStatusText = (status) => {
            const texts = {
                pending: "Chưa nhận hàng",
                received: "Đã nhận hàng",
                warehoused: "Đã nhập kho",
                refunded: "Đã hoàn tiền",
                cancelled: "Đã hủy",
            };
            return texts[status] || status;
        };

        const getReturnStatusClass = (status) => {
            const colors = {
                pending: "bg-yellow-100 text-yellow-800",
                received: "bg-blue-100 text-blue-800",
                warehoused: "bg-purple-100 text-purple-800",
                refunded: "bg-green-100 text-green-800",
                cancelled: "bg-red-100 text-red-800",
            };
            return colors[status] || "bg-gray-100 text-gray-800";
        };

        const backToList = () => {
            if (document.referrer && document.referrer.includes("/orders")) {
                window.history.back();
            } else {
                window.location.href = "/orders";
            }
        };

        const getShippingMethodText = (method) => {
            const methodMap = {
                standard: "Tiêu chuẩn",
                express: "Nhanh",
                same_day: "Trong ngày",
            };
            return methodMap[method] || method;
        };

        const getShippingStatusText = (status) => {
            const statusMap = {
                pending: "Chờ lấy hàng",
                picked_up: "Đã lấy hàng",
                in_transit: "Đang vận chuyển",
                delivered: "Đã giao hàng",
                failed: "Giao hàng thất bại",
            };
            return statusMap[status] || status;
        };

        const getShippingStatusClass = (status) => {
            const classMap = {
                pending: "bg-yellow-100 text-yellow-800",
                picked_up: "bg-blue-100 text-blue-800",
                in_transit: "bg-purple-100 text-purple-800",
                delivered: "bg-green-100 text-green-800",
                failed: "bg-red-100 text-red-800",
            };
            return classMap[status] || "bg-gray-100 text-gray-800";
        };

        // Initialize data when component mounts
        onMounted(() => {
            if (order?.id) {
                // Always fetch full detail to ensure shipping + latest status
                // Avoids stepper regression after hard refresh with partial props
                loadOrderDetail();
            } else {
                orderLoaded.value = true;
            }
        });

        // --- Auto shipping fee logic (mirrors ShippingForm.vue) ---
        // --- Dynamic shipping fee calculation using provider.pricing_config ---
        const currentProvider = computed(() => {
            return (
                shippingProviders.value.find(
                    (p) => p.id == partnerForm.value.provider_id,
                ) || null
            );
        });

        const recomputeShippingFee = async () => {
            const providerId = partnerForm.value.provider_id;
            if (!providerId) {
                partnerForm.value.shipping_fee = 0;
                feeBreakdown.value = { base: 0, method_adj: 0 };
                return;
            }
            let provider = currentProvider.value;

            // Fallback: fetch full detail if missing pricing_config or base is undefined
            const needFetch =
                !provider ||
                !provider.pricing_config ||
                provider.pricing_config?.base_fee == null;
            if (needFetch) {
                try {
                    const detail =
                        await shippingProviderApi.getById(providerId);
                    if (detail?.data) {
                        provider = detail.data;
                        // Update in list so subsequent selections have pricing_config
                        const idx = shippingProviders.value.findIndex(
                            (p) => p.id == providerId,
                        );
                        if (idx !== -1) {
                            shippingProviders.value[idx] = provider;
                        }
                    }
                } catch (e) {
                    console.warn(
                        "Không lấy được chi tiết provider để tính phí",
                        e,
                    );
                }
            }

            if (!provider) {
                partnerForm.value.shipping_fee = 0;
                feeBreakdown.value = { base: 0, method_adj: 0 };
                return;
            }

            // Parse pricing_config if accidentally returned as JSON string
            let pricing = provider.pricing_config;
            if (pricing && typeof pricing === "string") {
                try {
                    pricing = JSON.parse(pricing);
                } catch (_) {
                    pricing = {};
                }
            }
            if (!pricing || typeof pricing !== "object") pricing = {};

            // Multiple fallbacks for base fee
            const rawBase =
                pricing.base_fee ?? provider.base_fee ?? provider.baseFee ?? 0;
            const base = Number(rawBase) || 0;

            // Only use base fee per yêu cầu
            partnerForm.value.shipping_fee = base;
            feeBreakdown.value = { base, method_adj: 0 };

            console.debug(
                "[ShipFee] providerId=",
                providerId,
                "base=",
                base,
                "pricing_config=",
                pricing,
            );
        };

        watch(
            () => partnerForm.value.provider_id,
            () => {
                recomputeShippingFee();
            },
        );

        // Sync when parent prop changes (e.g., navigation)
        watch(
            () => props.order,
            (val) => {
                if (val && typeof val === "object") {
                    Object.assign(order, val);
                }
            },
            { immediate: false },
        );

        return {
            loading,
            orderLoaded,
            showPaymentModal,
            showReturnModal,
            shippingMenuOpen,
            showPartnerForm,
            partnerForm,
            feeBreakdown,
            currentProvider,
            paymentForm,
            returnForm,
            order,
            steps,
            currentStep,
            canApproveOrder,
            canCreateShipping,
            canExportStock,
            canReturn,
            canFinishNow,
            canCancelOrder,
            canDeleteOrder,
            canEditOrder,
            remainingDebt,
            hasReturns,
            totalReturnedQuantity,
            totalOrderQuantity,
            isFullyReturned,
            isPartiallyReturned,
            displayStatus,
            loadOrderDetail,
            editOrder,
            approveOrder,
            toggleShippingMenu,
            chooseShipping,
            submitPartnerForm,
            cancelPartnerForm,
            quickCreateShipping,
            exportStock,
            shippingProviders,
            providersLoading,
            openPartnerForm,
            loadShippingProviders,
            onProviderChange,
            finishOrder,
            openReturnModal,
            closeReturnModal,
            submitReturn,
            openPaymentModal,
            closePaymentModal,
            resetPaymentForm,
            submitPayment,
            validatePaymentForm,
            cancelOrder,
            deleteOrder,
            formatCurrency,
            formatDate,
            getStatusText,
            getStatusClass,
            getReturnStatusText,
            getReturnStatusClass,
            getShippingMethodText,
            getShippingStatusText,
            getShippingStatusClass,
            printOrder,
            backToList,
        };
    },
};
</script>
