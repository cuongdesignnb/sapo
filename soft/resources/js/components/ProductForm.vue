<template>
    <!-- Modal Overlay -->
    <div
        class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4"
    >
        <div
            class="bg-white rounded-lg shadow-xl w-full max-w-2xl max-h-screen overflow-y-auto"
        >
            <!-- Header -->
            <div class="flex items-center justify-between p-6 border-b">
                <h2 class="text-xl font-semibold text-gray-900">
                    {{ isEdit ? "Sửa sản phẩm" : "Thêm sản phẩm mới" }}
                </h2>
                <button
                    @click="$emit('close')"
                    class="text-gray-400 hover:text-gray-600"
                >
                    <span class="text-2xl">&times;</span>
                </button>
            </div>

            <!-- Form -->
            <form @submit.prevent="handleSubmit" class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- SKU -->
                    <div>
                        <label class="form-label">Mã SKU *</label>
                        <input
                            type="text"
                            v-model="form.sku"
                            class="form-input"
                            :class="{ 'border-red-500': errors.sku }"
                            placeholder="VD: SP001"
                            required
                        />
                        <div v-if="errors.sku" class="form-error">
                            {{ errors.sku }}
                        </div>
                    </div>

                    <!-- Tên sản phẩm -->
                    <div>
                        <label class="form-label">Tên sản phẩm *</label>
                        <input
                            type="text"
                            v-model="form.name"
                            class="form-input"
                            :class="{ 'border-red-500': errors.name }"
                            placeholder="Nhập tên sản phẩm"
                            required
                        />
                        <div v-if="errors.name" class="form-error">
                            {{ errors.name }}
                        </div>
                    </div>

                    <!-- Số lượng có thể bán (readonly) -->
                    <div>
                        <label class="form-label"
                            >Số lượng có thể bán (tổng)</label
                        >
                        <input
                            type="number"
                            v-model.number="form.quantity"
                            class="form-input bg-gray-100"
                            readonly
                            placeholder="Tự động tính"
                        />
                        <div class="text-xs text-gray-500 mt-1">
                            📊 Tự động tính từ tổng các kho
                        </div>
                    </div>

                    <!-- Barcode -->
                    <div>
                        <label class="form-label">Barcode</label>
                        <input
                            type="text"
                            v-model="form.barcode"
                            class="form-input"
                            placeholder="Mã vạch sản phẩm"
                        />
                    </div>

                    <!-- Loại sản phẩm -->
                    <div>
                        <label class="form-label">Loại sản phẩm</label>
                        <select v-model="form.category_name" class="form-input">
                            <option value="">Chọn loại sản phẩm</option>
                            <option
                                v-for="category in categories"
                                :key="category"
                                :value="category"
                            >
                                {{ category }}
                            </option>
                        </select>
                    </div>

                    <!-- Nhãn hiệu -->
                    <div>
                        <label class="form-label">Nhãn hiệu</label>
                        <select v-model="form.brand_name" class="form-input">
                            <option value="">Chọn nhãn hiệu</option>
                            <option
                                v-for="brand in brands"
                                :key="brand"
                                :value="brand"
                            >
                                {{ brand }}
                            </option>
                        </select>
                    </div>

                    <!-- Khối lượng -->
                    <div>
                        <label class="form-label">Khối lượng</label>
                        <input
                            type="text"
                            v-model="form.weight"
                            class="form-input"
                            placeholder="VD: 750ml, 70cl"
                        />
                    </div>

                    <!-- Trạng thái -->
                    <div>
                        <label class="form-label">Trạng thái</label>
                        <select v-model="form.status" class="form-input">
                            <option value="active">Hoạt động</option>
                            <option value="inactive">Ngừng hoạt động</option>
                        </select>
                    </div>
                </div>

                <!-- Serial/IMEI Tracking Section -->
                <div class="mt-6 border-t pt-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">
                                Tồn kho
                            </h3>
                            <p class="text-sm text-gray-500 mt-1">
                                Quản lý số lượng tồn kho và định mức tồn.
                            </p>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center gap-4">
                        <label class="form-label mb-0"
                            >Quản lý theo serial/IMEI</label
                        >
                        <div
                            class="relative inline-flex items-center cursor-pointer"
                            @click="form.track_serial = !form.track_serial"
                        >
                            <div
                                :class="[
                                    'w-11 h-6 rounded-full transition-colors duration-200',
                                    form.track_serial
                                        ? 'bg-blue-600'
                                        : 'bg-gray-300',
                                ]"
                            >
                                <div
                                    :class="[
                                        'w-5 h-5 bg-white rounded-full shadow transform transition-transform duration-200 mt-0.5',
                                        form.track_serial
                                            ? 'translate-x-5 ml-0.5'
                                            : 'translate-x-0.5',
                                    ]"
                                ></div>
                            </div>
                            <span
                                class="ml-2 text-sm"
                                :class="
                                    form.track_serial
                                        ? 'text-blue-600 font-medium'
                                        : 'text-gray-500'
                                "
                            >
                                {{ form.track_serial ? "Có" : "Không" }}
                            </span>
                        </div>
                    </div>
                    <div
                        v-if="form.track_serial"
                        class="mt-3 p-3 bg-blue-50 rounded-lg text-sm text-blue-800"
                    >
                        <p>
                            Khi bật, mỗi đơn vị sản phẩm cần được gán một mã
                            Serial/IMEI riêng biệt khi nhập kho. Số lượng tồn
                            kho sẽ bằng số serial đang ở trạng thái "Trong kho".
                        </p>
                        <p
                            class="mt-1 text-blue-600"
                            v-if="isEdit && product && product.quantity > 0"
                        >
                            ⚠️ Lưu ý: Sản phẩm hiện có
                            {{ product.quantity }} đơn vị tồn kho. Bạn cần nhập
                            Serial cho số hàng tồn hiện tại sau khi bật tính
                            năng này.
                        </p>
                    </div>
                </div>

                <!-- Pricing Section -->
                <div class="mt-6 border-t pt-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">
                        Thông tin giá
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Giá vốn -->
                        <div>
                            <label class="form-label">Giá vốn</label>
                            <input
                                type="number"
                                v-model.number="form.cost_price"
                                class="form-input"
                                min="0"
                                step="1000"
                                placeholder="0"
                            />
                        </div>

                        <!-- Giá bán sỉ -->
                        <div>
                            <label class="form-label">Giá bán sỉ</label>
                            <input
                                type="number"
                                v-model.number="form.wholesale_price"
                                class="form-input"
                                min="0"
                                step="1000"
                                placeholder="0"
                            />
                        </div>

                        <!-- Giá bán lẻ -->
                        <div>
                            <label class="form-label">Giá bán lẻ</label>
                            <input
                                type="number"
                                v-model.number="form.retail_price"
                                class="form-input"
                                min="0"
                                step="1000"
                                placeholder="0"
                            />
                        </div>
                    </div>
                </div>

                <!-- Ghi chú -->
                <div class="mt-6">
                    <label class="form-label">Ghi chú</label>
                    <textarea
                        v-model="form.note"
                        class="form-input"
                        rows="3"
                        placeholder="Ghi chú về sản phẩm..."
                    ></textarea>
                </div>
                <!-- Warehouse Initialization Section -->
                <div class="mt-6 border-t pt-6" v-if="!isEdit">
                    <div class="flex items-center mb-4">
                        <h3 class="text-lg font-medium text-gray-900">
                            Khởi tạo kho hàng
                        </h3>
                        <div
                            class="ml-2 w-3 h-3 bg-blue-500 rounded-full"
                        ></div>
                    </div>
                    <div class="text-sm text-gray-600 mb-4">
                        Ghi nhận số lượng Tồn kho ban đầu và Giá vốn của sản
                        phẩm tại các Chi nhánh
                    </div>

                    <div class="space-y-4">
                        <div
                            class="grid grid-cols-3 gap-4 text-sm font-medium text-gray-700 border-b pb-2"
                        >
                            <div>Chi nhánh</div>
                            <div>Tồn kho ban đầu</div>
                            <div>Giá vốn</div>
                        </div>

                        <div
                            v-for="warehouse in warehouses"
                            :key="warehouse.id"
                            class="grid grid-cols-3 gap-4 items-center py-2"
                        >
                            <div class="text-sm">{{ warehouse.name }}</div>

                            <div>
                                <input
                                    type="number"
                                    v-model.number="
                                        warehouseQuantities[warehouse.id]
                                    "
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-center"
                                    min="0"
                                    placeholder="0"
                                    @input="calculateTotalQuantity"
                                />
                            </div>

                            <div>
                                <input
                                    type="number"
                                    v-model.number="
                                        warehouseCosts[warehouse.id]
                                    "
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-center"
                                    min="0"
                                    placeholder="0"
                                    @input="calculateAverageCost"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Buttons -->
                <div class="flex justify-end space-x-3 mt-6 pt-6 border-t">
                    <button
                        type="button"
                        @click="$emit('close')"
                        class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
                    >
                        Hủy
                    </button>
                    <button
                        type="submit"
                        :disabled="loading"
                        class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 disabled:opacity-50"
                    >
                        <span v-if="loading">Đang lưu...</span>
                        <span v-else>{{
                            isEdit ? "Cập nhật" : "Tạo mới"
                        }}</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<script>
