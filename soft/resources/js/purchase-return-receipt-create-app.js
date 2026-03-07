import { createApp } from 'vue'
import { createPinia } from 'pinia'
import PurchaseReturnReceiptCreate from './components/PurchaseReturnReceiptCreate.vue'

const element = document.getElementById('purchase-return-receipt-create-app')
if (element) {
    const app = createApp(PurchaseReturnReceiptCreate)
    app.use(createPinia())
    app.mount('#purchase-return-receipt-create-app')
}