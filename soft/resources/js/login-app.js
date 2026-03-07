import { createApp } from 'vue'
import { createPinia } from 'pinia'
import Login from './views/Login.vue'

const loginAppElement = document.getElementById('login-app')

if (loginAppElement) {
    const app = createApp(Login)
    app.use(createPinia())
    app.mount('#login-app')
}
