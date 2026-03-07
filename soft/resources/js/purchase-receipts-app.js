import { createApp } from 'vue'
import { createPinia } from 'pinia'
import PurchaseReceiptsList from './components/PurchaseReceiptsList.vue'

// Chỉ mount Vue nếu element tồn tại
const purchaseReceiptsAppElement = document.getElementById('purchase-receipts-app')

if (purchaseReceiptsAppElement) {
    const app = createApp(PurchaseReceiptsList)
    app.use(createPinia())
    app.mount('#purchase-receipts-app')
}