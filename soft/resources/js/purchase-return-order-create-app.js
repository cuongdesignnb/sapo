import { createApp } from 'vue'
import { createPinia } from 'pinia'
import PurchaseReturnOrderCreate from './components/PurchaseReturnOrderCreate.vue'

const element = document.getElementById('purchase-return-order-create-app')
if (element) {
    const app = createApp(PurchaseReturnOrderCreate)
    app.use(createPinia())
    app.mount('#purchase-return-order-create-app')
}
