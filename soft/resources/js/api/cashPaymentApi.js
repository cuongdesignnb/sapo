import axios from "axios";

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
    (error) => Promise.reject(error),
);

// Response interceptor
apiClient.interceptors.response.use(
    (response) => response.data,
    (error) => {
        console.error("API Error:", error.response?.data || error.message);

        // Handle 401 unauthorized
        if (error.response?.status === 401) {
            console.log("Token expired, redirecting to login...");
            sessionStorage.removeItem("api_token");
            window.location.href = "/force-logout?reason=unauthorized";
            return Promise.reject(new Error("Session expired"));
        }

        return Promise.reject(error.response?.data || error);
    },
);

// Cash Payment API methods
const cashPaymentApi = {
    // Lấy danh sách phiếu chi
    getAll: (params = {}) => {
        console.log("📋 Getting cash payments with params:", params);
        return apiClient.get("/cash-vouchers/payments", { params });
    },

    // Chi tiết phiếu chi
    getById: (id) => {
        console.log("🔍 Getting cash payment by ID:", id);
        return apiClient.get(`/cash-vouchers/payments/${id}`);
    },

    // Tạo phiếu chi mới
    create: (data) => {
        console.log("➕ Creating cash payment with data:", data);
        return apiClient.post("/cash-vouchers/payments", data);
    },

    // Cập nhật phiếu chi
    update: (id, data) => {
        console.log("✏️ Updating cash payment:", id, data);
        return apiClient.put(`/cash-vouchers/payments/${id}`, data);
    },

    // Gửi duyệt
    submitForApproval: (id) => {
        console.log("📤 Submitting cash payment for approval:", id);
        return apiClient.patch(`/cash-vouchers/payments/${id}/submit`);
    },

    // Duyệt phiếu chi
    approve: (id) => {
        console.log("✅ Approving cash payment:", id);
        return apiClient.patch(`/cash-vouchers/payments/${id}/approve`);
    },

    // Hủy phiếu chi
    cancel: (id) => {
        console.log("❌ Cancelling cash payment:", id);
        return apiClient.patch(`/cash-vouchers/payments/${id}/cancel`);
    },

    // Xóa phiếu chi
    delete: (id) => {
        console.log("🗑️ Deleting cash payment:", id);
        return apiClient.delete(`/cash-vouchers/payments/${id}`);
    },

    // Lấy danh sách loại phiếu chi
    getPaymentTypes: (params = {}) => {
        console.log("📋 Getting payment types with params:", params);
        return apiClient.get("/cash-vouchers/payment-types", { params });
    },

    // Tạo loại phiếu chi mới
    createPaymentType: (data) => {
        console.log("➕ Creating payment type:", data);
        return apiClient.post("/cash-vouchers/payment-types", data);
    },

    // Cập nhật loại phiếu chi
    updatePaymentType: (id, data) => {
        console.log("✏️ Updating payment type:", id, data);
        return apiClient.put(`/cash-vouchers/payment-types/${id}`, data);
    },

    // Xóa loại phiếu chi
    deletePaymentType: (id) => {
        console.log("🗑️ Deleting payment type:", id);
        return apiClient.delete(`/cash-vouchers/payment-types/${id}`);
    },

    // Lấy danh sách người nhận theo loại
    getRecipients: (type) => {
        console.log("👥 Getting recipients for type:", type);
        return apiClient.get("/cash-vouchers/payments/recipients", {
            params: { type },
        });
    },

    // Lấy danh sách kho/chi nhánh
    getWarehouses: (params = {}) => {
        console.log("🏢 Getting warehouses with params:", params);
        return apiClient.get("/my-warehouses", { params });
    },

    // Export data
    export: (params = {}) => {
        console.log("📥 Exporting cash payments with params:", params);
        return apiClient.get("/cash-vouchers/payments/export", {
            params,
            responseType: "blob",
        });
    },

    // In phiếu chi
    print: (id) => {
        console.log("🖨️ Printing cash payment:", id);
        return apiClient.get(`/cash-vouchers/payments/${id}/print`, {
            responseType: "blob",
        });
    },

    // Thống kê phiếu chi
    getStats: (params = {}) => {
        console.log("📊 Getting payment stats with params:", params);
        return apiClient.get("/cash-vouchers/payments/stats", { params });
    },
};

