import { createApp } from "vue";
import CashReceiptList from "./views/CashReceiptList.vue";

// Chỉ mount Vue nếu element tồn tại
const cashReceiptsAppElement = document.getElementById("cash-receipts-app");

if (cashReceiptsAppElement) {
    const app = createApp(CashReceiptList);
    app.mount("#cash-receipts-app");
}
