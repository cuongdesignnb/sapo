<template>
    <div class="bg-white">
        <!-- Header -->
        <div class="p-6 border-b">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900">
                        Danh sách phiếu thu
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
                            <li class="text-gray-900">Phiếu thu</li>
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
                        class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
                    >
                        ➕ Tạo phiếu thu
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
        <div class="overflow-hidden">
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
                                Người nộp
                            </th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                            >
                                Số tiền
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
                                colspan="7"
                                class="px-6 py-12 text-center text-gray-500"
                            >
                                <div class="flex items-center justify-center">
                                    <div
                                        class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600"
                                    ></div>
                                    <span class="ml-2">Đang tải...</span>
                                </div>
                            </td>
                        </tr>
                        <tr v-else-if="receipts.length === 0">
                            <td
                                colspan="7"
                                class="px-6 py-12 text-center text-gray-500"
                            >
                                Không có phiếu thu nào
                            </td>
                        </tr>
                        <tr
                            v-else
                            v-for="receipt in receipts"
                            :key="receipt.id"
                            class="hover:bg-gray-50"
                        >
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-blue-600">
                                    {{ receipt.code }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">
                                    {{ receipt.receipt_type?.name || "N/A" }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">
                                    {{ receipt.recipient?.name || "N/A" }}
                                </div>
                                <div class="text-sm text-gray-500">
                                    {{
                                        receipt.recipient_type === "customer"
                                            ? "Khách hàng"
                                            : "Nhà cung cấp"
                                    }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">
                                    {{ formatCurrency(receipt.amount) }}
                                </div>
                                <div class="text-sm text-gray-500">
                                    {{
                                        receipt.payment_method === "cash"
                                            ? "Tiền mặt"
                                            : "Chuyển khoản"
                                    }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">
                                    {{ formatDate(receipt.receipt_date) }}
                                </div>
                                <div class="text-sm text-gray-500">
                                    {{ receipt.creator?.name || "N/A" }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span
                                    :class="getStatusClass(receipt.status)"
                                    class="inline-flex px-2 py-1 text-xs font-semibold rounded-full"
                                >
                                    {{ getStatusText(receipt.status) }}
                                </span>
                            </td>
                            <td
                                class="px-6 py-4 whitespace-nowrap text-sm font-medium"
                            >
                                <div class="flex items-center space-x-2">
                                    <button
                                        @click="viewReceipt(receipt)"
                                        class="text-blue-600 hover:text-blue-900"
                                    >
                                        👁️
                                    </button>
                                    <button
                                        v-if="receipt.status === 'draft'"
                                        @click="editReceipt(receipt)"
                                        class="text-green-600 hover:text-green-900"
                                    >
                                        ✏️
                                    </button>
                                    <button
                                        v-if="receipt.status === 'draft'"
                                        @click="submitForApproval(receipt)"
                                        class="text-orange-600 hover:text-orange-900"
                                        title="Gửi duyệt"
                                    >
                                        📤
                                    </button>
                                    <button
                                        v-if="receipt.status === 'pending'"
                                        @click="approveReceipt(receipt)"
                                        class="text-green-600 hover:text-green-900"
                                        title="Duyệt"
                                    >
                                        ✅
                                    </button>
                                    <button
                                        v-if="
                                            ['draft', 'pending'].includes(
                                                receipt.status,
                                            )
                                        "
                                        @click="cancelReceipt(receipt)"
                                        class="text-red-600 hover:text-red-900"
                                        title="Hủy"
                                    >
                                        ❌
                                    </button>
                                    <button
                                        v-if="receipt.status === 'approved'"
                                        @click="printReceipt(receipt)"
                                        class="text-purple-600 hover:text-purple-900"
                                        title="In phiếu"
                                    >
                                        🖨️
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
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
                                    ? 'bg-blue-600 text-white border-blue-600'
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
                <CashReceiptForm
                    :receipt="editingReceipt"
                    :receipt-types="receiptTypes"
                    :warehouses="warehouses"
                    @submit="handleFormSubmit"
                    @cancel="closeForm"
                    @error="showNotification"
                    @types-changed="loadReceiptTypes"
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
                        Chi tiết phiếu thu
                    </h3>
                </div>
                <div class="p-6" v-if="viewingReceipt">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <strong>Mã phiếu:</strong> {{ viewingReceipt.code }}
                        </div>
                        <div>
                            <strong>Loại phiếu:</strong>
                            {{ viewingReceipt.receipt_type?.name || "N/A" }}
                        </div>
                        <div>
                            <strong>Người nộp:</strong>
                            {{ viewingReceipt.recipient?.name || "N/A" }}
                        </div>
                        <div>
                            <strong>Số tiền:</strong>
                            {{ formatCurrency(viewingReceipt.amount) }}
                        </div>
                        <div>
                            <strong>Ngày tạo:</strong>
                            {{ formatDate(viewingReceipt.receipt_date) }}
                        </div>
                        <div>
                            <strong>Trạng thái:</strong>
                            <span
                                :class="getStatusClass(viewingReceipt.status)"
                                class="inline-flex px-2 py-1 text-xs font-semibold rounded-full"
                            >
                                {{ getStatusText(viewingReceipt.status) }}
                            </span>
                        </div>
                        <div class="col-span-2">
                            <strong>Ghi chú:</strong>
                            {{ viewingReceipt.note || "Không có" }}
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
                        Quản lý loại phiếu thu
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
                        class="mb-6 bg-blue-50 border border-blue-200 rounded-lg p-4"
                    >
                        <h4 class="font-medium text-blue-900 mb-3">
                            {{
                                editingType
                                    ? "Sửa loại phiếu"
                                    : "Thêm loại phiếu thu mới"
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
                                    placeholder="VD: THU_NO_KH"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500"
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
                                    placeholder="VD: Thu nợ khách hàng"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500"
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
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500"
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
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500"
                                >
                                    <option value="">Chọn loại tác động</option>
                                    <option value="debt">Công nợ</option>
                                    <option value="revenue">Doanh thu</option>
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
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500"
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
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500"
                                />
                            </div>
                        </div>
                        <div class="flex space-x-2 mt-3">
                            <button
                                @click="saveReceiptType"
                                :disabled="typeFormLoading"
                                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm disabled:opacity-50"
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
                                <tr v-if="allReceiptTypes.length === 0">
                                    <td
                                        colspan="6"
                                        class="px-4 py-4 text-center text-gray-500 text-sm"
                                    >
                                        Chưa có loại phiếu thu nào
                                    </td>
                                </tr>
                                <tr
                                    v-for="type in allReceiptTypes"
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
                                                        "revenue"
                                                      ? "doanh thu"
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
                                            @click="deleteReceiptType(type)"
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
import CashReceiptForm from "../components/CashReceiptForm.vue";
import { cashReceiptApi, cashReceiptHelpers } from "../api/cashReceiptApi";

export default {
    name: "CashReceiptList",
    components: {
        CashReceiptForm,
    },
    data() {
        return {
            loading: false,
            receipts: [],
            receiptTypes: [],
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
            editingReceipt: null,
            viewingReceipt: null,
            searchTimeout: null,
            // Type management
            allReceiptTypes: [],
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
                this.loadAllReceiptTypes();
                this.resetTypeForm();
            }
        },
    },
    methods: {
        // Initialize all data
        async initializeData() {
            console.log("🚀 Initializing CashReceiptList...");
            await Promise.all([
                this.loadData(),
                this.loadReceiptTypes(),
                this.loadWarehouses(),
            ]);
        },

        async loadData(page = 1) {
            this.loading = true;
            try {
                console.log("📥 Loading receipts...", {
                    page,
                    filters: this.filters,
                });

                const params = {
                    page,
                    per_page: 15,
                    ...this.filters,
                };

                // Clean empty params
                Object.keys(params).forEach((key) => {
                    if (params[key] === "" || params[key] === null) {
                        delete params[key];
                    }
                });

                const response = await cashReceiptApi.getAll(params);
                console.log("📥 Receipts response:", response);

                if (response && response.data) {
                    this.receipts = response.data.data || [];
                    this.pagination = {
                        current_page: response.data.current_page || 1,
                        last_page: response.data.last_page || 1,
                        from: response.data.from || 0,
                        to: response.data.to || 0,
                        total: response.data.total || 0,
                    };
                    console.log("📋 Loaded receipts:", this.receipts.length);
                } else {
                    this.receipts = [];
                    console.warn("No receipts data received");
                }
            } catch (error) {
                console.error("❌ Error loading receipts:", error);
                this.showNotification(
                    "Lỗi khi tải danh sách phiếu thu: " +
                        (error.message || "Unknown error"),
                    "error",
                );
                this.receipts = [];
            } finally {
                this.loading = false;
            }
        },

        async loadReceiptTypes() {
            try {
                console.log("📥 Loading receipt types...");
                const response = await cashReceiptApi.getReceiptTypes({
                    active_only: true,
                });
                console.log("📥 Receipt types response:", response);

                if (response && response.data) {
                    this.receiptTypes =
                        response.data.data || response.data || [];
                } else {
                    this.receiptTypes = [];
                }
                console.log(
                    "📋 Loaded receipt types:",
                    this.receiptTypes.length,
                );
            } catch (error) {
                console.error("❌ Error loading receipt types:", error);
                this.receiptTypes = [];
            }
        },

        async loadWarehouses() {
            try {
                console.log("📥 Loading warehouses...");

                // Use fetch instead of axios for consistency
                const token =
                    document
                        .querySelector('meta[name="api-token"]')
                        ?.getAttribute("content") ||
                    sessionStorage.getItem("api_token");

                const headers = {
                    Accept: "application/json",
                    "Content-Type": "application/json",
                };

                if (token) {
                    headers["Authorization"] = `Bearer ${token}`;
                }

                const csrfToken = document
                    .querySelector('meta[name="csrf-token"]')
                    ?.getAttribute("content");
                if (csrfToken) {
                    headers["X-CSRF-TOKEN"] = csrfToken;
                }

                const response = await fetch("/api/my-warehouses", {
                    method: "GET",
                    headers: headers,
                });

                console.log("📥 Warehouses response status:", response.status);

                if (!response.ok) {
                    throw new Error(
                        `HTTP ${response.status}: ${response.statusText}`,
                    );
                }

                const data = await response.json();
                console.log("📥 Warehouses response:", data);

                if (data && data.success && data.data) {
                    this.warehouses = data.data;
                } else {
                    this.warehouses = [];
                }
                console.log("📋 Loaded warehouses:", this.warehouses.length);
            } catch (error) {
                console.error("❌ Error loading warehouses:", error);
                this.warehouses = [];
            }
        },

        async handleFormSubmit(formData) {
            try {
                console.log("💾 Submitting form data:", formData);

                // Use fetch API instead of cashReceiptApi for now
                const token =
                    document
                        .querySelector('meta[name="api-token"]')
                        ?.getAttribute("content") ||
                    sessionStorage.getItem("api_token");

                const headers = {
                    Accept: "application/json",
                    "Content-Type": "application/json",
                };

                if (token) {
                    headers["Authorization"] = `Bearer ${token}`;
                }

                const csrfToken = document
                    .querySelector('meta[name="csrf-token"]')
                    ?.getAttribute("content");
                if (csrfToken) {
                    headers["X-CSRF-TOKEN"] = csrfToken;
                }

                let response, url, method;

                if (this.editingReceipt && this.editingReceipt.id) {
                    // Update
                    url = `/api/cash-vouchers/receipts/${this.editingReceipt.id}`;
                    method = "PUT";
                } else {
                    // Create
                    url = "/api/cash-vouchers/receipts";
                    method = "POST";
                }

                console.log("🌐 API Request:", { url, method, formData });

                response = await fetch(url, {
                    method: method,
                    headers: headers,
                    body: JSON.stringify(formData),
                });

                console.log("📡 API Response status:", response.status);

                if (!response.ok) {
                    const errorData = await response.json();
                    console.error("❌ API Error response:", errorData);
                    throw new Error(
                        errorData.message ||
                            `HTTP ${response.status}: ${response.statusText}`,
                    );
                }

                const result = await response.json();
                console.log("✅ API Success response:", result);

                if (result.success) {
                    this.showNotification(
                        this.editingReceipt
                            ? "Cập nhật phiếu thu thành công"
                            : "Tạo phiếu thu thành công",
                        "success",
                    );
                    this.closeForm();
                    await this.loadData();
                } else {
                    throw new Error(result.message || "Có lỗi xảy ra");
                }
            } catch (error) {
                console.error("❌ Error saving receipt:", error);
                this.showNotification(
                    error.message || "Có lỗi xảy ra khi lưu phiếu",
                    "error",
                );
            }
        },

        async submitForApproval(receipt) {
            if (!confirm("Bạn có chắc chắn muốn gửi phiếu này để duyệt?"))
                return;

            try {
                await cashReceiptApi.submitForApproval(receipt.id);
                this.showNotification("Gửi duyệt thành công", "success");
                await this.loadData();
            } catch (error) {
                console.error("❌ Error submitting receipt:", error);
                this.showNotification(
                    error.message || "Có lỗi xảy ra",
                    "error",
                );
            }
        },

        async approveReceipt(receipt) {
            if (!confirm("Bạn có chắc chắn muốn duyệt phiếu này?")) return;

            try {
                await cashReceiptApi.approve(receipt.id);
                this.showNotification("Duyệt phiếu thành công", "success");
                await this.loadData();
            } catch (error) {
                console.error("❌ Error approving receipt:", error);
                this.showNotification(
                    error.message || "Có lỗi xảy ra",
                    "error",
                );
            }
        },

        async cancelReceipt(receipt) {
            if (!confirm("Bạn có chắc chắn muốn hủy phiếu này?")) return;

            try {
                await cashReceiptApi.cancel(receipt.id);
                this.showNotification("Hủy phiếu thành công", "success");
                await this.loadData();
            } catch (error) {
                console.error("❌ Error cancelling receipt:", error);
                this.showNotification(
                    error.message || "Có lỗi xảy ra",
                    "error",
                );
            }
        },

        async exportData() {
            try {
                const exportParams = { ...this.filters };
                await cashReceiptApi.export(exportParams);
                this.showNotification("Xuất file thành công", "success");
            } catch (error) {
                console.error("❌ Export error:", error);
                this.showNotification(
                    error.message || "Lỗi khi xuất file",
                    "error",
                );
            }
        },

        // Helper methods using API helpers
        formatCurrency(amount) {
            return cashReceiptHelpers.formatCurrency(amount);
        },

        formatDate(date) {
            return cashReceiptHelpers.formatDate(date);
        },

        getStatusText(status) {
            return cashReceiptHelpers.getStatusText(status);
        },

        getStatusClass(status) {
            return cashReceiptHelpers.getStatusClass(status);
        },

        // UI Methods
        openCreateForm() {
            console.log("🆕 Opening create form...");
            this.editingReceipt = null;
            this.showCreateForm = true;
            this.showEditForm = false;
        },

        debouncedSearch() {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                this.applyFilters();
            }, 500);
        },

        applyFilters() {
            this.loadData(1);
        },

        changePage(page) {
            if (page >= 1 && page <= this.pagination.last_page) {
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

        closeForm() {
            this.showCreateForm = false;
            this.showEditForm = false;
            this.editingReceipt = null;
        },

        viewReceipt(receipt) {
            this.viewingReceipt = receipt;
            this.showViewModal = true;
        },

        editReceipt(receipt) {
            console.log("✏️ Editing receipt:", receipt);
            this.editingReceipt = { ...receipt };
            this.showEditForm = true;
            this.showCreateForm = false;
        },

        printReceipt(receipt) {
            window.open(`/cash-receipts/${receipt.id}/print`, "_blank");
        },

        showNotification(message, type = "success") {
            console.log(`${type.toUpperCase()}: ${message}`);

            // Simple notification implementation
            // You can enhance this with a proper notification system
            if (type === "error") {
                alert("❌ " + message);
            } else {
                // For success messages, you could use a toast library
                console.log("✅ " + message);
            }
        },

        // =============== Type Management Methods ===============
        async loadAllReceiptTypes() {
            try {
                const response = await cashReceiptApi.getReceiptTypes();
                if (response && response.data) {
                    this.allReceiptTypes =
                        response.data.data || response.data || [];
                }
            } catch (error) {
                console.error("Error loading all receipt types:", error);
                this.allReceiptTypes = [];
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

        async saveReceiptType() {
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
                    await cashReceiptApi.updateReceiptType(
                        this.editingType.id,
                        this.typeForm,
                    );
                    this.showNotification("Cập nhật loại phiếu thu thành công");
                } else {
                    await cashReceiptApi.createReceiptType(this.typeForm);
                    this.showNotification("Thêm loại phiếu thu thành công");
                }
                this.resetTypeForm();
                await this.loadAllReceiptTypes();
                await this.loadReceiptTypes();
            } catch (error) {
                console.error("Error saving receipt type:", error);
                const msg = error?.errors
                    ? Object.values(error.errors).flat().join(", ")
                    : error?.message || "Lỗi khi lưu loại phiếu";
                this.showNotification(msg, "error");
            } finally {
                this.typeFormLoading = false;
            }
        },

        async deleteReceiptType(type) {
            if (
                !confirm(`Bạn có chắc chắn muốn xóa loại phiếu "${type.name}"?`)
            )
                return;

            try {
                await cashReceiptApi.deleteReceiptType(type.id);
                this.showNotification("Xóa loại phiếu thu thành công");
                await this.loadAllReceiptTypes();
                await this.loadReceiptTypes();
            } catch (error) {
                console.error("Error deleting receipt type:", error);
                const msg = error?.message || "Không thể xóa loại phiếu";
                this.showNotification(msg, "error");
            }
        },
    },
};
</script>
