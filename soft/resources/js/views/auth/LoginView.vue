<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
      <!-- Header -->
      <div>
        <div class="mx-auto h-12 w-12 flex items-center justify-center rounded-full bg-blue-100">
          <span class="text-2xl">🏪</span>
        </div>
        <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
          Đăng nhập hệ thống
        </h2>
        <p class="mt-2 text-center text-sm text-gray-600">
          Bán hàng Pro - Quản lý kho hàng
        </p>
      </div>

      <!-- Login Form -->
      <form class="mt-8 space-y-6" @submit.prevent="handleLogin">
        <div class="rounded-md shadow-sm -space-y-px">
          <!-- Email -->
          <div>
            <label for="email" class="sr-only">Email</label>
            <input
              id="email"
              name="email"
              type="email"
              v-model="form.email"
              required
              class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-t-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm"
              :class="{ 'border-red-500': errors.email }"
              placeholder="Email đăng nhập"
            />
          </div>
          
          <!-- Password -->
          <div>
            <label for="password" class="sr-only">Mật khẩu</label>
            <input
              id="password"
              name="password"
              type="password"
              v-model="form.password"
              required
              class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-b-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm"
              :class="{ 'border-red-500': errors.password }"
              placeholder="Mật khẩu"
            />
          </div>
        </div>

        <!-- Error Message -->
        <div v-if="authStore.error" class="bg-red-50 border border-red-200 rounded-md p-3">
          <div class="flex">
            <div class="text-sm text-red-700">
              {{ authStore.error }}
            </div>
          </div>
        </div>

        <!-- Submit Button -->
        <div>
          <button
            type="submit"
            :disabled="authStore.isLoading"
            class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <span v-if="authStore.isLoading" class="mr-2">
              <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
            </span>
            {{ authStore.isLoading ? 'Đang đăng nhập...' : 'Đăng nhập' }}
          </button>
        </div>

        <!-- Demo Accounts -->
        <div class="mt-6 bg-gray-50 rounded-lg p-4">
          <h3 class="text-sm font-medium text-gray-700 mb-2">Tài khoản demo:</h3>
          <div class="grid grid-cols-1 gap-2 text-xs">
            <div class="flex justify-between">
              <span class="font-medium">Super Admin:</span>
              <span class="text-gray-600">admin@banhangpro.com / admin123</span>
            </div>
            <div class="flex justify-between">
              <span class="font-medium">Quản lý kho HN:</span>
              <span class="text-gray-600">manager.hn@banhangpro.com / 123456</span>
            </div>
            <div class="flex justify-between">
              <span class="font-medium">Nhân viên:</span>
              <span class="text-gray-600">staff.hn01@banhangpro.com / 123456</span>
            </div>
          </div>
        </div>
      </form>
    </div>
  </div>
</template>

<script>
import { reactive } from 'vue'
import { useAuthStore } from '@/stores/authStore'
import { useRouter } from 'vue-router'

export default {
  name: 'LoginView',
  setup() {
    const authStore = useAuthStore()
    const router = useRouter()

    const form = reactive({
      email: '',
      password: ''
    })

    const errors = reactive({
      email: false,
      password: false
    })

    const handleLogin = async () => {
      // Reset errors
      errors.email = false
      errors.password = false

      // Basic validation
      if (!form.email) {
        errors.email = true
        return
      }
      if (!form.password) {
        errors.password = true
        return
      }

      const result = await authStore.login({
        email: form.email,
        password: form.password
      })

      if (result.success) {
        // Redirect based on user role
        router.push(result.redirectPath)
      }
    }

    return {
      authStore,
      form,
      errors,
      handleLogin
    }
  }
}
</script>