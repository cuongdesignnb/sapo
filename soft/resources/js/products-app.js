import { createApp } from 'vue'
import { createPinia } from 'pinia'
import ProductList from './views/ProductList.vue'

// Chỉ mount Vue nếu element tồn tại
const productAppElement = document.getElementById('product-app')

if (productAppElement) {
    const app = createApp(ProductList)
    app.use(createPinia())
    app.mount('#product-app')
}