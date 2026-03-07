import axios from "axios";

class WarehouseApi {
    constructor() {
        this.baseURL = "/api/warehouses";
        this.productBaseURL = "/api/warehouse-products";
        this.http = axios.create({
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
                "X-Requested-With": "XMLHttpRequest",
            },
        });

        // Add CSRF token to requests
        const token = document.querySelector('meta[name="csrf-token"]');
        if (token) {
            this.http.defaults.headers.common["X-CSRF-TOKEN"] =
                token.getAttribute("content");
        }

        // Add request interceptor to include Bearer token
        this.http.interceptors.request.use(
            (config) => {
                // Add Bearer token (same as category API)
                let accessToken = sessionStorage.getItem("api_token");
                if (!accessToken) {
                    accessToken = document
                        .querySelector('meta[name="api-token"]')
                        ?.getAttribute("content");
                }

                if (accessToken) {
                    config.headers.Authorization = `Bearer ${accessToken}`;
                }

                return config;
            },
            (error) => {
                return Promise.reject(error);
            }
        );

        // Add response interceptor to handle 401 errors
        this.http.interceptors.response.use(
            (response) => response,
            (error) => {
                if (error.response?.status === 401) {
                    // Token expired or invalid, redirect to login
                    this.handleUnauthorized();
                }
                return Promise.reject(error);
            }
        );
    }

    /**
     * Handle unauthorized access
     */
    handleUnauthorized() {
        // Clear token
        sessionStorage.removeItem("api_token");

        // Force logout to avoid /login <-> /dashboard redirect loop
        window.location.href = "/force-logout?reason=unauthorized";
    }

    /**
     * Get warehouses list with search, filter, pagination
     */
    async getWarehouses(params = {}) {
        try {
            const response = await this.http.get(this.baseURL, { params });
            return response.data;
        } catch (error) {
            throw this.handleError(error);
        }
    }

    /**
     * Get warehouse by ID
     */
    async getWarehouse(id) {
        try {
            const response = await this.http.get(`${this.baseURL}/${id}`);
            return response.data;
        } catch (error) {
            throw this.handleError(error);
        }
    }

    /**
     * Create new warehouse
     */
    async createWarehouse(data) {
        try {
            const response = await this.http.post(this.baseURL, data);
            return response.data;
        } catch (error) {
            throw this.handleError(error);
        }
    }

    /**
     * Update warehouse
     */
    async updateWarehouse(id, data) {
        try {
            const response = await this.http.put(`${this.baseURL}/${id}`, data);
            return response.data;
        } catch (error) {
            throw this.handleError(error);
        }
    }

    /**
     * Delete warehouse
     */
    async deleteWarehouse(id) {
        try {
            const response = await this.http.delete(`${this.baseURL}/${id}`);
            return response.data;
        } catch (error) {
            throw this.handleError(error);
        }
    }

    /**
     * Get warehouse products
     */
    async getWarehouseProducts(warehouseId, params = {}) {
        try {
            const response = await this.http.get(
                `${this.baseURL}/${warehouseId}/products`,
                { params }
            );
            return response.data;
        } catch (error) {
            throw this.handleError(error);
        }
    }

    /**
     * Get all warehouse products with filters
     */
    async getAllWarehouseProducts(params = {}) {
        try {
            const response = await this.http.get(this.productBaseURL, {
                params,
            });
            return response.data;
        } catch (error) {
            throw this.handleError(error);
        }
    }

    /**
     * Update warehouse product stock
     */
    async updateWarehouseProduct(id, data) {
        try {
            const response = await this.http.put(
                `${this.productBaseURL}/${id}`,
                data
            );
            return response.data;
        } catch (error) {
            throw this.handleError(error);
        }
    }

    /**
     * Adjust stock quantity
     */
    async adjustStock(data) {
        try {
            const response = await this.http.post(
                `${this.productBaseURL}/adjust-stock`,
                data
            );
            return response.data;
        } catch (error) {
            throw this.handleError(error);
        }
    }

    /**
     * Transfer product between warehouses
     */
    async transferProduct(data) {
        try {
            const response = await this.http.post(
                `${this.productBaseURL}/transfer`,
                data
            );
            return response.data;
        } catch (error) {
            throw this.handleError(error);
        }
    }
    async bulkTransferProducts(data) {
        try {
            const response = await api.post(
                "/warehouse-products/bulk-transfer",
                data
            );
            return response.data;
        } catch (error) {
            console.error("Bulk transfer error:", error);
            throw error.response?.data || error;
        }
    }

    /**
     * Get low stock alerts
     */
    async getLowStockAlerts(params = {}) {
        try {
            const response = await this.http.get(
                `${this.productBaseURL}/low-stock-alerts`,
                { params }
            );
            return response.data;
        } catch (error) {
            throw this.handleError(error);
        }
    }

    /**
     * Get out of stock products
     */
    async getOutOfStock(params = {}) {
        try {
            const response = await this.http.get(
                `${this.productBaseURL}/out-of-stock`,
                { params }
            );
            return response.data;
        } catch (error) {
            throw this.handleError(error);
        }
    }

    /**
     * Get stock summary
     */
    async getStockSummary(params = {}) {
        try {
            const response = await this.http.get(
                `${this.productBaseURL}/stock-summary`,
                { params }
            );
            return response.data;
        } catch (error) {
            throw this.handleError(error);
        }
    }

    /**
     * Get warehouse capacity analysis
     */
    async getCapacityAnalysis(warehouseId) {
        try {
            const response = await this.http.get(
                `${this.productBaseURL}/capacity-analysis/${warehouseId}`
            );
            return response.data;
        } catch (error) {
            throw this.handleError(error);
        }
    }

    /**
     * Warehouse Switching APIs
     */
    async getAvailableWarehouses() {
        try {
            const response = await this.http.get(
                "/api/warehouse-switching/available"
            );
            return response.data;
        } catch (error) {
            throw this.handleError(error);
        }
    }

    async getCurrentWarehouse() {
        try {
            const response = await this.http.get(
                "/api/warehouse-switching/current"
            );
            return response.data;
        } catch (error) {
            throw this.handleError(error);
        }
    }

    async switchWarehouse(warehouseId) {
        try {
            const response = await this.http.post(
                "/api/warehouse-switching/switch",
                {
                    warehouse_id: warehouseId,
                }
            );
            return response.data;
        } catch (error) {
            throw this.handleError(error);
        }
    }

    async clearWarehouse() {
        try {
            const response = await this.http.delete(
                "/api/warehouse-switching/clear"
            );
            return response.data;
        } catch (error) {
            throw this.handleError(error);
        }
    }

    /**
     * Handle API errors
     */
    handleError(error) {
        if (error.response) {
            const { status, data } = error.response;

            switch (status) {
                case 401:
                    return {
                        type: "unauthorized",
                        message: "Phiên đăng nhập đã hết hạn",
                    };
                case 422:
                    return {
                        type: "validation",
                        message: data.message || "Dữ liệu không hợp lệ",
                        errors: data.errors || {},
                    };
                case 404:
                    return {
                        type: "not_found",
                        message: "Không tìm thấy kho hàng",
                    };
                case 403:
                    return {
                        type: "forbidden",
                        message: "Không có quyền thực hiện thao tác này",
                    };
                case 500:
                    return {
                        type: "server_error",
                        message: "Lỗi server, vui lòng thử lại sau",
                    };
                default:
                    return {
                        type: "error",
                        message: data.message || "Đã xảy ra lỗi",
                    };
            }
        } else if (error.request) {
            return {
                type: "network_error",
                message: "Lỗi kết nối mạng",
            };
        } else {
            return {
                type: "error",
                message: error.message || "Đã xảy ra lỗi",
            };
        }
    }

    /**
     * Format currency for display
     */
    formatCurrency(amount) {
        return new Intl.NumberFormat("vi-VN", {
            style: "currency",
            currency: "VND",
        }).format(amount);
    }

    /**
     * Format date for display
     */
    formatDate(date) {
        if (!date) return "";
        return new Date(date).toLocaleDateString("vi-VN");
    }

    /**
     * Format datetime for display
     */
    formatDateTime(datetime) {
        if (!datetime) return "";
        return new Date(datetime).toLocaleString("vi-VN");
    }
}

// Export singleton instance
export default new WarehouseApi();
