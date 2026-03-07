<template>
    <div
        class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
    >
        <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold">
                    Chọn phương thức vận chuyển
                </h3>
                <button
                    @click="$emit('close')"
                    class="text-gray-400 hover:text-gray-600"
                >
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="space-y-4">
                <!-- Third Party -->
                <div
                    class="border rounded-lg p-4 cursor-pointer hover:bg-gray-50"
                    :class="
                        selectedMethod === 'third_party'
                            ? 'border-blue-500 bg-blue-50'
                            : 'border-gray-200'
                    "
                    @click="selectMethod('third_party')"
                >
                    <div class="flex items-center">
                        <input
                            type="radio"
                            v-model="selectedMethod"
                            value="third_party"
                            class="text-blue-600"
                        />
                        <div class="ml-3">
                            <label class="font-medium cursor-pointer"
                                >Gửi cho bên giao hàng</label
                            >
                            <p class="text-sm text-gray-500">
                                Sử dụng đơn vị vận chuyển thứ 3
                            </p>
                        </div>
                    </div>

                    <!-- Third party fields -->
                    <div
                        v-if="selectedMethod === 'third_party'"
                        class="mt-4 space-y-3"
                    >
                        <div>
                            <label
                                class="block text-sm font-medium text-gray-700 mb-1"
                            >
                                Đơn vị vận chuyển *
                            </label>
                            <input
                                v-model="form.provider_name"
                                type="text"
                                placeholder="VD: Grab, GiaoHangNhanh, Viettel Post..."
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            />
                        </div>
                        <div>
                            <label
                                class="block text-sm font-medium text-gray-700 mb-1"
                            >
                                Giá cước vận chuyển
                            </label>
                            <input
                                v-model="form.shipping_fee"
                                type="number"
                                placeholder="0"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            />
                            <p class="text-xs text-gray-500 mt-1">
                                Không bắt buộc nhập, chỉ để lưu thông tin
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Self Delivery -->
                <div
                    class="border rounded-lg p-4 cursor-pointer hover:bg-gray-50"
                    :class="
                        selectedMethod === 'self_delivery'
                            ? 'border-blue-500 bg-blue-50'
                            : 'border-gray-200'
                    "
                    @click="selectMethod('self_delivery')"
                >
                    <div class="flex items-center">
                        <input
                            type="radio"
                            v-model="selectedMethod"
                            value="self_delivery"
                            class="text-blue-600"
                        />
                        <div class="ml-3">
                            <label class="font-medium cursor-pointer"
                                >Tự giao hàng</label
                            >
                            <p class="text-sm text-gray-500">
                                Cửa hàng tự giao hàng
                            </p>
                        </div>
                    </div>

                    <!-- Self delivery fields -->
                    <div
                        v-if="selectedMethod === 'self_delivery'"
                        class="mt-4 space-y-3"
                    >
                        <div>
                            <label
                                class="block text-sm font-medium text-gray-700 mb-1"
                            >
                                Tên người nhận
                            </label>
                            <input
                                v-model="form.receiver_name"
                                type="text"
                                placeholder="Để trống sẽ dùng tên khách hàng"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            />
                        </div>
                        <div>
                            <label
                                class="block text-sm font-medium text-gray-700 mb-1"
                            >
                                Số điện thoại
                            </label>
                            <input
                                v-model="form.receiver_phone"
                                type="text"
                                placeholder="Để trống sẽ dùng SĐT khách hàng"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            />
                        </div>
                        <div>
                            <label
                                class="block text-sm font-medium text-gray-700 mb-1"
                            >
                                Địa chỉ giao hàng
                            </label>
                            <textarea
                                v-model="form.receiver_address"
                                placeholder="Để trống sẽ dùng địa chỉ trong đơn hàng"
                                rows="2"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            ></textarea>
                        </div>
                    </div>
                </div>

                <!-- Pickup -->
                <div
                    class="border rounded-lg p-4 cursor-pointer hover:bg-gray-50"
                    :class="
                        selectedMethod === 'pickup'
                            ? 'border-blue-500 bg-blue-50'
                            : 'border-gray-200'
                    "
                    @click="selectMethod('pickup')"
                >
                    <div class="flex items-center">
                        <input
                            type="radio"
                            v-model="selectedMethod"
                            value="pickup"
                            class="text-blue-600"
                        />
                        <div class="ml-3">
                            <label class="font-medium cursor-pointer"
                                >Nhận tại cửa hàng</label
                            >
                            <p class="text-sm text-gray-500">
                                Khách hàng đến cửa hàng lấy hàng
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Note -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Ghi chú
                    </label>
                    <textarea
                        v-model="form.note"
                        placeholder="Ghi chú thêm về vận chuyển..."
                        rows="2"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    ></textarea>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex justify-end space-x-3 mt-6">
                <button
                    @click="$emit('close')"
                    class="px-4 py-2 text-gray-600 hover:text-gray-800"
                >
                    Hủy
                </button>
                <button
                    @click="submit"
                    :disabled="
                        !selectedMethod ||
                        (selectedMethod === 'third_party' &&
                            !form.provider_name)
                    "
                    class="px-6 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    Xác nhận
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive } from "vue";

const emit = defineEmits(["close", "submit"]);

const selectedMethod = ref("");
const form = reactive({
    provider_name: "",
    shipping_fee: 0,
    receiver_name: "",
    receiver_phone: "",
    receiver_address: "",
    note: "",
});

const selectMethod = (method) => {
    selectedMethod.value = method;

    // Reset form when changing method
    if (method !== "third_party") {
        form.provider_name = "";
        form.shipping_fee = 0;
    }
    if (method !== "self_delivery") {
        form.receiver_name = "";
        form.receiver_phone = "";
        form.receiver_address = "";
    }
};

const submit = () => {
    const data = {
        shipping_method: selectedMethod.value,
        note: form.note,
    };

    if (selectedMethod.value === "third_party") {
        data.provider_name = form.provider_name;
        data.shipping_fee = form.shipping_fee || 0;
    }

    if (selectedMethod.value === "self_delivery") {
        data.receiver_name = form.receiver_name;
        data.receiver_phone = form.receiver_phone;
        data.receiver_address = form.receiver_address;
    }

    emit("submit", data);
};
</script>

<style scoped>
/* Modal animation */
.fixed {
    animation: fadeIn 0.2s ease-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

.bg-white {
    animation: slideUp 0.2s ease-out;
}

@keyframes slideUp {
    from {
        transform: translateY(20px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}
</style>
