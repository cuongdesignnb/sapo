<template>
  <div class="pos-system bg-gray-100">
    <!-- Header -->
    <div class="flex" style="height: calc(100vh - 120px);">
      <!-- Left Panel - Product Search & Cart -->
      <div class="w-2/3 bg-white border-r">
        <!-- Search Bar -->
        <div class="p-4 border-b bg-gray-50">
          <div class="relative">
            <input
              ref="productSearchInput"
              type="text"
              v-model="productSearch"
              @input="searchProducts"
              @keydown.enter="addFirstProduct"
              placeholder="🔍 Thêm sản phẩm vào đơn (F3)"
              class="w-full pl-10 pr-4 py-3 text-lg border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
            />
            <div class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400">
              <span class="text-xl">🔍</span>
            </div>
          </div>
          
          <!-- Product Results Dropdown -->
          <div v-if="productResults.length > 0" class="absolute z-50 w-full max-w-2xl bg-white border border-gray-300 rounded-lg mt-1 max-h-60 overflow-y-auto shadow-lg">
            <div 
              v-for="(product, index) in productResults" 
              :key="product.id"
              @click="addProduct(product)"
              :class="['px-4 py-3 hover:bg-blue-50 cursor-pointer border-b border-gray-100', 
                       { 'bg-blue-50': index === selectedProductIndex }]"
            >
              <div class="flex justify-between items-center">
                <div>
                  <div class="font-medium text-gray-900">{{ product.name }}</div>
                  <div class="text-sm text-gray-500">
                    SKU: {{ product.sku }} | Tồn: {{ product.stock }} | {{ product.category_name }}
                  </div>
                </div>
                <div class="text-right">
                  <div class="font-bold text-blue-600">{{ formatCurrency(product.retail_price) }}</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Order Tabs -->
        <div class="border-b bg-gray-50">
          <div class="flex">
            <div 
              v-for="(tab, index) in orderTabs" 
              :key="tab.id"
              @click="switchTab(index)"
              :class="['px-4 py-2 border-r cursor-pointer flex items-center space-x-2',
                       { 'bg-white border-b-2 border-blue-500': currentTabIndex === index,
                         'hover:bg-gray-100': currentTabIndex !== index }]"
            >
              <span>📋</span>
              <span>{{ tab.name }}</span>
              <span v-if="tab.items.length > 0" class="bg-blue-500 text-white text-xs px-2 py-1 rounded-full">
                {{ tab.items.length }}
              </span>
              <button 
                v-if="orderTabs.length > 1"
                @click.stop="closeTab(index)"
                class="text-gray-400 hover:text-red-500 ml-2"
              >
                ×
              </button>
            </div>
            <button 
              @click="addNewTab"
              class="px-4 py-2 text-blue-600 hover:bg-gray-100"
            >
              + Thêm đơn
            </button>
          </div>
        </div>

        <!-- Cart Items -->
        <div class="flex-1 overflow-y-auto" style="max-height: calc(100vh - 300px);">
          <div v-if="currentTab.items.length === 0" class="flex flex-col items-center justify-center h-64 text-gray-500">
            <div class="text-6xl mb-4">🛒</div>
            <div class="text-lg">Đơn hàng trống</div>
            <div class="text-sm">Thêm sản phẩm để bắt đầu</div>
          </div>

          <div v-else class="p-4">
            <table class="w-full">
              <thead class="bg-gray-50">
                <tr>
                  <th class="text-left p-3">STT</th>
                  <th class="text-left p-3">Ảnh</th>
                  <th class="text-left p-3">Tên sản phẩm</th>
                  <th class="text-left p-3">Đơn vị</th>
                  <th class="text-left p-3">Số lượng</th>
                  <th class="text-left p-3">Đơn giá</th>
                  <th class="text-left p-3">Thành tiền</th>
                  <th class="text-left p-3">Thao tác</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(item, index) in currentTab.items" :key="item.id" class="border-b">
                  <td class="p-3">{{ index + 1 }}</td>
                  <td class="p-3">
                    <div class="w-12 h-12 bg-gray-200 rounded flex items-center justify-center">
                      📷
                    </div>
                  </td>
                  <td class="p-3">
                    <div class="font-medium">{{ item.name }}</div>
                    <div class="text-sm text-gray-500">{{ item.sku }}</div>
                  </td>
                  <td class="p-3">Chai</td>
                  <td class="p-3">
                    <div class="flex items-center space-x-2">
                      <button 
                        @click="decreaseQuantity(index)"
                        class="w-8 h-8 bg-gray-200 rounded hover:bg-gray-300"
                      >
                        -
                      </button>
                      <input
                        type="number"
                        v-model.number="item.quantity"
                        @input="updateItemTotal(index)"
                        class="w-16 text-center border border-gray-300 rounded"
                        min="1"
                      />
                      <button 
                        @click="increaseQuantity(index)"
                        class="w-8 h-8 bg-gray-200 rounded hover:bg-gray-300"
                      >
                        +
                      </button>
                    </div>
                  </td>
                  <td class="p-3">
                    <input
                      type="number"
                      v-model.number="item.price"
                      @input="updateItemTotal(index)"
                      class="w-24 px-2 py-1 border border-gray-300 rounded"
                      min="0"
                    />
                  </td>
                  <td class="p-3 font-medium">
                    {{ formatCurrency(item.total) }}
                  </td>
                  <td class="p-3">
                    <button 
                      @click="removeItem(index)"
                      class="text-red-500 hover:text-red-700"
                    >
                      🗑️
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Right Panel - Customer & Payment -->
      <div class="w-1/3 bg-white flex flex-col">
        <!-- Customer Section -->
        <div class="p-4 border-b">
          <div class="flex items-center justify-between mb-3">
            <h3 class="font-semibold">👤 Khách hàng</h3>
            <button 
              @click="clearCustomer"
              v-if="selectedCustomer"
              class="text-blue-600 text-sm hover:underline"
            >
              Đổi khách hàng
            </button>
          </div>
          
          <div v-if="!selectedCustomer">
            <div class="relative">
              <input
                type="text"
                v-model="customerSearch"
                @input="searchCustomers"
                placeholder="🔍 Tìm khách hàng (F4)"
                class="w-full px-3 py-2 border border-gray-300 rounded"
              />
              
              <!-- Customer Results -->
              <div v-if="customerResults.length > 0" class="absolute z-50 w-full bg-white border border-gray-300 rounded mt-1 max-h-40 overflow-y-auto shadow-lg">
                <div 
                  v-for="customer in customerResults" 
                  :key="customer.id"
                  @click="selectCustomer(customer)"
                  class="px-3 py-2 hover:bg-gray-50 cursor-pointer border-b"
                >
                  <div class="font-medium">{{ customer.name }}</div>
                  <div class="text-sm text-gray-500">{{ customer.phone }} - {{ customer.code }}</div>
                </div>
              </div>
            </div>
            
            <button 
              @click="useRetailCustomer"
              class="w-full mt-2 px-3 py-2 bg-gray-100 text-gray-700 rounded hover:bg-gray-200"
            >
              Khách hàng bán lẻ
            </button>
          </div>
          
          <div v-else class="bg-blue-50 p-3 rounded">
            <div class="font-medium">{{ selectedCustomer.name }}</div>
            <div class="text-sm text-gray-600">{{ selectedCustomer.phone }}</div>
            <div class="text-sm text-gray-600">Mã: {{ selectedCustomer.code }}</div>
            <div class="text-sm" :class="selectedCustomer.total_debt > 0 ? 'text-red-600' : 'text-green-600'">
              Nợ: {{ formatCurrency(selectedCustomer.total_debt || 0) }}
            </div>
          </div>
        </div>

        <!-- Order Summary -->
        <div class="p-4 border-b bg-gray-50">
          <h3 class="font-semibold mb-3">💰 Thông tin đơn hàng</h3>
          
          <div class="space-y-2 text-sm">
            <div class="flex justify-between">
              <span>Tổng tiền ({{ currentTab.items.length }} sản phẩm):</span>
              <span class="font-medium">{{ formatCurrency(orderSubTotal) }}</span>
            </div>
            
            <div class="flex justify-between items-center">
              <span>VAT (%):</span>
              <input
                type="number"
                v-model.number="currentTab.vatPercent"
                @input="calculateTotals"
                class="w-16 px-2 py-1 text-right border border-gray-300 rounded"
                min="0"
                max="100"
                step="0.1"
              />
            </div>
            
            <div class="flex justify-between">
              <span>Tiền VAT:</span>
              <span>{{ formatCurrency(vatAmount) }}</span>
            </div>
            
            <div class="flex justify-between items-center">
              <span>Chiết khấu (%):</span>
              <input
                type="number"
                v-model.number="currentTab.discountPercent"
                @input="calculateTotals"
                class="w-16 px-2 py-1 text-right border border-gray-300 rounded"
                min="0"
                max="100"
                step="0.1"
              />
            </div>
            
            <div class="flex justify-between">
              <span>Tiền chiết khấu:</span>
              <span class="text-red-600">-{{ formatCurrency(discountAmount) }}</span>
            </div>
            
            <div class="border-t pt-2">
              <div class="flex justify-between font-bold text-lg">
                <span>KHÁCH PHẢI TRẢ:</span>
                <span class="text-blue-600">{{ formatCurrency(totalAmount) }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Payment Section -->
        <div class="flex-1 p-4">
          <h3 class="font-semibold mb-3">💳 Thanh toán</h3>
          
          <div class="space-y-3">
            <div>
              <label class="block text-sm font-medium mb-1">Phương thức thanh toán</label>
              <select v-model="paymentMethod" class="w-full px-3 py-2 border border-gray-300 rounded">
                <option value="cash">💵 Tiền mặt</option>
                <option value="transfer">🏦 Chuyển khoản</option>
                <option value="card">💳 Thẻ tín dụng</option>
                <option value="wallet">📱 Ví điện tử</option>
                <option value="debt">📋 Công nợ</option>
              </select>
            </div>
            
            <div>
              <label class="block text-sm font-medium mb-1">Tiền khách đưa (F2)</label>
              <input
                ref="paidAmountInput"
                type="number"
                v-model.number="paidAmount"
                @input="calculateChange"
                class="w-full px-3 py-2 text-lg border border-gray-300 rounded text-right"
                min="0"
                :placeholder="formatCurrency(totalAmount)"
              />
            </div>
            
            <!-- Quick Amount Buttons -->
            <div class="grid grid-cols-3 gap-2">
              <button 
                v-for="amount in quickAmounts" 
                :key="amount"
                @click="setQuickAmount(amount)"
                class="px-3 py-2 bg-gray-100 hover:bg-gray-200 rounded text-sm"
              >
                {{ formatCurrency(amount) }}
              </button>
            </div>
            
            <div v-if="paidAmount > 0" class="bg-green-50 p-3 rounded">
              <div class="flex justify-between">
                <span>Tiền thừa trả khách:</span>
                <span class="font-bold text-green-600">{{ formatCurrency(changeAmount) }}</span>
              </div>
            </div>
            
            <div v-if="debtAmount > 0" class="bg-yellow-50 p-3 rounded">
              <div class="flex justify-between">
                <span>Còn nợ:</span>
                <span class="font-bold text-yellow-600">{{ formatCurrency(debtAmount) }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="p-4 border-t bg-gray-50 space-y-3">
          <!-- Main Action Button -->
          <button 
            @click="processPayment"
            :disabled="!canProcessPayment"
            class="w-full py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed font-semibold text-lg"
          >
            💰 THANH TOÁN (F1)
          </button>
          
          <!-- Second Row -->
          <div class="grid grid-cols-2 gap-2">
            <button 
              @click="clearCurrentTab"
              class="px-3 py-2 bg-gray-100 text-gray-700 rounded hover:bg-gray-200 text-sm"
            >
              🗑️ Xóa đơn
            </button>
            <button 
              @click="viewOrderList"
              class="px-3 py-2 bg-gray-100 text-gray-700 rounded hover:bg-gray-200 text-sm"
            >
              📋 Danh sách đơn
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Loading Overlay -->
    <div v-if="loading" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg p-6 text-center">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500 mx-auto mb-4"></div>
        <div>Đang xử lý...</div>
      </div>
    </div>

    <!-- Success Modal -->
    <div v-if="showSuccessModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg p-6 w-full max-w-md text-center">
        <div class="text-6xl mb-4">✅</div>
        <h3 class="text-lg font-semibold mb-2">Thanh toán thành công!</h3>
        <p class="text-gray-600 mb-4">Đơn hàng {{ successOrder?.code }} đã được tạo</p>
        <div class="space-y-2">
          <button 
            @click="printInvoice"
            class="w-full py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
          >
            🖨️ In hóa đơn
          </button>
          <button 
            @click="closeSuccessModal"
            class="w-full py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300"
          >
            Đóng
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, computed, onMounted, nextTick } from 'vue'

export default {
  name: 'POSSystem',
  setup() {
    // Reactive data
    const loading = ref(false)
    const selectedWarehouse = ref(null)
    const currentShift = ref('Ca sáng')
    const currentTime = ref('')
    const showSettings = ref(false)
    const showSuccessModal = ref(false)
    const successOrder = ref(null)

    // Product search
    const productSearch = ref('')
    const productResults = ref([])
    const selectedProductIndex = ref(0)

    // Customer search
    const customerSearch = ref('')
    const customerResults = ref([])
    const selectedCustomer = ref(null)

    // Order tabs
    const orderTabs = ref([
      {
        id: 1,
        name: 'Đơn 1',
        items: [],
        vatPercent: 0,
        discountPercent: 0
      }
    ])
    const currentTabIndex = ref(0)

    // Payment
    const paymentMethod = ref('cash')
    const paidAmount = ref(0)

    // Computed
    const currentTab = computed(() => orderTabs.value[currentTabIndex.value])
    
    const orderSubTotal = computed(() => {
      return currentTab.value.items.reduce((total, item) => {
        return total + (item.total || 0)
      }, 0)
    })

    const vatAmount = computed(() => {
  const subtotal = Number(orderSubTotal.value) || 0
  const percent = Number(currentTab.value.vatPercent) || 0
  const vat = (subtotal * percent) / 100
  
  console.log('📈 VAT calculation:', { subtotal, percent, vat })
  return vat
})


    const discountAmount = computed(() => {
  const subtotal = Number(orderSubTotal.value) || 0
  const percent = Number(currentTab.value.discountPercent) || 0
  const discount = (subtotal * percent) / 100
  
  console.log('💸 Discount calculation:', { subtotal, percent, discount })
  return discount
})

    const totalAmount = computed(() => {
  const subtotal = Number(orderSubTotal.value) || 0
  const vat = Number(vatAmount.value) || 0
  const discount = Number(discountAmount.value) || 0
  const total = subtotal + vat - discount
  
  console.log('💰 Total calculation:', {
    subtotal,
    vat, 
    discount,
    total
  })
  
  return total
})

    const changeAmount = computed(() => {
      return Math.max(0, paidAmount.value - totalAmount.value)
    })

    const debtAmount = computed(() => {
      return Math.max(0, totalAmount.value - paidAmount.value)
    })

    const canProcessPayment = computed(() => {
      return currentTab.value.items.length > 0 && totalAmount.value > 0
    })

    const quickAmounts = computed(() => {
      const base = Math.ceil(totalAmount.value / 1000) * 1000
      return [
        totalAmount.value,
        base,
        base + 50000,
        base + 100000,
        500000,
        1000000
      ].filter((amount, index, arr) => arr.indexOf(amount) === index && amount > 0)
        .sort((a, b) => a - b)
        .slice(0, 6)
    })

    // Methods
    const updateTime = () => {
      currentTime.value = new Date().toLocaleTimeString('vi-VN')
    }

    const initPOS = async () => {
      try {
        // Load warehouses from window config
        if (window.posConfig?.warehouses) {
          selectedWarehouse.value = window.posConfig.defaultWarehouse
        }
        
        // Set up time update
        updateTime()
        setInterval(updateTime, 1000)
        
        // Focus on product search
        await nextTick()
        if (this.$refs?.productSearchInput) {
          this.$refs.productSearchInput.focus()
        }
      } catch (error) {
        console.error('Error initializing POS:', error)
      }
    }

    const searchProducts = async () => {
      if (productSearch.value.length < 2) {
        productResults.value = []
        return
      }

      try {
        const response = await fetch(`/api/pos/products/search?search=${encodeURIComponent(productSearch.value)}&warehouse_id=${selectedWarehouse.value?.id}`, {
          headers: {
            'Authorization': `Bearer ${window.posConfig?.apiToken}`,
            'Accept': 'application/json'
          }
        })
        
        const data = await response.json()
        if (data.success) {
          productResults.value = data.data
          selectedProductIndex.value = 0
        }
      } catch (error) {
        console.error('Error searching products:', error)
      }
    }

    const searchCustomers = async () => {
      if (customerSearch.value.length < 2) {
        customerResults.value = []
        return
      }

      try {
        const response = await fetch(`/api/pos/customers/search?search=${encodeURIComponent(customerSearch.value)}`, {
          headers: {
            'Authorization': `Bearer ${window.posConfig?.apiToken}`,
            'Accept': 'application/json'
          }
        })
        
        const data = await response.json()
        if (data.success) {
          customerResults.value = data.data
        }
      } catch (error) {
        console.error('Error searching customers:', error)
      }
    }

    const addProduct = (product) => {
      const existingIndex = currentTab.value.items.findIndex(item => item.id === product.id)
      
      if (existingIndex >= 0) {
        currentTab.value.items[existingIndex].quantity += 1
        updateItemTotal(existingIndex)
      } else {
        currentTab.value.items.push({
          id: product.id,
          name: product.name,
          sku: product.sku,
          quantity: 1,
          price: product.retail_price,
          total: product.retail_price,
          stock: product.stock
        })
      }
      
      productSearch.value = ''
      productResults.value = []
    }

    const addFirstProduct = () => {
      if (productResults.value.length > 0) {
        addProduct(productResults.value[0])
      }
    }

    const updateItemTotal = (index) => {
      const item = currentTab.value.items[index]
      item.total = item.quantity * item.price
      calculateTotals()
    }

    const increaseQuantity = (index) => {
      currentTab.value.items[index].quantity += 1
      updateItemTotal(index)
    }

    const decreaseQuantity = (index) => {
      if (currentTab.value.items[index].quantity > 1) {
        currentTab.value.items[index].quantity -= 1
        updateItemTotal(index)
      }
    }

    const removeItem = (index) => {
      currentTab.value.items.splice(index, 1)
      calculateTotals()
    }

    const selectCustomer = (customer) => {
      selectedCustomer.value = customer
      customerSearch.value = customer.name
      customerResults.value = []
    }

    const clearCustomer = () => {
      selectedCustomer.value = null
      customerSearch.value = ''
    }

    const useRetailCustomer = () => {
      selectedCustomer.value = {
        id: null,
        name: 'Khách hàng bán lẻ',
        code: 'KHBLE',
        phone: '',
        total_debt: 0
      }
    }

    const calculateTotals = () => {
      // Triggers computed properties to recalculate
    }

    const calculateChange = () => {
      // Triggers computed properties to recalculate
    }

    const setQuickAmount = (amount) => {
      paidAmount.value = amount
    }

    const switchTab = (index) => {
      currentTabIndex.value = index
    }

    const addNewTab = () => {
      const newTabId = Math.max(...orderTabs.value.map(t => t.id)) + 1
      orderTabs.value.push({
        id: newTabId,
        name: `Đơn ${newTabId}`,
        items: [],
        vatPercent: 0,
        discountPercent: 0
      })
      currentTabIndex.value = orderTabs.value.length - 1
    }

    const closeTab = (index) => {
      if (orderTabs.value.length > 1) {
        orderTabs.value.splice(index, 1)
        if (currentTabIndex.value >= index && currentTabIndex.value > 0) {
          currentTabIndex.value -= 1
        }
      }
    }

    const clearCurrentTab = () => {
      if (confirm('Bạn có chắc muốn xóa đơn hàng này?')) {
        currentTab.value.items = []
        currentTab.value.vatPercent = 0
        currentTab.value.discountPercent = 0
        paidAmount.value = 0
        selectedCustomer.value = null
        customerSearch.value = ''
      }
    }

    const processPayment = async () => {
      if (!canProcessPayment.value) return

      loading.value = true
      try {
        const orderData = {
          customer_id: selectedCustomer.value?.id,
          warehouse_id: selectedWarehouse.value?.id,
          items: currentTab.value.items.map(item => ({
            product_id: item.id,
            quantity: item.quantity,
            price: item.price
          })),
          subtotal: orderSubTotal.value,
          discount_percent: currentTab.value.discountPercent,
          discount_amount: discountAmount.value,
          vat_percent: currentTab.value.vatPercent,
          vat_amount: vatAmount.value,
          total: totalAmount.value,
          paid: paidAmount.value,
          payment_method: paymentMethod.value,
          print_invoice: true
        }

        const response = await fetch('/api/pos/orders', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${window.posConfig?.apiToken}`,
            'Accept': 'application/json',
            'X-CSRF-TOKEN': window.posConfig?.csrfToken
          },
          body: JSON.stringify(orderData)
        })

        const data = await response.json()
        
        if (data.success) {
          successOrder.value = data.data.order
          showSuccessModal.value = true
          clearCurrentTab()
        } else {
          alert('Lỗi: ' + data.message)
        }
      } catch (error) {
        console.error('Error processing payment:', error)
        alert('Có lỗi xảy ra khi xử lý thanh toán')
      } finally {
        loading.value = false
      }
    }

    const printInvoice = () => {
      if (successOrder.value) {
        window.open(`/pos/${successOrder.value.id}/print`, '_blank')
      }
    }

    const closeSuccessModal = () => {
      showSuccessModal.value = false
      successOrder.value = null
    }

    const viewOrderList = () => {
      window.location.href = '/orders'
    }

    const formatCurrency = (amount) => {
  if (!amount || amount === 0) return '0 ₫'
  // Fix: Đảm bảo không bị nhân đôi
  return new Intl.NumberFormat('vi-VN').format(Math.round(amount)) + ' ₫'
}

    // Lifecycle
    onMounted(() => {
      initPOS()
    })

    return {
      loading,
      selectedWarehouse,
      currentShift,
      currentTime,
      showSettings,
      showSuccessModal,
      successOrder,
      productSearch,
      productResults,
      selectedProductIndex,
      customerSearch,
      customerResults,
      selectedCustomer,
      orderTabs,
      currentTabIndex,
      paymentMethod,
      paidAmount,
      currentTab,
      orderSubTotal,
      vatAmount,
      discountAmount,
      totalAmount,
      changeAmount,
      debtAmount,
      canProcessPayment,
      quickAmounts,
      updateTime,
      initPOS,
      searchProducts,
      searchCustomers,
      addProduct,
      addFirstProduct,
      updateItemTotal,
      increaseQuantity,
      decreaseQuantity,
      removeItem,
      selectCustomer,
      clearCustomer,
      useRetailCustomer,
      calculateTotals,
      calculateChange,
      setQuickAmount,
      switchTab,
      addNewTab,
      closeTab,
      clearCurrentTab,
      processPayment,
      printInvoice,
      closeSuccessModal,
      viewOrderList,
      formatCurrency
    }
  }
}
</script>

<style scoped>
/* Custom styles for POS */
.pos-system {
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

/* Hide scrollbar but keep functionality */
.overflow-y-auto::-webkit-scrollbar {
  width: 4px;
}

.overflow-y-auto::-webkit-scrollbar-track {
  background: #f1f1f1;
}

.overflow-y-auto::-webkit-scrollbar-thumb {
  background: #c1c1c1;
  border-radius: 2px;
}

.overflow-y-auto::-webkit-scrollbar-thumb:hover {
  background: #a8a8a8;
}

/* Input focus styles */
input:focus, select:focus {
  outline: none;
  ring: 2px;
  ring-color: #3b82f6;
}

/* Button hover effects */
button:hover {
  transform: translateY(-1px);
  transition: transform 0.1s ease;
}

button:active {
  transform: translateY(0);
}

/* Animation for modals */
.fixed.inset-0 {
  animation: fadeIn 0.2s ease-out;
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

/* Tab styles */
.border-b-2.border-blue-500 {
  border-bottom-width: 3px;
}

/* Currency input styling */
input[type="number"] {
  -moz-appearance: textfield;
}

input[type="number"]::-webkit-outer-spin-button,
input[type="number"]::-webkit-inner-spin-button {
  -webkit-appearance: none;
  margin: 0;
}
</style>