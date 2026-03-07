<template>
    <div v-if="canShowReturnButton" class="mt-4 pt-4 border-t">
        <button
            @click="showReturnModal = true"
            class="inline-flex items-center px-4 py-2 bg-orange-600 text-white rounded-md hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-orange-500"
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
                    d="M16 15v-1a4 4 0 00-4-4H8m0 0l3 3m-3-3l3-3m5 14-5-2m0 0l-5 2m5-2v-8a2 2 0 012-2h3a2 2 0 012 2v8z"
                />
            </svg>
            Trả hàng
        </button>

        <!-- Return Modal -->
        <div
            v-if="showReturnModal"
            class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4"
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
                                />
                            </svg>
                        </button>
                    </div>

                    <form @submit.prevent="createReturn">
                        <!-- Order Info -->
                        <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                            <h4 class="font-medium mb-2">Thông tin đơn hàng</h4>
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <span class="text-gray-600"
                                        >Mã đơn hàng:</span
                                    >
                                    <span class="font-medium ml-2">{{
                                        order.code
                                    }}</span>
                                </div>
                                <div>
                                    <span class="text-gray-600"
                                        >Khách hàng:</span
                                    >
                                    <span class="font-medium ml-2">{{
                                        order.customer?.name
                                    }}</span>
                                </div>
                                <div>
                                    <span class="text-gray-600"
                                        >Tổng tiền:</span
                                    >
                                    <span class="font-medium ml-2">{{
                                        formatCurrency(order.total)
                                    }}</span>
                                </div>
                                <div>
                                    <span class="text-gray-600">Ngày đặt:</span>
                                    <span class="font-medium ml-2">{{
                                        formatDate(order.created_at)
                                    }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Return Reason -->
                        <div class="mb-4">
                            <label
                                class="block text-sm font-medium text-gray-700 mb-2"
                            >
                                Lý do trả hàng
                            </label>
                            <textarea
                                v-model="returnForm.return_reason"
                                rows="3"
                                class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-500"
                                placeholder="Nhập lý do trả hàng..."
                            ></textarea>
                        </div>

                        <!-- Products to Return -->
                        <div class="mb-6">
                            <h4 class="font-medium mb-3">
                                Chọn sản phẩm trả hàng
                            </h4>
                            <div class="space-y-3">
                                <div
                                    v-for="item in order.items"
                                    :key="item.id"
                                    class="border rounded-lg p-4"
                                >
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <div
                                            class="flex items-center space-x-3"
                                        >
                                            <input
                                                type="checkbox"
                                                :id="`item-${item.id}`"
                                                v-model="selectedItems[item.id]"
                                                @change="toggleItem(item)"
                                                class="rounded border-gray-300 text-orange-600 focus:ring-orange-500"
                                            />
                                            <div>
                                                <h5 class="font-medium">
                                                    {{ item.product?.name }}
                                                </h5>
                                                <p
                                                    class="text-sm text-gray-600"
                                                >
                                                    Mã:
                                                    {{ item.product?.code }} |
                                                    Đã mua:
                                                    {{ item.quantity }} | Giá:
                                                    {{
                                                        formatCurrency(
                                                            item.unit_price
                                                        )
                                                    }}
                                                </p>
                                            </div>
                                        </div>

                                        <!-- Quantity Input -->
                                        <div
                                            v-if="selectedItems[item.id]"
                                            class="flex items-center space-x-2"
                                        >
                                            <label class="text-sm text-gray-600"
                                                >Số lượng trả:</label
                                            >
                                            <input
                                                type="number"
                                                v-model.number="
                                                    returnQuantities[item.id]
                                                "
                                                :max="
                                                    getMaxReturnQuantity(item)
                                                "
                                                min="1"
                                                class="w-20 border border-gray-300 rounded px-2 py-1 text-center focus:outline-none focus:ring-2 focus:ring-orange-500"
                                            />
                                            <span class="text-sm text-gray-500">
                                                /
                                                {{ getMaxReturnQuantity(item) }}
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Return Reason for Item -->
                                    <div
                                        v-if="selectedItems[item.id]"
                                        class="mt-3"
                                    >
                                        <label
                                            class="block text-sm text-gray-600 mb-1"
                                        >
                                            Lý do trả sản phẩm này:
                                        </label>
                                        <input
                                            type="text"
                                            v-model="itemReasons[item.id]"
                                            class="w-full border border-gray-300 rounded px-3 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500"
                                            placeholder="Lý do cụ thể..."
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Note -->
                        <div class="mb-6">
                            <label
                                class="block text-sm font-medium text-gray-700 mb-2"
                            >
                                Ghi chú thêm
                            </label>
                            <textarea
                                v-model="returnForm.note"
                                rows="2"
                                class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-500"
                                placeholder="Ghi chú thêm..."
                            ></textarea>
                        </div>

                        <!-- Summary -->
                        <div
                            v-if="hasSelectedItems"
                            class="mb-6 p-4 bg-blue-50 rounded-lg"
                        >
                            <h4 class="font-medium mb-2">
                                Tóm tắt đơn trả hàng
                            </h4>
                            <div class="space-y-1 text-sm">
                                <div
                                    v-for="item in getSelectedItemsForReturn()"
                                    :key="item.id"
                                >
                                    <span class="font-medium">{{
                                        item.product?.name
                                    }}</span
                                    >: {{ returnQuantities[item.id] }} ×
                                    {{ formatCurrency(item.unit_price) }} =
                                    <span class="font-medium">{{
                                        formatCurrency(
                                            returnQuantities[item.id] *
                                                item.unit_price
                                        )
                                    }}</span>
                                </div>
                                <div class="border-t pt-2 font-medium">
                                    Tổng tiền trả:
                                    {{ formatCurrency(getTotalReturnAmount()) }}
                                </div>
                            </div>
                        </div>

                        <!-- Buttons -->
                        <div class="flex justify-end space-x-3">
                            <button
                                type="button"
                                @click="closeReturnModal"
                                class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
                            >
                                Hủy
                            </button>
                            <button
                                type="submit"
                                :disabled="!hasSelectedItems || loading"
                                class="px-4 py-2 bg-orange-600 text-white rounded-md hover:bg-orange-700 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                {{
                                    loading
                                        ? "Đang xử lý..."
                                        : "Tạo đơn trả hàng"
                                }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, computed, watch } from "vue";
