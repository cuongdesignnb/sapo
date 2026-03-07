import axios from 'axios';

const API_BASE_URL = '/api/customer-debts';

// Helper function to get headers with Bearer token
const getHeaders = () => {
    const headers = {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    };
    
    // Add CSRF token
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (csrfToken) {
        headers['X-CSRF-TOKEN'] = csrfToken;
    }
    
    // Add Bearer token (same as category API)
    let accessToken = sessionStorage.getItem('api_token');
    if (!accessToken) {
        accessToken = document.querySelector('meta[name="api-token"]')?.getAttribute('content');
    }
    
    if (accessToken) {
        headers['Authorization'] = `Bearer ${accessToken}`;
    }
    
    return headers;
};

class CustomerDebtApi {
    /**
     * Get all customer debts with filters
     */
    async getAll(params = {}) {
        try {
            const response = await axios.get(API_BASE_URL, { 
                params, 
                headers: getHeaders() 
            });
            return response.data;
        } catch (error) {
            throw this.handleError(error);
        }
    }

    /**
     * Get customer debt by ID
     */
    async getById(id) {
        try {
            const response = await axios.get(`${API_BASE_URL}/${id}`, { 
                headers: getHeaders() 
            });
            return response.data;
        } catch (error) {
            throw this.handleError(error);
        }
    }

    /**
     * Create new customer debt
     */
    async create(data) {
        try {
            const response = await axios.post(API_BASE_URL, data, { 
                headers: getHeaders() 
            });
            return response.data;
        } catch (error) {
            throw this.handleError(error);
        }
    }

    /**
     * Update customer debt
     */
    async update(id, data) {
        try {
            const response = await axios.put(`${API_BASE_URL}/${id}`, data, { 
                headers: getHeaders() 
            });
            return response.data;
        } catch (error) {
            throw this.handleError(error);
        }
    }

    /**
     * Delete customer debt
     */
    async delete(id) {
        try {
            const response = await axios.delete(`${API_BASE_URL}/${id}`, { 
                headers: getHeaders() 
            });
            return response.data;
        } catch (error) {
            throw this.handleError(error);
        }
    }

    /**
     * Bulk delete customer debts
     */
    async bulkDelete(ids) {
        try {
            const response = await axios.delete(`${API_BASE_URL}/bulk/delete`, {
                data: { ids },
                headers: getHeaders()
            });
            return response.data;
        } catch (error) {
            throw this.handleError(error);
        }
    }

    /**
     * Create payment transaction
     */
    async addPayment(data) {
        try {
            const response = await axios.post(`${API_BASE_URL}/payment`, data, { 
                headers: getHeaders() 
            });
            return response.data;
        } catch (error) {
            throw this.handleError(error);
        }
    }

    /**
     * Create adjustment transaction
     */
    async addAdjustment(data) {
        try {
            const response = await axios.post(`${API_BASE_URL}/adjustment`, data, { 
                headers: getHeaders() 
            });
            return response.data;
        } catch (error) {
            throw this.handleError(error);
        }
    }

    /**
     * Get debt summary for customer
     */
    async getCustomerSummary(customerId) {
        try {
            const response = await axios.get(`${API_BASE_URL}/customer/summary`, {
                params: { customer_id: customerId },
                headers: getHeaders()
            });
            return response.data;
        } catch (error) {
            throw this.handleError(error);
        }
    }

    /**
     * Get debt timeline for customer
     */
    async getCustomerTimeline(customerId, limit = 50) {
        try {
            const response = await axios.get(`${API_BASE_URL}/customer/timeline`, {
                params: { customer_id: customerId, limit },
                headers: getHeaders()
            });
            return response.data;
        } catch (error) {
            throw this.handleError(error);
        }
    }

    /**
     * Get customers with debt
     */
    async getCustomersWithDebt() {
        try {
            const response = await axios.get(`${API_BASE_URL}/customers/with-debt`, { 
                headers: getHeaders() 
            });
            return response.data;
        } catch (error) {
            throw this.handleError(error);
        }
    }

    /**
     * Get statistics
     */
    async getStatistics(params = {}) {
        try {
            const response = await axios.get(`${API_BASE_URL}/reports/statistics`, { 
                params, 
                headers: getHeaders() 
            });
            return response.data;
        } catch (error) {
            throw this.handleError(error);
        }
    }

    /**
     * Export data
     */
    async export(filters = {}) {
        try {
            const response = await axios.post(`${API_BASE_URL}/export`, filters, { 
                headers: getHeaders() 
            });
            return response.data;
        } catch (error) {
            throw this.handleError(error);
        }
    }

    /**
     * Import data
     */
    async import(data) {
        try {
            const response = await axios.post(`${API_BASE_URL}/import`, { data }, { 
                headers: getHeaders() 
            });
            return response.data;
        } catch (error) {
            throw this.handleError(error);
        }
    }

    /**
     * Search customers for debt operations
     */
    async searchCustomers(query) {
        try {
            const response = await axios.get('/api/customers', {
                params: { search: query, per_page: 20 },
                headers: getHeaders()
            });
            return response.data;
        } catch (error) {
            throw this.handleError(error);
        }
    }

