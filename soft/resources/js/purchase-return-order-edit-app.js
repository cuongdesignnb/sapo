import { createApp } from 'vue'
import { createPinia } from 'pinia'
import PurchaseReturnOrderEdit from './components/PurchaseReturnOrderEdit.vue'

const element = document.getElementById('purchase-return-order-edit-app')
if (element) {
    const app = createApp(PurchaseReturnOrderEdit)
    app.use(createPinia())
    app.mount('#purchase-return-order-edit-app')
}