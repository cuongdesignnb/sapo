<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';

// State for search and products
const query = ref('');
const products = ref([]);
const isSearching = ref(false);

// State for the cart (giỏ hàng)
const cart = ref([]);

// Payment details
const discount = ref(0);
const taxRate = ref(0.1); // 10% VAT
const customerPaid = ref(0);

const currentTime = computed(() => {
    const now = new Date();
    return now.toLocaleString('vi-VN', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
});

// Fetch products based on search query
const searchProducts = async () => {
    isSearching.value = true;
    try {
        const response = await axios.get('/api/pos/products', {
            params: { search: query.value }
        });
        products.value = response.data;
    } catch (error) {
        console.error('Error fetching products:', error);
    } finally {
        isSearching.value = false;
    }
};

onMounted(() => {
    // Load initial products (default without query)
    searchProducts();
});

// Watch input changes with debounce for search
let timeout;
const handleSearchInput = () => {
    clearTimeout(timeout);
    timeout = setTimeout(() => {
        searchProducts();
    }, 400);
};

// Add product to cart
const addToCart = (product) => {
    const existingItem = cart.value.find(item => item.product.id === product.id);
    if (existingItem) {
        existingItem.quantity += 1;
    } else {
        cart.value.push({
            product: product,
            quantity: 1,
            price: product.retail_price
        });
    }
};

// Remove from cart
const removeFromCart = (index) => {
    cart.value.splice(index, 1);
};

// Update quantity
const updateQuantity = (index, delta) => {
    const newQty = cart.value[index].quantity + delta;
    if (newQty > 0) {
        // Option to check stock quantity here
        cart.value[index].quantity = newQty;
    } else {
        removeFromCart(index);
    }
};

// Computed totals
const subtotal = computed(() => {
    return cart.value.reduce((total, item) => total + (item.price * item.quantity), 0);
});

const calculatedDiscount = computed(() => {
    return discount.value;
});

const totalAmount = computed(() => {
    return subtotal.value - calculatedDiscount.value;
});

const changeDue = computed(() => {
    return (customerPaid.value > 0) ? (customerPaid.value - totalAmount.value) : 0;
});

const isCheckingOut = ref(false);
const toastMsg = ref('');

// Checkout action
const processCheckout = async () => {
    if (cart.value.length === 0) {
        alert("Giỏ hàng trống!");
        return;
    }

    if (isCheckingOut.value) return;
    isCheckingOut.value = true;

    try {
        const payload = {
            subtotal: subtotal.value,
            discount: discount.value,
            total: totalAmount.value,
            customer_paid: customerPaid.value,
            items: cart.value.map(item => ({
                product_id: item.product.id,
                quantity: item.quantity,
                price: item.price
            }))
        };

        const response = await axios.post('/api/pos/checkout', payload);
        
        if (response.data.success) {
            // Show toast message
            toastMsg.value = `${response.data.message} - Phiếu ${response.data.invoice_code}`;
            setTimeout(() => toastMsg.value = '', 4000);

            // Reset cart
            cart.value = [];
            discount.value = 0;
            customerPaid.value = 0;
            
            // Reload query logic to see if stock quantities have updated.
            searchProducts(); 
        } else {
            alert("Lỗi: " + response.data.message);
        }
    } catch(err) {
        console.error("Checkout Error:", err);
        alert("Lỗi khi kết nối tới máy chủ.");
    } finally {
        isCheckingOut.value = false;
    }
};
</script>

<template>
    <Head title="Bán Hàng (POS)" />

    <!-- Full screen POS UI -->
    <div class="flex flex-col h-screen overflow-hidden bg-gray-100">
        
        <!-- Top Navbar for POS -->
        <header class="bg-blue-600 text-white h-14 flex items-center justify-between px-4 shadow-md flex-shrink-0 z-10">
            <div class="flex items-center gap-4">
                <div class="font-bold text-lg flex items-center gap-2">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                    KiotViet POS
                </div>
                <!-- Search Bar -->
                <div class="relative w-96 ml-6 text-gray-800">
                    <input 
                        v-model="query" 
                        @input="handleSearchInput" 
                        type="text" 
                        placeholder="Thêm hàng hóa vào đơn (F3)" 
                        class="w-full pl-10 pr-4 py-1.5 rounded outline-none focus:ring-2 focus:ring-blue-300 text-sm"
                    >
                    <svg class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    
                    <!-- Quick Dropdown Search Results -->
                    <div v-if="query && products.length > 0" class="absolute left-0 right-0 top-full mt-1 bg-white shadow-lg rounded border border-gray-200 p-2 z-50 max-h-60 overflow-y-auto">
                        <div v-for="product in products" :key="'dd-'+product.id" @click="addToCart(product); query=''" class="flex items-center justify-between p-2 hover:bg-blue-50 cursor-pointer border-b border-gray-100 last:border-0">
                            <div>
                                <div class="font-semibold text-sm">{{ product.name }}</div>
                                <div class="text-xs text-gray-500">{{ product.sku }} | Tồn: {{ product.stock_quantity }}</div>
                            </div>
                            <div class="text-blue-600 font-bold text-sm">{{ Number(product.retail_price).toLocaleString() }} &curren;</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="flex items-center gap-3">
                <Link href="/" class="text-sm font-medium hover:bg-blue-700 px-3 py-1.5 rounded bg-blue-500 transition-colors flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    Về Quản lý
                </Link>
                <div class="flex items-center gap-2 cursor-pointer ml-2">
                    <div class="w-8 h-8 bg-blue-800 rounded-full flex items-center justify-center text-white font-bold text-sm">A</div>
                    <span class="text-sm font-medium">Admin</span>
                </div>
            </div>
        </header>

        <!-- Main Workspace: Split Left (Products/Cart) and Right (Payment Summary) -->
        <main class="flex-1 flex overflow-hidden">
            
            <!-- Left Side: Cart Items -->
            <div class="flex-1 flex flex-col bg-white overflow-hidden relative shadow-inner z-0 border-r border-gray-300">
                <!-- Data Header -->
                <div class="grid grid-cols-12 gap-4 px-4 py-2 border-b border-gray-200 bg-gray-50 font-semibold text-gray-600 text-xs text-center sticky top-0 shadow-sm z-10 uppercase tracking-wider">
                    <div class="col-span-1">Stt</div>
                    <div class="col-span-4 text-left">Tên hàng hóa</div>
                    <div class="col-span-2">Số lượng</div>
                    <div class="col-span-2 text-right">Đơn giá</div>
                    <div class="col-span-2 text-right">Thành tiền</div>
                    <div class="col-span-1"></div>
                </div>
                
                <!-- Cart List -->
                <div class="flex-1 overflow-y-auto p-2 space-y-2">
                    <div v-if="cart.length === 0" class="flex flex-col items-center justify-center h-full text-gray-400">
                        <svg class="w-16 h-16 mb-2 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                        <p class="font-medium">Chưa có sản phẩm nào trong đơn</p>
                    </div>
                    
                    <div v-for="(item, index) in cart" :key="index" class="grid grid-cols-12 gap-4 px-2 py-3 border-b border-gray-100 items-center hover:bg-gray-50 transition-colors bg-white rounded shadow-sm ring-1 ring-gray-900/5">
                        <div class="col-span-1 text-center font-medium text-gray-500">{{ index + 1 }}</div>
                        <div class="col-span-4 flex flex-col items-start leading-snug">
                            <span class="font-bold text-gray-800 text-sm overflow-hidden text-ellipsis line-clamp-2 w-full" :title="item.product.name">{{ item.product.name }}</span>
                            <span class="text-xs text-blue-600 font-medium tracking-wide mt-0.5">{{ item.product.sku }}</span>
                        </div>
                        
                        <div class="col-span-2 flex items-center justify-center bg-gray-100/50 rounded-lg p-1 w-fit mx-auto ring-1 ring-gray-300">
                            <button @click="updateQuantity(index, -1)" class="w-7 h-7 flex items-center justify-center rounded bg-white text-gray-600 hover:bg-red-50 hover:text-red-500 transition-colors shadow-sm cursor-pointer disabled:opacity-50">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 12H4"></path></svg>
                            </button>
                            <input type="text" readonly :value="item.quantity" class="w-10 text-center bg-transparent text-sm font-bold text-gray-800 focus:outline-none focus:ring-0 select-none">
                            <button @click="updateQuantity(index, 1)" class="w-7 h-7 flex items-center justify-center rounded bg-white text-gray-600 hover:bg-blue-50 hover:text-blue-600 transition-colors shadow-sm cursor-pointer disabled:opacity-50">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path></svg>
                            </button>
                        </div>
                        <div class="col-span-2 text-right">
                            <input type="number" v-model="item.price" class="w-full text-right outline-none bg-transparent border-b border-dashed border-gray-300 focus:border-blue-500 py-1 font-semibold text-gray-700">
                        </div>
                        <div class="col-span-2 text-right font-bold text-gray-900 text-[15px]">
                            {{ (item.price * item.quantity).toLocaleString() }}
                        </div>
                        <div class="col-span-1 flex justify-center">
                            <button @click="removeFromCart(index)" class="text-gray-400 hover:text-red-500 hover:bg-red-50 p-2 rounded-full transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Product Quick Pick (Optional, below cart) -->
                <div class="h-48 bg-white border-t border-gray-200 z-10 flex flex-col bg-gray-50/50">
                    <div class="font-semibold text-sm px-4 py-2 bg-gray-100/50 border-b border-gray-200 sticky top-0 uppercase tracking-wider text-gray-600">Gợi ý sản phẩm</div>
                    <div class="flex-1 overflow-x-auto p-3 flex gap-3 pb-4">
                        <div 
                            v-if="products.length === 0" 
                            class="text-sm text-gray-400 h-full flex items-center justify-center w-full"
                        >
                            <span v-if="isSearching">Đang tải...</span>
                            <span v-else>Không tìm thấy sản phẩm, gõ vào ô tìm kiếm...</span>
                        </div>
                        <div 
                            v-else
                            v-for="product in products" 
                            :key="'quick-'+product.id"
                            @click="addToCart(product)"
                            class="w-32 flex-none bg-white rounded border border-gray-200 p-2 hover:border-blue-500 hover:shadow-md cursor-pointer transition-all flex flex-col shadow-sm group"
                        >
                            <div class="flex-1 text-sm font-semibold text-gray-800 line-clamp-3 leading-snug group-hover:text-blue-700">{{ product.name }}</div>
                            <div class="text-blue-600 font-bold mt-2 font-mono text-sm tracking-tighter">{{ Number(product.retail_price).toLocaleString() }} ₫</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Side: Order Summary & Checkout -->
            <div class="w-80 lg:w-[400px] flex flex-col bg-white overflow-y-auto flex-shrink-0 z-10 shadow-[-2px_0_5px_-2px_rgba(0,0,0,0.1)] h-full">
                <!-- Customer info -->
                <div class="p-4 border-b border-gray-100 flex items-center justify-between">
                    <div class="flex items-center gap-2 flex-1 relative">
                        <svg class="w-5 h-5 text-gray-400 absolute left-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                        <input type="text" placeholder="Tìm khách hàng (F4)" class="w-full pl-8 py-2 text-sm border-b border-gray-300 focus:border-blue-500 outline-none transition-colors">
                    </div>
                    <button class="ml-2 bg-blue-50 text-blue-600 hover:bg-blue-100 w-8 h-8 rounded flex items-center justify-center transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path></svg>
                    </button>
                </div>

                <!-- Tabs (Invoice, Delivery) -->
                <div class="flex border-b border-gray-200">
                    <button class="flex-1 py-3 text-sm font-bold border-b-2 border-blue-600 text-blue-600 bg-blue-50/50">Hóa đơn 1</button>
                    <button class="flex-1 py-3 text-sm font-semibold text-gray-500 hover:text-gray-700 hover:bg-gray-50">+</button>
                </div>

                <!-- Invoice Details Calculation -->
                <div class="p-4 space-y-4 text-[15px] flex-1">
                    <div class="flex justify-between items-center text-gray-700 font-medium">
                        <span>Tổng tiền hàng</span>
                        <span class="font-bold">{{ subtotal.toLocaleString() }}</span>
                    </div>
                    
                    <div class="flex justify-between items-center text-gray-700 font-medium">
                        <span class="border-b border-dashed border-gray-400 cursor-pointer hover:text-blue-600 transition-colors">Giảm giá</span>
                        <div class="flex items-center">
                            <input type="number" v-model="discount" class="w-24 text-right border-b border-gray-300 focus:border-blue-500 outline-none pr-1">
                        </div>
                    </div>
                    
                    <div class="flex justify-between border-t border-gray-200 pt-3 text-gray-900 font-bold text-lg mt-1">
                        <span>Khách cần trả</span>
                        <span class="text-blue-700 tracking-tight text-xl">{{ totalAmount.toLocaleString() }}</span>
                    </div>

                    <div class="flex justify-between items-center pt-2 text-gray-700 font-medium">
                        <span>Khách thanh toán</span>
                        <input type="number" v-model="customerPaid" :placeholder="totalAmount" class="w-32 text-right border-b border-gray-300 focus:border-blue-500 outline-none font-bold text-gray-900">
                    </div>

                    <div class="flex justify-between items-center pb-2 text-gray-500 text-sm font-medium">
                        <span>Tiền thừa trả khách</span>
                        <span>{{ changeDue.toLocaleString() }}</span>
                    </div>
                    
                    <div class="mt-4 flex gap-2 w-full justify-between pb-6">
                         <div class="flex gap-2">
                             <button class="p-2 border border-gray-200 rounded text-gray-500 hover:bg-gray-50 hover:text-gray-700 tooltip" title="In Tạm Tính">
                                 <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                             </button>
                         </div>
                    </div>
                </div>

                <!-- Checkout Button -->
                <div class="mt-auto border-t border-gray-200 sticky bottom-0 z-20 relative">
                    <button 
                        @click="processCheckout" 
                        :disabled="isCheckingOut"
                        class="w-full bg-blue-600 hover:bg-blue-700 disabled:opacity-75 disabled:cursor-wait text-white font-bold text-lg py-5 flex items-center justify-center gap-2 transition-colors focus:ring-4 focus:ring-blue-300"
                    >
                        <span v-if="!isCheckingOut">Thanh toán</span>
                        <span v-else>Đang xử lý...</span>
                        <svg v-if="!isCheckingOut" class="w-6 h-6 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                    </button>
                    
                    <!-- POS Toast Notification inner right column -->
                    <transition
                        enter-active-class="transform transition ease-out duration-300"
                        enter-from-class="translate-y-full opacity-0"
                        enter-to-class="translate-y-0 opacity-100"
                        leave-active-class="transition ease-in duration-200"
                        leave-from-class="translate-y-0 opacity-100"
                        leave-to-class="translate-y-full opacity-0"
                    >
                        <div v-if="toastMsg" class="absolute bottom-20 right-4 left-4 bg-green-500 text-white p-3 rounded shadow-lg text-sm font-semibold flex items-center gap-2 z-50">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            {{ toastMsg }}
                        </div>
                    </transition>
                </div>
            </div>
        </main>
    </div>
</template>

<style scoped>
/* Chrome, Safari, Edge, Opera: Hide number arrows */
input::-webkit-outer-spin-button,
input::-webkit-inner-spin-button {
  -webkit-appearance: none;
  margin: 0;
}
/* Firefox: Hide number arrows */
input[type=number] {
  -moz-appearance: textfield;
}
</style>
