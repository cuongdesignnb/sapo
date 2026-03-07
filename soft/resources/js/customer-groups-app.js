// =================================================================
// File: resources/js/customer-groups-app.js
// =================================================================

import { createApp } from 'vue'
import { createPinia } from 'pinia'
import CustomerGroupList from './views/CustomerGroupList.vue'

// Chỉ mount Vue nếu element tồn tại
const customerGroupsAppElement = document.getElementById('customer-groups-app')

if (customerGroupsAppElement) {
    const app = createApp(CustomerGroupList)
    app.use(createPinia())
    app.mount('#customer-groups-app')
}