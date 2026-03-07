import { createApp } from "vue";
import CashLedgerList from "./views/CashLedgerList.vue";

// Chỉ mount Vue nếu element tồn tại
const cashLedgerAppElement = document.getElementById("cash-ledger-app");

if (cashLedgerAppElement) {
    const app = createApp(CashLedgerList);
    app.mount("#cash-ledger-app");
}
