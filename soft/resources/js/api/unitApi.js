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
        
        // ✅ Bearer token từ sessionStorage hoặc meta (GIỐNG CHATGPT)
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

// Download client interceptor (GIỐNG PATTERN CHATGPT)
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

// Unit API methods
export const unitApi = {
    // Get all units with filters and pagination
    getAll: (params = {}) => {
        return apiClient.get('/units', { params })
    },

    // Get single unit
    getById: (id) => {
        return apiClient.get(`/units/${id}`)
    },

    // Create new unit
    create: (data) => {
        return apiClient.post('/units', data)
    },

    // Update unit
    update: (id, data) => {
        return apiClient.put(`/units/${id}`, data)
    },

    // Delete unit
    delete: (id) => {
        return apiClient.delete(`/units/${id}`)
    },

    // Bulk delete units
    bulkDelete: (ids) => {
        return apiClient.post('/units/bulk-delete', { ids })
    },

    // Export units to CSV
    export: async (filters = {}) => {
        try {
            const response = await downloadClient.get('/units/export', {
                params: filters,
                responseType: 'blob'
            })
            
            return response
        } catch (error) {
            console.error('Export error:', error)
            throw error
        }
    },

    // Import units from CSV
    import: (file) => {
        const formData = new FormData()
        formData.append('file', file)
        return apiClient.post('/units/import', formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        })
    },

    // Download import template
    downloadTemplate: async () => {
        try {
            const response = await downloadClient.get('/units/import-template', {
                responseType: 'blob'
            })
            
            return response
        } catch (error) {
            console.error('Download template error:', error)
            throw error
        }
    }
}

export default unitApi