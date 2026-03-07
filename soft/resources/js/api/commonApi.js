import axios from "axios";
import { attachAuthInterceptors } from "@/api/authInterceptor";

// Base API client
const apiClient = axios.create({
    baseURL: "/api",
    timeout: 10000,
    headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
    },
});

// Request interceptor
apiClient.interceptors.request.use(
    (config) => {
        // CSRF token từ meta
        const csrfToken = document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute("content");
        if (csrfToken) {
            config.headers["X-CSRF-TOKEN"] = csrfToken;
        }

        // Bearer token từ sessionStorage hoặc meta
        let accessToken = sessionStorage.getItem("api_token");
        if (!accessToken) {
            accessToken = document
                .querySelector('meta[name="api-token"]')
                ?.getAttribute("content");
        }

        if (accessToken) {
            config.headers["Authorization"] = `Bearer ${accessToken}`;
        }

        return config;
    },
    (error) => Promise.reject(error)
);

attachAuthInterceptors(apiClient);
apiClient.interceptors.response.use((r) => r.data);

// Common API methods
export const commonApi = {
    /**
     * Get suppliers for select options
     */
    getSuppliers: (params = {}) => {
        return apiClient.get("/suppliers/options/list", { params });
    },

    /**
     * Get warehouses for current user
     */
    getWarehouses: () => {
        return apiClient.get("/my-warehouses");
    },

    /**
     * Get products for select options
     */
    getProducts: (params = {}) => {
        const defaultParams = {
            limit: 1000,
            status: "active",
            ...params,
        };
        return apiClient.get("/products", { params: defaultParams });
    },

    /**
     * Get customer groups
     */
    getCustomerGroups: () => {
        return apiClient.get("/customer-groups/options/list");
    },

    /**
     * Get supplier groups
     */
    getSupplierGroups: () => {
        return apiClient.get("/supplier-groups");
    },

    /**
     * Get brands
     */
    getBrands: () => {
        return apiClient.get("/brands");
    },

    /**
     * Get categories
     */
    getCategories: () => {
        return apiClient.get("/categories");
    },

    /**
     * Get units
     */
    getUnits: () => {
        return apiClient.get("/units");
    },

    /**
     * Get roles
     */
    getRoles: () => {
        return apiClient.get("/roles");
    },

    /**
     * Search products
     */
    searchProducts: (query, params = {}) => {
        const searchParams = {
            search: query,
            limit: 50,
            ...params,
        };
        return apiClient.get("/products", { params: searchParams });
    },

    /**
     * Search customers
     */
    searchCustomers: (query, params = {}) => {
        const searchParams = {
            search: query,
            limit: 50,
            ...params,
        };
        return apiClient.get("/customers", { params: searchParams });
    },

    /**
     * Search suppliers
     */
    searchSuppliers: (query, params = {}) => {
        const searchParams = {
            search: query,
            limit: 50,
            ...params,
        };
        return apiClient.get("/suppliers", { params: searchParams });
    },
};

export default commonApi;
