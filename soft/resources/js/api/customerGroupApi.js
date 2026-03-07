import axios from 'axios'

const API_BASE = '/api'

// Helper function to get headers with Bearer token
const getHeaders = () => {
  const headers = {
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  }
  
  // Add CSRF token
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
  if (csrfToken) {
    headers['X-CSRF-TOKEN'] = csrfToken
  }
  
  // Add Bearer token (same as category API)
  let accessToken = sessionStorage.getItem('api_token')
  if (!accessToken) {
    accessToken = document.querySelector('meta[name="api-token"]')?.getAttribute('content')
  }
  
  if (accessToken) {
    headers['Authorization'] = `Bearer ${accessToken}`
  }
  
  return headers
}

export default {
  // Basic CRUD
  getCustomerGroups: (params = {}) => axios.get(`${API_BASE}/customer-groups`, { 
    params, 
    headers: getHeaders() 
  }),
  
  getCustomerGroup: (id) => axios.get(`${API_BASE}/customer-groups/${id}`, { 
    headers: getHeaders() 
  }),
  
  createCustomerGroup: (data) => axios.post(`${API_BASE}/customer-groups`, data, { 
    headers: getHeaders() 
  }),
  
  updateCustomerGroup: (id, data) => axios.put(`${API_BASE}/customer-groups/${id}`, data, { 
    headers: getHeaders() 
  }),
  
  deleteCustomerGroup: (id) => axios.delete(`${API_BASE}/customer-groups/${id}`, { 
    headers: getHeaders() 
  }),
  
  bulkDelete: (ids) => axios.delete(`${API_BASE}/customer-groups`, { 
    data: { ids }, 
    headers: getHeaders() 
  }),

  // Import/Export
  importCustomerGroups: (file) => {
    const formData = new FormData()
    formData.append('file', file)
    
    const headers = getHeaders()
    headers['Content-Type'] = 'multipart/form-data'
    
    return axios.post(`${API_BASE}/customer-groups/import`, formData, { headers })
  },
  
  exportCustomerGroups: (params = {}) => {
    const queryString = new URLSearchParams(params).toString()
    const token = sessionStorage.getItem('api_token') || 
                  document.querySelector('meta[name="api-token"]')?.getAttribute('content')
    
    // Add token to export URL
    const separator = queryString ? '&' : ''
    const tokenParam = token ? `${separator}token=${token}` : ''
    
    window.open(`${API_BASE}/customer-groups/export?${queryString}${tokenParam}`, '_blank')
  },

  // Options & Utilities
  getCustomerGroupOptions: (search = '') => axios.get(`${API_BASE}/customer-groups/options/list`, { 
    params: { search }, 
    headers: getHeaders() 
  }),
  
  getStatistics: () => axios.get(`${API_BASE}/customer-groups/stats/overview`, { 
    headers: getHeaders() 
  }),

  // Group Customers
  getGroupCustomers: (groupId, params = {}) => axios.get(`${API_BASE}/customer-groups/${groupId}/customers`, { 
    params, 
    headers: getHeaders() 
  }),

  // Helper methods for type management
  getTypes: () => {
    return Promise.resolve({
      data: {
        success: true,
        data: [
          { value: 'vip', label: 'VIP', color: 'bg-purple-100 text-purple-800' },
          { value: 'normal', label: 'Thường', color: 'bg-blue-100 text-blue-800' },
          { value: 'local', label: 'Địa phương', color: 'bg-green-100 text-green-800' },
          { value: 'import', label: 'Xuất nhập khẩu', color: 'bg-orange-100 text-orange-800' }
        ]
      }
    })
  },

  // Validation helpers
  validateDiscount: (value) => {
    const discount = parseFloat(value)
    if (isNaN(discount)) return 'Chiết khấu phải là số'
    if (discount < 0) return 'Chiết khấu không được âm'
    if (discount > 100) return 'Chiết khấu không được vượt quá 100%'
    return null
  },

  validatePaymentTerms: (value) => {
    const terms = parseInt(value)
    if (isNaN(terms)) return 'Điều kiện thanh toán phải là số'
    if (terms < 0) return 'Điều kiện thanh toán không được âm'
    return null
  },

  // Format helpers
  formatDiscount: (value) => {
    return `${parseFloat(value || 0).toFixed(1)}%`
  },

  formatPaymentTerms: (value) => {
    const days = parseInt(value || 0)
    return days === 0 ? 'Thanh toán ngay' : `${days} ngày`
  },

  getTypeText: (type) => {
    const types = {
      'vip': 'VIP',
      'normal': 'Thường', 
      'local': 'Địa phương',
      'import': 'Xuất nhập khẩu'
    }
    return types[type] || 'Không xác định'
  },

  getTypeColorClass: (type) => {
    const colors = {
      'vip': 'bg-purple-100 text-purple-800',
      'normal': 'bg-blue-100 text-blue-800',
      'local': 'bg-green-100 text-green-800', 
      'import': 'bg-orange-100 text-orange-800'
    }
    return colors[type] || 'bg-gray-100 text-gray-800'
  },

  // Error handling wrapper
  handleApiError: (error) => {
    console.error('API Error:', error)
    
    if (error.response) {
      // Server responded with error status
      const { status, data } = error.response
      
      switch (status) {
        case 422:
          return {
            type: 'validation',
            message: data.message || 'Dữ liệu không hợp lệ',
            errors: data.errors || {}
          }
        case 409:
          return {
            type: 'conflict',
            message: data.message || 'Có xung đột dữ liệu'
          }
        case 404:
          return {
            type: 'not_found',
            message: 'Nhóm khách hàng không tồn tại'
          }
        case 500:
          return {
            type: 'server_error',
            message: 'Lỗi máy chủ, vui lòng thử lại sau'
          }
        default:
          return {
            type: 'unknown',
            message: data.message || 'Có lỗi xảy ra'
          }
      }
    } else if (error.request) {
      // Network error
      return {
        type: 'network',
        message: 'Lỗi kết nối mạng'
      }
    } else {
      // Other error
      return {
        type: 'unknown',
        message: error.message || 'Có lỗi không xác định'
      }
    }
  },

  // Search helpers
  buildSearchParams: (filters = {}) => {
    const params = {}
    
    // Basic filters
    if (filters.search) params.search = filters.search
    if (filters.type) params.type = filters.type
    if (filters.has_customers) params.has_customers = filters.has_customers
    
    // Sorting
    if (filters.sort_by) params.sort_by = filters.sort_by
    if (filters.sort_order) params.sort_order = filters.sort_order
    
    // Pagination
    if (filters.page) params.page = filters.page
    if (filters.per_page) params.per_page = filters.per_page
    
    return params
  },

  // Template download for import
  downloadTemplate: () => {
    const csvContent = [
      'Mã nhóm,Tên nhóm,Loại,Mô tả,Chiết khấu (%),Điều kiện thanh toán (ngày)',
      'CG001,Khách hàng VIP,vip,Khách hàng VIP với ưu đãi đặc biệt,10,30',
      'CG002,Khách hàng thường,normal,Khách hàng mua lẻ thông thường,0,0',
      'CG003,Đại lý địa phương,local,Đại lý bán buôn tại địa phương,5,15',
      'CG004,Đối tác xuất khẩu,import,Đối tác xuất nhập khẩu,8,45'
    ].join('\n')

    const blob = new Blob(['\ufeff' + csvContent], { type: 'text/csv;charset=utf-8;' })
    const link = document.createElement('a')
    const url = URL.createObjectURL(blob)
    
    link.setAttribute('href', url)
    link.setAttribute('download', 'customer_groups_template.csv')
    link.style.visibility = 'hidden'
    
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
  },

  // Batch operations helper
  batchProcess: async (items, operation, batchSize = 10) => {
    const results = []
    const errors = []
    
    for (let i = 0; i < items.length; i += batchSize) {
      const batch = items.slice(i, i + batchSize)
      
      try {
        const batchResults = await Promise.allSettled(
          batch.map(item => operation(item))
        )
        
        batchResults.forEach((result, index) => {
          if (result.status === 'fulfilled') {
            results.push(result.value)
          } else {
            errors.push({
              item: batch[index],
              error: result.reason
            })
          }
        })
      } catch (error) {
        errors.push({
          batch,
          error
        })
      }
    }
    
    return { results, errors }
  }
}