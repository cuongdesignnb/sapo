// resources/js/order-detail-app.js
import { createApp } from "vue";
import { createPinia } from "pinia";
import OrderDetail from "./components/OrderDetail.vue";

const el = document.getElementById("order-detail-app");
if (el) {
    const orderId = el.dataset.orderId;

    const baseOrder = (id) => ({
        id: id ? Number(id) : undefined,
        code: "",
        status: undefined, // let component render skeleton until loaded
        items: [],
        customer: {},
        paid: 0,
        total: 0,
        discount_amount: 0,
        debt: 0,
        shipping_fee: 0,
        warehouse: null,
        cashier: null,
    });

    const app = createApp(OrderDetail, { order: baseOrder(orderId) });
    app.use(createPinia());
    app.mount("#order-detail-app");
}
