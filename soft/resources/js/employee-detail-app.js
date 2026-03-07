import { createApp } from "vue";
import { createPinia } from "pinia";
import EmployeeDetail from "./views/EmployeeDetail.vue";

const el = document.getElementById("employee-detail-app");
if (el) {
    const employeeId = el.dataset.employeeId;
    const app = createApp(EmployeeDetail, {
        employeeId: employeeId ? Number(employeeId) : null,
    });
    app.use(createPinia());
    app.mount("#employee-detail-app");
}
