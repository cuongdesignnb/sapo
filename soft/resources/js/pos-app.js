import { createApp } from 'vue'
import { createPinia } from 'pinia'
import POSSystem from './views/POSSystem.vue'

// Chỉ mount Vue nếu element tồn tại
const posAppElement = document.getElementById('pos-app')

if (posAppElement) {
    const app = createApp(POSSystem)
    app.use(createPinia())
    app.mount('#pos-app')
}