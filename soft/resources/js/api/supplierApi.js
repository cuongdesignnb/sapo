// =================================================================
// File: resources/js/api/supplierApi.js
// =================================================================

import axios from "axios";

const API_BASE = "/api";

// Helper function to get headers with Bearer token
const getHeaders = () => {
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

    // Add Bearer token (same as category API)
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

export default {
    // Basic CRUD
    getSuppliers: (params = {}) =>
        axios.get(`${API_BASE}/suppliers`, {
            params,
            headers: getHeaders(),
        }),

    getSupplier: (id) =>
        axios.get(`${API_BASE}/suppliers/${id}`, {
            headers: getHeaders(),
        }),

    createSupplier: (data) =>
        axios.post(`${API_BASE}/suppliers`, data, {
            headers: getHeaders(),
        }),

    updateSupplier: (id, data) =>
        axios.put(`${API_BASE}/suppliers/${id}`, data, {
            headers: getHeaders(),
        }),

    deleteSupplier: (id) =>
        axios.delete(`${API_BASE}/suppliers/${id}`, {
            headers: getHeaders(),
        }),

    bulkDelete: (ids) =>
        axios.delete(`${API_BASE}/suppliers`, {
            data: { ids },
            headers: getHeaders(),
        }),

    // Import/Export
    importSuppliers: (file) => {
        const formData = new FormData();
        formData.append("file", file);

        const headers = getHeaders();
        headers["Content-Type"] = "multipart/form-data";

        return axios.post(`${API_BASE}/suppliers/import`, formData, {
            headers,
        });
    },

    exportSuppliers: (params = {}) => {
        const queryString = new URLSearchParams(params).toString();
        const token =
            sessionStorage.getItem("api_token") ||
            document
                .querySelector('meta[name="api-token"]')
                ?.getAttribute("content");

        // Add token to export URL
        const separator = queryString ? "&" : "";
        const tokenParam = token ? `${separator}token=${token}` : "";

        window.open(
            `${API_BASE}/suppliers/export?${queryString}${tokenParam}`,
            "_blank"
        );
    },

    // Options & Groups
    getSupplierOptions: (search = "") =>
        axios.get(`${API_BASE}/suppliers/options/list`, {
            params: { search },
            headers: getHeaders(),
        }),

    getSupplierGroups: () =>
        axios.get(`${API_BASE}/suppliers/groups/list`, {
            headers: getHeaders(),
        }),

    // Statistics
    getStatistics: () =>
        axios.get(`${API_BASE}/suppliers/stats/overview`, {
            headers: getHeaders(),
        }),

    // Debt Management
    addDebt: (supplierId, data) =>
        axios.post(`${API_BASE}/suppliers/${supplierId}/debts`, data, {
            headers: getHeaders(),
        }),

    getDebtHistory: (supplierId, params = {}) =>
        axios.get(`${API_BASE}/suppliers/${supplierId}/debts`, {
            params,
            headers: getHeaders(),
        }),

    // Purchase History - NEW
    getPurchaseHistory: (supplierId, params = {}) =>
        axios.get(`${API_BASE}/suppliers/${supplierId}/purchase-history`, {
            params,
            headers: getHeaders(),
        }),

    exportPurchaseHistory: (supplierId, params = {}) => {
        const queryString = new URLSearchParams(params).toString();

        // Get headers for authentication
        const headers = getHeaders();

        // For file download, we need to use fetch with blob
        const url = `${API_BASE}/suppliers/${supplierId}/purchase-history/export?${queryString}`;

        return fetch(url, {
            method: "GET",
            headers: headers,
            credentials: "same-origin",
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.blob();
            })
            .then((blob) => {
                // Create download link
                const url = window.URL.createObjectURL(blob);
                const link = document.createElement("a");
                link.href = url;
                link.download = `lich-su-nhap-hang-${supplierId}-${
                    new Date().toISOString().split("T")[0]
                }.csv`;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                window.URL.revokeObjectURL(url);

                return { success: true };
            });
    },

    // Group Management
    getGroups: (params = {}) =>
        axios.get(`${API_BASE}/supplier-groups`, {
            params,
            headers: getHeaders(),
        }),

    createGroup: (data) =>
        axios.post(`${API_BASE}/supplier-groups`, data, {
            headers: getHeaders(),
        }),

    updateGroup: (id, data) =>
        axios.put(`${API_BASE}/supplier-groups/${id}`, data, {
            headers: getHeaders(),
        }),

    deleteGroup: (id) =>
        axios.delete(`${API_BASE}/supplier-groups/${id}`, {
            headers: getHeaders(),
        }),

    // Thêm các methods khác nếu cần...
};

// Named exports để dùng trong component
export const getPurchaseHistory = async (supplierId, params = {}) => {
    try {
        const response = await axios.get(
            `${API_BASE}/suppliers/${supplierId}/purchase-history`,
            {
                params,
                headers: getHeaders(),
            }
        );
        return response.data;
    } catch (error) {
        console.error("Error fetching supplier purchase history:", error);
        throw error;
    }
};

export const exportPurchaseHistory = async (supplierId, params = {}) => {
    try {
        const queryString = new URLSearchParams(params).toString();

        // Get headers for authentication
        const headers = getHeaders();

        // For file download, we need to use fetch with blob
        const url = `${API_BASE}/suppliers/${supplierId}/purchase-history/export?${queryString}`;

        const response = await fetch(url, {
            method: "GET",
            headers: headers,
            credentials: "same-origin",
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const blob = await response.blob();

        // Create download link
        const downloadUrl = window.URL.createObjectURL(blob);
        const link = document.createElement("a");
        link.href = downloadUrl;
        link.download = `lich-su-nhap-hang-${supplierId}-${
            new Date().toISOString().split("T")[0]
        }.csv`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(downloadUrl);

        return { success: true };
    } catch (error) {
        console.error("Error exporting supplier purchase history:", error);
        throw error;
    }
};