import { orderReturnApi, orderReturnHelpers } from "../api/orderReturnApi";

export default {
    name: "OrderReturnButton",
    props: {
        order: {
            type: Object,
            required: true,
        },
    },
    emits: ["returnCreated"],
    setup(props, { emit }) {
        const showReturnModal = ref(false);
        const loading = ref(false);
        const selectedItems = ref({});
        const returnQuantities = ref({});
        const itemReasons = ref({});

        const returnForm = ref({
            return_reason: "",
            note: "",
        });

        // Computed
        const canShowReturnButton = computed(() => {
            return (
                props.order &&
                ["completed", "delivered"].includes(props.order.status)
            );
        });

        const hasSelectedItems = computed(() => {
            return Object.values(selectedItems.value).some(
                (selected) => selected
            );
        });

        // Methods
        const toggleItem = (item) => {
            if (selectedItems.value[item.id]) {
                returnQuantities.value[item.id] = 1; // Default quantity
            } else {
                delete returnQuantities.value[item.id];
                delete itemReasons.value[item.id];
            }
        };

        const getMaxReturnQuantity = (item) => {
            // In a real implementation, you'd check how many have already been returned
            // For now, assume full quantity can be returned
            return item.quantity;
        };

        const getSelectedItemsForReturn = () => {
            return props.order.items.filter(
                (item) => selectedItems.value[item.id]
            );
        };

        const getTotalReturnAmount = () => {
            return getSelectedItemsForReturn().reduce((total, item) => {
                const quantity = returnQuantities.value[item.id] || 0;
                return total + quantity * item.unit_price;
            }, 0);
        };

        const createReturn = async () => {
            if (!hasSelectedItems.value) {
                alert("Vui lòng chọn ít nhất một sản phẩm để trả");
                return;
            }

            loading.value = true;
            try {
                const items = getSelectedItemsForReturn().map((item) => ({
                    order_item_id: item.id,
                    quantity: returnQuantities.value[item.id],
                    return_reason: itemReasons.value[item.id] || "",
                }));

                const data = {
                    items,
                    return_reason: returnForm.value.return_reason,
                    note: returnForm.value.note,
                };

                const response = await orderReturnApi.create(
                    props.order.id,
                    data
                );

                if (response.success) {
                    alert("Tạo đơn trả hàng thành công!");
                    emit("returnCreated", response.data);
                    closeReturnModal();
                } else {
                    alert(response.message || "Có lỗi xảy ra");
                }
            } catch (error) {
                alert("Có lỗi xảy ra khi tạo đơn trả hàng");
                console.error(error);
            } finally {
                loading.value = false;
            }
        };

        const closeReturnModal = () => {
            showReturnModal.value = false;
            selectedItems.value = {};
            returnQuantities.value = {};
            itemReasons.value = {};
            returnForm.value = {
                return_reason: "",
                note: "",
            };
        };

        const formatCurrency = orderReturnHelpers.formatCurrency;
        const formatDate = orderReturnHelpers.formatDate;

        return {
            showReturnModal,
            loading,
            selectedItems,
            returnQuantities,
            itemReasons,
            returnForm,
            canShowReturnButton,
            hasSelectedItems,
            toggleItem,
            getMaxReturnQuantity,
            getSelectedItemsForReturn,
            getTotalReturnAmount,
            createReturn,
            closeReturnModal,
            formatCurrency,
            formatDate,
        };
    },
};
</script>
