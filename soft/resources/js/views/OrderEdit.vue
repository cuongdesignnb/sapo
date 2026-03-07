<template>
  <div class="container mx-auto p-6">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold text-gray-900">Sửa đơn hàng {{ order?.code }}</h1>
      <a href="/orders" class="text-gray-600 hover:text-gray-800">← Quay lại danh sách</a>
    </div>

    <div v-if="loading" class="flex justify-center items-center py-12">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
      <span class="ml-2 text-gray-600">Đang tải dữ liệu...</span>
    </div>

    <div v-else-if="order && order.status !== 'pending'" class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
      <div class="text-red-700">
        ⚠️ Chỉ có thể sửa đơn hàng ở trạng thái "Chờ duyệt". Đơn hàng này đang ở trạng thái: 
        <span class="font-semibold">{{ getStatusText(order.status) }}</span>
      </div>
      <div class="mt-2">
        <a href="/orders" class="text-red-600 hover:text-red-800 underline">← Quay lại danh sách</a>
      </div>
    </div>

    <div v-else-if="order" class="grid grid-cols-1 lg:grid-cols-2 gap-8">
      <!-- Left Column -->
      <div class="space-y-6">
        <!-- Customer Section -->
        <div class="bg-white border rounded-lg p-4">
          <h3 class="text-lg font-semibold mb-4">Thông tin khách hàng</h3>
          
          <div class="relative mb-4">
            <input
              type="text"
              v-model="customerSearch"
              @input="searchCustomers"
              @focus="showCustomerDropdown = true"
              placeholder="Tìm theo tên, SĐT, mã khách hàng..."
              class="w-full px-3 py-2 border border-gray-300 rounded-md"
              :class="{ 'border-red-500': errors.customer_id }"
            />
            
            <div v-if="showCustomerDropdown && customerResults.length > 0" 
                 class="absolute z-10 w-full bg-white border rounded-md mt-1 max-h-48 overflow-y-auto shadow-lg">
              <div v-for="customer in customerResults" 
                   :key="customer.id"
                   @click="selectCustomer(customer)"
                   class="px-3 py-2 hover:bg-blue-50 cursor-pointer">
                <div class="font-medium">{{ customer.name }}</div>
                <div class="text-sm text-gray-500">{{ customer.phone }} - {{ customer.code }}</div>
              </div>
            </div>
          </div>

          <div v-if="selectedCustomer" class="bg-blue-50 border border-blue-200 p-3 rounded-md">
            <div class="font-medium">{{ selectedCustomer.name }}</div>
            <div class="text-sm text-gray-600">{{ selectedCustomer.phone }} - {{ selectedCustomer.code }}</div>
          </div>
          
          <div v-if="errors.customer_id" class="text-red-500 text-sm mt-1">{{ errors.customer_id }}</div>
        </div>

        <!-- Products Section -->
        <div class="bg-white border rounded-lg p-4">
          <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold">Sản phẩm đặt hàng</h3>
            <button @click="addProduct" class="bg-green-500 text-white px-3 py-1 rounded text-sm hover:bg-green-600">
              + Thêm
            </button>
          </div>

          <div class="relative mb-4">
            <input
              type="text"
              v-model="productSearch"
              @input="searchProducts"
              @focus="showProductDropdown = true"
              placeholder="Tìm sản phẩm để thêm nhanh..."
              class="w-full px-3 py-2 border border-gray-300 rounded-md"
            />
            
            <div v-if="showProductDropdown && productResults.length > 0" 
                 class="absolute z-10 w-full bg-white border rounded-md mt-1 max-h-48 overflow-y-auto shadow-lg">
              <div v-for="product in productResults" 
                   :key="product.id"
                   @click="quickAddProduct(product)"
                   class="px-3 py-2 hover:bg-green-50 cursor-pointer">
                <div class="font-medium">{{ product.name }}</div>
                <div class="text-sm text-gray-500">SKU: {{ product.sku }} - Giá: {{ formatCurrency(product.retail_price) }}</div>
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
                  <td class="p-3">{{ index + 1 }}</td>
                  <td class="p-3">
                    <div class="font-medium">{{ item.product_name || 'Chưa chọn sản phẩm' }}</div>
                    <div v-if="item.sku" class="text-xs text-gray-500">SKU: {{ item.sku }}</div>
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
                  <td class="p-3 text-right font-medium">
                    {{ formatCurrency(item.total || 0) }}
                  </td>
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
          
          <div v-if="errors.items" class="text-red-500 text-sm mt-2">{{ errors.items }}</div>
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
                <option v-for="warehouse in warehouses" :key="warehouse.id" :value="warehouse.id">
                  {{ warehouse.name }}
                </option>
              </select>
              <div v-if="errors.warehouse_id" class="text-red-500 text-sm mt-1">{{ errors.warehouse_id }}</div>
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
              <span class="font-medium">{{ totalQuantity }} sản phẩm</span>
            </div>
            
            <div class="flex justify-between items-center">
              <span class="text-sm text-gray-600">Số loại sản phẩm:</span>
              <span class="font-medium">{{ form.items.length }} loại</span>
            </div>
            
            <div class="border-t pt-3">
              <div class="flex justify-between items-center">
                <span class="text-lg font-semibold">Tổng tiền:</span>
                <span class="text-xl font-bold text-blue-600">{{ formatCurrency(totalAmount) }}</span>
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
    <div v-if="order && order.status === 'pending'" class="flex justify-end space-x-3 mt-8 pt-6 border-t">
      <a href="/orders" class="px-6 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
        Hủy
      </a>
      
      <button @click="updateOrder" 
              :disabled="loading"
              class="px-6 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 disabled:opacity-50">
        <span v-if="loading">⏳ Đang cập nhật...</span>
        <span v-else>📝 Cập nhật đơn hàng</span>
      </button>
    </div>
  </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue'
