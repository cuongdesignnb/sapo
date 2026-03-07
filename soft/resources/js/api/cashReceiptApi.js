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

// Cash Receipt API methods
const cashReceiptApi = {
    // Lấy danh sách phiếu thu
    getAll: (params = {}) => {
        console.log("📋 Getting cash receipts with params:", params);
        return apiClient.get("/cash-vouchers/receipts", { params });
    },

    // Chi tiết phiếu thu
    getById: (id) => {
        console.log("🔍 Getting cash receipt by ID:", id);
        return apiClient.get(`/cash-vouchers/receipts/${id}`);
    },

    // Tạo phiếu thu mới
    create: (data) => {
        console.log("➕ Creating cash receipt with data:", data);
        return apiClient.post("/cash-vouchers/receipts", data);
    },

    // Cập nhật phiếu thu
    update: (id, data) => {
        console.log("✏️ Updating cash receipt:", id, data);
        return apiClient.put(`/cash-vouchers/receipts/${id}`, data);
    },

    // Gửi duyệt
    submitForApproval: (id) => {
        console.log("📤 Submitting cash receipt for approval:", id);
        return apiClient.patch(`/cash-vouchers/receipts/${id}/submit`);
    },

    // Duyệt phiếu thu
    approve: (id) => {
        console.log("✅ Approving cash receipt:", id);
        return apiClient.patch(`/cash-vouchers/receipts/${id}/approve`);
    },

    // Hủy phiếu thu
    cancel: (id) => {
        console.log("❌ Cancelling cash receipt:", id);
        return apiClient.patch(`/cash-vouchers/receipts/${id}/cancel`);
    },

    // Lấy danh sách đối tượng
    getRecipients: (type) => {
        console.log("👥 Getting recipients for type:", type);
        return apiClient.get("/cash-vouchers/receipts/recipients", {
            params: { type },
        });
    },

    // Lấy loại phiếu thu
    getReceiptTypes: (params = {}) => {
        console.log("📝 Getting receipt types");
        return apiClient.get("/cash-vouchers/receipt-types", { params });
    },

    // Tạo loại phiếu thu mới
    createReceiptType: (data) => {
        console.log("➕ Creating receipt type:", data);
        return apiClient.post("/cash-vouchers/receipt-types", data);
    },

    // Cập nhật loại phiếu thu
    updateReceiptType: (id, data) => {
        console.log("✏️ Updating receipt type:", id, data);
        return apiClient.put(`/cash-vouchers/receipt-types/${id}`, data);
    },

    // Xóa loại phiếu thu
    deleteReceiptType: (id) => {
        console.log("🗑️ Deleting receipt type:", id);
        return apiClient.delete(`/cash-vouchers/receipt-types/${id}`);
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

            const url = `/api/cash-vouchers/receipts/export${
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
                    `Export failed: ${response.status} ${response.statusText}`,
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
            link.download = `danh-sach-phieu-thu-${new Date()
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
};

// Helper functions
const cashReceiptHelpers = {
    // Format trạng thái
    getStatusText(status) {
        const statusMap = {
            draft: "Nháp",
            pending: "Chờ duyệt",
            approved: "Đã duyệt",
            cancelled: "Đã hủy",
        };
        return statusMap[status] || status;
    },

    // Màu sắc Tailwind cho trạng thái
    getStatusClass(status) {
        const classMap = {
            draft: "bg-gray-100 text-gray-800",
            pending: "bg-yellow-100 text-yellow-800",
            approved: "bg-green-100 text-green-800",
            cancelled: "bg-red-100 text-red-800",
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
        });
    },

    // Kiểm tra có thể chỉnh sửa
    canEdit(receipt) {
        return receipt.status === "draft";
    },

    // Kiểm tra có thể gửi duyệt
    canSubmit(receipt) {
        return receipt.status === "draft";
    },

    // Kiểm tra có thể duyệt
    canApprove(receipt) {
        return receipt.status === "pending";
    },

    // Kiểm tra có thể hủy
    canCancel(receipt) {
        return ["draft", "pending"].includes(receipt.status);
    },

    // Kiểm tra có thể in
    canPrint(receipt) {
        return receipt.status === "approved";
    },
};

// Export
export { cashReceiptApi };
export { cashReceiptHelpers };
