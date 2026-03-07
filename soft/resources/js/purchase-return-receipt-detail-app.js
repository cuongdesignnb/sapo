import { createApp } from 'vue'
import { createPinia } from 'pinia'
import PurchaseReturnReceiptDetail from './components/PurchaseReturnReceiptDetail.vue'

const element = document.getElementById('purchase-return-receipt-detail-app')
if (element) {
    const app = createApp(PurchaseReturnReceiptDetail)
    app.use(createPinia())
    app.mount('#purchase-return-receipt-detail-app')
}