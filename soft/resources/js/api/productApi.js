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

// Base API client cho các request thông thường
const apiClient = axios.create({
    baseURL: "/api",
    timeout: 10000,
    headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
    },
});

// Request interceptor cho apiClient
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

// Attach shared auth interceptor (handles 401/419 once)
attachAuthInterceptors(apiClient);
// Keep lightweight data unwrap
apiClient.interceptors.response.use((r) => r.data);

// Product API methods
const productApi = {
    // Get all products with filters and pagination
    getAll: (params = {}) => {
        console.log("📋 Getting products with params:", params);
        return apiClient.get("/products", { params });
    },

    // Get single product
    getById: (id) => {
        console.log("🔍 Getting product by ID:", id);
        return apiClient.get(`/products/${id}`);
    },
    getStockHistory: (productId, params = {}) => {
        console.log("📊 Getting stock history for product:", productId, params);
        return apiClient.get(`/products/stock-history/${productId}`, {
            params,
        });
    },
    getStockInfo: (id) => {
        console.log("📦 Getting stock info for product:", id);
        return apiClient.get(`/products/stock-info/${id}`);
    },

    // Create new product
    create: (data) => {
        console.log("➕ Creating product with data:", data);
        return apiClient.post("/products", data);
    },

    // Update product
    update: (id, data) => {
        console.log("📝 Updating product:", id, data);
        return apiClient.put(`/products/${id}`, data);
    },

    // Delete product
    delete: (id) => {
        console.log("🗑️ Deleting product:", id);
        return apiClient.delete(`/products/${id}`);
    },

    // Bulk delete products
    bulkDelete: (ids) => {
        console.log("🗑️ Bulk deleting products:", ids);
        return apiClient.post("/products/bulk-delete", { ids });
    },

    // Import products from Excel/CSV
    import: async (file) => {
        try {
            console.log("📥 Importing products from file:", file.name);

            const formData = new FormData();
            formData.append("file", file);

            const headers = getAuthHeaders();
            headers["Content-Type"] = "multipart/form-data";

            const response = await axios.post(
                "/api/products/import",
                formData,
                {
                    headers: headers,
                    timeout: 60000, // Longer timeout for import
                }
            );

            console.log("✅ Import response:", response.data);
            return response.data;
        } catch (error) {
            console.error("❌ Import failed:", error);
            throw error;
        }
    },

    // Export products to CSV
    export: async (filters = {}) => {
        try {
            console.log("📤 Starting export with filters:", filters);

            const headers = getAuthHeaders();
            delete headers["Content-Type"]; // Remove for file download

            // Build query string for filters
            const queryParams = new URLSearchParams();
            Object.keys(filters).forEach((key) => {
                if (
                    filters[key] !== "" &&
                    filters[key] !== null &&
                    filters[key] !== undefined
                ) {
                    if (Array.isArray(filters[key])) {
                        queryParams.append(key, filters[key].join(","));
                    } else {
                        queryParams.append(key, filters[key]);
                    }
                }
            });

            const url = `/api/products/export${
                queryParams.toString() ? "?" + queryParams.toString() : ""
            }`;

            console.log("🔗 Export URL:", url);
            console.log("🔐 Headers:", headers);

            // Make fetch request
            const response = await fetch(url, {
                method: "GET",
                headers: headers,
            });

            console.log("📊 Export response status:", response.status);
            console.log(
                "📄 Content-Type:",
                response.headers.get("content-type")
            );

            if (!response.ok) {
                const errorText = await response.text();
                console.error("Export error response:", errorText);
                throw new Error(
                    `Export failed: ${response.status} ${response.statusText}`
                );
            }

            // Get blob and create download
            const blob = await response.blob();
            console.log("📦 Export blob size:", blob.size, "bytes");

            if (blob.size === 0) {
                throw new Error("Export file is empty");
            }

            // Create download link
            const downloadUrl = window.URL.createObjectURL(blob);
            const link = document.createElement("a");
            link.href = downloadUrl;
            link.download = `products-export-${new Date()
                .toISOString()
                .slice(0, 10)}.csv`;
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

    // Download import template
    downloadTemplate: async () => {
        try {
            console.log("📥 Downloading import template");

            const headers = getAuthHeaders();
            delete headers["Content-Type"]; // Remove for file download

            const response = await fetch("/api/products/import-template", {
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
            link.download = "products_import_template.csv";
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

    // Get product statistics
    getStatistics: () => {
        console.log("📊 Getting product statistics");
        return apiClient.get("/products/statistics");
    },

    // Search products
    search: (query, params = {}) => {
        console.log("🔍 Searching products:", query);
        return apiClient.get("/products", {
            params: { search: query, ...params },
        });
    },

    // Get low stock products
    getLowStock: (params = {}) => {
        console.log("⚠️ Getting low stock products");
        return apiClient.get("/products/low-stock", { params });
    },
};

// Master data API
const masterDataApi = {
    // Get categories for dropdown
    getCategories: () => {
        console.log("📂 Getting categories");
        return apiClient.get("/categories");
    },

    // Get brands for dropdown
    getBrands: () => {
        console.log("🏷️ Getting brands");
        return apiClient.get("/brands");
    },

    // Get suppliers for dropdown
    getSuppliers: () => {
        console.log("🏢 Getting suppliers");
        return apiClient.get("/suppliers");
    },

    // Get units for dropdown
    getUnits: () => {
        console.log("📏 Getting units");
        return apiClient.get("/units");
    },
};

// Export các objects
export { productApi, masterDataApi };

// Export default
export default apiClient;
