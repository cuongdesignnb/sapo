// resources/js/api/orderApi.js
import axios from "axios";
import { attachAuthInterceptors } from "@/api/authInterceptor";

// Helper function to get authentication headers
const getAuthHeaders = () => {
    const headers = {
        "Content-Type": "application/json",
        Accept: "application/json",
    };

    // Add CSRF token
    const csrfToken = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute("content");
    if (csrfToken) {
        headers["X-CSRF-TOKEN"] = csrfToken;
    }

    // Add Bearer token
    let accessToken = sessionStorage.getItem("api_token");
    if (!accessToken) {
        accessToken = document
            .querySelector('meta[name="api-token"]')
            ?.getAttribute("content");
    }

    if (accessToken) {
        headers["Authorization"] = `Bearer ${accessToken}`;
    }

    return headers;
};

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
        // Add CSRF token
        const csrfToken = document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute("content");
        if (csrfToken) {
            config.headers["X-CSRF-TOKEN"] = csrfToken;
        }

        // Add Bearer token
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

// Order API methods
const orderApi = {
    // Lấy danh sách đơn hàng
    getAll: (params = {}) => {
        console.log("📋 Getting orders with params:", params);
        return apiClient.get("/orders", { params });
    },

    // Dữ liệu cho bộ lọc (dropdown options)
    getFilterData: (params = {}) => {
        console.log("🧰 Getting order filter data:", params);
        return apiClient.get("/orders/filter-data", { params });
    },

    // Chi tiết đơn hàng
    getById: (id) => {
        console.log("🔍 Getting order by ID:", id);
        return apiClient.get(`/orders/${id}`);
    },

    // Tạo đơn hàng mới
    create: (data) => {
        console.log("➕ Creating order with data:", data);
        return apiClient.post("/orders", data);
    },
    update: (id, data) => {
        console.log("✏️ Updating order:", id, data);
        return apiClient.put(`/orders/${id}`, data);
    },
    // Cập nhật trạng thái
    updateStatus: (id, data) => {
        console.log("📝 Updating order status:", id, data);
        return apiClient.patch(`/orders/${id}/status`, data);
    },

    // Thêm thanh toán
    addPayment: (id, data) => {
        console.log("💳 Adding payment to order:", id, data);
        return apiClient.post(`/orders/${id}/payments`, data);
    },

    // Xóa đơn hàng
    delete: (id) => {
        console.log("🗑️ Deleting order:", id);
        return apiClient.delete(`/orders/${id}`);
    },

    // Xóa nhiều đơn hàng
    bulkDelete: (ids) => {
        console.log("🗑️ Bulk deleting orders:", ids);
        return apiClient.post("/orders/bulk-delete", { ids });
    },

    // Thống kê
    getStats: (params = {}) => {
        console.log("📊 Getting order statistics");
        return apiClient.get("/orders/stats", { params });
    },
    // Print order - API THẬT GIỐNG CASHRECEIPT
    print: (id) => {
        console.log("🖨️ Printing order:", id);
        const printUrl = `/api/orders/${id}/print`;
        window.open(printUrl, "_blank");
        return Promise.resolve({
            success: true,
            message: "Đang mở cửa sổ in",
            url: printUrl,
        });
    },

    // Export Excel
    export: async (params = {}) => {
        try {
            console.log("📤 Starting export with params:", params);

            const headers = getAuthHeaders();
            delete headers["Content-Type"]; // Remove for file download

            // Build query string
            const queryParams = new URLSearchParams();
            Object.keys(params).forEach((key) => {
                if (
                    params[key] !== "" &&
                    params[key] !== null &&
                    params[key] !== undefined
                ) {
                    if (Array.isArray(params[key])) {
                        queryParams.append(key, params[key].join(","));
                    } else {
                        queryParams.append(key, params[key]);
                    }
                }
            });

            const url = `/api/orders/export${
                queryParams.toString() ? "?" + queryParams.toString() : ""
            }`;

            console.log("🔗 Export URL:", url);

            const response = await fetch(url, {
                method: "GET",
                headers: headers,
            });

            console.log("📊 Export response status:", response.status);

            if (!response.ok) {
                const errorText = await response.text();
                console.error("Export error response:", errorText);
                throw new Error(
                    `Export failed: ${response.status} ${response.statusText}`
                );
            }

            const blob = await response.blob();
            console.log("📦 Export blob size:", blob.size, "bytes");

            if (blob.size === 0) {
                throw new Error("Export file is empty");
            }

            // Create download link
            const downloadUrl = window.URL.createObjectURL(blob);
            const link = document.createElement("a");
            link.href = downloadUrl;
            link.download = `danh-sach-don-hang-${new Date()
                .toISOString()
                .slice(0, 10)}.xlsx`;
            link.style.display = "none";

            // Trigger download
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);

            // Cleanup
            window.URL.revokeObjectURL(downloadUrl);

            console.log("🎉 Export completed successfully!");
            return {
                success: true,
                message: "Xuất file thành công",
                filename: link.download,
            };
        } catch (error) {
            console.error("❌ Export failed:", error);
            throw {
                success: false,
                message: error.message || "Lỗi xuất file",
                error: error,
            };
        }
    },

    // Tải file mẫu import
    downloadTemplate: async () => {
        try {
            console.log("📥 Downloading import template");

            const headers = getAuthHeaders();
            delete headers["Content-Type"]; // Remove for file download

            const response = await fetch("/api/orders/import-template", {
                method: "GET",
                headers: headers,
            });

            console.log("📊 Template response status:", response.status);

            if (!response.ok) {
                const errorText = await response.text();
                console.error("Template error response:", errorText);
                throw new Error(
                    `Template download failed: ${response.status} ${response.statusText}`
                );
            }

            const blob = await response.blob();
            console.log("📦 Template blob size:", blob.size, "bytes");

            if (blob.size === 0) {
                throw new Error("Template file is empty");
            }

            // Create download link
            const downloadUrl = window.URL.createObjectURL(blob);
            const link = document.createElement("a");
            link.href = downloadUrl;
            link.download = "mau-import-don-hang.xlsx";
            link.style.display = "none";

            // Trigger download
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);

            // Cleanup
            window.URL.revokeObjectURL(downloadUrl);

            console.log("🎉 Template downloaded successfully!");
            return {
                success: true,
                message: "Tải template thành công",
            };
        } catch (error) {
            console.error("❌ Template download failed:", error);
            throw {
                success: false,
                message: error.message || "Lỗi tải template",
                error: error,
            };
        }
    },

    // Import Excel
    import: async (file) => {
        try {
            console.log("📥 Importing orders from file:", file.name);

            const formData = new FormData();
            formData.append("file", file);

            const headers = getAuthHeaders();
            headers["Content-Type"] = "multipart/form-data";

            const response = await axios.post("/api/orders/import", formData, {
                headers: headers,
                timeout: 60000, // Longer timeout for import
            });

            console.log("✅ Import response:", response.data);
            return response.data;
        } catch (error) {
            console.error("❌ Import failed:", error);
            throw error;
        }
    },
};

