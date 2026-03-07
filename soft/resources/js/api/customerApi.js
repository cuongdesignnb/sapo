import axios from 'axios'

// Base API client (sử dụng lại từ productApi)
const apiClient = axios.create({
    baseURL: '/api',
    timeout: 10000,
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    }
})

// Request interceptor
apiClient.interceptors.request.use(
    config => {
        // CSRF token từ meta
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        if (csrfToken) {
            config.headers['X-CSRF-TOKEN'] = csrfToken
        }
        
        // ✅ Bearer token từ sessionStorage hoặc meta
        let accessToken = sessionStorage.getItem('api_token')
        if (!accessToken) {
            accessToken = document.querySelector('meta[name="api-token"]')?.getAttribute('content')
        }
        
        if (accessToken) {
            config.headers['Authorization'] = `Bearer ${accessToken}`
        }
        
        return config
    },
    error => Promise.reject(error)
)

// Response interceptor
apiClient.interceptors.response.use(
    response => response.data,
    error => {
        console.error('API Error:', error.response?.data || error.message)
        return Promise.reject(error.response?.data || error)
    }
)

// Download client for file downloads
const downloadClient = axios.create({
    baseURL: '/api',
    timeout: 30000,
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    }
})

// Download client interceptor
downloadClient.interceptors.request.use(
    config => {
        // CSRF token từ meta
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        if (csrfToken) {
            config.headers['X-CSRF-TOKEN'] = csrfToken
        }
        
        // ✅ Bearer token từ sessionStorage hoặc meta
        let accessToken = sessionStorage.getItem('api_token')
        if (!accessToken) {
            accessToken = document.querySelector('meta[name="api-token"]')?.getAttribute('content')
        }
        
        if (accessToken) {
            config.headers['Authorization'] = `Bearer ${accessToken}`
        }
        
        return config
    },
    error => Promise.reject(error)
)

// Download response interceptor
downloadClient.interceptors.response.use(
    response => response,
    error => {
        console.error('Download API Error:', error.response?.data || error.message)
        return Promise.reject(error.response?.data || error)
    }
)

// Customer API methods
export const customerApi = {
    /**
     * Get customers list with search, filter, pagination
     */
    getCustomers: (params = {}) => {
        return apiClient.get('/customers', { params })
    },

    /**
     * Get customer by ID
     */
    getCustomer: (id) => {
        return apiClient.get(`/customers/${id}`)
    },

    /**
     * Get data for creating new customer
     */
    getCreateData: () => {
        return apiClient.get('/customers/create')
    },

    /**
     * Create new customer
     */
    createCustomer: (data) => {
        return apiClient.post('/customers', data)
    },

    /**
     * Get data for editing customer
     */
    getEditData: (id) => {
        return apiClient.get(`/customers/${id}/edit`)
    },

    /**
     * Update customer
     */
    updateCustomer: (id, data) => {
        return apiClient.put(`/customers/${id}`, data)
    },

    /**
     * Delete customer
     */
    deleteCustomer: (id) => {
        return apiClient.delete(`/customers/${id}`)
    },

    /**
     * Bulk delete customers
     */
    bulkDeleteCustomers: (ids) => {
        return apiClient.post('/customers/bulk-delete', { ids })
    },

    /**
     * Export customers to CSV
     */
    exportCustomers: async (params = {}) => {
        try {
            const response = await downloadClient.get('/customers/export/csv', {
                params,
                responseType: 'blob'
            })
            return response
        } catch (error) {
            console.error('Export error:', error)
            throw error
        }
    },

    /**
     * Import customers from CSV
     */
    importCustomers: (file) => {
        const formData = new FormData()
        formData.append('file', file)
        return apiClient.post('/customers/import', formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        })
    },

    /**
     * Get customer statistics
     */
    getStatistics: () => {
        return apiClient.get('/customers/stats/overview')
    },

    /**
     * Search customers
     */
    searchCustomers: (query, params = {}) => {
        const searchParams = {
            search: query,
            ...params
        }
        return apiClient.get('/customers', { params: searchParams })
    },

    /**
     * Filter customers by group
     */
    filterByGroup: (groupId, params = {}) => {
        const filterParams = {
            group_id: groupId,
            ...params
        }
        return apiClient.get('/customers', { params: filterParams })
    },

    /**
     * Filter customers by status
     */
    filterByStatus: (status, params = {}) => {
        const filterParams = {
            status: status,
            ...params
        }
        return apiClient.get('/customers', { params: filterParams })
    },

    /**
     * Sort customers
     */
    sortCustomers: (sortField, sortOrder = 'asc', params = {}) => {
        const sortParams = {
            sort_field: sortField,
            sort_order: sortOrder,
            ...params
        }
        return apiClient.get('/customers', { params: sortParams })
    },

    /**
     * Get customers with pagination
     */
    getCustomersPage: (page = 1, perPage = 20, params = {}) => {
        const pageParams = {
            page: page,
            per_page: perPage,
            ...params
        }
        return apiClient.get('/customers', { params: pageParams })
    },

    /**
     * Download CSV file
     */
    downloadCSV: (filename) => {
        const link = document.createElement('a')
        link.href = `/storage/exports/${filename}`
        link.download = filename
        document.body.appendChild(link)
        link.click()
        document.body.removeChild(link)
    },

    /**
     * Format currency for display
     */
    formatCurrency: (amount) => {
        return new Intl.NumberFormat('vi-VN', {
            style: 'currency',
            currency: 'VND'
        }).format(amount)
    },

    /**
     * Format date for display
     */
    formatDate: (date) => {
        if (!date) return ''
        return new Date(date).toLocaleDateString('vi-VN')
    },

    /**
     * Format datetime for display
     */
    formatDateTime: (datetime) => {
        if (!datetime) return ''
        return new Date(datetime).toLocaleString('vi-VN')
    },

    /**
     * Validate email format
     */
    validateEmail: (email) => {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
        return emailRegex.test(email)
    },

    /**
     * Validate phone number format
     */
    validatePhone: (phone) => {
        const phoneRegex = /^[0-9]{10,11}$/
        return phoneRegex.test(phone.replace(/\s/g, ''))
    }
}

export default customerApi