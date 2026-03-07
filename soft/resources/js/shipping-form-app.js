import { createApp } from "vue";
import { createPinia } from "pinia";
import ShippingForm from "./views/ShippingForm.vue";

const shippingFormAppElement = document.getElementById("shipping-form-app");

if (shippingFormAppElement) {
    const app = createApp(ShippingForm);
    app.use(createPinia());
    app.mount("#shipping-form-app");
}