// Helper functions
const orderHelpers = {
    // Format trạng thái
    getStatusText(status) {
        const statusMap = {
            pending: "Chờ xử lý",
            confirmed: "Đã xác nhận",
            processing: "Đang xử lý",
            shipping: "Đang giao hàng",
            delivered: "Đã giao hàng",
            completed: "Hoàn thành",
            cancelled: "Đã hủy",
            refunded: "Đã hoàn tiền",
        };
        return statusMap[status] || status;
    },

    // Màu sắc Tailwind cho trạng thái
    getStatusClass(status) {
        const classMap = {
            pending: "bg-yellow-100 text-yellow-800",
            confirmed: "bg-blue-100 text-blue-800",
            processing: "bg-orange-100 text-orange-800",
            shipping: "bg-purple-100 text-purple-800",
            delivered: "bg-green-100 text-green-800",
            completed: "bg-green-100 text-green-800",
            cancelled: "bg-red-100 text-red-800",
            refunded: "bg-gray-100 text-gray-800",
        };
        return classMap[status] || "bg-gray-100 text-gray-800";
    },

    // Format tiền tệ
    formatCurrency(amount) {
        return new Intl.NumberFormat("vi-VN", {
            style: "currency",
            currency: "VND",
        }).format(amount);
    },

    // Format ngày tháng
    formatDate(date) {
        return new Date(date).toLocaleDateString("vi-VN", {
            year: "numeric",
            month: "2-digit",
            day: "2-digit",
            hour: "2-digit",
            minute: "2-digit",
        });
    },

    // Kiểm tra có thể chỉnh sửa
    canEdit(order) {
        return order.status === "pending";
    },

    // Kiểm tra có thể xóa
    canDelete(order) {
        return ["pending", "cancelled"].includes(order.status);
    },

    // Kiểm tra có thể hủy
    canCancel(order) {
        return ["pending", "confirmed"].includes(order.status);
    },

    // Xử lý export Excel
    async handleExport(params = {}) {
        try {
            await orderApi.export(params);
            return { success: true, message: "Xuất file thành công" };
        } catch (error) {
            return {
                success: false,
                message: "Lỗi khi xuất file: " + error.message,
            };
        }
    },

    // Xử lý download template
    async handleDownloadTemplate() {
        try {
            await orderApi.downloadTemplate();
            return { success: true, message: "Tải file mẫu thành công" };
        } catch (error) {
            return {
                success: false,
                message: "Lỗi khi tải file mẫu: " + error.message,
            };
        }
    },

    // Xử lý import Excel
    async handleImport(file) {
        try {
            const response = await orderApi.import(file);
            return response.data;
        } catch (error) {
            return {
                success: false,
                message:
                    "Lỗi khi import file: " +
                    (error.response?.data?.message || error.message),
            };
        }
    },
};

