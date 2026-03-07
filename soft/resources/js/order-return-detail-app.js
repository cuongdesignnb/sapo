// resources/js/order-return-detail-app.js
import { createApp } from "vue";
import { createPinia } from "pinia";
import OrderReturnDetailPage from "./components/OrderReturnDetailPage.vue";

// Chỉ mount Vue nếu element tồn tại
const orderReturnDetailAppElement = document.getElementById(
    "order-return-detail-app"
);

if (orderReturnDetailAppElement) {
    // Lấy returnId từ data attribute
    const returnId = orderReturnDetailAppElement.dataset.returnId;

    const app = createApp(OrderReturnDetailPage, {
        returnId: returnId,
    });

    app.use(createPinia());
    app.mount("#order-return-detail-app");
}
