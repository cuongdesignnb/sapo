import { defineStore } from 'pinia'
import { productApi, masterDataApi } from '../api/productApi'

export const useProductStore = defineStore('products', {
    state: () => ({
        // Products data
        products: [],
        selectedProduct: null,
        selectedProductIds: [],

        // Loading states
        loading: false,
        saving: false,
        deleting: false,

        // Filters
        filters: {
            search: '',
            category_name: '',
            brand_name: '',
            status: 'active',
            sort_field: 'created_at',
            sort_direction: 'desc'
        },

        // Pagination
        pagination: {
            current_page: 1,
            last_page: 1,
            per_page: 20,
            total: 0,
            from: 0,
            to: 0
        },

        // Master data for dropdowns
        categories: [],
        brands: [],
        suppliers: [],

        // Cache for performance
        cache: new Map(),
        cacheTimeout: 5 * 60 * 1000 // 5 minutes
    }),

    getters: {
        // Get products with formatted data
        formattedProducts: (state) => {
            return state.products.map(product => ({
                ...product,
                category: product.category_name || '',
                brand: product.brand_name || '',
                sellable_quantity: product.quantity || 0,
                sellable_unit: '1 phiên bán',
                stock_quantity: product.quantity || 0,
                stock_unit: '1 phiên bán'
            }))
        },

        // Check if has selected products
        hasSelectedProducts: (state) => {
            return state.selectedProductIds.length > 0
        },

        // Get selected products count
        selectedCount: (state) => {
            return state.selectedProductIds.length
        }
    },

    actions: {
        // Fetch products with filters and pagination
        async fetchProducts(params = {}) {
            this.loading = true
            
            try {
                // Merge with current filters
                const queryParams = {
                    ...this.filters,
                    ...params,
                    page: this.pagination.current_page
                }

                // Check cache first
                const cacheKey = JSON.stringify(queryParams)
                const cached = this.cache.get(cacheKey)
                
                if (cached && Date.now() - cached.timestamp < this.cacheTimeout) {
                    this.products = cached.data
                    this.pagination = cached.pagination
                    return cached
                }

                const response = await productApi.getAll(queryParams)

                if (response.success) {
                    this.products = response.data
                    this.pagination = response.pagination

                    // Cache the result
                    this.cache.set(cacheKey, {
                        data: response.data,
                        pagination: response.pagination,
                        timestamp: Date.now()
                    })
                }

                return response
            } catch (error) {
                console.error('Error fetching products:', error)
                throw error
            } finally {
                this.loading = false
            }
        },

        // Get single product
        async getProduct(id) {
            try {
                const response = await productApi.getById(id)
                
                if (response.success) {
                    this.selectedProduct = response.data
                }
                
                return response
            } catch (error) {
                console.error('Error fetching product:', error)
                throw error
            }
        },

        // Create new product
        async createProduct(data) {
            this.saving = true
            
            try {
                const response = await productApi.create(data)
                
                if (response.success) {
                    // Clear cache and refresh list
                    this.clearCache()
                    await this.fetchProducts()
                }
                
                return response
            } catch (error) {
                console.error('Error creating product:', error)
                throw error
            } finally {
                this.saving = false
            }
        },

        // Update product
        async updateProduct(id, data) {
            this.saving = true
            
            try {
                const response = await productApi.update(id, data)
                
                if (response.success) {
                    // Update in current list
                    const index = this.products.findIndex(p => p.id === id)
                    if (index !== -1) {
                        this.products[index] = response.data
                    }
                    
                    // Clear cache
                    this.clearCache()
                }
                
                return response
            } catch (error) {
                console.error('Error updating product:', error)
                throw error
            } finally {
                this.saving = false
            }
        },

        // Delete product
        async deleteProduct(id) {
            this.deleting = true
            
            try {
                const response = await productApi.delete(id)
                
                if (response.success) {
                    // Remove from current list
                    this.products = this.products.filter(p => p.id !== id)
                    
                    // Clear cache and refresh
                    this.clearCache()
                    await this.fetchProducts()
                }
                
                return response
            } catch (error) {
                console.error('Error deleting product:', error)
                throw error
            } finally {
                this.deleting = false
            }
        },

        // Bulk delete products
        async bulkDeleteProducts(ids = null) {
            const idsToDelete = ids || this.selectedProductIds
            
            if (idsToDelete.length === 0) return
            
            this.deleting = true
            
            try {
                const response = await productApi.bulkDelete(idsToDelete)
                
                if (response.success) {
                    // Clear selections
                    this.selectedProductIds = []
                    
                    // Clear cache and refresh
                    this.clearCache()
                    await this.fetchProducts()
                }
                
                return response
            } catch (error) {
                console.error('Error bulk deleting products:', error)
                throw error
            } finally {
                this.deleting = false
            }
        },

        // Load master data for dropdowns
        async loadMasterData() {
            try {
                const [categoriesRes, brandsRes, suppliersRes] = await Promise.all([
                    masterDataApi.getCategories(),
                    masterDataApi.getBrands(),
                    masterDataApi.getSuppliers()
                ])

                this.categories = categoriesRes.data || []
                this.brands = brandsRes.data || []
                this.suppliers = suppliersRes.data || []
            } catch (error) {
                console.error('Error loading master data:', error)
            }
        },

        // Update filters
        updateFilters(newFilters) {
            this.filters = { ...this.filters, ...newFilters }
            this.pagination.current_page = 1 // Reset to first page
        },

        // Update pagination
        updatePagination(page) {
            this.pagination.current_page = page
        },

        // Toggle product selection
        toggleProductSelection(productId) {
            const index = this.selectedProductIds.indexOf(productId)
            if (index > -1) {
                this.selectedProductIds.splice(index, 1)
            } else {
                this.selectedProductIds.push(productId)
            }
        },

        // Select all products
        selectAllProducts() {
            this.selectedProductIds = this.products.map(p => p.id)
        },

        // Clear all selections
        clearSelection() {
            this.selectedProductIds = []
        },

        // Clear cache
        clearCache() {
            this.cache.clear()
        },

        // Reset store
        reset() {
            this.products = []
            this.selectedProduct = null
            this.selectedProductIds = []
            this.clearCache()
        }
    }
})