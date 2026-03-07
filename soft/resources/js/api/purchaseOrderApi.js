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

// Response interceptor
apiClient.interceptors.response.use(
    (response) => response.data,
    (error) => {
        console.error("API Error:", error.response?.data || error.message);
        return Promise.reject(error.response?.data || error);
    }
);

// Download client for file downloads
const downloadClient = axios.create({
    baseURL: "/api",
    timeout: 30000,
    headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
    },
});

// Download client interceptor
downloadClient.interceptors.request.use(
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

// Download response interceptor
downloadClient.interceptors.response.use(
    (response) => response,
    (error) => {
        console.error(
            "Download API Error:",
            error.response?.data || error.message
        );
        return Promise.reject(error.response?.data || error);
    }
);

// Purchase Order API methods
export const purchaseOrderApi = {
    /**
     * Get purchase orders list with search, filter, pagination
     */
    getPurchaseOrders: (params = {}) => {
        // type: planned | actual | all
        return apiClient.get("/purchase-orders", { params });
    },

    /**
     * Get purchase order by ID
     */
    getPurchaseOrder: (id) => {
        return apiClient.get(`/purchase-orders/${id}`);
    },

    /**
     * Create new purchase order
     */
    createPurchaseOrder: (data) => {
        return apiClient.post("/purchase-orders", data);
    },

    /**
     * Update purchase order
     */
    updatePurchaseOrder: (id, data) => {
        return apiClient.put(`/purchase-orders/${id}`, data);
    },

    /**
     * Update purchase order status
     */
    updatePurchaseOrderStatus: (id, status, note = "") => {
        return apiClient.put(`/purchase-orders/${id}/status`, { status, note });
    },

    /**
     * Convert planned (order-only) purchase order to actual
     */
    convertToActual: (id) => {
        return apiClient.post(`/purchase-orders/${id}/convert-to-actual`);
    },

    /**
     * Get suppliers list
     */
    getSuppliers: (params = {}) => {
        return apiClient.get("/suppliers", { params });
    },

    /**
     * Get warehouses list
     */
    getWarehouses: (params = {}) => {
        return apiClient.get("/warehouses", { params });
    },

    /**
     * Get products list
     */
    getProducts: (params = {}) => {
        return apiClient.get("/products", { params });
    },

    /**
     * Get units list
     */
    getUnits: (params = {}) => {
        return apiClient.get("/units", { params });
    },

    /**
     * Submit purchase order for approval
     */
    submitForApproval: (id) => {
        return apiClient.put(`/purchase-orders/${id}/submit-for-approval`);
    },
    /**
     * Record payment for purchase order
     */
    recordPayment: (id, data) => {
        return apiClient.post(`/purchase-orders/${id}/payments`, data);
    },

    /**
     * Get payment history
     */
    getPaymentHistory: (id) => {
        return apiClient.get(`/purchase-orders/${id}/payments`);
    },

    /**
     * Get payment overview
     */
    getPaymentOverview: (params = {}) => {
        return apiClient.get("/purchase-orders/payments/overview", { params });
    },

    /**
     * Bulk payment
     */
    bulkPayment: (data) => {
        return apiClient.post("/purchase-orders/payments/bulk", data);
    },

    /**
     * Get statistics
     */
    getStatistics: (params = {}) => {
        return apiClient.get("/purchase-orders/statistics", { params });
    },
    /**
     * Delete purchase order
     */
    deletePurchaseOrder: (id) => {
        return apiClient.delete(`/purchase-orders/${id}`);
    },

    /**
     * Bulk delete purchase orders
     */
    bulkDeletePurchaseOrders: (ids) => {
        return apiClient.post("/purchase-orders/bulk-delete", { ids });
    },

    /**
     * Export purchase orders
     */
    exportPurchaseOrders: async (params = {}) => {
        try {
            const response = await downloadClient.get(
                "/purchase-orders/export",
                {
                    params,
                    responseType: "blob",
                }
            );
            return response;
        } catch (error) {
            console.error("Export error:", error);
            throw error;
        }
    },

    /**
     * Get available purchase orders for receipt creation
     */
    getAvailableOrders: (params = {}) => {
        const filterParams = {
            type: "actual", // only actual orders can be received
            status: "approved,ordered,partial_received",
            ...params,
        };
        return apiClient.get("/purchase-orders", { params: filterParams });
    },

    /**
     * Search purchase orders
     */
    searchPurchaseOrders: (query, params = {}) => {
        const searchParams = {
            search: query,
            ...params,
        };
        return apiClient.get("/purchase-orders", { params: searchParams });
    },

    /**
     * Filter by supplier
     */
    filterBySupplier: (supplierId, params = {}) => {
        const filterParams = {
            supplier_id: supplierId,
            ...params,
        };
        return apiClient.get("/purchase-orders", { params: filterParams });
    },

    /**
     * Filter by warehouse
     */
    filterByWarehouse: (warehouseId, params = {}) => {
        const filterParams = {
            warehouse_id: warehouseId,
            ...params,
        };
        return apiClient.get("/purchase-orders", { params: filterParams });
    },

    /**
     * Filter by status
     */
    filterByStatus: (status, params = {}) => {
        const filterParams = {
            status: status,
            ...params,
        };
        return apiClient.get("/purchase-orders", { params: filterParams });
    },

    /**
     * Filter by date range
     */
    filterByDateRange: (dateFrom, dateTo, params = {}) => {
        const filterParams = {
            date_from: dateFrom,
            date_to: dateTo,
            ...params,
        };
        return apiClient.get("/purchase-orders", { params: filterParams });
    },

    /**
     * Get purchase orders with pagination
     */
    getPurchaseOrdersPage: (page = 1, perPage = 20, params = {}) => {
        const pageParams = {
            page: page,
            per_page: perPage,
            ...params,
        };
        return apiClient.get("/purchase-orders", { params: pageParams });
    },

    /**
     * Get purchase order statistics
     */
    getStatistics: () => {
        return apiClient.get("/purchase-orders/stats");
    },

    /**
     * Format currency for display
     */
    formatCurrency: (amount) => {
        return new Intl.NumberFormat("vi-VN", {
            style: "currency",
            currency: "VND",
        }).format(amount);
    },

    /**
     * Format date for display
     */
    formatDate: (date) => {
        if (!date) return "";
        return new Date(date).toLocaleDateString("vi-VN");
    },

    /**
     * Format datetime for display
     */
    formatDateTime: (datetime) => {
        if (!datetime) return "";
        return new Date(datetime).toLocaleString("vi-VN");
    },

    /**
     * Get status text
     */
    getStatusText: (status) => {
        const texts = {
            draft: "Nháp",
            pending: "Chờ duyệt",
            approved: "Đã duyệt",
            ordered: "Đã đặt hàng",
            partial_received: "Nhập một phần",
            received: "Đã nhập kho",
            completed: "Hoàn thành",
            cancelled: "Đã hủy",
        };
        return texts[status] || status;
    },

    /**
     * Get status class
     */
    getStatusClass: (status) => {
        const classes = {
            draft: "bg-secondary",
            pending: "bg-warning",
            approved: "bg-info",
            ordered: "bg-primary",
            partial_received: "bg-warning",
            received: "bg-success",
            completed: "bg-success",
            cancelled: "bg-danger",
        };
        return classes[status] || "bg-secondary";
    },

    /**
     * Check if order can be edited
     */
    canEdit: (order) => {
        return ["draft", "pending"].includes(order.status);
    },

    /**
     * Check if order can be deleted
     */
    canDelete: (order) => {
        return ["draft", "cancelled"].includes(order.status);
    },

    /**
     * Check if order can create receipt
     */
    canCreateReceipt: (order) => {
        if (!order) return false;
        // Không cho tạo phiếu nhập khi vẫn còn là đơn đặt hàng kế hoạch
        if (order.is_order_only) return false;
        return ["approved", "ordered", "partial_received"].includes(
            order.status
        );
    },

    /**
     * Check if order can be approved
     */
    canApprove: (order) => {
        return order.status === "pending";
    },

    /**
     * Check if order can be rejected
     */
    canReject: (order) => {
        return order.status === "pending";
    },
};

// Add alias methods
purchaseOrderApi.getAll = (params = {}) => {
    return apiClient.get("/purchase-orders", { params });
};

purchaseOrderApi.show = (id) => {
    return apiClient.get(`/purchase-orders/${id}`);
};

export default purchaseOrderApi;
