<template>
  <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 w-full max-w-4xl max-h-96 overflow-y-auto">
      <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-semibold">Tổng quan tồn kho</h3>
        <button @click="$emit('close')" class="text-gray-400 hover:text-gray-600">✕</button>
      </div>
      <div class="text-center py-8">
        <p class="text-gray-500">Tính năng đang phát triển...</p>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'StockOverviewModal',
  emits: ['close']
}
</script>