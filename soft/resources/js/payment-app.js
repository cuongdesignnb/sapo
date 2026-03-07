import { createApp } from "vue";
import { createPinia } from "pinia";
import PaymentForm from "./views/PaymentForm.vue";

const paymentAppElement = document.getElementById("payment-app");

if (paymentAppElement) {
    const app = createApp(PaymentForm);
    app.use(createPinia());
    app.mount("#payment-app");
}
