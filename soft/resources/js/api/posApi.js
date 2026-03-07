// resources/js/api/posApi.js
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
    let accessToken = window.posConfig?.apiToken;
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
    baseURL: "/api/pos",
    timeout: 10000,
    headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
    },
});

// Request interceptor
apiClient.interceptors.request.use(
    (config) => {
        const headers = getAuthHeaders();
        Object.assign(config.headers, headers);
        return config;
    },
    (error) => Promise.reject(error)
);

// Response interceptor
apiClient.interceptors.response.use(
    (response) => response.data,
    (error) => {
        console.error("POS API Error:", error.response?.data || error.message);

        // Handle 401 unauthorized
        if (error.response?.status === 401) {
            console.log("Token expired, redirecting to login...");
            window.location.href = "/force-logout?reason=unauthorized";
            return Promise.reject(new Error("Session expired"));
        }

        return Promise.reject(error.response?.data || error);
    }
);

// POS API methods
const posApi = {
    // Lấy dữ liệu khởi tạo POS
    getInitData: () => {
        console.log("🏪 Getting POS init data");
        return apiClient.get("/");
    },

    // Tìm kiếm sản phẩm
    searchProducts: (params) => {
        console.log("🔍 Searching products:", params);
        return apiClient.get("/products/search", { params });
    },

    // Tìm kiếm khách hàng
    searchCustomers: (params) => {
        console.log("👤 Searching customers:", params);
        return apiClient.get("/customers/search", { params });
    },

    // Tạo đơn hàng
    createOrder: (data) => {
        console.log("📝 Creating POS order:", data);
        return apiClient.post("/orders", data);
    },

    // Lấy đơn hàng gần đây
    getRecentOrders: (params = {}) => {
        console.log("📋 Getting recent orders");
        return apiClient.get("/orders/recent", { params });
    },

    // In hóa đơn
    printInvoice: (orderId) => {
        console.log("🖨️ Printing invoice:", orderId);
        return apiClient.get(`/orders/${orderId}/print`);
    },
};

// Helper functions
const posHelpers = {
    // Format tiền tệ
    formatCurrency(amount) {
        return new Intl.NumberFormat("vi-VN", {
            style: "currency",
            currency: "VND",
        }).format(amount || 0);
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

    // Tính tổng tiền từ items
    calculateSubtotal(items) {
        return items.reduce((total, item) => {
            return total + (item.total || 0);
        }, 0);
    },

    // Tính VAT
    calculateVAT(subtotal, vatPercent) {
        return (subtotal * (vatPercent || 0)) / 100;
    },

    // Tính chiết khấu
    calculateDiscount(subtotal, discountPercent) {
        return (subtotal * (discountPercent || 0)) / 100;
    },

    // Tính tổng cần thanh toán
    calculateTotal(subtotal, vatAmount, discountAmount) {
        return subtotal + (vatAmount || 0) - (discountAmount || 0);
    },

    // Tính tiền thừa
    calculateChange(paid, total) {
        return Math.max(0, (paid || 0) - (total || 0));
    },

    // Tính tiền nợ
    calculateDebt(total, paid) {
        return Math.max(0, (total || 0) - (paid || 0));
    },

    // Validate đơn hàng
    validateOrder(orderData) {
        const errors = [];

        if (!orderData.warehouse_id) {
            errors.push("Vui lòng chọn kho");
        }

        if (!orderData.items || orderData.items.length === 0) {
            errors.push("Vui lòng thêm sản phẩm");
        }

        if (
            orderData.items &&
            orderData.items.some((item) => item.quantity <= 0)
        ) {
            errors.push("Số lượng sản phẩm phải lớn hơn 0");
        }

        if (orderData.items && orderData.items.some((item) => item.price < 0)) {
            errors.push("Giá sản phẩm không được âm");
        }

        if (orderData.total <= 0) {
            errors.push("Tổng tiền phải lớn hơn 0");
        }

        return {
            isValid: errors.length === 0,
            errors,
        };
    },

    // Tạo mã đơn hàng tạm thời
    generateTempOrderCode() {
        const now = new Date();
        const timestamp = now.getTime().toString().slice(-6);
        return `TEMP${timestamp}`;
    },

    // Lưu đơn hàng vào localStorage (backup)
    saveOrderToLocal(tabId, orderData) {
        try {
            const key = `pos_order_${tabId}`;
            localStorage.setItem(
                key,
                JSON.stringify({
                    ...orderData,
                    savedAt: new Date().toISOString(),
                })
            );
        } catch (error) {
            console.warn("Cannot save order to localStorage:", error);
        }
    },

    // Lấy đơn hàng từ localStorage
    getOrderFromLocal(tabId) {
        try {
            const key = `pos_order_${tabId}`;
            const data = localStorage.getItem(key);
            return data ? JSON.parse(data) : null;
        } catch (error) {
            console.warn("Cannot get order from localStorage:", error);
            return null;
        }
    },

    // Xóa đơn hàng khỏi localStorage
    removeOrderFromLocal(tabId) {
        try {
            const key = `pos_order_${tabId}`;
            localStorage.removeItem(key);
        } catch (error) {
            console.warn("Cannot remove order from localStorage:", error);
        }
    },

    // Get payment method text
    getPaymentMethodText(method) {
        const methodMap = {
            cash: "Tiền mặt",
            transfer: "Chuyển khoản",
            card: "Thẻ tín dụng",
            wallet: "Ví điện tử",
            debt: "Công nợ",
        };
        return methodMap[method] || method;
    },

    // Generate quick payment amounts
    generateQuickAmounts(total) {
        const base = Math.ceil(total / 1000) * 1000;
        const amounts = [
            total,
            base,
            base + 50000,
            base + 100000,
            500000,
            1000000,
        ];

        return [...new Set(amounts)]
            .filter((amount) => amount > 0)
            .sort((a, b) => a - b)
            .slice(0, 6);
    },

    // Check if can process payment
    canProcessPayment(orderData) {
        return (
            orderData.items && orderData.items.length > 0 && orderData.total > 0
        );
    },
};

// Export
export { posApi, posHelpers };
