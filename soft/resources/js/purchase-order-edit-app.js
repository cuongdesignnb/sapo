import { createApp } from 'vue'
import { createPinia } from 'pinia'
import PurchaseOrderEdit from './views/PurchaseOrderEdit.vue'

// Chỉ mount Vue nếu element tồn tại
const purchaseOrderEditAppElement = document.getElementById('purchase-order-edit-app')

if (purchaseOrderEditAppElement) {
    const app = createApp(PurchaseOrderEdit)
    app.use(createPinia())
    app.mount('#purchase-order-edit-app')
}