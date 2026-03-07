import { createApp } from 'vue'
import { createPinia } from 'pinia'
import PurchaseOrderDetail from './components/PurchaseOrderDetail.vue'

// Chỉ mount Vue nếu element tồn tại
const purchaseOrderDetailAppElement = document.getElementById('purchase-order-detail-app')

if (purchaseOrderDetailAppElement) {
    const app = createApp(PurchaseOrderDetail)
    app.use(createPinia())
    app.mount('#purchase-order-detail-app')
}