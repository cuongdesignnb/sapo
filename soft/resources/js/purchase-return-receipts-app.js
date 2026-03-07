import { createApp } from 'vue'
import { createPinia } from 'pinia'
import PurchaseReturnReceiptsList from './components/PurchaseReturnReceiptsList.vue'

// Chỉ mount Vue nếu element tồn tại
const purchaseReturnReceiptsAppElement = document.getElementById('purchase-return-receipts-app')

if (purchaseReturnReceiptsAppElement) {
    const app = createApp(PurchaseReturnReceiptsList)
    app.use(createPinia())
    app.mount('#purchase-return-receipts-app')
}