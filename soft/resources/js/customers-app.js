import { createApp } from 'vue'
import { createPinia } from 'pinia'
import CustomerList from './views/CustomerList.vue'

// Chỉ mount Vue nếu element tồn tại
const customersAppElement = document.getElementById('customers-app')

if (customersAppElement) {
    const app = createApp(CustomerList)
    app.use(createPinia())
    app.mount('#customers-app')
}