    /**
     * Search orders for reference
     */
    async searchOrders(query) {
        try {
            const response = await axios.get('/api/orders', {
                params: { search: query, per_page: 20 },
                headers: getHeaders()
            });
            return response.data;
        } catch (error) {
            throw this.handleError(error);
        }
    }

    /**
     * Validate debt data before submit
     */
    validateDebtData(data) {
        const errors = {};

        if (!data.customer_id) {
            errors.customer_id = 'Khách hàng là bắt buộc';
        }

        if (!data.amount || isNaN(data.amount)) {
            errors.amount = 'Số tiền phải là số hợp lệ';
        }

        if (data.amount === 0) {
            errors.amount = 'Số tiền phải khác 0';
        }

        if (data.recorded_at && !this.isValidDate(data.recorded_at)) {
            errors.recorded_at = 'Ngày ghi nhận không hợp lệ';
        }

        return {
            isValid: Object.keys(errors).length === 0,
            errors
        };
    }

    /**
     * Format amount for display
     */
    formatAmount(amount) {
        if (amount === null || amount === undefined) return '0 VNĐ';
        
        const prefix = amount > 0 ? '+' : '';
        return prefix + new Intl.NumberFormat('vi-VN').format(Math.abs(amount)) + ' VNĐ';
    }

    /**
     * Get transaction type text
     */
    getTransactionType(amount) {
        return amount > 0 ? 'Nợ' : 'Thanh toán';
    }

    /**
     * Get status color class
     */
    getStatusColorClass(amount) {
        return amount > 0 ? 'text-red-600' : 'text-green-600';
    }

    /**
     * Get badge color class
     */
    getBadgeColorClass(amount) {
        return amount > 0 ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800';
    }

    /**
     * Calculate debt summary from transactions
     */
    calculateDebtSummary(transactions) {
        if (!Array.isArray(transactions)) return null;

        const summary = transactions.reduce((acc, transaction) => {
            if (transaction.amount > 0) {
                acc.totalDebt += transaction.amount;
                acc.debtCount += 1;
            } else {
                acc.totalPaid += Math.abs(transaction.amount);
                acc.paymentCount += 1;
            }
            return acc;
        }, {
            totalDebt: 0,
            totalPaid: 0,
            debtCount: 0,
            paymentCount: 0
        });

        summary.currentBalance = summary.totalDebt - summary.totalPaid;
        summary.totalTransactions = summary.debtCount + summary.paymentCount;

        return summary;
    }

    /**
     * Generate auto ref code
     */
    generateRefCode(prefix = 'CD') {
        const timestamp = new Date().toISOString().replace(/[-:T.]/g, '').slice(0, 14);
        const random = Math.floor(Math.random() * 999) + 100;
        return `${prefix}${timestamp}${random}`;
    }

    /**
     * Check if date is valid
     */
    isValidDate(dateString) {
        const date = new Date(dateString);
        return date instanceof Date && !isNaN(date);
    }

    /**
     * Format date for display
     */
    formatDate(dateString) {
        if (!dateString) return '';
        const date = new Date(dateString);
        return date.toLocaleDateString('vi-VN', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    /**
     * Export to CSV
     */
    exportToCSV(data, filename = 'customer-debts') {
        if (!Array.isArray(data) || data.length === 0) {
            throw new Error('Không có dữ liệu để xuất');
        }

        const headers = Object.keys(data[0]);
        const csvContent = [
            headers.join(','),
            ...data.map(row => headers.map(header => 
                `"${(row[header] || '').toString().replace(/"/g, '""')}"`
            ).join(','))
        ].join('\n');

        const blob = new Blob(['\uFEFF' + csvContent], { 
            type: 'text/csv;charset=utf-8;' 
        });
        
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = `${filename}-${new Date().toISOString().slice(0, 10)}.csv`;
        link.click();
        
        URL.revokeObjectURL(link.href);
    }

    /**
     * Handle API errors
     */
    handleError(error) {
        if (error.response) {
            // Server responded with error status
            const { status, data } = error.response;
            
            switch (status) {
                case 400:
                    return {
                        message: data.message || 'Dữ liệu không hợp lệ',
                        errors: data.errors || {},
                        status
                    };
                case 401:
                    return {
                        message: 'Bạn không có quyền thực hiện thao tác này',
                        status
                    };
                case 403:
                    return {
                        message: 'Truy cập bị từ chối',
                        status
                    };
                case 404:
                    return {
                        message: 'Không tìm thấy dữ liệu',
                        status
                    };
                case 422:
                    return {
                        message: data.message || 'Dữ liệu không hợp lệ',
                        errors: data.errors || {},
                        status
                    };
                case 500:
                    return {
                        message: 'Lỗi máy chủ, vui lòng thử lại sau',
                        status
                    };
                default:
                    return {
                        message: data.message || 'Có lỗi xảy ra',
                        status
                    };
            }
        } else if (error.request) {
            // Network error
            return {
                message: 'Không thể kết nối đến máy chủ',
                status: 0
            };
        } else {
            // Other errors
            return {
                message: error.message || 'Có lỗi không xác định',
                status: -1
            };
        }
    }
}

// Export singleton instance
export default new CustomerDebtApi();