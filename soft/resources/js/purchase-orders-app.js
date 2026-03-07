import { createApp } from 'vue'
import { createPinia } from 'pinia'
import PurchaseOrdersList from './views/PurchaseOrdersList.vue'

// Chỉ mount Vue nếu element tồn tại
const purchaseOrdersAppElement = document.getElementById('purchase-orders-app')

if (purchaseOrdersAppElement) {
    const app = createApp(PurchaseOrdersList)
    app.use(createPinia())
    app.mount('#purchase-orders-app')
}