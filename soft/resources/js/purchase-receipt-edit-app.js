import { createApp } from "vue";
import PurchaseReceiptCreate from "./components/PurchaseReceiptCreate.vue";

// Reuse create component for editing (it will detect existing ID and load data)
createApp(PurchaseReceiptCreate).mount("#purchase-receipt-edit-app");
