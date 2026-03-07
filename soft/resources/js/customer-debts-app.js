import { createApp } from 'vue'
import { createPinia } from 'pinia'
import CustomerDebtList from './views/CustomerDebtList.vue'

// Chỉ mount Vue nếu element tồn tại
const customerDebtsAppElement = document.getElementById('customer-debts-app')

if (customerDebtsAppElement) {
    const app = createApp(CustomerDebtList)
    app.use(createPinia())
    app.mount('#customer-debts-app')
}