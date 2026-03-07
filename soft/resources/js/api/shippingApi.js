import axios from "axios";

const apiClient = axios.create({
    baseURL: "/api",
    timeout: 10000,
    headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
    },
});

apiClient.interceptors.request.use((config) => {
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
});

apiClient.interceptors.response.use(
    (response) => response.data,
    (error) => {
        if (error.response?.status === 401) {
            sessionStorage.removeItem("api_token");
            window.location.href = "/force-logout?reason=unauthorized";
        }
        return Promise.reject(error.response?.data || error);
    }
);

export const shippingApi = {
    // Danh sách tất cả đơn vận chuyển
    getAll: (params = {}) => apiClient.get("/shippings", { params }),

    // Chi tiết đơn vận chuyển
    getById: (id) => apiClient.get(`/shippings/${id}`),

    // Thống kê vận chuyển (fix duplicate + correct endpoint)
    // Thống kê vận chuyển (endpoint theo routes/api.php -> /shipping/stats)
    getStats: (params = {}) => apiClient.get("/shipping/stats", { params }),

    // Tạo đơn giao hàng
    createShipping: (orderId, data) =>
        apiClient.post(`/orders/${orderId}/shipping`, data),

    // Lấy thông tin tracking
    getTracking: (orderId) =>
        apiClient.get(`/orders/${orderId}/shipping/tracking`),

    // Cập nhật trạng thái shipping
    updateStatus: (orderId, shippingId, data) =>
        apiClient.patch(
            `/orders/${orderId}/shipping/${shippingId}/status`,
            data
        ),

    // Xác nhận giao hàng
    confirmDelivery: (orderId, shippingId, data) =>
        apiClient.post(
            `/orders/${orderId}/shipping/${shippingId}/confirm-delivery`,
            data
        ),

    // Lấy danh sách providers
    getProviders: () => apiClient.get("/shipping/providers"),
};

export const shippingHelpers = {
    // Format trạng thái
    getStatusText(status) {
        const statusMap = {
            pending: "Chờ lấy hàng",
            picked_up: "Đã lấy hàng",
            in_transit: "Đang vận chuyển",
            delivered: "Đã giao hàng",
            failed: "Giao hàng thất bại",
        };
        return statusMap[status] || status;
    },

    // Format payment by
    getPaymentByText(paymentBy) {
        return paymentBy === "sender" ? "Người gửi" : "Người nhận";
    },

    // Get status color
    getStatusColor(status) {
        const colorMap = {
            pending: "text-yellow-600 bg-yellow-100",
            picked_up: "text-blue-600 bg-blue-100",
            in_transit: "text-indigo-600 bg-indigo-100",
            delivered: "text-green-600 bg-green-100",
            failed: "text-red-600 bg-red-100",
        };
        return colorMap[status] || "text-gray-600 bg-gray-100";
    },

    // Get payment by color
    getPaymentByColor(paymentBy) {
        return paymentBy === "sender"
            ? "text-blue-600 bg-blue-100"
            : "text-orange-600 bg-orange-100";
    },

    // Format currency
    formatCurrency(amount) {
        return new Intl.NumberFormat("vi-VN", {
            style: "currency",
            currency: "VND",
        }).format(amount || 0);
    },

    // Calculate total order cost (including shipping if sender pays)
    calculateTotalCost(order, shipping) {
        if (!shipping) return order.total;

        if (shipping.payment_by === "sender") {
            return (
                parseFloat(order.total) + parseFloat(shipping.shipping_fee || 0)
            );
        }

        return order.total;
    },

    // Check if can confirm delivery
    canConfirmDelivery(shipping) {
        return shipping.status === "in_transit";
    },

    // Check if can update status
    canUpdateStatus(shipping) {
        return ["pending", "picked_up", "in_transit"].includes(shipping.status);
    },
};
