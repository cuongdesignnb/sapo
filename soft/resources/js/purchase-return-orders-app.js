import { createApp } from 'vue'
import { createPinia } from 'pinia'
import PurchaseReturnOrdersList from './components/PurchaseReturnOrdersList.vue'

// Chỉ mount Vue nếu element tồn tại
const purchaseReturnOrdersAppElement = document.getElementById('purchase-return-orders-app')

if (purchaseReturnOrdersAppElement) {
    const app = createApp(PurchaseReturnOrdersList)
    app.use(createPinia())
    app.mount('#purchase-return-orders-app')
}