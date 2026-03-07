import axios from "axios";
import { attachAuthInterceptors } from "@/api/authInterceptor";

const apiClient = axios.create({
    baseURL: "/api",
    timeout: 10000,
    headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
    },
});

apiClient.interceptors.request.use(
    (config) => {
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
    },
    (error) => Promise.reject(error),
);

attachAuthInterceptors(apiClient);
apiClient.interceptors.response.use((r) => r.data);

const productSerialApi = {
    /**
     * Danh sách serial với filter
     * @param {Object} params - { product_id, warehouse_id, status, search, per_page, page }
     */
    getAll(params = {}) {
        return apiClient.get("/product-serials", { params });
    },

    /**
     * Chi tiết serial
     */
    getById(id) {
        return apiClient.get(`/product-serials/${id}`);
    },

    /**
     * Lịch sử serial
     */
    getHistory(id, params = {}) {
        return apiClient.get(`/product-serials/${id}/history`, { params });
    },

    /**
     * Lấy danh sách serial khả dụng (in_stock) cho một sản phẩm trong kho
     * @param {number} productId
     * @param {number} warehouseId
     */
    getAvailable(productId, warehouseId) {
        return apiClient.get("/product-serials/available", {
            params: { product_id: productId, warehouse_id: warehouseId },
        });
    },

    /**
     * Tra cứu serial nhanh
     */
    lookup(serialNumber) {
        return apiClient.get("/product-serials/lookup", {
            params: { serial_number: serialNumber },
        });
    },

    /**
     * Nhập serial hàng loạt
     * @param {Object} data - { product_id, warehouse_id, serial_numbers[], cost_price, purchase_receipt_item_id, note }
     */
    bulkImport(data) {
        return apiClient.post("/product-serials/bulk-import", data);
    },

    /**
     * Cập nhật serial
     */
    update(id, data) {
        return apiClient.put(`/product-serials/${id}`, data);
    },

    /**
     * Xóa serial (chỉ in_stock)
     */
    delete(id) {
        return apiClient.delete(`/product-serials/${id}`);
    },
};

export { productSerialApi };
export default productSerialApi;