import { orderApi } from '../api/orderApi.js'

export default {
  name: 'OrderEdit',
  setup() {
    const orderId = document.getElementById('order-edit-app').dataset.orderId
    const loading = ref(false)
    const errors = ref({})
    const order = ref(null)
    const warehouses = ref([])
    const customerSearch = ref('')
    const productSearch = ref('')
    const selectedCustomer = ref(null)
    const showCustomerDropdown = ref(false)
    const showProductDropdown = ref(false)
    const customerResults = ref([])
    const productResults = ref([])

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
      const token = sessionStorage.getItem('api_token') || 
                   document.querySelector('meta[name="api-token"]')?.getAttribute('content')
      
      return {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      }
    }

    const loadOrder = async () => {
      try {
        loading.value = true
        const response = await orderApi.getById(orderId)
        
        if (response.success) {
          order.value = response.data
          
          // Initialize form
          form.value = {
            customer_id: order.value.customer_id,
            warehouse_id: order.value.warehouse_id,
            items: order.value.items?.map(item => ({
              product_id: item.product_id,
              product_name: item.product_name || item.product?.name,
              sku: item.sku || item.product?.sku,
              quantity: item.quantity,
              price: item.price,
              total: item.total
            })) || [],
            delivery_address: order.value.delivery_address || '',
            delivery_contact: order.value.delivery_contact || '',
            delivery_phone: order.value.delivery_phone || '',
            note: order.value.note || '',
            tags: order.value.tags || '',
            source: order.value.source || 'Web',
            priority: order.value.priority || 'normal'
          }
          
          if (order.value.customer) {
            selectedCustomer.value = order.value.customer
            customerSearch.value = order.value.customer.name
          }
        }
      } catch (error) {
        console.error('Error loading order:', error)
        alert('Lỗi khi tải thông tin đơn hàng')
      } finally {
        loading.value = false
      }
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

    const searchCustomers = async () => {
      if (customerSearch.value.length < 2) {
        customerResults.value = []
        showCustomerDropdown.value = false
        return
      }

      try {
        const searchParams = new URLSearchParams({
          search: customerSearch.value,
          per_page: 10
        })

        const response = await fetch(`/api/customers?${searchParams}`, {
          headers: getAuthHeaders()
        })
        
        if (response.ok) {
          const data = await response.json()
          
          if (data.success && data.data && data.data.data && Array.isArray(data.data.data)) {
            customerResults.value = data.data.data.map(c => ({
              id: c.id,
              name: c.name,
              phone: c.phone,
              code: c.code,
              total_debt: c.total_debt || 0
            }))
            showCustomerDropdown.value = true
          }
        }
      } catch (error) {
        console.error('Error searching customers:', error)
      }
    }

    const selectCustomer = (customer) => {
      selectedCustomer.value = customer
      form.value.customer_id = customer.id
      customerSearch.value = customer.name
      customerResults.value = []
      showCustomerDropdown.value = false
      
      if (!form.value.delivery_contact) {
        form.value.delivery_contact = customer.name
      }
      if (!form.value.delivery_phone) {
        form.value.delivery_phone = customer.phone
      }
    }

    const searchProducts = async () => {
      if (productSearch.value.length < 2) {
        productResults.value = []
        showProductDropdown.value = false
        return
      }

      try {
        const searchParams = new URLSearchParams({
          search: productSearch.value,
          per_page: 10
        })

        const response = await fetch(`/api/products?${searchParams}`, {
          headers: getAuthHeaders()
        })
        
        if (response.ok) {
          const data = await response.json()
          
          let productsArray = []
          
          if (data.success) {
            if (data.data && data.data.data && Array.isArray(data.data.data)) {
              productsArray = data.data.data
            } else if (data.data && Array.isArray(data.data)) {
              productsArray = data.data
            }
            
            if (productsArray.length > 0) {
              productResults.value = productsArray.map(p => ({
                id: p.id,
                name: p.name,
                sku: p.sku,
                retail_price: p.retail_price || p.price || 0,
                stock_quantity: p.quantity || p.stock_quantity || 0
              }))
              showProductDropdown.value = true
            }
          }
        }
      } catch (error) {
        console.error('Error searching products:', error)
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

    const getStatusText = (status) => {
      const statusMap = {
        'pending': 'Chờ duyệt',
        'confirmed': 'Đã xác nhận',
        'processing': 'Đang xử lý',
        'shipping': 'Đang giao hàng',
        'delivered': 'Đã giao hàng',
        'completed': 'Hoàn thành',
        'cancelled': 'Đã hủy',
        'refunded': 'Đã hoàn tiền'
      }
      return statusMap[status] || status
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

    const updateOrder = async () => {
      if (!validateForm()) return

      try {
        loading.value = true
        await orderApi.update(orderId, form.value)
        window.location.href = '/orders'
      } catch (error) {
        console.error('Error updating order:', error)
        alert('Lỗi khi cập nhật đơn hàng: ' + error.message)
      } finally {
        loading.value = false
      }
    }

    onMounted(() => {
      loadOrder()
      loadWarehouses()
    })

    return {
      orderId,
      loading,
      errors,
      order,
      form,
      warehouses,
      customerSearch,
      productSearch,
      selectedCustomer,
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
      getStatusText,
      updateOrder
    }
  }
}
</script>