<template>
    <div class="order-workflow-container">
        <!-- Header với tiến trình 5 bước -->
        <div class="workflow-header bg-white rounded-lg shadow-sm p-6 mb-6">
            <div class="flex items-center justify-between mb-4">
                <h1 class="text-2xl font-bold text-gray-900">
                    Đơn hàng {{ order.code }}
                </h1>
                <div class="flex items-center space-x-2">
                    <span
                        class="px-3 py-1 rounded-full text-sm font-medium"
                        :class="getStatusClass(order.status)"
                    >
                        {{ getStatusText(order.status) }}
                    </span>
                    <button
                        v-if="order.can_cancel"
                        @click="cancelOrder"
                        class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600"
                    >
                        Hủy đơn
                    </button>
                </div>
            </div>

            <!-- Tiến trình 5 bước -->
            <div class="workflow-steps">
                <div class="flex items-center justify-between">
                    <div
                        v-for="(step, index) in workflowSteps"
                        :key="step.status"
                        class="flex items-center"
                        :class="
                            index < workflowSteps.length - 1 ? 'flex-1' : ''
                        "
                    >
                        <!-- Step circle -->
                        <div class="flex items-center">
                            <div
                                class="w-10 h-10 rounded-full flex items-center justify-center text-sm font-medium"
                                :class="getStepClass(step.status, index)"
                            >
                                <i
                                    v-if="isStepCompleted(step.status)"
                                    class="fas fa-check"
                                ></i>
                                <span v-else>{{ index + 1 }}</span>
                            </div>
                            <div class="ml-3">
                                <div class="text-sm font-medium text-gray-900">
                                    {{ step.label }}
                                </div>
                                <div class="text-xs text-gray-500">
                                    {{ step.description }}
                                </div>
                            </div>
                        </div>

                        <!-- Connector line -->
                        <div
                            v-if="index < workflowSteps.length - 1"
                            class="flex-1 h-0.5 mx-4"
                            :class="
                                isStepCompleted(workflowSteps[index + 1].status)
                                    ? 'bg-green-400'
                                    : 'bg-gray-200'
                            "
                        ></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Button (góc phải) -->
        <div class="fixed top-20 right-6 z-50" v-if="nextAction">
            <button
                @click="executeNextAction"
                class="px-6 py-3 rounded-lg font-medium shadow-lg text-white"
                :class="getActionButtonClass(nextAction.color)"
            >
                {{ nextAction.label }}
            </button>
        </div>

        <!-- Order Details -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Thông tin đơn hàng -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h3 class="text-lg font-semibold mb-4">
                        Thông tin đơn hàng
                    </h3>

                    <!-- Customer Info -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div>
                            <label class="text-sm text-gray-600"
                                >Khách hàng</label
                            >
                            <p class="font-medium">
                                {{ order.customer?.name }}
                            </p>
                            <p class="text-sm text-gray-500">
                                {{ order.customer?.phone }}
                            </p>
                        </div>
                        <div>
                            <label class="text-sm text-gray-600"
                                >Kho hàng</label
                            >
                            <p class="font-medium">
                                {{ order.warehouse?.name }}
                            </p>
                        </div>
                    </div>

                    <!-- Order Items -->
                    <div class="border-t pt-4">
                        <h4 class="font-medium mb-3">Sản phẩm</h4>
                        <div class="space-y-3">
                            <div
                                v-for="item in order.items"
                                :key="item.id"
                                class="flex justify-between items-center py-2 border-b border-gray-100"
                            >
                                <div class="flex-1">
                                    <p class="font-medium">
                                        {{ item.product_name }}
                                    </p>
                                    <p class="text-sm text-gray-500">
                                        {{ item.sku }}
                                    </p>
                                </div>
                                <div class="text-center w-20">
                                    <p class="font-medium">
                                        {{ item.quantity }}
                                    </p>
                                </div>
                                <div class="text-right w-32">
                                    <p class="font-medium">
                                        {{ formatCurrency(item.price) }}
                                    </p>
                                    <p class="text-sm text-gray-500">
                                        {{ formatCurrency(item.total) }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Total -->
                    <div class="border-t pt-4 mt-4">
                        <div
                            class="flex justify-between items-center text-lg font-semibold"
                        >
                            <span>Tổng tiền:</span>
                            <span>{{ formatCurrency(order.total) }}</span>
                        </div>
                        <div
                            class="flex justify-between items-center text-sm text-gray-600 mt-1"
                        >
                            <span>Đã thanh toán:</span>
                            <span>{{ formatCurrency(order.paid) }}</span>
                        </div>
                        <div
                            class="flex justify-between items-center text-sm font-medium text-red-600 mt-1"
                        >
                            <span>Còn lại:</span>
                            <span>{{ formatCurrency(order.debt) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar - Actions & Info -->
            <div class="space-y-6">
                <!-- Shipping Info -->
                <div
                    v-if="order.shipping"
                    class="bg-white rounded-lg shadow-sm p-4"
                >
                    <h4 class="font-medium mb-3">Thông tin vận chuyển</h4>
                    <div class="space-y-2 text-sm">
                        <div>
                            <span class="text-gray-600">Phương thức:</span>
                            <span class="ml-2">{{
                                getShippingMethodText(
                                    order.shipping.shipping_method
                                )
                            }}</span>
                        </div>
                        <div v-if="order.shipping.carrier">
                            <span class="text-gray-600">Đơn vị:</span>
                            <span class="ml-2">{{
                                order.shipping.carrier
                            }}</span>
                        </div>
                        <div v-if="order.shipping.shipping_fee > 0">
                            <span class="text-gray-600">Phí vận chuyển:</span>
                            <span class="ml-2">{{
                                formatCurrency(order.shipping.shipping_fee)
                            }}</span>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="bg-white rounded-lg shadow-sm p-4">
                    <h4 class="font-medium mb-3">Thao tác nhanh</h4>
                    <div class="space-y-2">
                        <button
                            class="w-full px-4 py-2 text-left text-sm bg-gray-50 hover:bg-gray-100 rounded"
                        >
                            In đơn hàng
                        </button>
                        <button
                            v-if="order.status === 'delivered'"
                            class="w-full px-4 py-2 text-left text-sm bg-gray-50 hover:bg-gray-100 rounded"
                        >
                            Đổi trả hàng
                        </button>
                        <button
                            class="w-full px-4 py-2 text-left text-sm bg-gray-50 hover:bg-gray-100 rounded"
                        >
                            Xem lịch sử
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Modals -->
        <ShippingMethodModal
            v-if="showShippingModal"
            @close="showShippingModal = false"
            @submit="handleShippingSubmit"
        />

        <PaymentModal
            v-if="showPaymentModal"
            :order="order"
            @close="showPaymentModal = false"
            @submit="handlePaymentSubmit"
        />
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { useRoute } from "vue-router";
import { orderApi } from "../api/orderApi";
import ShippingMethodModal from "./modals/ShippingMethodModal.vue";
import PaymentModal from "./modals/PaymentModal.vue";

const route = useRoute();
const order = ref({});
const nextAction = ref(null);
const showShippingModal = ref(false);
const showPaymentModal = ref(false);

// Workflow steps definition
const workflowSteps = [
    { status: "ordered", label: "Đặt hàng", description: "Tạo đơn hàng" },
    { status: "approved", label: "Duyệt", description: "Xác nhận đơn hàng" },
    {
        status: "shipping_created",
        label: "Vận chuyển",
        description: "Tạo đơn giao hàng",
    },
    {
        status: "delivered",
        label: "Xuất kho",
        description: "Giao hàng thành công",
    },
    {
        status: "completed",
        label: "Hoàn thành",
        description: "Thanh toán xong",
    },
];

const loadOrder = async () => {
    try {
        const response = await orderApi.getNextAction(route.params.id);
        order.value = response.data.order;
        nextAction.value = response.data.next_action;
    } catch (error) {
        console.error("Error loading order:", error);
    }
};

const executeNextAction = () => {
    switch (nextAction.value.action) {
        case "approve":
            approveOrder();
            break;
        case "create_shipping":
            showShippingModal.value = true;
            break;
        case "export_stock":
            exportStock();
            break;
        case "payment":
            showPaymentModal.value = true;
            break;
    }
};

const approveOrder = async () => {
    try {
        await orderApi.approveOrder(order.value.id);
        await loadOrder(); // Reload to get updated status
    } catch (error) {
        console.error("Error approving order:", error);
    }
};

const exportStock = async () => {
    try {
        await orderApi.exportStock(order.value.id);
        await loadOrder();
    } catch (error) {
        console.error("Error exporting stock:", error);
    }
};

const handleShippingSubmit = async (shippingData) => {
    try {
        await orderApi.createShipping(order.value.id, shippingData);
        showShippingModal.value = false;
        await loadOrder();
    } catch (error) {
        console.error("Error creating shipping:", error);
    }
};

const handlePaymentSubmit = async (paymentData) => {
    try {
        await orderApi.completePayment(order.value.id, paymentData);
        showPaymentModal.value = false;
        await loadOrder();
    } catch (error) {
        console.error("Error completing payment:", error);
    }
};

const cancelOrder = async () => {
    if (confirm("Bạn có chắc chắn muốn hủy đơn hàng này?")) {
        try {
            const note = prompt("Lý do hủy đơn:");
            if (note) {
                await orderApi.cancelOrder(order.value.id, { note });
                await loadOrder();
            }
        } catch (error) {
            console.error("Error cancelling order:", error);
        }
    }
};

// Utility functions
const isStepCompleted = (stepStatus) => {
    const statusOrder = [
        "ordered",
        "approved",
        "shipping_created",
        "delivered",
        "completed",
    ];
    const currentIndex = statusOrder.indexOf(order.value.status);
    const stepIndex = statusOrder.indexOf(stepStatus);
    return currentIndex >= stepIndex;
};

const getStepClass = (stepStatus, index) => {
    if (isStepCompleted(stepStatus)) {
        return "bg-green-500 text-white";
    } else if (order.value.status === stepStatus) {
        return "bg-blue-500 text-white";
    } else {
        return "bg-gray-200 text-gray-600";
    }
};

const getStatusClass = (status) => {
    const classes = {
        ordered: "bg-yellow-100 text-yellow-800",
        approved: "bg-blue-100 text-blue-800",
        shipping_created: "bg-purple-100 text-purple-800",
        delivered: "bg-green-100 text-green-800",
        completed: "bg-green-100 text-green-800",
        cancelled: "bg-red-100 text-red-800",
    };
    return classes[status] || "bg-gray-100 text-gray-800";
};

const getStatusText = (status) => {
    const texts = {
        ordered: "Đặt hàng",
        approved: "Đã duyệt",
        shipping_created: "Đã tạo vận chuyển",
        delivered: "Đã giao hàng",
        completed: "Hoàn thành",
        cancelled: "Đã hủy",
    };
    return texts[status] || status;
};

const getActionButtonClass = (color) => {
    const classes = {
        blue: "bg-blue-500 hover:bg-blue-600",
        purple: "bg-purple-500 hover:bg-purple-600",
        orange: "bg-orange-500 hover:bg-orange-600",
        green: "bg-green-500 hover:bg-green-600",
    };
    return classes[color] || "bg-gray-500 hover:bg-gray-600";
};

const getShippingMethodText = (method) => {
    const methods = {
        third_party: "Gửi cho bên giao hàng",
        self_delivery: "Tự giao hàng",
        pickup: "Nhận tại cửa hàng",
    };
    return methods[method] || method;
};

const formatCurrency = (amount) => {
    return new Intl.NumberFormat("vi-VN", {
        style: "currency",
        currency: "VND",
    }).format(amount || 0);
};

onMounted(() => {
    loadOrder();
});
</script>

<style scoped>
.order-workflow-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.workflow-steps {
    margin-top: 20px;
}

@media (max-width: 768px) {
    .workflow-steps .flex {
        flex-direction: column;
        align-items: flex-start;
    }

    .workflow-steps .flex-1 {
        width: 100%;
        margin: 10px 0;
    }
}

/* Custom styles for better mobile experience */
@media (max-width: 640px) {
    .fixed.right-6 {
        position: fixed;
        bottom: 20px;
        right: 20px;
        top: auto;
    }
}
</style>
