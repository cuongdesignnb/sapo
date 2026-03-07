// resources/js/order-edit-app.js
import { createApp } from "vue";
import { createPinia } from "pinia";
import OrderEdit from "./views/OrderEdit.vue";

const el = document.getElementById("order-edit-app");
if (el) {
    const app = createApp(OrderEdit);
    app.use(createPinia());
    app.mount("#order-edit-app");
}