// Helper functions
const cashPaymentHelpers = {
    // Format tiền tệ
    formatCurrency: (amount) => {
        if (!amount || isNaN(amount)) return "0 ₫";
        return new Intl.NumberFormat("vi-VN", {
            style: "currency",
            currency: "VND",
        }).format(amount);
    },

    // Format ngày tháng
    formatDate: (date) => {
        if (!date) return "N/A";
        try {
            return new Date(date).toLocaleDateString("vi-VN", {
                year: "numeric",
                month: "2-digit",
                day: "2-digit",
            });
        } catch (error) {
            return date;
        }
    },

    // Format ngày giờ
    formatDateTime: (datetime) => {
        if (!datetime) return "N/A";
        try {
            return new Date(datetime).toLocaleString("vi-VN", {
                year: "numeric",
                month: "2-digit",
                day: "2-digit",
                hour: "2-digit",
                minute: "2-digit",
            });
        } catch (error) {
            return datetime;
        }
    },

    // Lấy class CSS cho trạng thái
    getStatusClass: (status) => {
        const statusClasses = {
            draft: "bg-gray-100 text-gray-800",
            pending: "bg-yellow-100 text-yellow-800",
            approved: "bg-green-100 text-green-800",
            cancelled: "bg-red-100 text-red-800",
        };
        return statusClasses[status] || "bg-gray-100 text-gray-800";
    },

    // Lấy text cho trạng thái
    getStatusText: (status) => {
        const statusTexts = {
            draft: "Nháp",
            pending: "Chờ duyệt",
            approved: "Đã duyệt",
            cancelled: "Đã hủy",
        };
        return statusTexts[status] || status;
    },

    // Lấy text cho phương thức thanh toán
    getPaymentMethodText: (method) => {
        const methodTexts = {
            cash: "Tiền mặt",
            transfer: "Chuyển khoản",
        };
        return methodTexts[method] || method;
    },

    // Lấy text cho loại người nhận
    getRecipientTypeText: (type) => {
        const typeTexts = {
            customer: "Khách hàng",
            supplier: "Nhà cung cấp",
        };
        return typeTexts[type] || type;
    },

    // Validate form data
    validatePaymentData: (data) => {
        const errors = [];

        if (!data.type_id) errors.push("Vui lòng chọn loại phiếu chi");
        if (!data.recipient_type) errors.push("Vui lòng chọn nhóm người nhận");
        if (!data.recipient_id) errors.push("Vui lòng chọn người nhận");
        if (!data.warehouse_id) errors.push("Vui lòng chọn chi nhánh");
        if (!data.amount || data.amount <= 0)
            errors.push("Vui lòng nhập số tiền hợp lệ");
        if (!data.payment_method)
            errors.push("Vui lòng chọn hình thức thanh toán");
        if (!data.payment_date) errors.push("Vui lòng chọn ngày ghi nhận");

        return errors;
    },

    // Generate payment code
    generatePaymentCode: (prefix = "PMT") => {
        const now = new Date();
        const year = now.getFullYear().toString().slice(-2);
        const month = (now.getMonth() + 1).toString().padStart(2, "0");
        const day = now.getDate().toString().padStart(2, "0");
        const time = now.getTime().toString().slice(-4);

        return `${prefix}${year}${month}${day}${time}`;
    },

    // Calculate totals
    calculateTotals: (payments) => {
        return payments.reduce(
            (totals, payment) => {
                const amount = parseFloat(payment.amount) || 0;

                totals.total += amount;

                if (payment.status === "approved") {
                    totals.approved += amount;
                }

                if (payment.payment_method === "cash") {
                    totals.cash += amount;
                } else if (payment.payment_method === "transfer") {
                    totals.transfer += amount;
                }

                return totals;
            },
            {
                total: 0,
                approved: 0,
                cash: 0,
                transfer: 0,
            },
        );
    },

    // Debounce function for search
    debounce: (func, wait) => {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },
};

// Export both API and helpers
export { cashPaymentApi, cashPaymentHelpers };

// Default export for backwards compatibility
export default cashPaymentApi;
