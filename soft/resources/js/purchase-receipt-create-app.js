import { createApp } from 'vue'
import { createPinia } from 'pinia'
import PurchaseReceiptCreate from './components/PurchaseReceiptCreate.vue'

// Chỉ mount Vue nếu element tồn tại
const purchaseReceiptCreateAppElement = document.getElementById('purchase-receipt-create-app')

if (purchaseReceiptCreateAppElement) {
    const app = createApp(PurchaseReceiptCreate)
    app.use(createPinia())
    app.mount('#purchase-receipt-create-app')
}