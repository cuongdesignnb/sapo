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

// Download client for file downloads
const downloadClient = axios.create({
    baseURL: "/api",
    timeout: 30000,
    headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
    },
});

// Request interceptor for apiClient
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
    (error) => Promise.reject(error)
);

// Request interceptor for downloadClient
downloadClient.interceptors.request.use(
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
    (error) => Promise.reject(error)
);

// Response interceptor for apiClient
apiClient.interceptors.response.use(
    (response) => response.data,
    (error) => {
        console.error("API Error:", error.response?.data || error.message);
        return Promise.reject(error.response?.data || error);
    }
);

// Response interceptor for downloadClient
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

export const purchaseReturnReceiptApi = {
    // ===== CRUD OPERATIONS =====

    /**
     * Get purchase return receipts list
     */
    getPurchaseReturnReceipts: (params = {}) => {
        return apiClient.get("/purchase-return-receipts", { params });
    },

    /**
     * Get all purchase return receipts (alias)
     */
    getAll: (params = {}) => {
        return apiClient.get("/purchase-return-receipts", { params });
    },

    /**
     * Get purchase return receipt by ID
     */
    getPurchaseReturnReceipt: (id) => {
        return apiClient.get(`/purchase-return-receipts/${id}`);
    },

    /**
     * Get purchase return receipt by ID (alias)
     */
    show: (id) => {
        return apiClient.get(`/purchase-return-receipts/${id}`);
    },

    /**
     * Create new purchase return receipt
     */
    createPurchaseReturnReceipt: (data) => {
        return apiClient.post("/purchase-return-receipts", data);
    },

    /**
     * Update purchase return receipt
     */
    updatePurchaseReturnReceipt: (id, data) => {
        return apiClient.put(`/purchase-return-receipts/${id}`, data);
    },

    /**
     * Delete purchase return receipt
     */
    deletePurchaseReturnReceipt: (id) => {
        return apiClient.delete(`/purchase-return-receipts/${id}`);
    },

    /**
     * Bulk delete purchase return receipts
     */
    bulkDeletePurchaseReturnReceipts: (ids) => {
        return apiClient.post("/purchase-return-receipts/bulk-delete", { ids });
    },

    // ===== STATUS OPERATIONS =====

    /**
     * Update purchase return receipt status
     */
    updatePurchaseReturnReceiptStatus: (id, status, reason = "") => {
        return apiClient.put(`/purchase-return-receipts/${id}/status`, {
            status,
            reason,
        });
    },

    /**
     * Approve return receipt
     */
    approve: (id, data = {}) => {
        return apiClient.patch(`/purchase-return-receipts/${id}/approve`, data);
    },

    /**
     * Cancel purchase return receipt
     */
    cancel: (id, data = {}) => {
        return apiClient.patch(`/purchase-return-receipts/${id}/cancel`, data);
    },

    /**
     * Submit for approval
     */
    submitForApproval: (id, data = {}) => {
        return apiClient.patch(
            `/purchase-return-receipts/${id}/submit-for-approval`,
            data
        );
    },

    // ===== RELATED DATA =====

    /**
     * Get approved return orders for receipt creation
     */
    getApprovedReturnOrders: (params = {}) => {
        const filterParams = {
            status: "approved",
            ...params,
        };
        return apiClient.get("/purchase-return-orders", {
            params: filterParams,
        });
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

    // ===== SEARCH & FILTER =====

    /**
     * Search purchase return receipts
     */
    searchPurchaseReturnReceipts: (query, params = {}) => {
        const searchParams = {
            search: query,
            ...params,
        };
        return apiClient.get("/purchase-return-receipts", {
            params: searchParams,
        });
    },

    /**
     * Filter by warehouse
     */
    filterByWarehouse: (warehouseId, params = {}) => {
        const filterParams = {
            warehouse_id: warehouseId,
            ...params,
        };
        return apiClient.get("/purchase-return-receipts", {
            params: filterParams,
        });
    },

    /**
     * Filter by supplier
     */
    filterBySupplier: (supplierId, params = {}) => {
        const filterParams = {
            supplier_id: supplierId,
            ...params,
        };
        return apiClient.get("/purchase-return-receipts", {
            params: filterParams,
        });
    },

    /**
     * Filter by status
     */
    filterByStatus: (status, params = {}) => {
        const filterParams = {
            status: status,
            ...params,
        };
        return apiClient.get("/purchase-return-receipts", {
            params: filterParams,
        });
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
        return apiClient.get("/purchase-return-receipts", {
            params: filterParams,
        });
    },

    /**
     * Get purchase return receipts with pagination
     */
    getPurchaseReturnReceiptsPage: (page = 1, perPage = 20, params = {}) => {
        const pageParams = {
            page: page,
            per_page: perPage,
            ...params,
        };
        return apiClient.get("/purchase-return-receipts", {
            params: pageParams,
        });
    },

    // ===== EXPORT =====

    /**
     * Export purchase return receipts
     */
    exportPurchaseReturnReceipts: async (params = {}) => {
        try {
            const response = await downloadClient.get(
                "/purchase-return-receipts/export",
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

    // ===== STATISTICS =====

    /**
     * Get purchase return receipts statistics
     */
    getStatistics: (params = {}) => {
        return apiClient.get("/purchase-return-receipts/statistics", {
            params,
        });
    },

    // ===== HELPER FUNCTIONS =====

    /**
     * Format currency for display
     */
    formatCurrency: (amount) => {
        return new Intl.NumberFormat("vi-VN", {
            style: "currency",
            currency: "VND",
        }).format(amount || 0);
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
            returned: "Đã trả hàng",
            completed: "Hoàn tất",
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
            returned: "bg-primary",
            completed: "bg-success",
            cancelled: "bg-danger",
        };
        return classes[status] || "bg-secondary";
    },

    /**
     * Check if receipt can be edited
     */
    canEdit: (receipt) => {
        return ["draft", "pending"].includes(receipt.status);
    },

    /**
     * Check if receipt can be approved
     */
    canApprove: (receipt) => {
        return receipt.status === "pending";
    },

    /**
     * Check if receipt can be cancelled
     */
    canCancel: (receipt) => {
        return ["pending", "approved"].includes(receipt.status);
    },

    /**
     * Check if receipt can be deleted
     */
    canDelete: (receipt) => {
        return ["draft", "cancelled"].includes(receipt.status);
    },

    /**
     * Get receipt status badge class for UI
     */
    getStatusBadgeClass: (status) => {
        const baseClasses = "px-2 py-1 text-xs font-medium rounded-full";
        const statusClasses = {
            draft: "bg-gray-100 text-gray-800",
            pending: "bg-yellow-100 text-yellow-800",
            approved: "bg-blue-100 text-blue-800",
            returned: "bg-purple-100 text-purple-800",
            completed: "bg-green-100 text-green-800",
            cancelled: "bg-red-100 text-red-800",
        };
        return `${baseClasses} ${
            statusClasses[status] || "bg-gray-100 text-gray-800"
        }`;
    },
};

export default purchaseReturnReceiptApi;
