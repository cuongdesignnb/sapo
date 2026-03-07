import { createApp } from 'vue'
import { createPinia } from 'pinia'
import PurchaseReturnOrderDetail from './components/PurchaseReturnOrderDetail.vue'

const element = document.getElementById('purchase-return-order-detail-app')
if (element) {
    const app = createApp(PurchaseReturnOrderDetail)
    app.use(createPinia())
    app.mount('#purchase-return-order-detail-app')
}