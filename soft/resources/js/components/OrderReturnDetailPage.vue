// resources/js/components/OrderReturnDetailPage.vue
<template>
    <div>
        <div v-if="loading" class="flex justify-center items-center py-12">
            <div
                class="animate-spin rounded-full h-8 w-8 border-b-2 border-orange-500"
            ></div>
            <span class="ml-2 text-gray-600">Đang tải dữ liệu...</span>
        </div>

        <div v-else-if="error" class="text-center py-12">
            <div class="text-red-600 mb-4">{{ error }}</div>
            <button
                @click="loadData"
                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
            >
                Thử lại
            </button>
        </div>

        <OrderReturnDetail
            v-else-if="orderReturn"
            :order-return="orderReturn"
            @close="handleClose"
            @updated="loadData"
        />
    </div>
</template>

<script>
import { ref, onMounted } from "vue";
import { orderReturnApi } from "../api/orderReturnApi";
import OrderReturnDetail from "./OrderReturnDetail.vue";

export default {
    name: "OrderReturnDetailPage",
    components: {
        OrderReturnDetail,
    },
    props: {
        returnId: {
            type: [String, Number],
            required: true,
        },
    },
    setup(props) {
        const loading = ref(false);
        const error = ref(null);
        const orderReturn = ref(null);

        const loadData = async () => {
            loading.value = true;
            error.value = null;

            try {
                const response = await orderReturnApi.getDetail(props.returnId);
                if (response.success) {
                    orderReturn.value = response.data;
                } else {
                    error.value =
                        response.message || "Có lỗi xảy ra khi tải dữ liệu";
                }
            } catch (err) {
                error.value = "Có lỗi xảy ra khi tải dữ liệu";
                console.error("Error loading order return:", err);
            } finally {
                loading.value = false;
            }
        };

        const handleClose = () => {
            // Redirect back to list page
            window.location.href = "/order-returns";
        };

        onMounted(() => {
            loadData();
        });

        return {
            loading,
            error,
            orderReturn,
            loadData,
            handleClose,
        };
    },
};
</script>
