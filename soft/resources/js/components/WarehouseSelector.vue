<template>
  <div class="warehouse-selector relative">
    <div 
      class="flex items-center space-x-2 bg-white border border-gray-300 rounded-lg px-3 py-2 cursor-pointer hover:bg-gray-50 transition-colors"
      @click="toggleDropdown"
    >
      <div class="flex items-center space-x-2 min-w-0 flex-1">
        <svg class="w-5 h-5 text-gray-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-4m-2 0H3m2-16v16"></path>
        </svg>
        
        <div class="min-w-0 flex-1">
          <div v-if="currentWarehouse" class="text-sm font-medium text-gray-900 truncate">
            {{ currentWarehouse.name }}
          </div>
          <div v-else class="text-sm text-gray-500">
            Chọn kho
          </div>
          <div v-if="currentWarehouse" class="text-xs text-gray-500 truncate">
            {{ currentWarehouse.code }}
          </div>
        </div>
      </div>
      
      <svg 
        class="w-4 h-4 text-gray-400 transition-transform"
        :class="{ 'rotate-180': showDropdown }"
        fill="none" 
        stroke="currentColor" 
        viewBox="0 0 24 24"
      >
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
      </svg>
    </div>

    <div 
      v-if="showDropdown"
      class="absolute top-full left-0 right-0 mt-1 bg-white border border-gray-300 rounded-lg shadow-lg z-50 max-h-64 overflow-y-auto"
    >
      <div v-if="loading" class="p-4 text-center text-gray-500">
        <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600 mx-auto"></div>
        <div class="mt-2 text-sm">Đang tải...</div>
      </div>

      <div v-else-if="availableWarehouses.length > 0" class="py-1">
        <div 
          v-for="warehouse in availableWarehouses" 
          :key="warehouse.id"
          class="px-4 py-3 hover:bg-gray-50 cursor-pointer transition-colors border-b border-gray-100 last:border-b-0"
          @click="selectWarehouse(warehouse)"
          :class="{
            'bg-blue-50 border-blue-200': currentWarehouse && currentWarehouse.id === warehouse.id
          }"
        >
          <div class="flex items-center justify-between">
            <div class="min-w-0 flex-1">
              <div class="text-sm font-medium text-gray-900 truncate">
                {{ warehouse.name }}
              </div>
              <div class="text-xs text-gray-500 truncate">
                {{ warehouse.code }}
              </div>
            </div>
            
            <div v-if="currentWarehouse && currentWarehouse.id === warehouse.id" class="ml-2">
              <svg class="w-5 h-5 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
              </svg>
            </div>
          </div>
        </div>
      </div>

      <div v-if="userRole === 'super_admin'" class="border-t border-gray-200">
        <div 
          class="px-4 py-2 text-center text-sm text-red-600 hover:bg-red-50 cursor-pointer transition-colors"
          @click="clearWarehouse"
        >
          🌐 View All
        </div>
      </div>
    </div>

    <div 
      v-if="notification.show"
      class="absolute top-full left-0 right-0 mt-2 p-3 rounded-lg shadow-lg z-50"
      :class="{
        'bg-green-100 border border-green-400 text-green-700': notification.type === 'success',
        'bg-red-100 border border-red-400 text-red-700': notification.type === 'error'
      }"
    >
      {{ notification.message }}
    </div>
  </div>
</template>

<script>
import warehouseApi from '../api/warehouseApi.js'

export default {
  name: 'WarehouseSelector',
  
  props: {
    userRole: {
      type: String,
      default: ''
    }
  },

  data() {
    return {
      showDropdown: false,
      loading: false,
      availableWarehouses: [],
      currentWarehouse: null,
      notification: {
        show: false,
        type: 'success',
        message: ''
      }
    }
  },

  mounted() {
    this.fetchCurrentWarehouse()
    this.fetchAvailableWarehouses()
    document.addEventListener('click', this.handleClickOutside)
  },

  beforeUnmount() {
    document.removeEventListener('click', this.handleClickOutside)
  },

  methods: {
    toggleDropdown() {
      this.showDropdown = !this.showDropdown
      if (this.showDropdown && this.availableWarehouses.length === 0) {
        this.fetchAvailableWarehouses()
      }
    },

    handleClickOutside(event) {
      if (!this.$el.contains(event.target)) {
        this.showDropdown = false
      }
    },

    async fetchCurrentWarehouse() {
      try {
        const response = await warehouseApi.getCurrentWarehouse()
        if (response.success) {
          this.currentWarehouse = response.data
        }
      } catch (error) {
        console.error('Error fetching current warehouse:', error)
      }
    },

    async fetchAvailableWarehouses() {
      this.loading = true
      try {
        const response = await warehouseApi.getAvailableWarehouses()
        if (response.success) {
          this.availableWarehouses = response.data
        }
      } catch (error) {
        console.error('Error fetching warehouses:', error)
        this.showNotification('Lỗi khi tải danh sách kho', 'error')
      } finally {
        this.loading = false
      }
    },

    async selectWarehouse(warehouse) {
      if (this.currentWarehouse && this.currentWarehouse.id === warehouse.id) {
        this.showDropdown = false
        return
      }

      this.loading = true
      try {
        const response = await warehouseApi.switchWarehouse(warehouse.id)
        if (response.success) {
          this.currentWarehouse = response.data
          this.showDropdown = false
          this.showNotification(response.message, 'success')
          
          setTimeout(() => {
            window.location.reload()
          }, 1000)
        }
      } catch (error) {
        console.error('Error switching warehouse:', error)
        const message = error.message || 'Lỗi khi chuyển kho'
        this.showNotification(message, 'error')
      } finally {
        this.loading = false
      }
    },

    async clearWarehouse() {
      this.loading = true
      try {
        const response = await warehouseApi.clearWarehouse()
        if (response.success) {
          this.currentWarehouse = null
          this.showDropdown = false
          this.showNotification(response.message, 'success')
          
          setTimeout(() => {
            window.location.reload()
          }, 1000)
        }
      } catch (error) {
        console.error('Error clearing warehouse:', error)
        this.showNotification('Lỗi khi thoát kho', 'error')
      } finally {
        this.loading = false
      }
    },

    showNotification(message, type = 'success') {
      this.notification = { show: true, type, message }
      setTimeout(() => {
        this.notification.show = false
      }, 3000)
    }
  }
}
</script>

<style scoped>
.warehouse-selector {
  min-width: 250px;
  max-width: 350px;
}
</style>