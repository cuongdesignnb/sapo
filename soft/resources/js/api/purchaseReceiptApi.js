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

// Purchase Receipt API methods
export const purchaseReceiptApi = {
    /**
     * Get purchase receipts list with search, filter, pagination
     */
    getPurchaseReceipts: (params = {}) => {
        return apiClient.get("/purchase-receipts", { params });
    },

    /**
     * Get purchase receipt by ID
     */
    getPurchaseReceipt: (id) => {
        return apiClient.get(`/purchase-receipts/${id}`);
    },

    /**
     * Create new purchase receipt
     */
    createPurchaseReceipt: (data) => {
        return apiClient.post("/purchase-receipts", data);
    },

    /**
     * Approve purchase receipt (trigger debt/payment logic)
     */
    approvePurchaseReceipt: (id) => {
        return apiClient.patch(`/purchase-receipts/${id}/approve`);
    },

    /**
     * Update purchase receipt
     */
    updatePurchaseReceipt: (id, data) => {
        return apiClient.put(`/purchase-receipts/${id}`, data);
    },

    /**
     * Cancel purchase receipt
     */
    cancelPurchaseReceipt: (id, reason = "") => {
        return apiClient.put(`/purchase-receipts/${id}/cancel`, { reason });
    },

    /**
     * Delete purchase receipt
     */
    deletePurchaseReceipt: (id) => {
        return apiClient.delete(`/purchase-receipts/${id}`);
    },

    /**
     * Bulk delete purchase receipts
     */
    bulkDeletePurchaseReceipts: (ids) => {
        return apiClient.post("/purchase-receipts/bulk-delete", { ids });
    },

    /**
     * Export purchase receipts
     */
    exportPurchaseReceipts: async (params = {}) => {
        try {
            const response = await downloadClient.get(
                "/purchase-receipts/export",
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
     * Search purchase receipts
     */
    searchPurchaseReceipts: (query, params = {}) => {
        const searchParams = {
            search: query,
            ...params,
        };
        return apiClient.get("/purchase-receipts", { params: searchParams });
    },

    /**
     * Filter by warehouse
     */
    filterByWarehouse: (warehouseId, params = {}) => {
        const filterParams = {
            warehouse_id: warehouseId,
            ...params,
        };
        return apiClient.get("/purchase-receipts", { params: filterParams });
    },

    /**
     * Filter by status
     */
    filterByStatus: (status, params = {}) => {
        const filterParams = {
            status: status,
            ...params,
        };
        return apiClient.get("/purchase-receipts", { params: filterParams });
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
        return apiClient.get("/purchase-receipts", { params: filterParams });
    },

    /**
     * Get purchase receipts with pagination
     */
    getPurchaseReceiptsPage: (page = 1, perPage = 20, params = {}) => {
        const pageParams = {
            page: page,
            per_page: perPage,
            ...params,
        };
        return apiClient.get("/purchase-receipts", { params: pageParams });
    },

    /**
     * Get purchase receipt statistics
     */
    getStatistics: () => {
        return apiClient.get("/purchase-receipts/stats");
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
            pending: "Chờ xử lý",
            partial: "Nhập một phần",
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
            pending: "bg-warning",
            partial: "bg-info",
            completed: "bg-success",
            cancelled: "bg-danger",
        };
        return classes[status] || "bg-secondary";
    },

    /**
     * Get condition text
     */
    getConditionText: (condition) => {
        const texts = {
            good: "Tốt",
            damaged: "Hư hỏng",
            expired: "Hết hạn",
        };
        return texts[condition] || condition;
    },

    /**
     * Get condition class
     */
    getConditionClass: (condition) => {
        const classes = {
            good: "bg-success",
            damaged: "bg-warning",
            expired: "bg-danger",
        };
        return classes[condition] || "bg-secondary";
    },

    /**
     * Check if receipt can be edited
     */
    canEdit: (receipt) => {
        return ["pending", "partial"].includes(receipt.status);
    },

    /**
     * Check if receipt can be deleted
     */
    canDelete: (receipt) => {
        return ["pending", "cancelled"].includes(receipt.status);
    },

    /**
     * Check if receipt can be cancelled
     */
    canCancel: (receipt) => {
        return ["pending", "partial"].includes(receipt.status);
    },

    /**
     * Validate receipt data
     */
    validateReceiptData: (data) => {
        const errors = {};

        // Cho phép tạo phiếu nhập độc lập không cần đơn đặt hàng
        if (!data.purchase_order_id && !data.supplier_id) {
            errors.supplier_id = "Chọn nhà cung cấp hoặc chọn đơn đặt hàng";
        }

        if (!data.warehouse_id) {
            errors.warehouse_id = "Vui lòng chọn kho nhập";
        }

        if (!data.items || data.items.length === 0) {
            errors.items = "Vui lòng thêm ít nhất một sản phẩm";
        }

        if (data.items) {
            data.items.forEach((item, index) => {
                if (!item.quantity_received || item.quantity_received <= 0) {
                    errors[`items.${index}.quantity_received`] =
                        "Số lượng nhập phải lớn hơn 0";
                }

                if (!item.unit_cost || item.unit_cost <= 0) {
                    errors[`items.${index}.unit_cost`] =
                        "Đơn giá phải lớn hơn 0";
                }
            });
        }

        return {
            isValid: Object.keys(errors).length === 0,
            errors,
        };
    },
    /**
     * Get purchase receipt by ID (alias for getPurchaseReceipt)
     */
    show: (id) => {
        return apiClient.get(`/purchase-receipts/${id}`);
    },

    /**
     * Cancel purchase receipt
     */
    cancel: (id, data) => {
        return apiClient.put(`/purchase-receipts/${id}/cancel`, data);
    },
    /**
     * Alias methods for compatibility
     */
    getAll: (params = {}) => {
        return apiClient.get("/purchase-receipts", { params });
    },

    getStats: () => {
        return apiClient.get("/purchase-receipts/stats");
    },

    delete: (id) => {
        return apiClient.delete(`/purchase-receipts/${id}`);
    },

    export: async (params = {}) => {
        try {
            const response = await downloadClient.get(
                "/purchase-receipts/export",
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
};
// Warehouse API for import
export const warehouseApi = {
    getMyWarehouses: () => {
        return apiClient.get("/warehouses/my-warehouses");
    },
};
export default purchaseReceiptApi;
