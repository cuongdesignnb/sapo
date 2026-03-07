@extends('layouts.master')

@section('title', 'Tạo đơn hàng')

@section('content')
<div id="order-create-app"></div>
@endsection

@push('scripts')
<script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
@verbatim
<script>
const { createApp, ref, computed, onMounted } = Vue

const OrderCreateApp = {
    setup() {
        const loading = ref(false)
        const errors = ref({})
        const warehouses = ref([])
        const customerSearch = ref('')
        const productSearch = ref('')
        const selectedCustomer = ref(null)
        const showCustomerDropdown = ref(false)
        const showProductDropdown = ref(false)
        const customerResults = ref([])
        const productResults = ref([])
        let customerSearchTimeout = null
        let productSearchTimeout = null

        const form = ref({
            customer_id: null,
            warehouse_id: null,
            items: [],
            delivery_address: '',
            delivery_contact: '',
            delivery_phone: '',
            note: '',
            tags: '',
            source: 'Web',
            priority: 'normal'
        })

        const totalQuantity = computed(() => {
            return form.value.items.reduce((total, item) => total + (item.quantity || 0), 0)
        })

        const totalAmount = computed(() => {
            return form.value.items.reduce((total, item) => total + (item.total || 0), 0)
        })

        const getAuthHeaders = () => {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            const apiToken = document.querySelector('meta[name="api-token"]')?.getAttribute('content') || sessionStorage.getItem('api_token')
            const headers = {
                'X-CSRF-TOKEN': csrfToken || '',
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
            if (apiToken) {
                headers['Authorization'] = `Bearer ${apiToken}`
            }
            return headers
        }

        const loadWarehouses = async () => {
            try {
                const response = await fetch('/api/my-warehouses', {
                    headers: getAuthHeaders()
                })
                
                if (response.ok) {
                    const data = await response.json()
                    warehouses.value = data.success ? data.data : []
                }
            } catch (error) {
                console.error('Error loading warehouses:', error)
            }
        }

        const addProduct = () => {
            form.value.items.push({
                product_id: '',
                product_name: '',
                sku: '',
                quantity: 1,
                price: 0,
                total: 0
            })
        }

        const removeProduct = (index) => {
            form.value.items.splice(index, 1)
        }

        const updateItemTotal = (index) => {
            const item = form.value.items[index]
            item.total = (item.quantity || 0) * (item.price || 0)
        }

        const formatCurrency = (amount) => {
            return new Intl.NumberFormat('vi-VN', {
                style: 'currency',
                currency: 'VND'
            }).format(amount || 0)
        }

        const searchCustomers = async () => {
            console.log('🔍 Searching customers for:', customerSearch.value)
            
            // Clear previous timeout
            if (customerSearchTimeout) {
                clearTimeout(customerSearchTimeout)
            }
            
            if (customerSearch.value.length < 2) {
                console.log('❌ Search term too short, need at least 2 characters')
                customerResults.value = []
                showCustomerDropdown.value = false
                return
            }
            
            // Debounce search
            customerSearchTimeout = setTimeout(async () => {
                await performCustomerSearch()
            }, 300)
        }

        const performCustomerSearch = async () => {
            try {
                console.log('🚀 Actually performing customer search...')
                const params = new URLSearchParams({
                    search: customerSearch.value,
                    per_page: 10
                })

                // Use web route for customers search
                const response = await fetch(`/search/customers?${params}`, {
                    headers: getAuthHeaders()
                })
                console.log('✅ Customer search response:', response.status)
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`)
                }
                
                const data = await response.json()
                console.log('👥 Customer full response:', data)
                
                if (data && data.success && data.data) {
                    let customersArray = []
                    
                    // API trả về paginated data: {success: true, data: {data: [...], pagination: {...}}}
                    if (data.data.data && Array.isArray(data.data.data)) {
                        customersArray = data.data.data
                        console.log('📋 Using paginated format')
                    } else if (Array.isArray(data.data)) {
                        customersArray = data.data
                        console.log('📋 Using direct array format')
                    } else {
                        console.log('❓ Unknown data format:', typeof data.data)
                    }
                    
                    customerResults.value = customersArray.map(c => ({
                        id: c.id,
                        name: c.name,
                        phone: c.phone,
                        code: c.code
                    }))
                    
                    showCustomerDropdown.value = customerResults.value.length > 0
                    console.log('✅ Found customers:', customerResults.value.length)
                }
            } catch (error) {
                console.error('❌ Error searching customers:', error)
                customerResults.value = []
                showCustomerDropdown.value = false
            }
        }

        const selectCustomer = async (customer) => {
            selectedCustomer.value = customer
            form.value.customer_id = customer.id
            customerSearch.value = customer.name
            customerResults.value = []
            showCustomerDropdown.value = false
            
            form.value.delivery_contact = customer.name
            form.value.delivery_phone = customer.phone
            
            // Load customer debt info
            await loadCustomerDebt(customer.id)
        }

        const customerDebt = ref({
            total_debt: 0,
            formatted_debt: '0 ₫',
            last_payment_date: null
        })
        const loadCustomerDebt = async (customerId) => {
            try {
                console.log('🔍 Loading debt for customer:', customerId)
                const response = await fetch(`/search/customers/${customerId}`, {
                    headers: getAuthHeaders()
                })
                
                if (response.ok) {
                    const data = await response.json()
                    if (data.success && data.data) {
                        customerDebt.value = {
                            total_debt: data.data.current_debt || 0,
                            formatted_debt: formatCurrency(data.data.current_debt || 0),
                            last_payment_date: data.data.last_order_date
                        }
                        console.log('💰 Customer debt loaded:', customerDebt.value)
                    }
                }
            } catch (error) {
                console.error('❌ Error loading customer debt:', error)
                customerDebt.value = { total_debt: 0, formatted_debt: '0 ₫', last_payment_date: null }
            }
        }

        

        const searchProducts = async () => {
            console.log('🔍 Searching products for:', productSearch.value)
            
            // Clear previous timeout
            if (productSearchTimeout) {
                clearTimeout(productSearchTimeout)
            }
            
            if (productSearch.value.length < 2) {
                console.log('❌ Product search term too short')
                productResults.value = []
                showProductDropdown.value = false
                return
            }
            
            // Debounce search
            productSearchTimeout = setTimeout(async () => {
                await performProductSearch()
            }, 300)
        }

        const performProductSearch = async () => {
            try {
                console.log('🚀 Actually performing product search...')
                const params = new URLSearchParams({
                    search: productSearch.value,
                    per_page: 10
                })

                // Use web route for products search
                const response = await fetch(`/search/products?${params}`, {
                    headers: getAuthHeaders()
                })
                console.log('✅ Product search response:', response.status)
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`)
                }
                
                const data = await response.json()
                console.log('📦 Product full response:', data)
                
                if (data && data.success && data.data) {
                    let productsArray = []
                    
                    // API trả về paginated data: {success: true, data: {data: [...], pagination: {...}}}
                    if (data.data.data && Array.isArray(data.data.data)) {
                        productsArray = data.data.data
                        console.log('📋 Using paginated format')
                    } else if (Array.isArray(data.data)) {
                        productsArray = data.data
                        console.log('📋 Using direct array format')
                    } else {
                        console.log('❓ Unknown data format:', typeof data.data)
                    }
                    
                    productResults.value = productsArray.map(p => ({
                        id: p.id,
                        name: p.name,
                        sku: p.sku,
                        retail_price: p.retail_price || p.price || 0
                    }))
                    
                    showProductDropdown.value = productResults.value.length > 0
                    console.log('✅ Found products:', productResults.value.length)
                }
            } catch (error) {
                console.error('❌ Error searching products:', error)
                productResults.value = []
                showProductDropdown.value = false
            }
        }

        const quickAddProduct = (product) => {
            const existingIndex = form.value.items.findIndex(item => item.product_id === product.id)
            
            if (existingIndex >= 0) {
                form.value.items[existingIndex].quantity += 1
                updateItemTotal(existingIndex)
            } else {
                form.value.items.push({
                    product_id: product.id,
                    product_name: product.name,
                    sku: product.sku,
                    quantity: 1,
                    price: product.retail_price || 0,
                    total: product.retail_price || 0
                })
            }
            
            productSearch.value = ''
            productResults.value = []
            showProductDropdown.value = false
        }

        const validateForm = () => {
            errors.value = {}
            
            if (!form.value.customer_id) {
                errors.value.customer_id = 'Vui lòng chọn khách hàng'
            }
            
            if (!form.value.warehouse_id) {
                errors.value.warehouse_id = 'Vui lòng chọn cửa hàng'
            }
            
            if (form.value.items.length === 0) {
                errors.value.items = 'Vui lòng thêm ít nhất một sản phẩm'
            }

            return Object.keys(errors.value).length === 0
        }

        const saveOrder = async (status = 'pending') => {
            if (!validateForm()) return

            try {
                loading.value = true
                const response = await fetch('/orders/store', {
                    method: 'POST',
                    headers: getAuthHeaders(),
                    body: JSON.stringify({ ...form.value, status })
                })
                
                if (response.ok) {
                    const result = await response.json()
                    const orderId = result?.data?.id
                    if (status === 'confirmed' && orderId) {
                        const approveRes = await fetch(`/orders/${orderId}/approve`, {
                            method: 'POST',
                            headers: getAuthHeaders(),
                            body: JSON.stringify({ note: 'Tạo và duyệt từ trang tạo đơn' })
                        })
                        if (!approveRes.ok) {
                            const err = await approveRes.json().catch(() => ({}))
                            alert('Đã tạo đơn nhưng duyệt thất bại: ' + (err.message || approveRes.statusText))
                        }
                    }
                    window.location.href = '/orders'
                } else {
                    const errorData = await response.json()
                    alert('Lỗi: ' + (errorData.message || 'Không thể tạo đơn hàng'))
                }
            } catch (error) {
                console.error('Error saving order:', error)
                alert('Lỗi khi tạo đơn hàng: ' + error.message)
            } finally {
                loading.value = false
            }
        }

        onMounted(() => {
            loadWarehouses()
        })

        return {
            loading,
            errors,
            form,
            warehouses,
            customerSearch,
            productSearch,
            selectedCustomer,
            customerDebt,
            showCustomerDropdown,
            showProductDropdown,
            customerResults,
            productResults,
            totalQuantity,
            totalAmount,
            searchCustomers,
            selectCustomer,
            searchProducts,
            quickAddProduct,
            addProduct,
            removeProduct,
            updateItemTotal,
            formatCurrency,
            saveOrder
        }
    },

    template: `
    <div class="max-w-screen-2xl w-full mx-auto px-6 py-6">
            <div class="flex items-center justify-between mb-6">
                <h1 class="text-2xl font-bold text-gray-900">Tạo đơn hàng</h1>
                <a href="/orders" class="text-gray-600 hover:text-gray-800">← Quay lại danh sách</a>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Left Column -->
                <div class="space-y-6">
                    <!-- Customer Section -->
                    <div class="bg-white border rounded-lg p-4">
                        <h3 class="text-lg font-semibold mb-4">Thông tin khách hàng</h3>
                        <div class="mb-4 relative">
                            <input
                                type="text"
                                v-model="customerSearch"
                                @input="searchCustomers"
                                placeholder="Tìm theo tên, SĐT, mã khách hàng..."
                                class="w-full px-3 py-2 border border-gray-300 rounded-md"
                                :class="{ 'border-red-500': errors.customer_id }"
                            />
                            
                            <!-- Customer search dropdown -->
                            <div v-if="showCustomerDropdown && customerResults.length > 0" 
                                 class="absolute z-10 mt-1 w-full bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-y-auto">
                                <div v-for="customer in customerResults" :key="customer.id"
                                     @click="selectCustomer(customer)"
                                     class="px-3 py-2 hover:bg-gray-100 cursor-pointer border-b">
                                    <div class="font-medium">{{ customer.name }}</div>
                                    <div class="text-sm text-gray-600">{{ customer.phone }} - {{ customer.code }}</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Selected customer info -->
                        <div v-if="selectedCustomer" class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-md">
                            <div class="flex justify-between items-start">
                                <div>
                                    <div class="font-medium text-blue-900">{{ selectedCustomer.name }}</div>
                                    <div class="text-sm text-blue-700">{{ selectedCustomer.phone }} - {{ selectedCustomer.code }}</div>
                                </div>
                                <div class="text-right">
                                    <div class="text-sm font-medium" :class="customerDebt.total_debt > 0 ? 'text-red-600' : 'text-green-600'">
                                        Công nợ: {{ customerDebt.formatted_debt }}
                                    </div>
                                    <div v-if="customerDebt.last_payment_date" class="text-xs text-gray-500">
                                        Đơn cuối: {{ customerDebt.last_payment_date }}
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div v-if="errors.customer_id" class="text-red-500 text-sm mt-1" v-text="errors.customer_id"></div>
                    </div>

                    <!-- Products Section -->
                    <div class="bg-white border rounded-lg p-4">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold">Sản phẩm đặt hàng</h3>
                            <button @click="addProduct" class="bg-green-500 text-white px-3 py-1 rounded text-sm hover:bg-green-600">
                                + Thêm
                            </button>
                        </div>
                        
                        <!-- Quick product search -->
                        <div class="mb-4 relative">
                            <input
                                type="text"
                                v-model="productSearch"
                                @input="searchProducts"
                                placeholder="Tìm kiếm sản phẩm để thêm nhanh..."
                                class="w-full px-3 py-2 border border-gray-300 rounded-md"
                            />
                            
                            <!-- Product search dropdown -->
                            <div v-if="showProductDropdown && productResults.length > 0" 
                                 class="absolute z-10 mt-1 w-full bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-y-auto">
                                <div v-for="product in productResults" :key="product.id"
                                     @click="quickAddProduct(product)"
                                     class="px-3 py-2 hover:bg-gray-100 cursor-pointer border-b">
                                    <div class="font-medium">{{ product.name }}</div>
                                    <div class="text-sm text-gray-600 flex justify-between">
                                        <span>{{ product.sku }}</span>
                                        <span class="font-medium text-blue-600">{{ formatCurrency(product.retail_price) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full border border-gray-300 rounded-md">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="p-3 text-left">#</th>
                                        <th class="p-3 text-left">Sản phẩm</th>
                                        <th class="p-3 text-center">SL</th>
                                        <th class="p-3 text-center">Đơn giá</th>
                                        <th class="p-3 text-center">Thành tiền</th>
                                        <th class="p-3 text-center">Xóa</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(item, index) in form.items" :key="index" class="border-t">
                                        <td class="p-3" v-text="index + 1"></td>
                                        <td class="p-3">
                                            <input 
                                                type="text" 
                                                v-model="item.product_name" 
                                                placeholder="Tên sản phẩm"
                                                class="w-full px-2 py-1 border rounded"
                                            />
                                        </td>
                                        <td class="p-3">
                                            <input
                                                type="number"
                                                v-model.number="item.quantity"
                                                @input="updateItemTotal(index)"
                                                class="w-full px-2 py-1 border rounded text-center"
                                                min="1"
                                            />
                                        </td>
                                        <td class="p-3">
                                            <input
                                                type="number"
                                                v-model.number="item.price"
                                                @input="updateItemTotal(index)"
                                                class="w-full px-2 py-1 border rounded text-right"
                                                min="0"
                                            />
                                        </td>
                                        <td class="p-3 text-right font-medium" v-text="formatCurrency(item.total || 0)"></td>
                                        <td class="p-3 text-center">
                                            <button @click="removeProduct(index)" class="text-red-500 hover:text-red-700">×</button>
                                        </td>
                                    </tr>
                                    
                                    <tr v-if="form.items.length === 0">
                                        <td colspan="6" class="p-6 text-center text-gray-500">
                                            Chưa có sản phẩm nào
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <div v-if="errors.items" class="text-red-500 text-sm mt-2" v-text="errors.items"></div>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="space-y-6">
                    <!-- Order Info -->
                    <div class="bg-white border rounded-lg p-4">
                        <h3 class="text-lg font-semibold mb-4">Thông tin đơn hàng</h3>
                        
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Bán tại *</label>
                                <select v-model="form.warehouse_id" 
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md"
                                        :class="{ 'border-red-500': errors.warehouse_id }">
                                    <option value="">-- Chọn cửa hàng --</option>
                                    <option v-for="warehouse in warehouses" :key="warehouse.id" :value="warehouse.id" v-text="warehouse.name">
                                    </option>
                                </select>
                                <div v-if="errors.warehouse_id" class="text-red-500 text-sm mt-1" v-text="errors.warehouse_id"></div>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Nguồn đơn hàng</label>
                                    <select v-model="form.source" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                                        <option value="Web">Web</option>
                                        <option value="Mobile">Mobile</option>
                                        <option value="POS">POS</option>
                                        <option value="Phone">Điện thoại</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Độ ưu tiên</label>
                                    <select v-model="form.priority" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                                        <option value="low">Thấp</option>
                                        <option value="normal">Bình thường</option>
                                        <option value="high">Cao</option>
                                        <option value="urgent">Khẩn cấp</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Tags</label>
                                <input type="text" v-model="form.tags" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md"
                                       placeholder="VIP, Sale, Gấp..."/>
                            </div>
                        </div>
                    </div>

                    <!-- Delivery Info -->
                    <div class="bg-white border rounded-lg p-4">
                        <h3 class="text-lg font-semibold mb-4">Thông tin giao hàng</h3>
                        
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Địa chỉ giao hàng</label>
                                <textarea v-model="form.delivery_address" 
                                          class="w-full px-3 py-2 border border-gray-300 rounded-md"
                                          rows="2"
                                          placeholder="Nhập địa chỉ giao hàng đầy đủ..."></textarea>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Người nhận</label>
                                    <input type="text" v-model="form.delivery_contact" 
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md"
                                           placeholder="Tên người nhận"/>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">SĐT nhận hàng</label>
                                    <input type="text" v-model="form.delivery_phone" 
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md"
                                           placeholder="Số điện thoại"/>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Order Summary -->
                    <div class="bg-white border rounded-lg p-4 bg-gradient-to-r from-blue-50 to-indigo-50">
                        <h3 class="text-lg font-semibold mb-4">Tóm tắt đơn hàng</h3>
                        
                        <div class="space-y-3">
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">Số lượng sản phẩm:</span>
                                <span class="font-medium" v-text="totalQuantity + ' sản phẩm'"></span>
                            </div>
                            
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">Số loại sản phẩm:</span>
                                <span class="font-medium" v-text="form.items.length + ' loại'"></span>
                            </div>
                            
                            <div class="border-t pt-3">
                                <div class="flex justify-between items-center">
                                    <span class="text-lg font-semibold">Tổng tiền:</span>
                                    <span class="text-xl font-bold text-blue-600" v-text="formatCurrency(totalAmount)"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Note -->
                    <div class="bg-white border rounded-lg p-4">
                        <h3 class="text-lg font-semibold mb-4">Ghi chú đơn hàng</h3>
                        <textarea v-model="form.note" 
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md"
                                  rows="3"
                                  placeholder="Ghi chú, yêu cầu đặc biệt..."></textarea>
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="flex justify-end space-x-3 mt-8 pt-6 border-t">
                <a href="/orders" class="px-6 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                    Hủy
                </a>
                
                <button @click="saveOrder('pending')" 
                        :disabled="loading"
                        class="px-6 py-2 border border-blue-300 rounded-md text-blue-600 hover:bg-blue-50 disabled:opacity-50">
                    <span v-if="loading">⏳ Đang lưu...</span>
                    <span v-else>💾 Lưu đơn hàng</span>
                </button>
                
                <button @click="saveOrder('confirmed')" 
                        :disabled="loading"
                        class="px-6 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 disabled:opacity-50">
                    <span v-if="loading">⏳ Đang lưu...</span>
                    <span v-else>✅ Tạo và duyệt</span>
                </button>
            </div>
        </div>
    `
}

const orderCreateAppElement = document.getElementById('order-create-app')
if (orderCreateAppElement) {
    createApp(OrderCreateApp).mount('#order-create-app')
}
</script>
@endverbatim
@endpush