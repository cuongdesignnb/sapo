<template>
    <div class="bg-white">
        <!-- Header -->
        <div class="p-6 border-b">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900">
                        Danh sách phiếu chi
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
                            <li class="text-gray-900">Phiếu chi</li>
                        </ol>
                    </nav>
                </div>
                <div class="flex space-x-3">
                    <button
                        @click="showTypeManager = true"
                        class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
                    >
                        ⚙️ Quản lý loại phiếu
                    </button>
                    <button
                        @click="exportData"
                        class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
                    >
                        📥 Xuất file
                    </button>
                    <button
                        @click="openCreateForm"
                        class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700"
                    >
                        ➕ Tạo phiếu chi
                    </button>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="p-6 border-b bg-gray-50">
            <div class="bg-white rounded-lg border border-gray-200 p-6">
                <div class="grid grid-cols-4 gap-4">
                    <div>
                        <label
                            class="block text-sm font-medium text-gray-700 mb-2"
                            >Tìm kiếm</label
                        >
                        <input
                            v-model="filters.search"
                            type="text"
                            placeholder="Tìm mã phiếu, ghi chú..."
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500"
                            @input="debouncedSearch"
                        />
                    </div>
                    <div>
                        <label
                            class="block text-sm font-medium text-gray-700 mb-2"
                            >Trạng thái</label
                        >
                        <select
                            v-model="filters.status"
                            @change="applyFilters"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500"
                        >
                            <option value="">Tất cả trạng thái</option>
                            <option value="draft">Nháp</option>
                            <option value="pending">Chờ duyệt</option>
                            <option value="approved">Đã duyệt</option>
                            <option value="cancelled">Đã hủy</option>
                        </select>
                    </div>
                    <div>
                        <label
                            class="block text-sm font-medium text-gray-700 mb-2"
                            >Từ ngày</label
                        >
                        <input
                            v-model="filters.date_from"
                            type="date"
                            @change="applyFilters"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500"
                        />
                    </div>
                    <div>
                        <label
                            class="block text-sm font-medium text-gray-700 mb-2"
                            >Đến ngày</label
                        >
                        <input
                            v-model="filters.date_to"
                            type="date"
                            @change="applyFilters"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                        >
                            Mã phiếu
                        </th>
                        <th
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                        >
                            Loại phiếu
                        </th>
                        <th
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                        >
                            Người nhận
                        </th>
                        <th
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                        >
                            Số tiền
                        </th>
                        <th
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                        >
                            Chi nhánh
                        </th>
                        <th
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                        >
                            Ngày tạo
                        </th>
                        <th
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                        >
                            Trạng thái
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
                            colspan="8"
                            class="px-6 py-12 text-center text-gray-500"
                        >
                            <div class="flex items-center justify-center">
                                <div
                                    class="animate-spin rounded-full h-6 w-6 border-b-2 border-red-600"
                                ></div>
                                <span class="ml-2">Đang tải...</span>
                            </div>
                        </td>
                    </tr>
                    <tr v-else-if="payments.length === 0">
                        <td
                            colspan="8"
                            class="px-6 py-12 text-center text-gray-500"
                        >
                            Không có phiếu chi nào
                        </td>
                    </tr>
                    <tr
                        v-else
                        v-for="payment in payments"
                        :key="payment.id"
                        class="hover:bg-gray-50"
                    >
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-red-600">
                                {{ payment.code }}
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900">
                                {{ payment.payment_type?.name || "N/A" }}
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900">
                                {{ payment.recipient?.name || "N/A" }}
                            </div>
                            <div class="text-sm text-gray-500">
                                {{
                                    payment.recipient_type === "customer"
                                        ? "Khách hàng"
                                        : "Nhà cung cấp"
                                }}
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900">
                                {{ formatCurrency(payment.amount) }}
                            </div>
                            <div class="text-sm text-gray-500">
                                {{
                                    payment.payment_method === "cash"
                                        ? "Tiền mặt"
                                        : "Chuyển khoản"
                                }}
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900">
                                {{ payment.warehouse?.name || "N/A" }}
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900">
                                {{ formatDate(payment.payment_date) }}
                            </div>
                            <div class="text-sm text-gray-500">
                                {{ formatDate(payment.created_at) }}
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span
                                :class="getStatusClass(payment.status)"
                                class="inline-flex px-2 py-1 text-xs font-semibold rounded-full"
                            >
                                {{ getStatusText(payment.status) }}
                            </span>
                        </td>
                        <td
                            class="px-6 py-4 whitespace-nowrap text-sm font-medium"
                        >
                            <div class="flex space-x-2">
                                <button
                                    @click="viewPayment(payment)"
                                    class="text-blue-600 hover:text-blue-900"
                                >
                                    👁️ Xem
                                </button>
                                <button
                                    v-if="payment.status === 'draft'"
                                    @click="editPayment(payment)"
                                    class="text-green-600 hover:text-green-900"
                                >
                                    ✏️ Sửa
                                </button>
                                <button
                                    v-if="payment.status === 'draft'"
                                    @click="submitForApproval(payment)"
                                    class="text-orange-600 hover:text-orange-900"
                                >
                                    📤 Gửi duyệt
                                </button>
                                <button
                                    v-if="payment.status === 'pending'"
                                    @click="approvePayment(payment)"
                                    class="text-green-600 hover:text-green-900"
                                >
                                    ✅ Duyệt
                                </button>
                                <button
                                    v-if="
                                        ['draft', 'pending'].includes(
                                            payment.status,
                                        )
                                    "
                                    @click="cancelPayment(payment)"
                                    class="text-red-600 hover:text-red-900"
                                >
                                    ❌ Hủy
                                </button>
                                <button
                                    @click="printPayment(payment)"
                                    class="text-purple-600 hover:text-purple-900"
                                >
                                    🖨️ In
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div
            v-if="pagination.last_page > 1"
            class="px-6 py-3 border-t bg-gray-50"
        >
            <div class="flex items-center justify-between">
                <div class="text-sm text-gray-700">
                    Hiển thị {{ pagination.from || 0 }} -
                    {{ pagination.to || 0 }} của {{ pagination.total || 0 }} kết
                    quả
                </div>
                <div class="flex space-x-1">
                    <button
                        @click="changePage(pagination.current_page - 1)"
                        :disabled="pagination.current_page <= 1"
                        class="px-3 py-1 border border-gray-300 rounded text-sm disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50"
                    >
                        Trước
                    </button>
                    <template v-for="page in getPageNumbers()" :key="page">
                        <button
                            v-if="page !== '...'"
                            @click="changePage(page)"
                            :class="[
                                'px-3 py-1 border text-sm rounded',
                                page === pagination.current_page
                                    ? 'bg-red-600 text-white border-red-600'
                                    : 'border-gray-300 hover:bg-gray-50',
                            ]"
                        >
                            {{ page }}
                        </button>
                        <span v-else class="px-3 py-1 text-sm text-gray-500"
                            >...</span
                        >
                    </template>
                    <button
                        @click="changePage(pagination.current_page + 1)"
                        :disabled="
                            pagination.current_page >= pagination.last_page
                        "
                        class="px-3 py-1 border border-gray-300 rounded text-sm disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50"
                    >
                        Sau
                    </button>
                </div>
            </div>
        </div>

        <!-- Create/Edit Modal -->
        <div
            v-if="showCreateForm || showEditForm"
            class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50"
        >
            <div
                class="relative top-20 mx-auto p-5 border w-11/12 max-w-4xl shadow-lg rounded-md bg-white"
            >
                <CashPaymentForm
                    :payment="editingPayment"
                    :payment-types="paymentTypes"
                    :warehouses="warehouses"
                    @submit="handleFormSubmit"
                    @cancel="closeForm"
                    @error="showNotification"
                    @types-changed="loadPaymentTypes"
                />
            </div>
        </div>

        <!-- View Modal -->
        <div
            v-if="showViewModal"
            class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50"
        >
            <div
                class="relative top-20 mx-auto p-5 border w-11/12 max-w-2xl shadow-lg rounded-md bg-white"
            >
                <div class="px-6 py-4 border-b">
                    <h3 class="text-lg font-medium text-gray-900">
                        Chi tiết phiếu chi
                    </h3>
                </div>
                <div class="p-6" v-if="viewingPayment">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <strong>Mã phiếu:</strong> {{ viewingPayment.code }}
                        </div>
                        <div>
                            <strong>Loại phiếu:</strong>
                            {{ viewingPayment.payment_type?.name || "N/A" }}
                        </div>
                        <div>
                            <strong>Người nhận:</strong>
                            {{ viewingPayment.recipient?.name || "N/A" }}
                        </div>
                        <div>
                            <strong>Số tiền:</strong>
                            {{ formatCurrency(viewingPayment.amount) }}
                        </div>
                        <div>
                            <strong>Ngày tạo:</strong>
                            {{ formatDate(viewingPayment.payment_date) }}
                        </div>
                        <div>
                            <strong>Trạng thái:</strong>
                            <span
                                :class="getStatusClass(viewingPayment.status)"
                                class="inline-flex px-2 py-1 text-xs font-semibold rounded-full"
                            >
                                {{ getStatusText(viewingPayment.status) }}
                            </span>
                        </div>
                        <div class="col-span-2">
                            <strong>Ghi chú:</strong>
                            {{ viewingPayment.note || "Không có" }}
                        </div>
                    </div>
                </div>
                <div class="flex justify-end px-6 py-4 border-t">
                    <button
                        @click="showViewModal = false"
                        class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
                    >
                        Đóng
                    </button>
                </div>
            </div>
        </div>

        <!-- Type Manager Modal -->
        <div
            v-if="showTypeManager"
            class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50"
        >
            <div
                class="relative top-10 mx-auto p-5 border w-11/12 max-w-3xl shadow-lg rounded-md bg-white"
            >
                <div
                    class="px-6 py-4 border-b flex justify-between items-center"
                >
                    <h3 class="text-lg font-medium text-gray-900">
                        Quản lý loại phiếu chi
                    </h3>
                    <button
                        @click="showTypeManager = false"
                        class="text-gray-400 hover:text-gray-600 text-xl"
                    >
                        &times;
                    </button>
                </div>
                <div class="p-6">
                    <!-- Add new type form -->
                    <div
                        class="mb-6 bg-red-50 border border-red-200 rounded-lg p-4"
                    >
                        <h4 class="font-medium text-red-900 mb-3">
                            {{
                                editingType
                                    ? "Sửa loại phiếu"
                                    : "Thêm loại phiếu chi mới"
                            }}
                        </h4>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label
                                    class="block text-sm font-medium text-gray-700 mb-1"
                                    >Mã loại
                                    <span class="text-red-500">*</span></label
                                >
                                <input
                                    v-model="typeForm.code"
                                    type="text"
                                    placeholder="VD: TRA_TIEN_NCC"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-red-500 focus:border-red-500"
                                />
                            </div>
                            <div>
                                <label
                                    class="block text-sm font-medium text-gray-700 mb-1"
                                    >Tên loại
                                    <span class="text-red-500">*</span></label
                                >
                                <input
                                    v-model="typeForm.name"
                                    type="text"
                                    placeholder="VD: Trả tiền nhà cung cấp"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-red-500 focus:border-red-500"
                                />
                            </div>
                            <div>
                                <label
                                    class="block text-sm font-medium text-gray-700 mb-1"
                                    >Nhóm đối tượng
                                    <span class="text-red-500">*</span></label
                                >
                                <select
                                    v-model="typeForm.recipient_type"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-red-500 focus:border-red-500"
                                >
                                    <option value="">Chọn nhóm</option>
                                    <option value="customer">Khách hàng</option>
                                    <option value="supplier">
                                        Nhà cung cấp
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label
                                    class="block text-sm font-medium text-gray-700 mb-1"
                                    >Loại tác động
                                    <span class="text-red-500">*</span></label
                                >
                                <select
                                    v-model="typeForm.impact_type"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-red-500 focus:border-red-500"
                                >
                                    <option value="">Chọn loại tác động</option>
                                    <option value="debt">Công nợ</option>
                                    <option value="expense">Chi phí</option>
                                    <option value="advance">Tạm ứng</option>
                                </select>
                            </div>
                            <div>
                                <label
                                    class="block text-sm font-medium text-gray-700 mb-1"
                                    >Hành động
                                    <span class="text-red-500">*</span></label
                                >
                                <select
                                    v-model="typeForm.impact_action"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-red-500 focus:border-red-500"
                                >
                                    <option value="">Chọn hành động</option>
                                    <option value="increase">Tăng</option>
                                    <option value="decrease">Giảm</option>
                                </select>
                            </div>
                            <div>
                                <label
                                    class="block text-sm font-medium text-gray-700 mb-1"
                                    >Mô tả</label
                                >
                                <input
                                    v-model="typeForm.description"
                                    type="text"
                                    placeholder="Mô tả ngắn"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-red-500 focus:border-red-500"
                                />
                            </div>
                        </div>
                        <div class="flex space-x-2 mt-3">
                            <button
                                @click="savePaymentType"
                                :disabled="typeFormLoading"
                                class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 text-sm disabled:opacity-50"
                            >
                                {{
                                    typeFormLoading
                                        ? "Đang lưu..."
                                        : editingType
                                          ? "Cập nhật"
                                          : "Thêm mới"
                                }}
                            </button>
                            <button
                                v-if="editingType"
                                @click="cancelEditType"
                                class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 text-sm"
                            >
                                Hủy sửa
                            </button>
                        </div>
                    </div>

                    <!-- Type list -->
                    <div class="border rounded-lg overflow-hidden">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th
                                        class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase"
                                    >
                                        Mã
                                    </th>
                                    <th
                                        class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase"
                                    >
                                        Tên
                                    </th>
                                    <th
                                        class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase"
                                    >
                                        Nhóm
                                    </th>
                                    <th
                                        class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase"
                                    >
                                        Tác động
                                    </th>
                                    <th
                                        class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase"
                                    >
                                        Trạng thái
                                    </th>
                                    <th
                                        class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase"
                                    >
                                        Hành động
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-if="allPaymentTypes.length === 0">
                                    <td
                                        colspan="6"
                                        class="px-4 py-4 text-center text-gray-500 text-sm"
                                    >
                                        Chưa có loại phiếu chi nào
                                    </td>
                                </tr>
                                <tr
                                    v-for="type in allPaymentTypes"
                                    :key="type.id"
                                    class="hover:bg-gray-50"
                                >
                                    <td class="px-4 py-2 text-sm font-mono">
                                        {{ type.code }}
                                    </td>
                                    <td class="px-4 py-2 text-sm">
                                        {{ type.name }}
                                    </td>
                                    <td class="px-4 py-2 text-sm">
                                        {{
                                            type.recipient_type === "customer"
                                                ? "Khách hàng"
                                                : "Nhà cung cấp"
                                        }}
                                    </td>
                                    <td class="px-4 py-2 text-sm">
                                        <span class="text-xs">
                                            {{
                                                type.impact_action ===
                                                "increase"
                                                    ? "↑ Tăng"
                                                    : "↓ Giảm"
                                            }}
                                            {{
                                                type.impact_type === "debt"
                                                    ? "công nợ"
                                                    : type.impact_type ===
                                                        "expense"
                                                      ? "chi phí"
                                                      : "tạm ứng"
                                            }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-sm">
                                        <span
                                            :class="
                                                type.is_active
                                                    ? 'bg-green-100 text-green-800'
                                                    : 'bg-red-100 text-red-800'
                                            "
                                            class="px-2 py-0.5 rounded-full text-xs"
                                        >
                                            {{
                                                type.is_active
                                                    ? "Hoạt động"
                                                    : "Ngừng"
                                            }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-sm">
                                        <button
                                            @click="startEditType(type)"
                                            class="text-blue-600 hover:text-blue-900 mr-2"
                                        >
                                            ✏️
                                        </button>
                                        <button
                                            @click="deletePaymentType(type)"
                                            class="text-red-600 hover:text-red-900"
                                        >
                                            🗑️
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import CashPaymentForm from "../components/CashPaymentForm.vue";
import { cashPaymentApi, cashPaymentHelpers } from "../api/cashPaymentApi";

export default {
    name: "CashPaymentList",
    components: {
        CashPaymentForm,
    },
    data() {
        return {
            loading: false,
            payments: [],
            paymentTypes: [],
            warehouses: [],
            pagination: {
                current_page: 1,
                last_page: 1,
                from: 0,
                to: 0,
                total: 0,
            },
            filters: {
                search: "",
                status: "",
                date_from: "",
                date_to: "",
                warehouse_id: "",
            },
            showCreateForm: false,
            showEditForm: false,
            showViewModal: false,
            showTypeManager: false,
            editingPayment: null,
            viewingPayment: null,
            searchTimeout: null,
            // Type management
            allPaymentTypes: [],
            editingType: null,
            typeFormLoading: false,
            typeForm: {
                code: "",
                name: "",
                recipient_type: "",
                impact_type: "",
                impact_action: "",
                description: "",
            },
        };
    },
    mounted() {
        this.initializeData();
    },
    watch: {
        showTypeManager(val) {
            if (val) {
                this.loadAllPaymentTypes();
                this.resetTypeForm();
            }
        },
    },
    methods: {
        // Initialize all data
        async initializeData() {
            console.log("🚀 Initializing payment list data...");
            try {
                await Promise.all([
                    this.loadData(),
                    this.loadPaymentTypes(),
                    this.loadWarehouses(),
                ]);
                console.log("✅ Payment list data initialized successfully");
            } catch (error) {
                console.error("❌ Error initializing data:", error);
                this.showNotification("Lỗi khi khởi tạo dữ liệu", "error");
            }
        },

        async loadData(page = 1) {
            this.loading = true;
            try {
                const params = {
                    page,
                    per_page: 15,
                    ...this.filters,
                };

                // Remove empty params
                Object.keys(params).forEach((key) => {
                    if (params[key] === "" || params[key] === null) {
                        delete params[key];
                    }
                });

                console.log("📋 Loading payments with params:", params);
                const response = await cashPaymentApi.getAll(params);

                this.payments = response.data.data || [];
                this.pagination = {
                    current_page: response.data.current_page || 1,
                    last_page: response.data.last_page || 1,
                    from: response.data.from || 0,
                    to: response.data.to || 0,
                    total: response.data.total || 0,
                };

                console.log("✅ Loaded", this.payments.length, "payments");
            } catch (error) {
                console.error("❌ Error loading payments:", error);
                this.showNotification(
                    "Lỗi khi tải danh sách phiếu chi",
                    "error",
                );
                this.payments = [];
            } finally {
                this.loading = false;
            }
        },

        async loadPaymentTypes() {
            try {
                console.log("📋 Loading payment types...");
                const response = await cashPaymentApi.getPaymentTypes({
                    active_only: true,
                });
                this.paymentTypes = response.data || [];
                console.log(
                    "✅ Loaded",
                    this.paymentTypes.length,
                    "payment types",
                );
            } catch (error) {
                console.error("❌ Error loading payment types:", error);
                this.paymentTypes = [];
            }
        },

        async loadWarehouses() {
            try {
                console.log("🏢 Loading warehouses...");
                const response = await cashPaymentApi.getWarehouses();
                this.warehouses = response.data || [];
                console.log("✅ Loaded", this.warehouses.length, "warehouses");
            } catch (error) {
                console.error("❌ Error loading warehouses:", error);
                this.warehouses = [];
            }
        },

        // Form handling methods
        openCreateForm() {
            console.log("🆕 Opening create payment form...");
            this.editingPayment = null;
            this.showCreateForm = true;
            this.showEditForm = false;
        },

        closeForm() {
            console.log("❌ Closing payment form...");
            this.showCreateForm = false;
            this.showEditForm = false;
            this.editingPayment = null;
        },

        // 🆕 UPDATED handleFormSubmit method with enhanced status handling
        async handleFormSubmit(formData) {
            try {
                console.log("💾 Submitting payment form:", formData);

                let response, successMessage;

                if (this.editingPayment) {
                    // Update existing payment
                    response = await cashPaymentApi.update(
                        this.editingPayment.id,
                        formData,
                    );
                    successMessage =
                        response.message || "Cập nhật phiếu chi thành công";
                } else {
                    // Create new payment
                    response = await cashPaymentApi.create(formData);

                    // 🆕 Enhanced success message based on status
                    if (response.message) {
                        successMessage = response.message;
                    } else {
                        successMessage = this.getCreateSuccessMessage(
                            formData.status,
                            formData.amount,
                        );
                    }
                }

                // 🆕 Show enhanced notification
                this.showNotification(successMessage, "success");

                // 🆕 Log activity for different statuses
                if (!this.editingPayment && formData.status === "approved") {
                    console.log(
                        "🎉 Payment created and approved immediately!",
                        {
                            amount: formData.amount,
                            recipient_type: formData.recipient_type,
                            recipient_id: formData.recipient_id,
                        },
                    );
                }

                this.closeForm();
                await this.loadData();
            } catch (error) {
                console.error("❌ Error saving payment:", error);

                // Enhanced error handling
                let errorMessage = "Có lỗi xảy ra khi lưu phiếu";

                if (error.message) {
                    errorMessage = error.message;
                } else if (error.errors) {
                    // Validation errors
                    const errorList = Object.values(error.errors).flat();
                    errorMessage = errorList.join(", ");
                }

                this.showNotification(errorMessage, "error");
            }
        },

        // 🆕 Helper method to generate success message
        getCreateSuccessMessage(status, amount) {
            const formattedAmount = this.formatCurrency(amount);

            switch (status) {
                case "draft":
                    return `💾 Lưu nháp phiếu chi ${formattedAmount} thành công`;
                case "pending":
                    return `📤 Tạo phiếu chi ${formattedAmount} và gửi duyệt thành công`;
                case "approved":
                    return `🎉 Tạo và duyệt phiếu chi ${formattedAmount} thành công! Đã tác động đến công nợ.`;
                default:
                    return `✅ Tạo phiếu chi ${formattedAmount} thành công`;
            }
        },

        // View/Edit/Action methods
        viewPayment(payment) {
            console.log("👁️ Viewing payment:", payment.code);
            this.viewingPayment = payment;
            this.showViewModal = true;
        },

        editPayment(payment) {
            console.log("✏️ Editing payment:", payment.code);
            this.editingPayment = { ...payment };
            this.showEditForm = true;
            this.showCreateForm = false;
        },

        async submitForApproval(payment) {
            if (!confirm("Bạn có chắc chắn muốn gửi phiếu này để duyệt?"))
                return;

            try {
                console.log(
                    "📤 Submitting payment for approval:",
                    payment.code,
                );
                await cashPaymentApi.submitForApproval(payment.id);
                this.showNotification("Gửi duyệt thành công", "success");
                this.loadData();
            } catch (error) {
                console.error("❌ Error submitting payment:", error);
                this.showNotification(
                    error.message || "Có lỗi xảy ra",
                    "error",
                );
            }
        },

        async approvePayment(payment) {
            if (!confirm("Bạn có chắc chắn muốn duyệt phiếu này?")) return;

            try {
                console.log("✅ Approving payment:", payment.code);
                await cashPaymentApi.approve(payment.id);
                this.showNotification("Duyệt phiếu thành công", "success");
                this.loadData();
            } catch (error) {
                console.error("❌ Error approving payment:", error);
                this.showNotification(
                    error.message || "Có lỗi xảy ra",
                    "error",
                );
            }
        },

        async cancelPayment(payment) {
            if (!confirm("Bạn có chắc chắn muốn hủy phiếu này?")) return;

            try {
                console.log("❌ Cancelling payment:", payment.code);
                await cashPaymentApi.cancel(payment.id);
                this.showNotification("Hủy phiếu thành công", "success");
                this.loadData();
            } catch (error) {
                console.error("❌ Error cancelling payment:", error);
                this.showNotification(
                    error.message || "Có lỗi xảy ra",
                    "error",
                );
            }
        },

        printPayment(payment) {
            console.log("🖨️ Printing payment:", payment.code);
            window.open(`/cash-payments/${payment.id}/print`, "_blank");
        },

        exportData() {
            console.log("📥 Exporting payment data...");
            // Implement export functionality
            this.showNotification(
                "Chức năng xuất file đang được phát triển",
                "info",
            );
        },

        // Filter and search methods
        debouncedSearch() {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                console.log("🔍 Searching payments:", this.filters.search);
                this.applyFilters();
            }, 500);
        },

        applyFilters() {
            console.log("🔧 Applying filters:", this.filters);
            this.loadData(1);
        },

        // Pagination methods
        changePage(page) {
            if (page >= 1 && page <= this.pagination.last_page) {
                console.log("📄 Changing to page:", page);
                this.loadData(page);
            }
        },

        getPageNumbers() {
            const current = this.pagination.current_page;
            const last = this.pagination.last_page;
            const pages = [];

            if (last <= 7) {
                for (let i = 1; i <= last; i++) {
                    pages.push(i);
                }
            } else {
                if (current <= 4) {
                    for (let i = 1; i <= 5; i++) pages.push(i);
                    pages.push("...");
                    pages.push(last);
                } else if (current >= last - 3) {
                    pages.push(1);
                    pages.push("...");
                    for (let i = last - 4; i <= last; i++) pages.push(i);
                } else {
                    pages.push(1);
                    pages.push("...");
                    for (let i = current - 1; i <= current + 1; i++)
                        pages.push(i);
                    pages.push("...");
                    pages.push(last);
                }
            }

            return pages;
        },

        // Utility methods
        formatCurrency(amount) {
            if (!amount || isNaN(amount)) return "0 ₫";
            return new Intl.NumberFormat("vi-VN", {
                style: "currency",
                currency: "VND",
            }).format(amount);
        },

        formatDate(date) {
            if (!date) return "N/A";
            try {
                return new Date(date).toLocaleDateString("vi-VN", {
                    year: "numeric",
                    month: "2-digit",
                    day: "2-digit",
                });
            } catch (error) {
                return date;
            }
        },

        getStatusClass(status) {
            const statusClasses = {
                draft: "bg-gray-100 text-gray-800",
                pending: "bg-yellow-100 text-yellow-800",
                approved: "bg-green-100 text-green-800",
                cancelled: "bg-red-100 text-red-800",
            };
            return statusClasses[status] || "bg-gray-100 text-gray-800";
        },

        getStatusText(status) {
            const statusTexts = {
                draft: "Nháp",
                pending: "Chờ duyệt",
                approved: "Đã duyệt",
                cancelled: "Đã hủy",
            };
            return statusTexts[status] || status;
        },

        // 🆕 Enhanced notification method
        showNotification(message, type = "info") {
            console.log(`${type.toUpperCase()}: ${message}`);

            // Enhanced notification with icons and proper styling
            const icons = {
                success: "✅",
                error: "❌",
                warning: "⚠️",
                info: "ℹ️",
            };

            const icon = icons[type] || icons.info;
            const fullMessage = `${icon} ${message}`;

            // For now using alert, but you can replace with toast notification
            alert(fullMessage);

            // You can implement a proper toast notification system here
            // Example: this.$toast.show({ message: fullMessage, type })
        },

        // =============== Type Management Methods ===============
        async loadAllPaymentTypes() {
            try {
                const response = await cashPaymentApi.getPaymentTypes();
                if (response && response.data) {
                    this.allPaymentTypes =
                        response.data.data || response.data || [];
                }
            } catch (error) {
                console.error("Error loading all payment types:", error);
                this.allPaymentTypes = [];
            }
        },

        resetTypeForm() {
            this.typeForm = {
                code: "",
                name: "",
                recipient_type: "",
                impact_type: "",
                impact_action: "",
                description: "",
            };
            this.editingType = null;
        },

        startEditType(type) {
            this.editingType = type;
            this.typeForm = {
                code: type.code,
                name: type.name,
                recipient_type: type.recipient_type,
                impact_type: type.impact_type,
                impact_action: type.impact_action,
                description: type.description || "",
            };
        },

        cancelEditType() {
            this.resetTypeForm();
        },

        async savePaymentType() {
            if (
                !this.typeForm.code ||
                !this.typeForm.name ||
                !this.typeForm.recipient_type ||
                !this.typeForm.impact_type ||
                !this.typeForm.impact_action
            ) {
                this.showNotification(
                    "Vui lòng điền đầy đủ các trường bắt buộc",
                    "error",
                );
                return;
            }

            this.typeFormLoading = true;
            try {
                if (this.editingType) {
                    await cashPaymentApi.updatePaymentType(
                        this.editingType.id,
                        this.typeForm,
                    );
                    this.showNotification(
                        "Cập nhật loại phiếu chi thành công",
                        "success",
                    );
                } else {
                    await cashPaymentApi.createPaymentType(this.typeForm);
                    this.showNotification(
                        "Thêm loại phiếu chi thành công",
                        "success",
                    );
                }
                this.resetTypeForm();
                await this.loadAllPaymentTypes();
                await this.loadPaymentTypes();
            } catch (error) {
                console.error("Error saving payment type:", error);
                const msg = error?.errors
                    ? Object.values(error.errors).flat().join(", ")
                    : error?.message || "Lỗi khi lưu loại phiếu";
                this.showNotification(msg, "error");
            } finally {
                this.typeFormLoading = false;
            }
        },

        async deletePaymentType(type) {
            if (
                !confirm(`Bạn có chắc chắn muốn xóa loại phiếu "${type.name}"?`)
            )
                return;

            try {
                await cashPaymentApi.deletePaymentType(type.id);
                this.showNotification(
                    "Xóa loại phiếu chi thành công",
                    "success",
                );
                await this.loadAllPaymentTypes();
                await this.loadPaymentTypes();
            } catch (error) {
                console.error("Error deleting payment type:", error);
                const msg = error?.message || "Không thể xóa loại phiếu";
                this.showNotification(msg, "error");
            }
        },
    },
};
</script>
