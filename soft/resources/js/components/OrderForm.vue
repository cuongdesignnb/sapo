<template>
    <div
        class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
    >
        <div
            class="bg-white rounded-lg w-full max-w-6xl max-h-screen overflow-y-auto"
        >
            <!-- Header -->
            <div class="flex items-center justify-between p-6 border-b">
                <h3 class="text-lg font-semibold">
                    {{ isEdit ? "Chỉnh sửa đơn hàng" : "Tạo đơn hàng mới" }}
                </h3>
                <button
                    @click="$emit('close')"
                    class="text-gray-400 hover:text-gray-600"
                >
                    ×
                </button>
            </div>

            <!-- Form -->
            <div class="p-6">
                <form @submit.prevent="handleSubmit" class="space-y-6">
                    <!-- Customer Selection -->
                    <div class="grid grid-cols-2 gap-6">
                        <div>
                            <label class="form-label">Khách hàng *</label>
                            <div class="relative">
                                <input
                                    type="text"
                                    v-model="customerSearch"
                                    @input="searchCustomers"
                                    class="form-input"
                                    placeholder="Tìm khách hàng..."
                                    autocomplete="off"
                                />

                                <!-- Customer Results -->
                                <div
                                    v-if="customerResults.length > 0"
                                    class="absolute z-10 w-full bg-white border border-gray-300 rounded-md shadow-lg mt-1 max-h-60 overflow-y-auto"
                                >
                                    <div
                                        v-for="customer in customerResults"
                                        :key="customer.id"
                                        @click="selectCustomer(customer)"
                                        class="p-3 hover:bg-gray-50 cursor-pointer border-b"
                                    >
                                        <div class="font-medium">
                                            {{ customer.name }}
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            {{ customer.phone }} -
                                            {{ customer.code }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-if="errors.customer_id" class="form-error">
                                {{ errors.customer_id }}
                            </div>

                            <!-- Selected Customer Info -->
                            <div
                                v-if="selectedCustomer"
                                class="mt-2 p-3 bg-blue-50 rounded-lg"
                            >
                                <div class="text-sm font-medium text-blue-900">
                                    {{ selectedCustomer.name }}
                                </div>
                                <div class="text-sm text-blue-700">
                                    {{ selectedCustomer.phone }} -
                                    {{ selectedCustomer.code }}
                                </div>
                                <div
                                    v-if="selectedCustomer.total_debt > 0"
                                    class="text-sm text-red-600"
                                >
                                    Công nợ:
                                    {{
                                        formatCurrency(
                                            selectedCustomer.total_debt,
                                        )
                                    }}
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="form-label">Kho *</label>
                            <select
                                v-model="form.warehouse_id"
                                class="form-input"
                                required
                            >
                                <option value="">Chọn kho</option>
                                <option
                                    v-for="warehouse in warehouses"
                                    :key="warehouse.id"
                                    :value="warehouse.id"
                                >
                                    {{ warehouse.name }}
                                </option>
                            </select>
                            <div v-if="errors.warehouse_id" class="form-error">
                                {{ errors.warehouse_id }}
                            </div>
                        </div>
                    </div>

                    <!-- Products Section -->
                    <div>
                        <label class="form-label">Sản phẩm *</label>
                        <div class="relative mb-4">
                            <input
                                type="text"
                                v-model="productSearch"
                                @input="searchProducts"
                                class="form-input"
                                placeholder="Tìm sản phẩm để thêm..."
                                autocomplete="off"
                            />

                            <!-- Product Results -->
                            <div
                                v-if="productResults.length > 0"
                                class="absolute z-10 w-full bg-white border border-gray-300 rounded-md shadow-lg mt-1 max-h-60 overflow-y-auto"
                            >
                                <div
                                    v-for="product in productResults"
                                    :key="product.id"
                                    @click="addProduct(product)"
                                    class="p-3 hover:bg-gray-50 cursor-pointer border-b"
                                >
                                    <div class="font-medium">
                                        {{ product.name }}
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        {{ product.sku }} -
                                        {{
                                            formatCurrency(
                                                product.retail_price || 0,
                                            )
                                        }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Selected Products Table -->
                        <div
                            v-if="form.items.length > 0"
                            class="border rounded-lg overflow-hidden"
                        >
                            <table class="w-full">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th
                                            class="text-left p-3 text-sm font-medium text-gray-600"
                                        >
                                            Sản phẩm
                                        </th>
                                        <th
                                            class="text-left p-3 text-sm font-medium text-gray-600"
                                        >
                                            Số lượng
                                        </th>
                                        <th
                                            class="text-left p-3 text-sm font-medium text-gray-600"
                                        >
                                            Đơn giá
                                        </th>
                                        <th
                                            class="text-left p-3 text-sm font-medium text-gray-600"
                                        >
                                            Thành tiền
                                        </th>
                                        <th
                                            class="text-left p-3 text-sm font-medium text-gray-600"
                                        >
                                            Thao tác
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template
                                        v-for="(item, index) in form.items"
                                        :key="index"
                                    >
                                        <tr class="border-t">
                                            <td class="p-3">
                                                <div>
                                                    {{ item.product_name }}
                                                </div>
                                                <span
                                                    v-if="item.track_serial"
                                                    class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 mt-1"
                                                >
                                                    📱 Serial/IMEI
                                                </span>
                                            </td>
                                            <td class="p-3">
                                                <input
                                                    v-if="!item.track_serial"
                                                    type="number"
                                                    v-model.number="
                                                        item.quantity
                                                    "
                                                    @input="
                                                        updateItemTotal(index)
                                                    "
                                                    class="w-20 px-2 py-1 border rounded"
                                                    min="1"
                                                />
                                                <span
                                                    v-else
                                                    class="text-sm font-medium"
                                                >
                                                    {{
                                                        item.serial_ids.length
                                                    }}
                                                    chiếc
                                                </span>
                                            </td>
                                            <td class="p-3">
                                                <input
                                                    type="number"
                                                    v-model.number="item.price"
                                                    @input="
                                                        updateItemTotal(index)
                                                    "
                                                    class="w-24 px-2 py-1 border rounded"
                                                    min="0"
                                                    step="1000"
                                                />
                                            </td>
                                            <td class="p-3">
                                                {{ formatCurrency(item.total) }}
                                            </td>
                                            <td class="p-3">
                                                <div
                                                    class="flex items-center gap-2"
                                                >
                                                    <button
                                                        v-if="item.track_serial"
                                                        type="button"
                                                        @click="
                                                            toggleSerialPicker(
                                                                index,
                                                            )
                                                        "
                                                        class="text-blue-600 hover:text-blue-800 text-sm whitespace-nowrap"
                                                    >
                                                        {{
                                                            item.show_serial_picker
                                                                ? "✕ Đóng"
                                                                : "📱 Chọn Serial"
                                                        }}
                                                    </button>
                                                    <button
                                                        type="button"
                                                        @click="
                                                            removeProduct(index)
                                                        "
                                                        class="text-red-500 hover:text-red-700"
                                                    >
                                                        Xóa
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <!-- Serial picker row -->
                                        <tr
                                            v-if="
                                                item.track_serial &&
                                                item.show_serial_picker
                                            "
                                            class="border-t bg-blue-50"
                                        >
                                            <td colspan="5" class="p-3">
                                                <div
                                                    class="mb-2 text-sm font-medium text-blue-800"
                                                >
                                                    Chọn Serial/IMEI (Kho đã
                                                    chọn) — Đã chọn:
                                                    {{ item.serial_ids.length }}
                                                </div>
                                                <div
                                                    v-if="item.loading_serials"
                                                    class="text-sm text-gray-500 py-2"
                                                >
                                                    ⏳ Đang tải danh sách
                                                    serial...
                                                </div>
                                                <div
                                                    v-else-if="
                                                        !form.warehouse_id
                                                    "
                                                    class="text-sm text-yellow-600 py-2"
                                                >
                                                    ⚠ Vui lòng chọn kho trước
                                                    khi chọn serial
                                                </div>
                                                <div
                                                    v-else-if="
                                                        item.available_serials
                                                            .length === 0
                                                    "
                                                    class="text-sm text-gray-500 py-2"
                                                >
                                                    Không có serial nào khả dụng
                                                    trong kho này
                                                </div>
                                                <div
                                                    v-else
                                                    class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2 max-h-48 overflow-y-auto"
                                                >
                                                    <label
                                                        v-for="serial in item.available_serials"
                                                        :key="serial.id"
                                                        class="flex items-center gap-2 p-2 rounded border cursor-pointer transition-colors"
                                                        :class="
                                                            isSerialSelected(
                                                                item,
                                                                serial.id,
                                                            )
                                                                ? 'bg-blue-100 border-blue-400'
                                                                : 'bg-white border-gray-200 hover:bg-gray-50'
                                                        "
                                                    >
                                                        <input
                                                            type="checkbox"
                                                            :checked="
                                                                isSerialSelected(
                                                                    item,
                                                                    serial.id,
                                                                )
                                                            "
                                                            @change="
                                                                toggleSerial(
                                                                    index,
                                                                    serial,
                                                                )
                                                            "
                                                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                                        />
                                                        <span
                                                            class="text-sm font-mono"
                                                            >{{
                                                                serial.serial_number
                                                            }}</span
                                                        >
                                                    </label>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                        <div v-if="errors.items" class="form-error">
                            {{ errors.items }}
                        </div>
                    </div>

                    <!-- Order Details -->
                    <div class="grid grid-cols-2 gap-6">
                        <div>
                            <label class="form-label">Địa chỉ giao hàng</label>
                            <textarea
                                v-model="form.delivery_address"
                                class="form-input"
                                rows="3"
                                placeholder="Nhập địa chỉ giao hàng..."
                            ></textarea>
                        </div>

                        <div class="space-y-4">
                            <div>
                                <label class="form-label">Người nhận</label>
                                <input
                                    type="text"
                                    v-model="form.delivery_contact"
                                    class="form-input"
                                    placeholder="Tên người nhận"
                                />
                            </div>
                            <div>
                                <label class="form-label">SĐT người nhận</label>
                                <input
                                    type="text"
                                    v-model="form.delivery_phone"
                                    class="form-input"
                                    placeholder="Số điện thoại người nhận"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Payment & Notes -->
                    <div class="grid grid-cols-3 gap-6">
                        <div>
                            <label class="form-label"
                                >Phương thức thanh toán</label
                            >
                            <select
                                v-model="form.payment_method"
                                class="form-input"
                            >
                                <option value="cash">Tiền mặt</option>
                                <option value="transfer">Chuyển khoản</option>
                                <option value="card">Thẻ</option>
                                <option value="wallet">Ví điện tử</option>
                                <option value="debt">Công nợ</option>
                            </select>
                        </div>

                        <div>
                            <label class="form-label"
                                >Số tiền đã thanh toán</label
                            >
                            <input
                                type="number"
                                v-model.number="form.paid"
                                class="form-input"
                                min="0"
                                step="1000"
                                :max="totalAmount"
                            />
                        </div>

                        <div>
                            <label class="form-label">Độ ưu tiên</label>
                            <select v-model="form.priority" class="form-input">
                                <option value="low">Thấp</option>
                                <option value="normal">Bình thường</option>
                                <option value="high">Cao</option>
                                <option value="urgent">Khẩn cấp</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="form-label">Ghi chú</label>
                        <textarea
                            v-model="form.note"
                            class="form-input"
                            rows="3"
                            placeholder="Ghi chú cho đơn hàng..."
                        ></textarea>
                    </div>

                    <div>
                        <label class="form-label">Tags</label>
                        <input
                            type="text"
                            v-model="form.tags"
                            class="form-input"
                            placeholder="Nhập tags, cách nhau bằng dấu phẩy"
                        />
                    </div>

                    <!-- Order Summary -->
                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="grid grid-cols-3 gap-4 text-sm">
                            <div>
                                <span class="text-gray-600">Tổng tiền:</span>
                                <span class="ml-2 font-medium">{{
                                    formatCurrency(totalAmount)
                                }}</span>
                            </div>
                            <div>
                                <span class="text-gray-600"
                                    >Đã thanh toán:</span
                                >
                                <span class="ml-2 font-medium">{{
                                    formatCurrency(form.paid)
                                }}</span>
                            </div>
                            <div>
                                <span class="text-gray-600">Còn nợ:</span>
                                <span class="ml-2 font-medium text-red-600">{{
                                    formatCurrency(
                                        Math.max(0, totalAmount - form.paid),
                                    )
                                }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="flex justify-end space-x-3 pt-6 border-t">
                        <button
                            type="button"
                            @click="$emit('close')"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300"
                        >
                            Hủy
                        </button>
                        <button
                            type="submit"
                            :disabled="loading"
                            class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 disabled:opacity-50"
                        >
                            <span v-if="loading">⏳ Đang lưu...</span>
                            <span v-else>{{
                                isEdit ? "💾 Cập nhật" : "➕ Tạo đơn hàng"
                            }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, computed, watch, onMounted } from "vue";
import { orderHelpers } from "../api/orderApi";
import { productSerialApi } from "../api/productSerialApi";

export default {
    name: "OrderForm",
    props: {
        order: {
            type: Object,
            default: null,
        },
    },
    emits: ["close", "save"],
    setup(props, { emit }) {
        const loading = ref(false);
        const errors = ref({});
        const customerSearch = ref("");
        const productSearch = ref("");
        const customerResults = ref([]);
        const productResults = ref([]);
        const selectedCustomer = ref(null);
        const warehouses = ref([]);

        // Form data WITHOUT shipping fields
        const form = ref({
            customer_id: null,
            warehouse_id: null,
            items: [],
            delivery_address: "",
            delivery_contact: "",
            delivery_phone: "",
            delivery_date: "",
            payment_method: "cash",
            paid: 0,
            note: "",
            tags: "",
            source: "Web",
            priority: "normal",
        });

        // Computed
        const isEdit = computed(() => {
            return props.order && props.order.id;
        });

        const totalAmount = computed(() => {
            return form.value.items.reduce((total, item) => {
                const itemTotal = (item.quantity || 0) * (item.price || 0);
                return total + itemTotal;
            }, 0);
        });

        // Methods
        const initForm = async () => {
            if (props.order) {
                // Edit mode
                form.value = {
                    customer_id: props.order.customer_id,
                    warehouse_id: props.order.warehouse_id,
                    items:
                        props.order.items?.map((item) => ({
                            product_id: item.product_id,
                            product_name:
                                item.product_name || item.product?.name,
                            sku: item.sku || item.product?.sku,
                            quantity: item.quantity,
                            price: item.price,
                            total: item.total,
                        })) || [],
                    delivery_address: props.order.delivery_address || "",
                    delivery_contact: props.order.delivery_contact || "",
                    delivery_phone: props.order.delivery_phone || "",
                    delivery_date: props.order.delivery_date || "",
                    payment_method: props.order.payment_method || "cash",
                    paid: props.order.paid || 0,
                    note: props.order.note || "",
                    tags: props.order.tags || "",
                    source: props.order.source || "Web",
                    priority: props.order.priority || "normal",
                };

                // Load customer with latest debt
                if (props.order.customer_id) {
                    await loadCustomerWithDebt(props.order.customer_id);
                } else {
                    selectedCustomer.value = props.order.customer;
                }
            } else {
                // Create mode
                form.value = {
                    customer_id: null,
                    warehouse_id: null,
                    items: [],
                    delivery_address: "",
                    delivery_contact: "",
                    delivery_phone: "",
                    delivery_date: "",
                    payment_method: "cash",
                    paid: 0,
                    note: "",
                    tags: "",
                    source: "Web",
                    priority: "normal",
                };
                selectedCustomer.value = null;
            }
            errors.value = {};
        };

        // Load customer with latest debt
        const loadCustomerWithDebt = async (customerId) => {
            try {
                console.log("🔍 Loading customer ID:", customerId);

                const response = await fetch(`/api/customers/${customerId}`, {
                    headers: {
                        Authorization: `Bearer ${sessionStorage.getItem("api_token") || document.querySelector('meta[name="api-token"]')?.getAttribute("content")}`,
                        Accept: "application/json",
                    },
                });

                console.log(
                    "📡 Customer API response status:",
                    response.status,
                );

                if (response.ok) {
                    const data = await response.json();
                    console.log("📊 Customer API data:", data);

                    if (data.success && data.data) {
                        console.log(
                            "💰 Customer total_debt:",
                            data.data.total_debt,
                        );
                        selectedCustomer.value = data.data;
                    } else {
                        selectedCustomer.value = props.order.customer;
                    }
                } else {
                    selectedCustomer.value = props.order.customer;
                }
            } catch (error) {
                console.error("Error loading customer debt:", error);
                selectedCustomer.value = props.order.customer;
            }
        };

        const searchCustomers = async () => {
            if (customerSearch.value.length >= 2) {
                try {
                    console.log(
                        "🔍 Searching customers for:",
                        customerSearch.value,
                    );

                    const response = await fetch(
                        "/api/customers?" +
                            new URLSearchParams({
                                search: customerSearch.value,
                                per_page: 10,
                            }),
                        {
                            headers: {
                                Authorization: `Bearer ${sessionStorage.getItem("api_token") || document.querySelector('meta[name="api-token"]')?.getAttribute("content")}`,
                                Accept: "application/json",
                            },
                        },
                    );

                    console.log(
                        "📡 Customer search response status:",
                        response.status,
                    );

                    if (response.ok) {
                        const data = await response.json();
                        console.log("📊 Customer search data:", data);

                        // Kiểm tra cấu trúc response - API trả về paginated data
                        if (
                            data.success &&
                            data.data &&
                            Array.isArray(data.data.data)
                        ) {
                            customerResults.value = data.data.data.map((c) => ({
                                id: c.id,
                                name: c.name,
                                phone: c.phone,
                                code: c.code,
                                total_debt: c.total_debt || 0,
                            }));
                        } else {
                            console.warn(
                                "⚠️ Unexpected customer data structure:",
                                data,
                            );
                            customerResults.value = [];
                        }
                    } else {
                        console.error(
                            "❌ Customer search failed:",
                            response.status,
                            response.statusText,
                        );
                        customerResults.value = [];
                    }
                } catch (error) {
                    console.error("💥 Error searching customers:", error);
                    customerResults.value = [];
                }
            } else {
                customerResults.value = [];
            }
        };

        const selectCustomer = (customer) => {
            selectedCustomer.value = customer;
            form.value.customer_id = customer.id;
            customerSearch.value = customer.name;
            customerResults.value = [];

            // Auto-fill delivery info
            form.value.delivery_contact = customer.name;
            form.value.delivery_phone = customer.phone;
        };

        const searchProducts = async () => {
            if (productSearch.value.length >= 2) {
                try {
                    console.log(
                        "🔍 Searching products for:",
                        productSearch.value,
                    );

                    const response = await fetch(
                        "/api/products?" +
                            new URLSearchParams({
                                search: productSearch.value,
                                per_page: 10,
                            }),
                        {
                            headers: {
                                Authorization: `Bearer ${sessionStorage.getItem("api_token") || document.querySelector('meta[name="api-token"]')?.getAttribute("content")}`,
                                Accept: "application/json",
                            },
                        },
                    );

                    console.log(
                        "📡 Product search response status:",
                        response.status,
                    );

                    if (response.ok) {
                        const data = await response.json();
                        console.log("📊 Product search data:", data);

                        // Kiểm tra cấu trúc response - Product API trả về direct array
                        if (data.success && Array.isArray(data.data)) {
                            productResults.value = data.data.map((p) => ({
                                id: p.id,
                                name: p.name,
                                sku: p.sku,
                                price: p.retail_price || 0,
                                stock: p.available_stock || p.quantity || 0,
                                track_serial: p.track_serial || false,
                            }));
                        } else {
                            console.warn(
                                "⚠️ Unexpected product data structure:",
                                data,
                            );
                            productResults.value = [];
                        }
                    } else {
                        console.error(
                            "❌ Product search failed:",
                            response.status,
                            response.statusText,
                        );
                        productResults.value = [];
                    }
                } catch (error) {
                    console.error("💥 Error searching products:", error);
                    productResults.value = [];
                }
            } else {
                productResults.value = [];
            }
        };

        const addProduct = (product) => {
            // Check if product already exists
            const existingIndex = form.value.items.findIndex(
                (item) => item.product_id === product.id,
            );

            if (existingIndex >= 0) {
                if (!product.track_serial) {
                    // Increase quantity only for non-serial products
                    form.value.items[existingIndex].quantity += 1;
                    updateItemTotal(existingIndex);
                }
            } else {
                // Add new product
                form.value.items.push({
                    product_id: product.id,
                    product_name: product.name,
                    sku: product.sku,
                    quantity: product.track_serial ? 0 : 1,
                    price: product.price,
                    total: product.track_serial ? 0 : product.price,
                    track_serial: product.track_serial || false,
                    serial_ids: [],
                    available_serials: [],
                    loading_serials: false,
                    show_serial_picker: false,
                });

                // Auto-load available serials if serial-tracked
                if (product.track_serial && form.value.warehouse_id) {
                    const idx = form.value.items.length - 1;
                    loadAvailableSerials(idx);
                }
            }

            productSearch.value = "";
            productResults.value = [];
        };

        const removeProduct = (index) => {
            form.value.items.splice(index, 1);
        };

        const updateItemTotal = (index) => {
            const item = form.value.items[index];
            item.total = (item.quantity || 0) * (item.price || 0);

            // Debug log để kiểm tra
            console.log(`📊 Updated item ${index}:`, {
                quantity: item.quantity,
                price: item.price,
                total: item.total,
            });
        };

        // Serial/IMEI methods
        const loadAvailableSerials = async (index) => {
            const item = form.value.items[index];
            if (!item || !item.track_serial) return;

            const warehouseId = form.value.warehouse_id;
            if (!warehouseId) return;

            item.loading_serials = true;
            try {
                const response = await productSerialApi.getAvailable(
                    item.product_id,
                    warehouseId,
                );
                if (response.success) {
                    item.available_serials = response.data || [];
                }
            } catch (error) {
                console.error("Error loading serials:", error);
                item.available_serials = [];
            } finally {
                item.loading_serials = false;
            }
        };

        const toggleSerialPicker = (index) => {
            const item = form.value.items[index];
            item.show_serial_picker = !item.show_serial_picker;
            if (
                item.show_serial_picker &&
                item.available_serials.length === 0
            ) {
                loadAvailableSerials(index);
            }
        };

        const toggleSerial = (index, serial) => {
            const item = form.value.items[index];
            const idx = item.serial_ids.indexOf(serial.id);
            if (idx >= 0) {
                item.serial_ids.splice(idx, 1);
            } else {
                item.serial_ids.push(serial.id);
            }
            // Auto-update quantity based on selected serials
            item.quantity = item.serial_ids.length;
            updateItemTotal(index);
        };

        const isSerialSelected = (item, serialId) => {
            return item.serial_ids && item.serial_ids.includes(serialId);
        };

        // Reload serials when warehouse changes
        const reloadSerialsForAllItems = () => {
            form.value.items.forEach((item, index) => {
                if (item.track_serial) {
                    item.serial_ids = [];
                    item.quantity = 0;
                    item.total = 0;
                    loadAvailableSerials(index);
                }
            });
        };

        const validateForm = () => {
            errors.value = {};

            if (!form.value.customer_id) {
                errors.value.customer_id = "Vui lòng chọn khách hàng";
            }

            if (!form.value.warehouse_id) {
                errors.value.warehouse_id = "Vui lòng chọn cửa hàng";
            }

            if (form.value.items.length === 0) {
                errors.value.items = "Vui lòng thêm ít nhất một sản phẩm";
            }

            // Check serial-tracked products have serials selected
            const serialItem = form.value.items.find(
                (item) => item.track_serial && item.serial_ids.length === 0,
            );
            if (serialItem) {
                errors.value.items = `Sản phẩm "${serialItem.product_name}" cần chọn Serial/IMEI`;
            }

            return Object.keys(errors.value).length === 0;
        };

        const handleSubmit = async () => {
            if (!validateForm()) {
                return;
            }

            loading.value = true;

            try {
                const formData = {
                    ...form.value,
                    items: form.value.items.map((item) => {
                        const mapped = {
                            product_id: item.product_id,
                            quantity: item.quantity,
                            price: item.price,
                            total: item.total,
                        };
                        if (item.track_serial && item.serial_ids?.length > 0) {
                            mapped.serial_ids = item.serial_ids;
                        }
                        return mapped;
                    }),
                    total: totalAmount.value,
                    debt: totalAmount.value - form.value.paid,
                };

                emit("save", formData);
            } catch (error) {
                console.error("Form submission error:", error);
            } finally {
                loading.value = false;
            }
        };

        const loadWarehouses = async () => {
            try {
                console.log("🏭 Loading warehouses...");

                const response = await fetch("/api/my-warehouses", {
                    headers: {
                        Authorization: `Bearer ${sessionStorage.getItem("api_token") || document.querySelector('meta[name="api-token"]')?.getAttribute("content")}`,
                        Accept: "application/json",
                    },
                });

                console.log("📡 Warehouse response status:", response.status);

                if (response.ok) {
                    const data = await response.json();
                    console.log("📊 Warehouse data:", data);

                    if (data.success && Array.isArray(data.data)) {
                        warehouses.value = data.data;
                    } else {
                        console.warn(
                            "⚠️ Unexpected warehouse data structure:",
                            data,
                        );
                        warehouses.value = [];
                    }
                } else {
                    console.error(
                        "❌ Warehouse loading failed:",
                        response.status,
                        response.statusText,
                    );
                }
            } catch (error) {
                console.error("💥 Error loading warehouses:", error);
            }
        };

        const formatCurrency = (amount) => {
            return new Intl.NumberFormat("vi-VN", {
                style: "currency",
                currency: "VND",
            }).format(amount || 0);
        };

        // Watch for changes
        watch(() => props.order, initForm, { immediate: true });

        // Watch warehouse change to reload serials
        watch(
            () => form.value.warehouse_id,
            (newVal, oldVal) => {
                if (newVal && newVal !== oldVal) {
                    reloadSerialsForAllItems();
                }
            },
        );

        // Initialize
        onMounted(() => {
            initForm();
            loadWarehouses();
        });

        return {
            loading,
            errors,
            customerSearch,
            productSearch,
            customerResults,
            productResults,
            selectedCustomer,
            warehouses,
            form,
            isEdit,
            totalAmount,
            initForm,
            loadCustomerWithDebt,
            searchCustomers,
            selectCustomer,
            searchProducts,
            addProduct,
            removeProduct,
            updateItemTotal,
            loadAvailableSerials,
            toggleSerialPicker,
            toggleSerial,
            isSerialSelected,
            validateForm,
            handleSubmit,
            formatCurrency,
        };
    },
};
</script>

<style scoped>
.form-input {
    @apply w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent;
}

.form-label {
    @apply block text-sm font-medium text-gray-700 mb-2;
}

.form-error {
    @apply text-red-500 text-sm mt-1;
}
</style>
