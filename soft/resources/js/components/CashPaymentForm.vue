<template>
    <div class="bg-white rounded-lg shadow">
        <!-- Header -->
        <div class="px-6 py-4 border-b">
            <h3 class="text-lg font-medium text-gray-900">
                {{ isEdit ? "Sửa phiếu chi" : "Thêm phiếu chi" }}
            </h3>
        </div>

        <!-- Form -->
        <form @submit.prevent="handleSubmit" class="p-6">
            <div class="grid grid-cols-2 gap-6">
                <!-- Left Column -->
                <div class="space-y-4">
                    <h4 class="font-medium text-gray-900">Thông tin chung</h4>

                    <!-- Payment Type -->
                    <div>
                        <label
                            class="block text-sm font-medium text-gray-700 mb-2"
                        >
                            Loại phiếu chi <span class="text-red-500">*</span>
                        </label>
                        <div class="flex space-x-2">
                            <select
                                v-model="form.type_id"
                                @change="onPaymentTypeChange"
                                class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500"
                                required
                            >
                                <option value="">Chọn loại phiếu chi</option>
                                <option
                                    v-for="type in localPaymentTypes"
                                    :key="type.id"
                                    :value="type.id"
                                >
                                    {{ type.name }}
                                </option>
                            </select>
                            <button 
                                type="button"
                                @click="showQuickAddType = !showQuickAddType"
                                class="px-3 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 text-lg font-bold flex-shrink-0"
                                title="Thêm loại phiếu chi mới"
                            >
                                +
                            </button>
                        </div>
                        <!-- Quick Add Type Form -->
                        <div v-if="showQuickAddType" class="mt-2 p-3 bg-red-50 border border-red-200 rounded-md">
                            <div class="grid grid-cols-2 gap-2 mb-2">
                                <input v-model="newType.code" type="text" placeholder="Mã (VD: TRA_TIEN_NCC)" class="px-2 py-1 border border-gray-300 rounded text-sm" />
                                <input v-model="newType.name" type="text" placeholder="Tên loại phiếu" class="px-2 py-1 border border-gray-300 rounded text-sm" />
                                <select v-model="newType.recipient_type" class="px-2 py-1 border border-gray-300 rounded text-sm">
                                    <option value="">Nhóm đối tượng</option>
                                    <option value="customer">Khách hàng</option>
                                    <option value="supplier">Nhà cung cấp</option>
                                </select>
                                <select v-model="newType.impact_type" class="px-2 py-1 border border-gray-300 rounded text-sm">
                                    <option value="">Loại tác động</option>
                                    <option value="debt">Công nợ</option>
                                    <option value="expense">Chi phí</option>
                                    <option value="advance">Tạm ứng</option>
                                </select>
                                <select v-model="newType.impact_action" class="px-2 py-1 border border-gray-300 rounded text-sm">
                                    <option value="">Hành động</option>
                                    <option value="increase">Tăng</option>
                                    <option value="decrease">Giảm</option>
                                </select>
                                <input v-model="newType.description" type="text" placeholder="Mô tả (tùy chọn)" class="px-2 py-1 border border-gray-300 rounded text-sm" />
                            </div>
                            <div class="flex space-x-2">
                                <button type="button" @click="quickCreateType" :disabled="creatingType" class="px-3 py-1 bg-red-600 text-white rounded text-sm hover:bg-red-700 disabled:opacity-50">
                                    {{ creatingType ? 'Đang tạo...' : 'Tạo loại phiếu' }}
                                </button>
                                <button type="button" @click="showQuickAddType = false" class="px-3 py-1 border border-gray-300 rounded text-sm hover:bg-gray-50">
                                    Đóng
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Recipient Type -->
                    <div>
                        <label
                            class="block text-sm font-medium text-gray-700 mb-2"
                        >
                            Nhóm người nhận <span class="text-red-500">*</span>
                        </label>
                        <select
                            v-model="form.recipient_type"
                            @change="onRecipientTypeChange"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500"
                            required
                        >
                            <option value="">Chọn nhóm người nhận</option>
                            <option value="customer">Khách hàng</option>
                            <option value="supplier">Nhà cung cấp</option>
                        </select>
                    </div>

                    <!-- Recipient -->
                    <div>
                        <label
                            class="block text-sm font-medium text-gray-700 mb-2"
                        >
                            Tên người nhận <span class="text-red-500">*</span>
                        </label>
                        <select
                            v-model="form.recipient_id"
                            @change="onRecipientChange"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500"
                            required
                            :disabled="!form.recipient_type"
                        >
                            <option value="">
                                {{
                                    form.recipient_type === "customer"
                                        ? "Chọn khách hàng"
                                        : form.recipient_type === "supplier"
                                          ? "Chọn nhà cung cấp"
                                          : "Chọn nhóm người nhận trước"
                                }}
                            </option>
                            <option
                                v-for="recipient in recipients"
                                :key="recipient.id"
                                :value="recipient.id"
                            >{{ recipient.name }}{{ recipient.phone ? ' - ' + recipient.phone : '' }}</option>
                        </select>
                        <!-- Show debt info -->
                        <div
                            v-if="
                                selectedRecipient &&
                                selectedRecipient.total_debt
                            "
                            class="mt-1 text-sm text-gray-600"
                        >
                            Công nợ hiện tại:
                            {{ formatCurrency(selectedRecipient.total_debt) }}
                        </div>
                    </div>

                    <!-- Warehouse -->
                    <div>
                        <label
                            class="block text-sm font-medium text-gray-700 mb-2"
                        >
                            Chi nhánh <span class="text-red-500">*</span>
                        </label>
                        <select
                            v-model="form.warehouse_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500"
                            required
                        >
                            <option value="">Chọn chi nhánh</option>
                            <option
                                v-for="warehouse in warehouses"
                                :key="warehouse.id"
                                :value="warehouse.id"
                            >
                                {{ warehouse.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Amount -->
                    <div>
                        <label
                            class="block text-sm font-medium text-gray-700 mb-2"
                        >
                            Số tiền <span class="text-red-500">*</span>
                        </label>
                        <input
                            v-model="form.amount"
                            type="number"
                            step="1000"
                            min="0"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500"
                            placeholder="Nhập số tiền"
                            required
                        />
                        <div
                            v-if="form.amount"
                            class="mt-1 text-sm text-gray-600"
                        >
                            {{ formatCurrency(form.amount) }}
                        </div>
                    </div>

                    <!-- Payment Method -->
                    <div>
                        <label
                            class="block text-sm font-medium text-gray-700 mb-2"
                        >
                            Hình thức thanh toán
                            <span class="text-red-500">*</span>
                        </label>
                        <select
                            v-model="form.payment_method"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500"
                            required
                        >
                            <option value="cash">Tiền mặt</option>
                            <option value="transfer">Chuyển khoản</option>
                        </select>
                    </div>

                    <!-- 🆕 STATUS -->
                    <div>
                        <label
                            class="block text-sm font-medium text-gray-700 mb-2"
                        >
                            Trạng thái <span class="text-red-500">*</span>
                        </label>
                        <select
                            v-model="form.status"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500"
                            required
                        >
                            <option value="draft">Nháp</option>
                            <option value="pending">Chờ duyệt</option>
                            <option value="approved">Duyệt luôn</option>
                        </select>
                        <!-- Status hint -->
                        <div class="mt-1 text-sm text-gray-500">
                            <span v-if="form.status === 'draft'"
                                >💡 Lưu nháp - chưa tác động đến công nợ</span
                            >
                            <span v-else-if="form.status === 'pending'"
                                >⏳ Chờ duyệt - cần duyệt sau để tác động</span
                            >
                            <span
                                v-else-if="form.status === 'approved'"
                                class="text-orange-600 font-medium"
                            >
                                ⚡ Duyệt luôn - sẽ tác động ngay lập tức đến
                                công nợ
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="space-y-4">
                    <h4 class="font-medium text-gray-900">Thông tin bổ sung</h4>

                    <!-- Payment Date -->
                    <div>
                        <label
                            class="block text-sm font-medium text-gray-700 mb-2"
                        >
                            Ngày ghi nhận <span class="text-red-500">*</span>
                        </label>
                        <input
                            v-model="form.payment_date"
                            type="date"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500"
                            required
                        />
                    </div>

                    <!-- Reference Number -->
                    <div>
                        <label
                            class="block text-sm font-medium text-gray-700 mb-2"
                            >Mã tham chiếu</label
                        >
                        <input
                            v-model="form.reference_number"
                            type="text"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500"
                            placeholder="Nhập mã tham chiếu (tùy chọn)"
                        />
                    </div>

                    <!-- Note -->
                    <div>
                        <label
                            class="block text-sm font-medium text-gray-700 mb-2"
                            >Ghi chú</label
                        >
                        <textarea
                            v-model="form.note"
                            rows="3"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500"
                            placeholder="Nhập ghi chú"
                        ></textarea>
                    </div>

                    <!-- Impact Preview -->
                    <div
                        v-if="selectedPaymentType"
                        class="bg-orange-50 border border-orange-200 rounded-md p-3"
                    >
                        <h5 class="font-medium text-orange-900 mb-2">
                            Tác động dự kiến:
                        </h5>
                        <p class="text-sm text-orange-800">
                            {{ getImpactPreview() }}
                        </p>
                        <!-- Impact timing info -->
                        <div class="mt-2 text-xs text-orange-700">
                            <span v-if="form.status === 'draft'"
                                >🕒 Tác động sẽ áp dụng khi phiếu được
                                duyệt</span
                            >
                            <span v-else-if="form.status === 'pending'"
                                >🕒 Tác động sẽ áp dụng khi phiếu được
                                duyệt</span
                            >
                            <span
                                v-else-if="form.status === 'approved'"
                                class="font-medium"
                                >⚡ Tác động sẽ áp dụng ngay lập tức</span
                            >
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex justify-end space-x-3 mt-6 pt-4 border-t">
                <button
                    type="button"
                    @click="$emit('cancel')"
                    class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
                >
                    Hủy
                </button>
                <button
                    type="submit"
                    :disabled="loading"
                    :class="[
                        'px-4 py-2 rounded-md text-white disabled:opacity-50',
                        form.status === 'approved'
                            ? 'bg-orange-600 hover:bg-orange-700'
                            : 'bg-red-600 hover:bg-red-700',
                    ]"
                >
                    {{ loading ? "Đang xử lý..." : getSubmitButtonText() }}
                </button>
            </div>
        </form>
    </div>
</template>

<script>
import { cashPaymentApi } from '../api/cashPaymentApi'

export default {
    name: "CashPaymentForm",
    props: {
        payment: {
            type: Object,
            default: null,
        },
        paymentTypes: {
            type: Array,
            default: () => [],
        },
        warehouses: {
            type: Array,
            default: () => [],
        },
    },
    emits: ["submit", "cancel", "error", "types-changed"],
    data() {
        return {
            loading: false,
            recipients: [],
            showQuickAddType: false,
            creatingType: false,
            localPaymentTypes: [],
            newType: {
                code: '',
                name: '',
                recipient_type: '',
                impact_type: '',
                impact_action: '',
                description: ''
            },
            form: {
                type_id: "",
                recipient_type: "",
                recipient_id: "",
                warehouse_id: "",
                amount: "",
                note: "",
                payment_method: "cash",
                status: "draft",
                reference_number: "",
                payment_date: this.getTodayDate(),
            },
        };
    },
    computed: {
        isEdit() {
            return !!this.payment && !!this.payment.id;
        },
        selectedPaymentType() {
            if (!this.form.type_id || !this.localPaymentTypes) return null;
            return (
                this.localPaymentTypes.find(
                    (type) => type && type.id == this.form.type_id,
                ) || null
            );
        },
        selectedRecipient() {
            if (!this.form.recipient_id || !this.recipients) return null;
            return (
                this.recipients.find(
                    (recipient) =>
                        recipient && recipient.id == this.form.recipient_id,
                ) || null
            );
        },
    },
    watch: {
        payment: {
            immediate: true,
            handler(payment) {
                if (payment && payment.id) {
                    this.populateForm(payment);
                } else {
                    this.resetForm();
                }
            },
        },
        paymentTypes: {
            immediate: true,
            handler(val) {
                this.localPaymentTypes = val || [];
            },
        },
    },
    mounted() {
        this.localPaymentTypes = this.paymentTypes || [];
        if (this.payment && this.payment.id) {
            this.populateForm(this.payment);
        }
    },
    methods: {
        getTodayDate() {
            return new Date().toISOString().split("T")[0];
        },

        populateForm(payment) {
            this.form = {
                type_id: payment.type_id || "",
                recipient_type: payment.recipient_type || "",
                recipient_id: payment.recipient_id || "",
                warehouse_id: payment.warehouse_id || "",
                amount: payment.amount || "",
                note: payment.note || "",
                payment_method: payment.payment_method || "cash",
                status: payment.status || "draft",
                reference_number: payment.reference_number || "",
                payment_date: payment.payment_date || this.getTodayDate(),
            };

            if (this.form.recipient_type) {
                this.loadRecipients();
            }
        },

        resetForm() {
            this.form = {
                type_id: "",
                recipient_type: "",
                recipient_id: "",
                warehouse_id: "",
                amount: "",
                note: "",
                payment_method: "cash",
                status: "draft",
                reference_number: "",
                payment_date: this.getTodayDate(),
            };
            this.recipients = [];
        },

        async onPaymentTypeChange() {
            console.log("Payment type changed:", this.form.type_id);
            const type = this.selectedPaymentType;
            if (type && type.recipient_type) {
                this.form.recipient_type = type.recipient_type;
                this.form.recipient_id = "";
                await this.loadRecipients();
            } else {
                this.form.recipient_type = "";
                this.form.recipient_id = "";
                this.recipients = [];
            }
        },

        async onRecipientTypeChange() {
            this.form.recipient_id = "";
            this.recipients = [];
            if (this.form.recipient_type) {
                await this.loadRecipients();
            }
        },

        onRecipientChange() {
            console.log("Recipient changed:", this.form.recipient_id);
        },

        async loadRecipients() {
            if (!this.form.recipient_type) {
                this.recipients = [];
                return;
            }

            try {
                console.log('Loading recipients for type:', this.form.recipient_type);
                
                const data = await cashPaymentApi.getRecipients(this.form.recipient_type);
                
                if (data && data.success && data.data) {
                    this.recipients = data.data;
                    console.log('Recipients loaded:', this.recipients.length);
                } else {
                    this.recipients = data?.data || [];
                }
            } catch (error) {
                console.error("Error loading recipients:", error);
                this.recipients = [];
            }
        },

        getImpactPreview() {
            if (!this.selectedPaymentType) return "";

            const actions = {
                increase: "tăng",
                decrease: "giảm",
            };

            const impacts = {
                debt: "công nợ",
                expense: "chi phí",
                advance: "tạm ứng",
            };

            const targets = {
                customer: "khách hàng",
                supplier: "nhà cung cấp",
            };

            const type = this.selectedPaymentType;
            const action = actions[type.impact_action] || type.impact_action;
            const impact = impacts[type.impact_type] || type.impact_type;
            const target = targets[type.recipient_type] || type.recipient_type;

            return `${type.name} sẽ ${action} ${impact} của ${target}`;
        },

        formatCurrency(amount) {
            if (!amount || isNaN(amount)) return "0 ₫";
            return new Intl.NumberFormat("vi-VN", {
                style: "currency",
                currency: "VND",
            }).format(amount);
        },

        getSubmitButtonText() {
            if (this.isEdit) return "Cập nhật";

            switch (this.form.status) {
                case "draft":
                    return "Lưu nháp";
                case "pending":
                    return "Tạo & Gửi duyệt";
                case "approved":
                    return "Tạo & Duyệt luôn";
                default:
                    return "Tạo phiếu";
            }
        },

        validateForm() {
            const errors = [];

            if (!this.form.type_id) errors.push("Vui lòng chọn loại phiếu chi");
            if (!this.form.recipient_type)
                errors.push("Vui lòng chọn nhóm người nhận");
            if (!this.form.recipient_id)
                errors.push("Vui lòng chọn người nhận");
            if (!this.form.warehouse_id) errors.push("Vui lòng chọn chi nhánh");
            if (!this.form.amount || this.form.amount <= 0)
                errors.push("Vui lòng nhập số tiền hợp lệ");
            if (!this.form.payment_method)
                errors.push("Vui lòng chọn hình thức thanh toán");
            if (!this.form.payment_date)
                errors.push("Vui lòng chọn ngày ghi nhận");
            if (!this.form.status) errors.push("Vui lòng chọn trạng thái");

            return errors;
        },

        async handleSubmit() {
            const errors = this.validateForm();
            if (errors.length > 0) {
                this.$emit("error", errors.join(", "));
                return;
            }

            // Confirm for immediate approve
            if (this.form.status === "approved" && !this.isEdit) {
                const confirmMsg =
                    `Bạn có chắc chắn muốn DUYỆT LUÔN phiếu chi này?\n\n` +
                    `• Số tiền: ${this.formatCurrency(this.form.amount)}\n` +
                    `• Người nhận: ${this.selectedRecipient?.name || "N/A"}\n` +
                    `• Tác động: ${this.getImpactPreview()}\n\n` +
                    `⚠️ Phiếu sẽ được duyệt ngay lập tức và tác động đến công nợ!`;

                if (!confirm(confirmMsg)) {
                    return;
                }
            }

            this.loading = true;
            try {
                await this.$emit("submit", { ...this.form });
            } catch (error) {
                console.error("Submit error:", error);
                this.$emit("error", error.message || "Có lỗi xảy ra khi xử lý");
            } finally {
                this.loading = false;
            }
        },

        async quickCreateType() {
            if (!this.newType.code || !this.newType.name || !this.newType.recipient_type || !this.newType.impact_type || !this.newType.impact_action) {
                alert('Vui lòng điền đầy đủ: Mã, Tên, Nhóm đối tượng, Loại tác động, Hành động');
                return;
            }
            this.creatingType = true;
            try {
                const data = await cashPaymentApi.createPaymentType(this.newType);
                if (data && data.success) {
                    this.localPaymentTypes = [...this.localPaymentTypes, data.data];
                    this.form.type_id = data.data.id;
                    this.onPaymentTypeChange();
                    this.showQuickAddType = false;
                    this.newType = { code: '', name: '', recipient_type: '', impact_type: '', impact_action: '', description: '' };
                    this.$emit('types-changed');
                } else {
                    alert(data?.message || 'Lỗi khi tạo loại phiếu');
                }
            } catch (error) {
                console.error('Error creating payment type:', error);
                const errMsg = error?.errors ? Object.values(error.errors).flat().join(', ') : (error?.message || 'Lỗi khi tạo loại phiếu');
                alert(errMsg);
            } finally {
                this.creatingType = false;
            }
        },
    },
};
</script>
