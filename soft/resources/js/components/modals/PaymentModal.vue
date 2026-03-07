<template>
    <div
        class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center"
    >
        <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900">
                    Thanh toán đơn hàng
                </h3>
                <button
                    @click="$emit('close')"
                    class="text-gray-400 hover:text-gray-600"
                >
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form @submit.prevent="handleSubmit">
                <div class="space-y-4">
                    <!-- Thông tin đơn hàng -->
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h4 class="font-medium text-gray-900 mb-2">
                            Thông tin đơn hàng
                        </h4>
                        <div class="text-sm space-y-1">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Mã đơn hàng:</span>
                                <span class="font-medium">{{
                                    order.code
                                }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Tổng tiền:</span>
                                <span class="font-medium">{{
                                    formatCurrency(order.total)
                                }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600"
                                    >Đã thanh toán:</span
                                >
                                <span class="font-medium text-green-600">{{
                                    formatCurrency(order.paid || 0)
                                }}</span>
                            </div>
                            <div class="flex justify-between border-t pt-2">
                                <span class="text-gray-600">Còn nợ:</span>
                                <span class="font-medium text-red-600">{{
                                    formatCurrency(order.debt || 0)
                                }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Số tiền thanh toán -->
                    <div>
                        <label
                            class="block text-sm font-medium text-gray-700 mb-2"
                        >
                            Số tiền thanh toán
                            <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="number"
                            v-model="form.amount"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            :max="order.debt || 0"
                            min="0"
                            step="1000"
                            required
                        />
                        <p class="text-xs text-gray-500 mt-1">
                            Tối đa: {{ formatCurrency(order.debt || 0) }}
                        </p>
                    </div>

                    <!-- Phương thức thanh toán -->
                    <div>
                        <label
                            class="block text-sm font-medium text-gray-700 mb-2"
                        >
                            Phương thức thanh toán
                            <span class="text-red-500">*</span>
                        </label>
                        <select
                            v-model="form.payment_method"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required
                        >
                            <option value="">Chọn phương thức</option>
                            <option value="cash">Tiền mặt</option>
                            <option value="transfer">Chuyển khoản</option>
                            <option value="card">Thẻ tín dụng/ghi nợ</option>
                            <option value="wallet">Ví điện tử</option>
                            <option value="other">Khác</option>
                        </select>
                    </div>

                    <!-- Mã giao dịch -->
                    <div
                        v-if="
                            form.payment_method &&
                            form.payment_method !== 'cash'
                        "
                    >
                        <label
                            class="block text-sm font-medium text-gray-700 mb-2"
                        >
                            Mã giao dịch/Tham chiếu
                        </label>
                        <input
                            type="text"
                            v-model="form.transaction_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Nhập mã giao dịch (nếu có)"
                        />
                    </div>

                    <!-- Ngày thanh toán -->
                    <div>
                        <label
                            class="block text-sm font-medium text-gray-700 mb-2"
                        >
                            Ngày thanh toán
                        </label>
                        <input
                            type="datetime-local"
                            v-model="form.payment_date"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                    </div>

                    <!-- Ghi chú -->
                    <div>
                        <label
                            class="block text-sm font-medium text-gray-700 mb-2"
                        >
                            Ghi chú
                        </label>
                        <textarea
                            v-model="form.note"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            rows="3"
                            placeholder="Ghi chú thanh toán (tùy chọn)"
                        ></textarea>
                    </div>

                    <!-- Tạo phiếu thu tự động -->
                    <div class="flex items-center">
                        <input
                            type="checkbox"
                            id="auto_receipt"
                            v-model="form.auto_create_receipt"
                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                        />
                        <label
                            for="auto_receipt"
                            class="ml-2 text-sm text-gray-700"
                        >
                            Tự động tạo phiếu thu tiền mặt
                        </label>
                    </div>

                    <!-- Hiển thị chi tiết thanh toán -->
                    <div
                        v-if="form.amount > 0"
                        class="bg-blue-50 p-4 rounded-lg"
                    >
                        <h5 class="font-medium text-blue-900 mb-2">
                            Chi tiết thanh toán
                        </h5>
                        <div class="text-sm space-y-1">
                            <div class="flex justify-between">
                                <span class="text-blue-700"
                                    >Số tiền thanh toán:</span
                                >
                                <span class="font-medium text-blue-900">{{
                                    formatCurrency(form.amount)
                                }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-blue-700"
                                    >Còn lại sau thanh toán:</span
                                >
                                <span
                                    class="font-medium"
                                    :class="
                                        remainingDebt > 0
                                            ? 'text-red-600'
                                            : 'text-green-600'
                                    "
                                >
                                    {{ formatCurrency(remainingDebt) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action buttons -->
                <div class="flex justify-end space-x-3 mt-6">
                    <button
                        type="button"
                        @click="$emit('close')"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500"
                    >
                        Hủy
                    </button>
                    <button
                        type="submit"
                        :disabled="!isFormValid || loading"
                        class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <i
                            v-if="loading"
                            class="fas fa-spinner fa-spin mr-2"
                        ></i>
                        {{ loading ? "Đang xử lý..." : "Thanh toán" }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";

const props = defineProps({
    order: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(["close", "submit"]);

const loading = ref(false);

const form = ref({
    amount: 0,
    payment_method: "",
    transaction_id: "",
    payment_date: "",
    note: "",
    auto_create_receipt: true,
});

// Computed properties
const remainingDebt = computed(() => {
    return Math.max(0, (props.order.debt || 0) - (form.value.amount || 0));
});

const isFormValid = computed(() => {
    return (
        form.value.amount > 0 &&
        form.value.amount <= (props.order.debt || 0) &&
        form.value.payment_method &&
        form.value.payment_method !== ""
    );
});

// Methods
const handleSubmit = async () => {
    if (!isFormValid.value) return;

    loading.value = true;
    try {
        await emit("submit", form.value);
    } catch (error) {
        console.error("Payment error:", error);
    } finally {
        loading.value = false;
    }
};

const formatCurrency = (amount) => {
    return new Intl.NumberFormat("vi-VN", {
        style: "currency",
        currency: "VND",
    }).format(amount || 0);
};

// Initialize form
onMounted(() => {
    form.value.amount = props.order.debt || 0;

    // Set default payment date to now
    const now = new Date();
    form.value.payment_date = now.toISOString().slice(0, 16);
});
</script>

<style scoped>
/* Animation for modal */
.fixed {
    animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

/* Custom input styles */
input:focus,
select:focus,
textarea:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
}

/* Custom checkbox */
input[type="checkbox"]:checked {
    background-color: #3b82f6;
    border-color: #3b82f6;
}
</style>
