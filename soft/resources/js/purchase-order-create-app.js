import { createApp } from 'vue'
import { createPinia } from 'pinia'
import PurchaseOrderCreate from './components/PurchaseOrderCreate.vue'

// Chỉ mount Vue nếu element tồn tại
const purchaseOrderCreateAppElement = document.getElementById('purchase-order-create-app')

if (purchaseOrderCreateAppElement) {
    const app = createApp(PurchaseOrderCreate)
    app.use(createPinia())
    app.mount('#purchase-order-create-app')
}