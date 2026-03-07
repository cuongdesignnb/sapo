import { createApp } from "vue";
import { createPinia } from "pinia";
import WarehouseSelector from "./components/WarehouseSelector.vue";

const warehouseSelectorElement = document.getElementById(
    "warehouse-selector-mount"
);

if (warehouseSelectorElement) {
    const userRole = warehouseSelectorElement.dataset.userRole || "";

    const app = createApp(WarehouseSelector, {
        userRole: userRole,
    });
    app.use(createPinia());
    app.mount("#warehouse-selector-mount");
}
