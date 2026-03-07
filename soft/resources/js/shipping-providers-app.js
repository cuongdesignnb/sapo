// resources/js/shipping-providers-app.js
import { createApp } from 'vue'
import { createPinia } from 'pinia'
import ShippingProviderList from './views/ShippingProviderList.vue'

// Chỉ mount Vue nếu element tồn tại
const shippingProvidersAppElement = document.getElementById('shipping-providers-app')

if (shippingProvidersAppElement) {
    const app = createApp(ShippingProviderList)
    app.use(createPinia())
    app.mount('#shipping-providers-app')
}