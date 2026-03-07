// resources/js/shipping-app.js
import { createApp } from 'vue'
import { createPinia } from 'pinia'
import ShippingList from './views/ShippingList.vue'

// Chỉ mount Vue nếu element tồn tại
const shippingAppElement = document.getElementById('shipping-app')

if (shippingAppElement) {
    const app = createApp(ShippingList)
    app.use(createPinia())
    app.mount('#shipping-app')
}