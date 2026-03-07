// resources/js/orders-app.js
import { createApp } from "vue";
import { createPinia } from "pinia";
import OrderList from "./components/OrderList.vue";

// Chỉ mount Vue nếu element tồn tại
const orderAppElement = document.getElementById("orders-app");

if (orderAppElement) {
    const app = createApp(OrderList);
    app.use(createPinia());
    app.mount("#orders-app");
}
