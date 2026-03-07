// API cho Order Returns (Khách hàng trả hàng)
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

    // Add Bearer token - check sessionStorage first, then meta tag
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

// Base API client for order returns
const orderReturnApiClient = axios.create({
    baseURL: "/api",
    timeout: 10000,
    headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
    },
});

// Request interceptor
orderReturnApiClient.interceptors.request.use(
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

// Response interceptor
orderReturnApiClient.interceptors.response.use(
    (response) => response.data,
    (error) => {
        console.error(
            "Order Return API Error:",
            error.response?.data || error.message
        );

        // Handle 401 unauthorized
        if (error.response?.status === 401) {
            console.log("Token expired, redirecting to login...");
            sessionStorage.removeItem("api_token");
            window.location.href = "/force-logout?reason=unauthorized";
            return Promise.reject(new Error("Session expired"));
        }

        return Promise.reject(error.response?.data || error);
    }
);

export const orderReturnApi = {
    // Lấy danh sách đơn trả hàng
    async getList(params = {}) {
        try {
            console.log("📋 Getting order returns with params:", params);
            return orderReturnApiClient.get("/order-returns", { params });
        } catch (error) {
            console.error("❌ Error getting order returns:", error);
            throw error;
        }
    },

    // Lấy chi tiết đơn trả hàng
    async getDetail(id) {
        try {
            console.log("🔍 Getting order return detail:", id);
            return orderReturnApiClient.get(`/order-returns/${id}`);
        } catch (error) {
            console.error("❌ Error getting order return detail:", error);
            throw error;
        }
    },

    // Tạo đơn trả hàng từ đơn hàng
    async create(orderId, data) {
        try {
            console.log("➕ Creating order return for order:", orderId, data);
            return orderReturnApiClient.post(
                `/orders/${orderId}/returns`,
                data
            );
        } catch (error) {
            console.error("❌ Error creating order return:", error);
            throw error;
        }
    },

    // Nhận hàng trả về
    async receive(id) {
        try {
            console.log("📦 Receiving order return:", id);
            return orderReturnApiClient.post(
                `/order-returns/${id}/receive`,
                {}
            );
        } catch (error) {
            console.error("❌ Error receiving order return:", error);
            throw error;
        }
    },

    // Nhập kho hàng trả về
    async warehouse(id) {
        try {
            console.log("🏠 Warehousing order return:", id);
            return orderReturnApiClient.post(
                `/order-returns/${id}/warehouse`,
                {}
            );
        } catch (error) {
            console.error("❌ Error warehousing order return:", error);
            throw error;
        }
    },

    // Hoàn tiền
    async refund(id, data) {
        try {
            console.log("💰 Refunding order return:", id, data);
            return orderReturnApiClient.post(
                `/order-returns/${id}/refund`,
                data
            );
        } catch (error) {
            console.error("❌ Error refunding order return:", error);
            throw error;
        }
    },

    // Hủy đơn trả hàng
    async cancel(id) {
        try {
            console.log("❌ Canceling order return:", id);
            return orderReturnApiClient.post(`/order-returns/${id}/cancel`, {});
        } catch (error) {
            console.error("❌ Error canceling order return:", error);
            throw error;
        }
    },
};

// Helper functions
export const orderReturnHelpers = {
    // Lấy màu trạng thái
    getStatusColor(status) {
        const colors = {
            pending: "bg-yellow-100 text-yellow-800", // Chưa nhận hàng
            received: "bg-blue-100 text-blue-800", // Đã nhận hàng
            warehoused: "bg-purple-100 text-purple-800", // Đã nhập kho
            refunded: "bg-green-100 text-green-800", // Đã hoàn tiền
            cancelled: "bg-red-100 text-red-800", // Đã hủy
        };
        return colors[status] || "bg-gray-100 text-gray-800";
    },

    // Lấy text trạng thái
    getStatusText(status) {
        const texts = {
            pending: "Chưa nhận hàng",
            received: "Đã nhận hàng",
            warehoused: "Đã nhập kho",
            refunded: "Đã hoàn tiền",
            cancelled: "Đã hủy",
        };
        return texts[status] || status;
    },

    // Kiểm tra có thể nhận hàng không
    canReceive(orderReturn) {
        return orderReturn.status === "pending";
    },

    // Kiểm tra có thể duyệt không
    canApprove(orderReturn) {
        return orderReturn.status === "pending";
    },

    // Kiểm tra có thể nhập kho không
    canWarehouse(orderReturn) {
        return orderReturn.status === "received";
    },

    // Kiểm tra có thể hoàn tiền không
    canRefund(orderReturn) {
        return (
            orderReturn.status === "warehoused" &&
            orderReturn.total - orderReturn.refunded > 0
        );
    },

    // Kiểm tra có thể hủy không
    canCancel(orderReturn) {
        return ["pending", "received"].includes(orderReturn.status);
    },

    // Tính số tiền còn phải hoàn (hoặc giảm nợ)
    getRemainingRefund(orderReturn) {
        const returnAmount = orderReturn.total - orderReturn.refunded;
        const currentDebt = orderReturnHelpers.getCurrentDebt(orderReturn);

        // Nếu order vẫn còn nợ, thì chỉ giảm nợ (không hoàn tiền thực)
        if (currentDebt > 0) {
            return Math.min(returnAmount, currentDebt);
        }

        // Nếu đã thanh toán đủ, hoàn tiền bình thường
        return returnAmount;
    },

    // Kiểm tra đơn hàng đã thanh toán chưa
    isOrderFullyPaid(orderReturn) {
        const currentDebt = orderReturnHelpers.getCurrentDebt(orderReturn);
        return currentDebt <= 0;
    },

    // Lấy nợ hiện tại từ CustomerDebt
    getCurrentDebt(orderReturn) {
        if (!orderReturn.order || !orderReturn.order.customer_debt) return 0;
        return parseFloat(orderReturn.order.customer_debt.debt_total || 0);
    },

    // Lấy label cho refund (hoàn tiền vs giảm nợ)
    getRefundLabel(orderReturn) {
        const currentDebt = orderReturnHelpers.getCurrentDebt(orderReturn);
        if (currentDebt > 0) {
            return "Giảm nợ";
        }
        return "Hoàn tiền";
    },

    // Tính nợ còn lại sau khi trả hàng
    getRemainingDebtAfterReturn(orderReturn) {
        if (!orderReturn.order) return 0;

        const currentDebt = orderReturnHelpers.getCurrentDebt(orderReturn);
        const returnAmount = orderReturn.total - orderReturn.refunded;

        // Nếu đã thanh toán đủ, không còn nợ
        if (currentDebt <= 0) return 0;

        // Nợ còn lại = nợ hiện tại - số tiền có thể giảm nợ
        return Math.max(0, currentDebt - returnAmount);
    },

    // Lấy class CSS cho nợ còn lại
    getRemainingDebtClass(orderReturn) {
        const remainingDebt =
            orderReturnHelpers.getRemainingDebtAfterReturn(orderReturn);
        if (remainingDebt <= 0) return "text-green-600"; // Không còn nợ
        return "text-red-600"; // Còn nợ
    },

    // Format currency
    formatCurrency(amount) {
        return new Intl.NumberFormat("vi-VN", {
            style: "currency",
            currency: "VND",
        }).format(amount);
    },

    // Format date
    formatDate(date) {
        return new Date(date).toLocaleString("vi-VN");
    },
};

export default orderReturnApi;
