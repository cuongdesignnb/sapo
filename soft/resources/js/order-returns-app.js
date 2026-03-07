// resources/js/order-returns-app.js
import { createApp } from "vue";
import { createPinia } from "pinia";
import OrderReturnsList from "./views/OrderReturnsList.vue";

console.log("🚀 order-returns-app.js loaded");

// Chỉ mount Vue nếu element tồn tại
const orderReturnsAppElement = document.getElementById("order-returns-app");

console.log(
    "🎯 Looking for #order-returns-app element:",
    orderReturnsAppElement
);

if (orderReturnsAppElement) {
    console.log("✅ Found #order-returns-app, mounting Vue app...");
    try {
        const app = createApp(OrderReturnsList);
        app.use(createPinia());
        app.mount("#order-returns-app");
        console.log("🎉 Vue app mounted successfully!");
    } catch (error) {
        console.error("❌ Error mounting Vue app:", error);
    }
} else {
    console.error("❌ #order-returns-app element not found!");
}
