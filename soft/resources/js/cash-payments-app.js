import { createApp } from "vue";
import CashPaymentList from "./views/CashPaymentList.vue";

// Chỉ mount Vue nếu element tồn tại
const cashPaymentsAppElement = document.getElementById("cash-payments-app");

if (cashPaymentsAppElement) {
    const app = createApp(CashPaymentList);
    app.mount("#cash-payments-app");
}
