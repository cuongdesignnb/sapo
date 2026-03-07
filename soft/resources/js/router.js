import { createRouter, createWebHistory } from "vue-router";

// Import views
import ProductList from "./views/ProductList.vue";
import OrderList from "./components/OrderList.vue";

const routes = [
    {
        path: "/",
        redirect: "/orders",
    },
    {
        path: "/products",
        name: "products",
        component: ProductList,
    },
    {
        path: "/orders",
        name: "orders",
        component: OrderList,
    },
];

const router = createRouter({
    history: createWebHistory("/app"),
    routes,
});

export default router;
