import axios from 'axios'

// Base API client
const apiClient = axios.create({
    baseURL: '/api',
    timeout: 10000,
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    }
})

// Request interceptor
apiClient.interceptors.request.use(
    config => {
        // CSRF token từ meta
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        if (csrfToken) {
            config.headers['X-CSRF-TOKEN'] = csrfToken
        }
        
        // Bearer token từ sessionStorage hoặc meta
        let accessToken = sessionStorage.getItem('api_token')
        if (!accessToken) {
            accessToken = document.querySelector('meta[name="api-token"]')?.getAttribute('content')
        }
        
        if (accessToken) {
            config.headers['Authorization'] = `Bearer ${accessToken}`
        }
        
        return config
    },
    error => Promise.reject(error)
)

// Response interceptor
apiClient.interceptors.response.use(
    response => response.data,
    error => {
        console.error('API Error:', error.response?.data || error.message)
        return Promise.reject(error.response?.data || error)
    }
)

// Purchase Return Order API methods
export const purchaseReturnOrderApi = {
    /**
     * Get purchase return orders list
     */
    getPurchaseReturnOrders: (params = {}) => {
        return apiClient.get("/purchase-return-orders", { params });
    },

    /**
     * Get purchase return order by ID
     */
    getPurchaseReturnOrder: (id) => {
        return apiClient.get(`/purchase-return-orders/${id}`);
    },

    /**
     * Create new purchase return order
     */
    createPurchaseReturnOrder: (data) => {
        return apiClient.post("/purchase-return-orders", data);
    },

    /**
     * Update purchase return order
     */
    updatePurchaseReturnOrder: (id, data) => {
        return apiClient.put(`/purchase-return-orders/${id}`, data);
    },

    /**
     * Update purchase return order status
     */
    updatePurchaseReturnOrderStatus: (id, status, reason = "") => {
        return apiClient.put(`/purchase-return-orders/${id}/status`, {
            status,
            reason,
        });
    },
    /**
     * Approve return order
     */
    approve: (id) => {
        return apiClient.patch(`/purchase-return-orders/${id}/approve`);
    },

    /**
     * Submit for approval
     */
    submitForApproval: (id) => {
        return apiClient.patch(
            `/purchase-return-orders/${id}/submit-for-approval`
        );
    },
    /**
     * Delete purchase return order
     */
    deletePurchaseReturnOrder: (id) => {
        return apiClient.delete(`/purchase-return-orders/${id}`);
    },

    /**
     * Get receipts by supplier - NEW METHOD
     */
    getReceiptsBySupplier: (supplierId) => {
        return apiClient.get(
            `/purchase-return-orders/receipts/by-supplier/${supplierId}`
        );
    },

    /**
     * Get returnable items from receipt - NEW METHOD
     */
    getReturnableItems: (receiptId) => {
        return apiClient.get(
            `/purchase-return-orders/receipts/${receiptId}/returnable-items`
        );
    },

    /**
     * Get receipt details - NEW METHOD
     */
    getReceiptDetails: (receiptId) => {
        return apiClient.get(`/purchase-receipts/${receiptId}`);
    },

    /**
     * Search purchase return orders
     */
    searchPurchaseReturnOrders: (query, params = {}) => {
        const searchParams = {
            search: query,
            ...params,
        };
        return apiClient.get("/purchase-return-orders", {
            params: searchParams,
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
        return apiClient.get("/purchase-return-orders", {
            params: filterParams,
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
        return apiClient.get("/purchase-return-orders", {
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
        return apiClient.get("/purchase-return-orders", {
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
        return apiClient.get("/purchase-return-orders", {
            params: filterParams,
        });
    },

    /**
     * Get purchase return orders with pagination
     */
    getPurchaseReturnOrdersPage: (page = 1, perPage = 20, params = {}) => {
        const pageParams = {
            page: page,
            per_page: perPage,
            ...params,
        };
        return apiClient.get("/purchase-return-orders", { params: pageParams });
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
     * Get products in warehouse (DEPRECATED - use getReceiptsBySupplier instead)
     */
    getWarehouseProducts: (warehouseId, params = {}) => {
        return apiClient.get(`/warehouses/${warehouseId}/products`, { params });
    },

    /**
     * Get supplier options for select
     */
    getSupplierOptions: (search = "") => {
        return apiClient.get("/suppliers/options/list", {
            params: { search },
        });
    },

    /**
     * Get warehouse options for select
     */
    getWarehouseOptions: (search = "") => {
        return apiClient.get("/warehouses/options/list", {
            params: { search },
        });
    },

    /**
     * Get product options for warehouse
     */
    getProductOptions: (warehouseId, search = "") => {
        return apiClient.get(`/warehouses/${warehouseId}/products/options`, {
            params: { search },
        });
    },

    /**
     * Bulk delete purchase return orders
     */
    bulkDeletePurchaseReturnOrders: (ids) => {
        return apiClient.post("/purchase-return-orders/bulk-delete", { ids });
    },

    /**
     * Export purchase return orders
     */
    exportPurchaseReturnOrders: async (params = {}) => {
        try {
            const response = await apiClient.get(
                "/purchase-return-orders/export",
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
     * Get statistics
     */
    getStatistics: () => {
        return apiClient.get("/purchase-return-orders/stats");
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
            pending: "bg-warning",
            approved: "bg-info",
            returned: "bg-primary",
            completed: "bg-success",
            cancelled: "bg-danger",
        };
        return classes[status] || "bg-secondary";
    },

    /**
     * Get status label
     */
    getStatusLabel: (status) => {
        const statusLabels = {
            pending: "Chờ duyệt",
            approved: "Đã duyệt",
            returned: "Đã trả hàng",
            completed: "Hoàn thành",
            cancelled: "Đã hủy",
        };
        return statusLabels[status] || status;
    },

    /**
     * Get status color
     */
    getStatusColor: (status) => {
        const statusColors = {
            pending: "yellow",
            approved: "blue",
            returned: "purple",
            completed: "green",
            cancelled: "red",
        };
        return statusColors[status] || "gray";
    },

    /**
     * Check if order can be edited
     */
    canEdit: (order) => {
        return ["pending"].includes(order.status);
    },
    /**
     * Check if order can be deleted
     */
    canDelete: (order) => {
        return ["pending", "cancelled"].includes(order.status);
    },
    /**
     * Check if order can be approved
     */
    canApprove: (order) => {
        return order.status === "pending";
    },

    /**
     * Check if order can be returned
     */
    canReturn: (order) => {
        return order.status === "approved";
    },

    /**
     * Check if order can be cancelled
     */
    canCancel: (order) => {
        return ["pending", "approved"].includes(order.status);
    },

    /**
     * Validate return order data
     */
    validateReturnOrderData: (data) => {
        const errors = {};

        if (!data.supplier_id) {
            errors.supplier_id = "Vui lòng chọn nhà cung cấp";
        }

        if (!data.warehouse_id) {
            errors.warehouse_id = "Vui lòng chọn kho trả hàng";
        }

        if (!data.items || data.items.length === 0) {
            errors.items = "Vui lòng thêm ít nhất một sản phẩm";
        }

        if (data.items) {
            data.items.forEach((item, index) => {
                if (!item.quantity || item.quantity <= 0) {
                    errors[`items.${index}.quantity`] =
                        "Số lượng trả phải lớn hơn 0";
                }

                if (!item.price || item.price < 0) {
                    errors[`items.${index}.price`] = "Đơn giá không được âm";
                }

                if (!item.purchase_receipt_item_id) {
                    errors[`items.${index}.purchase_receipt_item_id`] =
                        "Thiếu thông tin phiếu nhập";
                }
            });
        }

        return {
            isValid: Object.keys(errors).length === 0,
            errors,
        };
    },
};

// Add alias methods
purchaseReturnOrderApi.getAll = (params = {}) => {
    return apiClient.get('/purchase-return-orders', { params })
}

purchaseReturnOrderApi.show = (id) => {
    return apiClient.get(`/purchase-return-orders/${id}`)
}

purchaseReturnOrderApi.create = (data) => {
    return apiClient.post('/purchase-return-orders', data)
}

purchaseReturnOrderApi.update = (id, data) => {
    return apiClient.put(`/purchase-return-orders/${id}`, data)
}

purchaseReturnOrderApi.delete = (id) => {
    return apiClient.delete(`/purchase-return-orders/${id}`)
}

export default purchaseReturnOrderApi