import { ref, computed, watch, onMounted } from "vue";
import warehouseApi from "../api/warehouseApi";
import { productApi } from "../api/productApi";

export default {
    name: "ProductForm",
    props: {
        product: {
            type: Object,
            default: null,
        },
        categories: {
            type: Array,
            default: () => [],
        },
        brands: {
            type: Array,
            default: () => [],
        },
    },
    emits: ["close", "save"],
    setup(props, { emit }) {
        const loading = ref(false);
        const errors = ref({});
        const warehouses = ref([]);
        const warehouseQuantities = ref({});
        const warehouseCosts = ref({});

        // Form data
        const form = ref({
            sku: "",
            name: "",
            quantity: 0,
            cost_price: 0,
            wholesale_price: 0,
            retail_price: 0,
            category_name: "",
            brand_name: "",
            barcode: "",
            weight: "",
            status: "active",
            track_serial: false,
            note: "",
        });

        // Computed
        const isEdit = computed(() => {
            return props.product && props.product.id;
        });
        const totalQuantity = computed(() => {
            return Object.values(warehouseQuantities.value).reduce(
                (sum, qty) => sum + (qty || 0),
                0,
            );
        });

        // Methods
        const initForm = () => {
            if (props.product) {
                // Edit mode - populate form with existing data
                form.value = {
                    sku: props.product.sku || "",
                    name: props.product.name || "",
                    quantity: props.product.quantity || 0,
                    cost_price: props.product.cost_price || 0,
                    wholesale_price: props.product.wholesale_price || 0,
                    retail_price: props.product.retail_price || 0,
                    category_name: props.product.category_name || "",
                    brand_name: props.product.brand_name || "",
                    barcode: props.product.barcode || "",
                    weight: props.product.weight || "",
                    status: props.product.status || "active",
                    track_serial: props.product.track_serial || false,
                    note: props.product.note || "",
                };

                // ✅ EDIT MODE: Load warehouse data nếu có
                if (
                    props.product.warehouse_products &&
                    props.product.warehouse_products.length > 0
                ) {
                    props.product.warehouse_products.forEach((wp) => {
                        warehouseQuantities.value[wp.warehouse_id] =
                            wp.quantity || 0;
                        warehouseCosts.value[wp.warehouse_id] = wp.cost || 0;
                    });
                }
            } else {
                // Create mode - reset form
                form.value = {
                    sku: "",
                    name: "",
                    quantity: 0,
                    cost_price: 0,
                    wholesale_price: 0,
                    retail_price: 0,
                    category_name: "",
                    brand_name: "",
                    barcode: "",
                    weight: "",
                    status: "active",
                    track_serial: false,
                    note: "",
                };

                // ✅ CREATE MODE: Reset warehouse data và sync cost_price
                if (warehouses.value.length > 0) {
                    warehouses.value.forEach((warehouse) => {
                        warehouseQuantities.value[warehouse.id] = 0;
                        // Sync giá vốn chung xuống tất cả kho
                        warehouseCosts.value[warehouse.id] =
                            form.value.cost_price;
                    });
                }
            }

            errors.value = {};
        };

        const validateForm = () => {
            errors.value = {};

            if (!form.value.sku?.trim()) {
                errors.value.sku = "Mã SKU là bắt buộc";
            }

            if (!form.value.name?.trim()) {
                errors.value.name = "Tên sản phẩm là bắt buộc";
            }

            if (form.value.quantity < 0) {
                errors.value.quantity = "Số lượng không được âm";
            }

            if (form.value.cost_price < 0) {
                errors.value.cost_price = "Giá vốn không được âm";
            }

            if (form.value.retail_price < 0) {
                errors.value.retail_price = "Giá bán lẻ không được âm";
            }

            return Object.keys(errors.value).length === 0;
        };

        // THAY TOÀN BỘ method handleSubmit:
        const handleSubmit = async () => {
            if (!validateForm()) {
                return;
            }

            loading.value = true;

            try {
                // Clean up form data
                const formData = {
                    ...form.value,
                    sku: form.value.sku.trim(),
                    name: form.value.name.trim(),
                    quantity: Number(form.value.quantity) || 0,
                    cost_price: Number(form.value.cost_price) || 0,
                    wholesale_price: Number(form.value.wholesale_price) || 0,
                    retail_price: Number(form.value.retail_price) || 0,
                };

                // THÊM warehouse data cho sản phẩm mới
                if (!isEdit.value) {
                    formData.warehouse_stocks = Object.entries(
                        warehouseQuantities.value,
                    )
                        .filter(([id, qty]) => qty > 0)
                        .map(([warehouse_id, quantity]) => ({
                            warehouse_id: parseInt(warehouse_id),
                            quantity: quantity,
                            cost: warehouseCosts.value[warehouse_id] || 0,
                        }));
                }

                emit("save", formData);
            } catch (error) {
                console.error("Form submission error:", error);
            } finally {
                loading.value = false;
            }
        };
        // SAU handleSubmit, THÊM:

        const fetchWarehouses = async () => {
            try {
                const response = await warehouseApi.getWarehouses();
                const data = response;
                if (data.success) {
                    warehouses.value = data.data.data || [];
                }
            } catch (error) {
                console.error("Error fetching warehouses:", error);
            }
        };

        const calculateTotalQuantity = () => {
            form.value.quantity = totalQuantity.value;
        };
        const calculateAverageCost = () => {
            const costs = Object.values(warehouseCosts.value).filter(
                (cost) => cost > 0,
            );
            if (costs.length > 0) {
                const avgCost =
                    costs.reduce((sum, cost) => sum + cost, 0) / costs.length;
                form.value.cost_price = Math.round(avgCost);
            }
        };
        // Watch for product changes
        watch(() => props.product, initForm, { immediate: true });
        watch(
            () => form.value.cost_price,
            (newPrice) => {
                if (newPrice && warehouses.value.length > 0) {
                    warehouses.value.forEach((warehouse) => {
                        warehouseCosts.value[warehouse.id] = newPrice;
                    });
                }
            },
            { immediate: false },
        );

        // Initialize form on mount
        onMounted(() => {
            initForm();
            fetchWarehouses();
        });

        return {
            loading,
            errors,
            form,
            isEdit,
            initForm,
            validateForm,
            handleSubmit,
            warehouses,
            warehouseQuantities,
            warehouseCosts,
            calculateTotalQuantity,
            calculateAverageCost,
        };
    },
};
</script>
