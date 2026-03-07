// resources/js/api/dashboardApi.js - Clean version
import axios from "axios";

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
        const csrfToken = document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute("content");
        if (csrfToken) {
            config.headers["X-CSRF-TOKEN"] = csrfToken;
        }

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
    (error) => Promise.reject(error),
);

// Response interceptor
apiClient.interceptors.response.use(
    (response) => response.data,
    (error) => {
        if (error.response?.status === 401) {
            sessionStorage.removeItem("api_token");
            window.location.href = "/force-logout?reason=unauthorized";
            return Promise.reject(new Error("Session expired"));
        }

        return Promise.reject(error.response?.data || error);
    },
);

// Dashboard API methods
const dashboardApi = {
    getOverview: (params = {}) => {
        return apiClient.get("/dashboard/overview", { params });
    },

    getSalesTrend: (params = {}) => {
        return apiClient.get("/dashboard/sales-trend", { params });
    },

    getTopProducts: (params = {}) => {
        return apiClient.get("/dashboard/top-products", { params });
    },

    getRevenueProfit: (params = {}) => {
        return apiClient.get("/dashboard/revenue-profit", { params });
    },

    getLowStockAlerts: (params = {}) => {
        return apiClient.get("/dashboard/low-stock-alerts", { params });
    },

    getCustomerAnalysis: (params = {}) => {
        return apiClient.get("/dashboard/customer-analysis", { params });
    },

    getWarehouses: () => {
        return apiClient.get("/warehouses");
    },

    resetAllData: (confirm) => {
        return apiClient.post("/system/reset-all-data", { confirm });
    },
};

// Helper functions
const dashboardHelpers = {
    formatCurrency(amount) {
        if (!amount || amount === 0) return "0₫";
        return new Intl.NumberFormat("vi-VN", {
            style: "currency",
            currency: "VND",
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        }).format(amount);
    },

    formatCurrencyShort(amount) {
        if (!amount || amount === 0) return "0";

        if (amount >= 1000000000) {
            return (amount / 1000000000).toFixed(1) + "B";
        } else if (amount >= 1000000) {
            return (amount / 1000000).toFixed(1) + "M";
        } else if (amount >= 1000) {
            return (amount / 1000).toFixed(1) + "K";
        }

        return this.formatCurrency(amount);
    },

    formatNumber(number) {
        if (!number) return "0";
        return new Intl.NumberFormat("vi-VN").format(number);
    },

    formatPercent(value, decimals = 1) {
        if (!value) return "0%";
        return value.toFixed(decimals) + "%";
    },

    formatDate(date) {
        if (!date) return "";
        return new Date(date).toLocaleDateString("vi-VN", {
            year: "numeric",
            month: "2-digit",
            day: "2-digit",
        });
    },

    formatDateTime(date) {
        if (!date) return "";
        return new Date(date).toLocaleString("vi-VN", {
            year: "numeric",
            month: "2-digit",
            day: "2-digit",
            hour: "2-digit",
            minute: "2-digit",
        });
    },

    getPeriodText(days) {
        switch (parseInt(days)) {
            case 7:
                return "7 ngày qua";
            case 30:
                return "30 ngày qua";
            case 90:
                return "90 ngày qua";
            default:
                return `${days} ngày qua`;
        }
    },

    calculateGrowthRate(current, previous) {
        if (!previous || previous === 0) return current > 0 ? 100 : 0;
        return ((current - previous) / previous) * 100;
    },
};

export { dashboardApi, dashboardHelpers };
