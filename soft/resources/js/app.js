import { createApp } from "vue";
import { createPinia } from "pinia";
// import router from './router'  // Comment tạm thời
import App from "./App.vue";

// Only mount if the target element exists (prevents guest pages from hanging)
const mountEl = document.getElementById("app");
if (mountEl) {
    const app = createApp(App);
    app.use(createPinia());
    // app.use(router)
    app.mount("#app");
}
