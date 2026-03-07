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

// Category API methods
export const categoryApi = {
    getAll: (params = {}) => apiClient.get('/categories', { params }),
    getById: (id) => apiClient.get(`/categories/${id}`),
    create: (data) => apiClient.post('/categories', data),
    update: (id, data) => apiClient.put(`/categories/${id}`, data),
    delete: (id) => apiClient.delete(`/categories/${id}`),
    bulkDelete: (ids) => apiClient.post('/categories/bulk-delete', { ids })
}

export default categoryApi
