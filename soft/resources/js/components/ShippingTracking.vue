<template>
    <div class="space-y-6">
        <!-- Shipping Info Header -->
        <div v-if="shipping" class="bg-white border rounded-lg p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold">Thông tin vận chuyển</h3>
                <div class="flex items-center space-x-2">
                    <span
                        class="px-3 py-1 rounded-full text-sm font-medium"
                        :class="shippingHelpers.getStatusColor(shipping.status)"
                    >
                        {{ shippingHelpers.getStatusText(shipping.status) }}
                    </span>
                    <span
                        class="px-3 py-1 rounded-full text-sm font-medium"
                        :class="
                            shippingHelpers.getPaymentByColor(
                                shipping.payment_by
                            )
                        "
                    >
                        {{
                            shippingHelpers.getPaymentByText(
                                shipping.payment_by
                            )
                        }}
                    </span>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div>
                    <p class="text-sm text-gray-600">Mã vận đơn</p>
                    <p class="font-medium">{{ shipping.tracking_number }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Đơn vị vận chuyển</p>
                    <p class="font-medium">
                        {{ shipping.provider?.name || shipping.carrier }}
                    </p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Phí vận chuyển</p>
                    <p class="font-medium">
                        {{
                            shippingHelpers.formatCurrency(
                                shipping.shipping_fee
                            )
                        }}
                    </p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Người nhận</p>
                    <p class="font-medium">{{ shipping.delivery_contact }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Số điện thoại</p>
                    <p class="font-medium">{{ shipping.delivery_phone }}</p>
                </div>
                <div v-if="shipping.weight">
                    <p class="text-sm text-gray-600">Khối lượng</p>
                    <p class="font-medium">{{ shipping.weight }} kg</p>
                </div>
            </div>

            <div v-if="shipping.delivery_address" class="mt-4">
                <p class="text-sm text-gray-600">Địa chỉ giao hàng</p>
                <p class="font-medium">{{ shipping.delivery_address }}</p>
            </div>

            <!-- Payment Info Alert -->
            <div
                v-if="shipping.shipping_fee > 0"
                class="mt-4 p-3 rounded-md"
                :class="
                    shipping.payment_by === 'sender'
                        ? 'bg-blue-50 border border-blue-200'
                        : 'bg-orange-50 border border-orange-200'
                "
            >
                <div class="flex items-center">
                    <svg
                        class="w-5 h-5 mr-2"
                        :class="
                            shipping.payment_by === 'sender'
                                ? 'text-blue-400'
                                : 'text-orange-400'
                        "
                        fill="currentColor"
                        viewBox="0 0 20 20"
                    >
                        <path
                            fill-rule="evenodd"
                            d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                            clip-rule="evenodd"
                        ></path>
                    </svg>
                    <div class="text-sm">
                        <p
                            class="font-medium"
                            :class="
                                shipping.payment_by === 'sender'
                                    ? 'text-blue-900'
                                    : 'text-orange-900'
                            "
                        >
                            {{
                                shipping.payment_by === "sender"
                                    ? "Người gửi"
                                    : "Người nhận"
                            }}
                            thanh toán phí vận chuyển:
                            <span class="font-bold">{{
                                shippingHelpers.formatCurrency(
                                    shipping.shipping_fee
                                )
                            }}</span>
                        </p>
                        <p
                            class="mt-1"
                            :class="
                                shipping.payment_by === 'sender'
                                    ? 'text-blue-700'
                                    : 'text-orange-700'
                            "
                        >
                            {{
                                shipping.payment_by === "sender"
                                    ? "Đã cộng vào tổng đơn hàng"
                                    : "Sẽ thu khi giao hàng (COD)"
                            }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Delivery Action Button -->
            <div
                v-if="canUpdate && shippingHelpers.canConfirmDelivery(shipping)"
                class="mt-6 pt-4 border-t"
            >
                <button
                    @click="showDeliveryModal = true"
                    class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500"
                >
                    <svg
                        class="w-5 h-5 mr-2"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M5 13l4 4L19 7"
                        ></path>
                    </svg>
                    Xác nhận giao hàng
                </button>
                <!-- Print Button -->
                <button
                    @click="printShippingLabel"
                    class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 ml-3"
                >
                    <svg
                        class="w-5 h-5 mr-2"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a1 1 0 001-1v-4a1 1 0 00-1-1H9a1 1 0 00-1 1v4a1 1 0 001 1zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"
                        ></path>
                    </svg>
                    In vận đơn
                </button>
            </div>
        </div>

        <!-- Tracking History -->
        <div class="bg-white border rounded-lg p-6">
            <h4 class="text-lg font-semibold mb-4">Lịch sử vận chuyển</h4>

            <div
                v-if="logs.length === 0"
                class="text-center py-8 text-gray-500"
            >
                Chưa có thông tin tracking
            </div>

            <div v-else class="space-y-4">
                <div
                    v-for="(log, index) in logs"
                    :key="log.id"
                    class="flex items-start space-x-4 pb-4"
                    :class="{ 'border-b': index < logs.length - 1 }"
                >
                    <div class="flex-shrink-0">
                        <div
                            class="w-3 h-3 rounded-full mt-2"
                            :class="getLogStatusColor(log.status)"
                        ></div>
                    </div>

                    <div class="flex-1">
                        <div class="flex items-center justify-between">
                            <p class="font-medium">
                                {{ shippingHelpers.getStatusText(log.status) }}
                            </p>
                            <span class="text-sm text-gray-500">
                                {{ formatDate(log.logged_at) }}
                            </span>
                        </div>
                        <p
                            v-if="log.location"
                            class="text-sm text-gray-600 mt-1"
                        >
                            📍 {{ log.location }}
                        </p>
                        <p class="text-sm text-gray-700 mt-1">
                            {{ log.description }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Add Status Update -->
            <div
                v-if="
                    canUpdate &&
                    shipping &&
                    shippingHelpers.canUpdateStatus(shipping)
                "
                class="mt-6 pt-4 border-t"
            >
                <button
                    @click="showUpdateModal = true"
                    class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50"
                >
                    <svg
                        class="w-4 h-4 mr-2"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M12 6v6m0 0v6m0-6h6m-6 0H6"
                        ></path>
                    </svg>
                    Cập nhật trạng thái
                </button>
            </div>
        </div>

        <!-- Delivery Confirmation Modal -->
        <div
            v-if="showDeliveryModal"
            class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4"
        >
            <div class="bg-white rounded-lg w-full max-w-md">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">
                        Xác nhận giao hàng
                    </h3>

                    <div class="space-y-4">
                        <div>
                            <label
                                class="block text-sm font-medium text-gray-700 mb-2"
                                >Vị trí giao hàng</label
                            >
                            <input
                                type="text"
                                v-model="deliveryForm.location"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500"
                                placeholder="Nhập vị trí giao hàng"
                            />
                        </div>

                        <div>
                            <label
                                class="block text-sm font-medium text-gray-700 mb-2"
                                >Ghi chú *</label
                            >
                            <textarea
                                v-model="deliveryForm.description"
                                rows="3"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500"
                                :class="{
                                    'border-red-500':
                                        deliveryErrors.description,
                                }"
                                placeholder="Mô tả về việc giao hàng..."
                            ></textarea>
                            <p
                                v-if="deliveryErrors.description"
                                class="mt-1 text-sm text-red-600"
                            >
                                {{ deliveryErrors.description }}
                            </p>
                        </div>

                        <!-- Payment reminder for receiver pay -->
                        <div
                            v-if="
                                shipping?.payment_by === 'receiver' &&
                                shipping?.shipping_fee > 0
                            "
                            class="bg-orange-50 border border-orange-200 rounded-md p-3"
                        >
                            <div class="flex items-center">
                                <svg
                                    class="w-5 h-5 text-orange-400 mr-2"
                                    fill="currentColor"
                                    viewBox="0 0 20 20"
                                >
                                    <path
                                        fill-rule="evenodd"
                                        d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                        clip-rule="evenodd"
                                    ></path>
                                </svg>
                                <div class="text-sm text-orange-700">
                                    <p class="font-medium">
                                        Lưu ý: Thu phí vận chuyển
                                        {{
                                            shippingHelpers.formatCurrency(
                                                shipping.shipping_fee
                                            )
                                        }}
                                        từ người nhận
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-3 mt-6">
                        <button
                            @click="closeDeliveryModal"
                            class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
                        >
                            Hủy
                        </button>
                        <button
                            @click="confirmDelivery"
                            :disabled="deliveryLoading"
                            class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 disabled:opacity-50"
                        >
                            <span v-if="deliveryLoading">Đang xử lý...</span>
                            <span v-else>Xác nhận giao hàng</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Status Update Modal -->
        <div
            v-if="showUpdateModal"
            class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4"
        >
            <div class="bg-white rounded-lg w-full max-w-md">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">
                        Cập nhật trạng thái
                    </h3>

                    <div class="space-y-4">
                        <div>
                            <label
                                class="block text-sm font-medium text-gray-700 mb-2"
                                >Trạng thái</label
                            >
                            <select
                                v-model="updateForm.status"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            >
                                <option value="picked_up">Đã lấy hàng</option>
                                <option value="in_transit">
                                    Đang vận chuyển
                                </option>
                                <option value="failed">
                                    Giao hàng thất bại
                                </option>
                            </select>
                        </div>

                        <div>
                            <label
                                class="block text-sm font-medium text-gray-700 mb-2"
                                >Vị trí</label
                            >
                            <input
                                type="text"
                                v-model="updateForm.location"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="Nhập vị trí hiện tại"
                            />
                        </div>

                        <div>
                            <label
                                class="block text-sm font-medium text-gray-700 mb-2"
                                >Mô tả *</label
                            >
                            <textarea
                                v-model="updateForm.description"
                                rows="3"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="Mô tả trạng thái hiện tại..."
                            ></textarea>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-3 mt-6">
                        <button
                            @click="closeUpdateModal"
                            class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
                        >
                            Hủy
                        </button>
                        <button
                            @click="updateStatus"
                            :disabled="updateLoading"
                            class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50"
                        >
                            <span v-if="updateLoading">Đang cập nhật...</span>
                            <span v-else>Cập nhật</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, onMounted } from "vue";
import { shippingApi, shippingHelpers } from "../api/shippingApi";

export default {
    name: "ShippingTracking",
    props: {
        orderId: {
            type: [String, Number],
            required: true,
        },
        canUpdate: {
            type: Boolean,
            default: false,
        },
    },
    emits: ["updated"],
    setup(props, { emit }) {
        const loading = ref(false);
        const shipping = ref(null);
        const logs = ref([]);

        // Delivery modal
        const showDeliveryModal = ref(false);
        const deliveryLoading = ref(false);
        const deliveryErrors = ref({});
        const deliveryForm = ref({
            location: "",
            description: "",
        });

        // Update modal
        const showUpdateModal = ref(false);
        const updateLoading = ref(false);
        const updateForm = ref({
            status: "picked_up",
            location: "",
            description: "",
        });

        const loadTracking = async () => {
            loading.value = true;
            try {
                if (!props.orderId) return;
                const response = await shippingApi.getTracking(props.orderId);
                if (response.success && response.data?.shipping) {
                    const s = response.data.shipping;
                    shipping.value = {
                        ...s,
                        tracking_number:
                            s.tracking_number || s.trackingNumber || "",
                        delivery_contact:
                            s.delivery_contact || s.order?.customer?.name || "",
                        delivery_phone:
                            s.delivery_phone || s.order?.customer?.phone || "",
                        delivery_address:
                            s.delivery_address ||
                            s.order?.delivery_address ||
                            "",
                    };
                    logs.value = Array.isArray(response.data.logs)
                        ? [...response.data.logs]
                        : [];
                }
            } catch (error) {
                console.error("❌ Error loading tracking:", error);
            } finally {
                loading.value = false;
            }
        };
        const confirmDelivery = async () => {
            deliveryErrors.value = {};

            if (!deliveryForm.value.description) {
                deliveryErrors.value.description = "Vui lòng nhập ghi chú";
                return;
            }

            deliveryLoading.value = true;
            try {
                const response = await shippingApi.confirmDelivery(
                    props.orderId,
                    shipping.value.id,
                    deliveryForm.value
                );

                if (response.success) {
                    closeDeliveryModal();
                    await loadTracking();
                    emit("updated");
                }
            } catch (error) {
                console.error("Error confirming delivery:", error);
            } finally {
                deliveryLoading.value = false;
            }
        };

        const updateStatus = async () => {
            if (!updateForm.value.description) {
                return;
            }

            updateLoading.value = true;
            try {
                const response = await shippingApi.updateStatus(
                    props.orderId,
                    shipping.value.id,
                    updateForm.value
                );

                if (response.success) {
                    closeUpdateModal();
                    await loadTracking();
                    emit("updated");
                }
            } catch (error) {
                console.error("Error updating status:", error);
            } finally {
                updateLoading.value = false;
            }
        };

        const closeDeliveryModal = () => {
            showDeliveryModal.value = false;
            deliveryForm.value = { location: "", description: "" };
            deliveryErrors.value = {};
        };

        const closeUpdateModal = () => {
            showUpdateModal.value = false;
            updateForm.value = {
                status: "picked_up",
                location: "",
                description: "",
            };
        };

        // Print shipping label
        const printShippingLabel = () => {
            if (!shipping.value?.id) {
                alert("Không tìm thấy thông tin vận đơn");
                return;
            }

            const printUrl = `/api/shipping/${shipping.value.id}/print`;
            window.open(printUrl, "_blank");
        };

        const getLogStatusColor = (status) => {
            const colorMap = {
                pending: "bg-yellow-400",
                picked_up: "bg-blue-400",
                in_transit: "bg-indigo-400",
                delivered: "bg-green-400",
                failed: "bg-red-400",
            };
            return colorMap[status] || "bg-gray-400";
        };

        const formatDate = (date) => {
            return new Date(date).toLocaleString("vi-VN");
        };

        onMounted(() => {
            loadTracking();
        });

        return {
            loading,
            shipping,
            logs,
            showDeliveryModal,
            deliveryLoading,
            deliveryErrors,
            deliveryForm,
            showUpdateModal,
            updateLoading,
            updateForm,
            shippingHelpers,
            loadTracking,
            confirmDelivery,
            updateStatus,
            closeDeliveryModal,
            closeUpdateModal,
            getLogStatusColor,
            formatDate,
            printShippingLabel,
        };
    },
};
</script>
