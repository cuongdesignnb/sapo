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
    (error) => Promise.reject(error)
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
    }
);

// Cash Ledger API methods
const cashLedgerApi = {
    // Lấy sổ quỹ
    getAll: (params = {}) => {
        console.log("📋 Getting cash ledger with params:", params);
        return apiClient.get("/cash-vouchers/ledger", { params });
    },

    // Lấy thống kê tổng hợp
    getSummary: (params = {}) => {
        console.log("📊 Getting cash ledger summary with params:", params);
        return apiClient.get("/cash-vouchers/ledger/summary", { params });
    },

    // Export Excel
    export: async (params = {}) => {
        try {
            console.log("📤 Starting ledger export with params:", params);

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

            const url = `/api/cash-vouchers/ledger/export${
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
            link.download = `so-quy-${new Date()
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
const cashLedgerHelpers = {
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

    // Format ngày giờ
    formatDateTime(date) {
        return new Date(date).toLocaleDateString("vi-VN", {
            year: "numeric",
            month: "2-digit",
            day: "2-digit",
            hour: "2-digit",
            minute: "2-digit",
        });
    },

    // Lấy tên loại giao dịch
    getTransactionTypeText(type) {
        return type === "receipt" ? "Phiếu thu" : "Phiếu chi";
    },

    // Lấy màu cho loại giao dịch
    getTransactionTypeClass(type) {
        return type === "receipt"
            ? "bg-green-100 text-green-800"
            : "bg-red-100 text-red-800";
    },

    // Format số dư running
    formatBalance(balance) {
        const formatted = this.formatCurrency(Math.abs(balance));
        return balance >= 0 ? formatted : `(${formatted})`;
    },

    // Lấy màu cho số dư
    getBalanceClass(balance) {
        return balance >= 0 ? "text-green-600" : "text-red-600";
    },
};

// Export
export { cashLedgerApi };
export { cashLedgerHelpers };
