import { createApp } from 'vue'
import { createPinia } from 'pinia'
import WarehouseList from './views/WarehouseList.vue'

const warehouseAppElement = document.getElementById('warehouse-app')

if (warehouseAppElement) {
    const app = createApp(WarehouseList)
    app.use(createPinia())
    app.mount('#warehouse-app')
}