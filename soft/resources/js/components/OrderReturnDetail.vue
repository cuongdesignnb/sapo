<template>
    <div class="bg-white">
        <!-- Header -->
        <div class="flex items-center justify-between p-6 border-b">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">
                    Chi tiết đơn trả hàng {{ orderReturn.code }}
                </h2>
                <p class="text-sm text-gray-500 mt-1">
                    Tạo lúc {{ formatDate(orderReturn.created_at) }}
                </p>
            </div>
            <div class="flex items-center space-x-3">
                <span
                    :class="getStatusClass(orderReturn.status)"
                    class="inline-flex px-3 py-1 text-sm font-semibold rounded-full"
                >
                    {{ getStatusText(orderReturn.status) }}
                </span>
                <button
                    @click="$emit('close')"
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
                        />
                    </svg>
                </button>
            </div>
        </div>

        <div class="p-6 space-y-6">
            <!-- Order & Customer Info -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Order Info -->
                <div class="bg-gray-50 rounded-lg p-4">
                    <h3 class="font-semibold mb-3">Thông tin đơn hàng gốc</h3>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Mã đơn hàng:</span>
                            <span class="font-medium">{{
                                orderReturn.order?.code
                            }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Tổng tiền đơn:</span>
                            <span class="font-medium">{{
                                formatCurrency(orderReturn.order?.total)
                            }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Ngày đặt:</span>
                            <span class="font-medium">{{
                                formatDate(orderReturn.order?.created_at)
                            }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Kho hàng:</span>
                            <span class="font-medium">{{
                                orderReturn.warehouse?.name
                            }}</span>
                        </div>
                    </div>
                </div>

                <!-- Customer Info -->
                <div class="bg-gray-50 rounded-lg p-4">
                    <h3 class="font-semibold mb-3">Thông tin khách hàng</h3>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Tên khách hàng:</span>
                            <span class="font-medium">{{
                                orderReturn.customer?.name
                            }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Số điện thoại:</span>
                            <span class="font-medium">{{
                                orderReturn.customer?.phone
                            }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Email:</span>
                            <span class="font-medium">{{
                                orderReturn.customer?.email || "N/A"
                            }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Mã khách hàng:</span>
                            <span class="font-medium">{{
                                orderReturn.customer?.code
                            }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Return Info -->
            <div class="bg-blue-50 rounded-lg p-4">
                <h3 class="font-semibold mb-3">Thông tin trả hàng</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Tổng tiền trả:</span>
                        <span class="font-bold text-blue-600">{{
                            formatCurrency(orderReturn.total)
                        }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600"
                            >{{ getRefundLabel(orderReturn) }} đã thực
                            hiện:</span
                        >
                        <span class="font-bold text-green-600">{{
                            formatCurrency(orderReturn.refunded)
                        }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600"
                            >Còn phải
                            {{
                                getRefundLabel(orderReturn).toLowerCase()
                            }}:</span
                        >
                        <span class="font-bold text-orange-600">{{
                            formatCurrency(getRemainingRefund(orderReturn))
                        }}</span>
                    </div>

                    <!-- Nợ còn lại sau khi trả hàng -->
                    <div class="flex justify-between pt-2 border-t">
                        <span class="text-gray-600"
                            >Nợ còn lại sau khi trả hàng:</span
                        >
                        <span
                            class="font-bold"
                            :class="getRemainingDebtClass(orderReturn)"
                            >{{
                                formatCurrency(
                                    getRemainingDebtAfterReturn(orderReturn)
                                )
                            }}</span
                        >
                    </div>
                </div>

                <div v-if="orderReturn.return_reason" class="mt-3">
                    <span class="text-gray-600 text-sm">Lý do trả hàng:</span>
                    <p class="mt-1 text-sm">{{ orderReturn.return_reason }}</p>
                </div>

                <div v-if="orderReturn.note" class="mt-3">
                    <span class="text-gray-600 text-sm">Ghi chú:</span>
                    <p class="mt-1 text-sm">{{ orderReturn.note }}</p>
                </div>
            </div>

            <!-- Return Items -->
            <div>
                <h3 class="font-semibold mb-3">Sản phẩm trả hàng</h3>
                <div class="overflow-x-auto">
                    <table class="w-full border rounded-lg">
                        <thead class="bg-gray-50">
                            <tr>
                                <th
                                    class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase"
                                >
                                    Sản phẩm
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase"
                                >
                                    Số lượng trả
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase"
                                >
                                    Đơn giá
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase"
                                >
                                    Thành tiền
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase"
                                >
                                    Lý do
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr
                                v-for="item in orderReturn.items"
                                :key="item.id"
                            >
                                <td class="px-4 py-3">
                                    <div class="font-medium">
                                        {{ item.product?.name }}
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        {{ item.product?.code }}
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    {{ item.quantity }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ formatCurrency(item.unit_price) }}
                                </td>
                                <td class="px-4 py-3 font-medium">
                                    {{ formatCurrency(item.total_price) }}
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    {{ item.return_reason || "N/A" }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex justify-end space-x-3 pt-4 border-t">
                <button
                    v-if="canApprove(orderReturn)"
                    @click="approveReturn"
                    :disabled="loading"
                    class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 disabled:opacity-50"
                >
                    {{ loading ? "Đang xử lý..." : "Duyệt đơn trả hàng" }}
                </button>

                <button
                    v-if="canRefund(orderReturn)"
                    @click="showRefundModal = true"
                    class="px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700"
                >
                    Hoàn tiền
                </button>

                <button
                    v-if="canCancel(orderReturn)"
                    @click="cancelReturn"
                    :disabled="loading"
                    class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 disabled:opacity-50"
                >
                    {{ loading ? "Đang xử lý..." : "Hủy đơn trả hàng" }}
                </button>

                <button
                    @click="printReturn"
                    class="px-4 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50"
                >
                    In phiếu trả hàng
                </button>
            </div>

            <!-- History/Timeline -->
            <div v-if="orderReturn.status_history?.length > 0">
                <h3 class="font-semibold mb-3">Lịch sử trạng thái</h3>
                <div class="space-y-3">
                    <div
                        v-for="history in orderReturn.status_history"
                        :key="history.id"
                        class="flex items-start space-x-3 p-3 bg-gray-50 rounded-lg"
                    >
                        <div
                            class="flex-shrink-0 w-2 h-2 bg-blue-500 rounded-full mt-2"
                        ></div>
                        <div class="flex-1">
                            <div class="text-sm font-medium">
                                {{ getStatusText(history.to_status) }}
                            </div>
                            <div class="text-xs text-gray-500">
                                {{ formatDate(history.created_at) }} -
                                {{ history.changed_by?.name }}
                            </div>
                            <div
                                v-if="history.note"
                                class="text-sm text-gray-600 mt-1"
                            >
                                {{ history.note }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Refund Modal -->
        <div
            v-if="showRefundModal"
            class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4"
        >
            <div class="bg-white rounded-lg w-full max-w-md">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">
                        Hoàn tiền cho khách hàng
                    </h3>

                    <div class="mb-4 p-3 bg-yellow-50 rounded-lg">
                        <div class="text-sm text-yellow-800">
                            <div>
                                Tổng tiền trả:
                                <span class="font-medium">{{
                                    formatCurrency(orderReturn.total)
                                }}</span>
                            </div>
                            <div>
                                Đã hoàn:
                                <span class="font-medium">{{
                                    formatCurrency(orderReturn.refunded)
                                }}</span>
                            </div>
                            <div>
                                Còn lại:
                                <span class="font-bold">{{
                                    formatCurrency(
                                        getRemainingRefund(orderReturn)
                                    )
                                }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label
                            class="block text-sm font-medium text-gray-700 mb-2"
                        >
                            Số tiền hoàn *
                        </label>
                        <input
                            v-model.number="refundForm.amount"
                            type="number"
                            :max="getRemainingRefund(orderReturn)"
                            min="0"
                            step="1000"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500"
                            required
                        />
                    </div>

                    <div class="mb-4">
                        <label
                            class="block text-sm font-medium text-gray-700 mb-2"
                        >
                            Ghi chú
                        </label>
                        <textarea
                            v-model="refundForm.note"
                            rows="3"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500"
                            placeholder="Ghi chú về việc hoàn tiền..."
                        ></textarea>
                    </div>

                    <div class="text-sm text-gray-600 mb-4">
                        <div class="font-medium mb-1">Cách thức hoàn tiền:</div>
                        <ul class="list-disc list-inside space-y-1">
                            <li>Nếu khách hàng có nợ cũ: sẽ trừ nợ trước</li>
                            <li>Nếu không có nợ: hoàn tiền trực tiếp</li>
                            <li>Số dư sau khi trừ nợ sẽ được hoàn lại</li>
                        </ul>
                    </div>

                    <div class="flex justify-end space-x-3">
                        <button
                            @click="closeRefundModal"
                            class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
                        >
                            Hủy
                        </button>
                        <button
                            @click="processRefund"
                            :disabled="
                                refundLoading ||
                                !refundForm.amount ||
                                refundForm.amount <= 0
                            "
                            class="px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700 disabled:opacity-50"
                        >
                            {{
                                refundLoading
                                    ? "Đang xử lý..."
                                    : "Xác nhận hoàn tiền"
                            }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, computed } from "vue";
import { orderReturnApi, orderReturnHelpers } from "../api/orderReturnApi";

export default {
    name: "OrderReturnDetail",
    props: {
        orderReturn: {
            type: Object,
            required: true,
        },
    },
    emits: ["close", "updated"],
    setup(props, { emit }) {
        const loading = ref(false);
        const showRefundModal = ref(false);
        const refundLoading = ref(false);

        const refundForm = ref({
            amount: 0,
            note: "",
        });

        // Methods
        const approveReturn = async () => {
            if (
                !confirm(
                    "Bạn có chắc chắn muốn duyệt đơn trả hàng này?\n\nViệc duyệt sẽ xác nhận đã nhận lại hàng vào kho."
                )
            ) {
                return;
            }

            loading.value = true;
            try {
                const response = await orderReturnApi.approve(
                    props.orderReturn.id
                );
                if (response.success) {
                    alert("Duyệt đơn trả hàng thành công!");
                    emit("updated");
                } else {
                    alert(response.message || "Có lỗi xảy ra");
                }
            } catch (error) {
                alert("Có lỗi xảy ra khi duyệt đơn trả hàng");
                console.error(error);
            } finally {
                loading.value = false;
            }
        };

        const cancelReturn = async () => {
            if (
                !confirm(
                    "Bạn có chắc chắn muốn hủy đơn trả hàng này?\n\nHàng đã nhận sẽ được trả lại kho ban đầu."
                )
            ) {
                return;
            }

            loading.value = true;
            try {
                const response = await orderReturnApi.cancel(
                    props.orderReturn.id
                );
                if (response.success) {
                    alert("Hủy đơn trả hàng thành công!");
                    emit("updated");
                } else {
                    alert(response.message || "Có lỗi xảy ra");
                }
            } catch (error) {
                alert("Có lỗi xảy ra khi hủy đơn trả hàng");
                console.error(error);
            } finally {
                loading.value = false;
            }
        };

        const processRefund = async () => {
            if (!refundForm.value.amount || refundForm.value.amount <= 0) {
                alert("Vui lòng nhập số tiền hoàn hợp lệ");
                return;
            }

            if (
                refundForm.value.amount > getRemainingRefund(props.orderReturn)
            ) {
                alert("Số tiền hoàn không thể vượt quá số tiền còn lại");
                return;
            }

            if (
                !confirm(
                    `Xác nhận hoàn tiền ${formatCurrency(
                        refundForm.value.amount
                    )} cho khách hàng?`
                )
            ) {
                return;
            }

            refundLoading.value = true;
            try {
                const response = await orderReturnApi.refund(
                    props.orderReturn.id,
                    refundForm.value
                );
                if (response.success) {
                    alert("Hoàn tiền thành công!");
                    closeRefundModal();
                    emit("updated");
                } else {
                    alert(response.message || "Có lỗi xảy ra");
                }
            } catch (error) {
                alert("Có lỗi xảy ra khi hoàn tiền");
                console.error(error);
            } finally {
                refundLoading.value = false;
            }
        };

        const closeRefundModal = () => {
            showRefundModal.value = false;
            refundForm.value = {
                amount: 0,
                note: "",
            };
        };

        const printReturn = () => {
            // Implement print functionality
            alert("Chức năng in phiếu trả hàng sẽ được triển khai sau");
        };

        // When modal opens, set default refund amount
        const openRefundModal = () => {
            refundForm.value.amount = getRemainingRefund(props.orderReturn);
            showRefundModal.value = true;
        };

        // Helper methods
        const getStatusClass = orderReturnHelpers.getStatusColor;
        const getStatusText = orderReturnHelpers.getStatusText;
        const canApprove = orderReturnHelpers.canApprove;
        const canRefund = orderReturnHelpers.canRefund;
        const canCancel = orderReturnHelpers.canCancel;
        const getRemainingRefund = orderReturnHelpers.getRemainingRefund;
        const isOrderFullyPaid = orderReturnHelpers.isOrderFullyPaid;
        const getRefundLabel = orderReturnHelpers.getRefundLabel;
        const getRemainingDebtAfterReturn =
            orderReturnHelpers.getRemainingDebtAfterReturn;
        const getRemainingDebtClass = orderReturnHelpers.getRemainingDebtClass;
        const formatCurrency = orderReturnHelpers.formatCurrency;
        const formatDate = orderReturnHelpers.formatDate;

        return {
            loading,
            showRefundModal,
            refundLoading,
            refundForm,
            approveReturn,
            cancelReturn,
            processRefund,
            closeRefundModal,
            printReturn,
            openRefundModal,
            getStatusClass,
            getStatusText,
            canApprove,
            canRefund,
            canCancel,
            getRemainingRefund,
            isOrderFullyPaid,
            getRefundLabel,
            getRemainingDebtAfterReturn,
            getRemainingDebtClass,
            formatCurrency,
            formatDate,
        };
    },
};
</script>
