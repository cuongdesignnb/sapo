// resources/js/dashboard-app.js
import { createApp } from 'vue'
import Dashboard from './components/Dashboard.vue'

// Chỉ mount Vue nếu element tồn tại
const dashboardAppElement = document.getElementById('dashboard-app')

if (dashboardAppElement) {
    const app = createApp(Dashboard)
    app.mount('#dashboard-app')
    
    console.log('🚀 Dashboard Vue app mounted successfully!')
}