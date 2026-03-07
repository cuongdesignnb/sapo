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

export const shippingProviderApi = {
    // Lấy danh sách providers
    getAll: (params = {}) => {
        return apiClient.get("/shipping-providers", { params });
    },

    // Chi tiết provider
    getById: (id) => {
        return apiClient.get(`/shipping-providers/${id}`);
    },

    // Tạo provider mới
    create: (data) => {
        return apiClient.post("/shipping-providers", data);
    },

    // Cập nhật provider
    update: (id, data) => {
        return apiClient.put(`/shipping-providers/${id}`, data);
    },

    // Xóa provider
    delete: (id) => {
        return apiClient.delete(`/shipping-providers/${id}`);
    },

    // Xóa nhiều providers
    bulkDelete: (ids) => {
        return apiClient.post("/shipping-providers/bulk-delete", { ids });
    },

    // Toggle trạng thái
    toggleStatus: (id) => {
        return apiClient.patch(`/shipping-providers/${id}/toggle-status`);
    },

    // Thống kê
    getStats: () => {
        return apiClient.get("/shipping-providers/stats");
    },
};

export default shippingProviderApi;
