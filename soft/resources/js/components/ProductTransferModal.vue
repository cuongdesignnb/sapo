<template>
  <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg w-full max-w-lg max-h-screen overflow-y-auto">
      <div class="p-6 border-b flex justify-between items-center">
        <h3 class="text-xl font-semibold flex items-center">
          <span class="mr-2">🔄</span>
          Chuyển sản phẩm sang kho khác
        </h3>
        <button @click="$emit('close')" class="text-gray-400 hover:text-gray-600 text-2xl">✕</button>
      </div>
      <form @submit.prevent="handleSubmit">
        <div class="p-6 space-y-6">
          <!-- Info kho nguồn -->
          <div>
            <label class="block text-sm font-medium mb-1">Từ kho:</label>
            <div class="px-3 py-2 bg-gray-50 border border-gray-200 rounded">{{ fromWarehouse?.name }} <span class="text-xs text-gray-400">({{ fromWarehouse?.code }})</span></div>
          </div>
          <!-- Info sản phẩm -->
          <div>
            <label class="block text-sm font-medium mb-1">Sản phẩm:</label>
            <div class="px-3 py-2 bg-gray-50 border border-gray-200 rounded">
              <div class="font-semibold">{{ product?.product?.name }}</div>
              <div class="text-xs text-gray-500">SKU: {{ product?.product?.sku }} | Barcode: {{ product?.product?.barcode }} | Tồn kho: {{ product?.available_stock }}</div>
            </div>
          </div>
          <!-- Chọn kho đích -->
          <div>
            <label class="block text-sm font-medium mb-1">Đến kho <span class="text-red-500">*</span>:</label>
            <select v-model="form.to_warehouse_id" class="w-full border rounded p-2" required>
              <option value="">Chọn kho đích</option>
              <option v-for="w in warehouses" :key="w.id" :value="w.id">
                {{ w.name }} ({{ w.code }})
              </option>
            </select>
          </div>
          <!-- Số lượng -->
          <div>
            <label class="block text-sm font-medium mb-1">Số lượng chuyển <span class="text-red-500">*</span>:</label>
            <input type="number" v-model.number="form.quantity" :max="product?.available_stock" min="1" class="w-full border rounded p-2" required>
            <div class="text-xs text-gray-500 mt-1">Tối đa: {{ product?.available_stock }} sản phẩm</div>
          </div>
          <!-- Ghi chú -->
          <div>
            <label class="block text-sm font-medium mb-1">Ghi chú:</label>
            <textarea v-model="form.note" class="w-full border rounded p-2" rows="2" placeholder="Nhập ghi chú (nếu có)"></textarea>
          </div>
        </div>
        <div class="flex justify-end space-x-3 p-6 border-t bg-gray-50">
          <button type="button" @click="$emit('close')" class="px-6 py-2 text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">Hủy</button>
          <button type="submit" :disabled="loading || !canSubmit" class="px-6 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 disabled:opacity-50 disabled:cursor-not-allowed">
            <span v-if="loading">Đang chuyển...</span>
            <span v-else>Chuyển kho</span>
          </button>
        </div>
      </form>
    </div>
  </div>
  <!-- Trong template -->
<ProductTransferModal
  v-if="showProductTransferModal"
  :open="showProductTransferModal"
  :product="selectedProductToTransfer"
  :from-warehouse="warehouse"             
  :warehouses="otherWarehouses"
  @close="closeProductTransferModal"
  @transferred="handleProductTransferred"
/>
</template>


<script>
import { ref, computed, watch } from 'vue'
import warehouseApi from '../api/warehouseApi'

export default {
  name: 'ProductTransferModal',
  props: {
    open: { type: Boolean, default: false },
    product: { type: Object, required: true },
    fromWarehouse: { type: Object, required: true },
    warehouses: { type: Array, default: () => [] },
  },
  emits: ['close', 'transferred'],
  setup(props, { emit }) {
    console.log('FROM WAREHOUSE PROP:', props.fromWarehouse)
    console.log('PRODUCT PROP:', props.product)

    const loading = ref(false)
    const form = ref({
      to_warehouse_id: '',
      quantity: '',
      note: ''
    })

    // Computed
    const canSubmit = computed(() => {
      return (
        form.value.to_warehouse_id &&
        form.value.quantity &&
        props.product?.available_stock &&
        form.value.quantity > 0 &&
        form.value.quantity <= props.product.available_stock
      )
    })

    // Khi mở modal hoặc product đổi, reset form
    watch(
      () => [props.open, props.product],
      ([open, product]) => {
        if (open && product) {
          form.value.to_warehouse_id = ''
          form.value.quantity = ''
          form.value.note = ''
        }
      }
    )

    // Hàm submit
    const handleSubmit = async () => {
      if (!canSubmit.value) return
      loading.value = true
      try {
        const payload = {
          from_warehouse_id: props.fromWarehouse.id,
          to_warehouse_id: form.value.to_warehouse_id,
          product_id: props.product.product_id,
          quantity: form.value.quantity,
          note: form.value.note
        }
        const res = await warehouseApi.transferProduct(payload)
        if (res.success) {
          emit('transferred')
          emit('close')
        } else {
          alert(res.message || 'Chuyển kho thất bại!')
        }
      } catch (e) {
        alert('Có lỗi khi chuyển kho')
      }
      loading.value = false
    }

    return {
      loading,
      form,
      canSubmit,
      handleSubmit,
    }
  }
}
</script>


<style scoped>
.animate-spin {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

.transition-colors {
  transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out;
}

.max-h-screen {
  max-height: 90vh;
}

input:focus, select:focus, textarea:focus {
  outline: none;
}
</style>