// =====================================================
// QUY TRÌNH MỚI 5 BƯỚC - API METHODS
// =====================================================
orderApi.getNextAction = async function (orderId) {
    try {
        const response = await apiClient.get(`/orders/${orderId}/next-action`);
        return response.data;
    } catch (error) {
        console.error("Error getting next action:", error);
        throw error;
    }
};

// BƯỚC 2: Duyệt đơn hàng
orderApi.approveOrder = async function (orderId, data = {}) {
    try {
        const response = await apiClient.post(
            `/orders/${orderId}/approve`,
            data
        );
        return response.data;
    } catch (error) {
        console.error("Error approving order:", error);
        throw error;
    }
};

// BƯỚC 3: Tạo đơn vận chuyển
orderApi.createShipping = async function (orderId, shippingData) {
    try {
        const response = await apiClient.post(
            `/orders/${orderId}/create-shipping`,
            shippingData
        );
        return response.data;
    } catch (error) {
        console.error("Error creating shipping:", error);
        throw error;
    }
};

// Lấy danh sách đơn vị vận chuyển (active)
orderApi.getShippingProviders = async function (params = {}) {
    try {
        // Expect backend supports /shipping-providers?status=active
        const apiRes = await apiClient.get(`/shipping-providers`, {
            params: { status: "active", per_page: 100, ...params },
        });
        // apiClient interceptor already returns response.data (the JSON body)
        // Structure from backend: { success: true, data: [ ...providers ], pagination: {...} }
        if (Array.isArray(apiRes)) {
            return apiRes; // already an array (unlikely here)
        }
        if (apiRes && Array.isArray(apiRes.data)) {
            return apiRes.data; // providers array
        }
        // Fallback: maybe backend returns directly an array
        return [];
    } catch (error) {
        console.error("Error fetching shipping providers:", error);
        throw error;
    }
};

// BƯỚC 4: Xuất kho
orderApi.exportStock = async function (orderId, data = {}) {
    try {
        const response = await apiClient.post(
            `/orders/${orderId}/export-stock`,
            data
        );
        return response.data;
    } catch (error) {
        console.error("Error exporting stock:", error);
        throw error;
    }
};

// BƯỚC 5: Thanh toán hoàn tất
orderApi.completePayment = async function (orderId, paymentData) {
    try {
        const response = await apiClient.post(
            `/orders/${orderId}/complete-payment`,
            paymentData
        );
        return response.data;
    } catch (error) {
        console.error("Error completing payment:", error);
        throw error;
    }
};

// Hủy đơn hàng
orderApi.cancelOrder = async function (orderId, data) {
    try {
        const response = await apiClient.post(
            `/orders/${orderId}/cancel`,
            data
        );
        return response.data;
    } catch (error) {
        console.error("Error cancelling order:", error);
        throw error;
    }
};

// Helper functions cho quy trình mới
const workflowHelpers = {
    // Lấy text trạng thái
    getStatusText(status) {
        const statusMap = {
            ordered: "Đặt hàng",
            approved: "Đã duyệt",
            shipping_created: "Đã tạo vận chuyển",
            delivered: "Đã giao hàng",
            completed: "Hoàn thành",
            cancelled: "Đã hủy",
        };
        return statusMap[status] || status;
    },

    // Lấy màu trạng thái
    getStatusColor(status) {
        const colorMap = {
            ordered: "yellow",
            approved: "blue",
            shipping_created: "purple",
            delivered: "green",
            completed: "green",
            cancelled: "red",
        };
        return colorMap[status] || "gray";
    },

    // Kiểm tra có thể thực hiện hành động
    canPerformAction(order, action) {
        const allowedActions = {
            ordered: ["approve", "cancel"],
            approved: ["create_shipping", "cancel"],
            shipping_created: ["export_stock", "cancel"],
            delivered: ["payment"],
            completed: [],
            cancelled: [],
        };

        return allowedActions[order.status]?.includes(action) || false;
    },

    // Format text phương thức vận chuyển
    getShippingMethodText(method) {
        const methodMap = {
            third_party: "Gửi cho bên giao hàng",
            self_delivery: "Tự giao hàng",
            pickup: "Nhận tại cửa hàng",
        };
        return methodMap[method] || method;
    },
};

// Export
export { orderApi };
export { orderHelpers };
export { workflowHelpers };
