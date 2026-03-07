import { createApp } from 'vue'
import { createPinia } from 'pinia'
import PurchaseReceiptDetail from './components/PurchaseReceiptDetail.vue'

// Chỉ mount Vue nếu element tồn tại
const purchaseReceiptDetailAppElement = document.getElementById('purchase-receipt-detail-app')

if (purchaseReceiptDetailAppElement) {
    const app = createApp(PurchaseReceiptDetail)
    app.use(createPinia())
    app.mount('#purchase-receipt-detail-app')
}