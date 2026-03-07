<template>
    <div class="min-h-screen flex items-center justify-center bg-gray-100">
        <div class="bg-white p-6 rounded shadow-md w-full max-w-md">
            <h2 class="text-2xl font-bold mb-4 text-center">
                Đăng nhập hệ thống
            </h2>
            <form @submit.prevent="handleLogin">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700"
                        >Email</label
                    >
                    <input
                        v-model="email"
                        type="email"
                        required
                        class="w-full border px-3 py-2 rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
                        :class="{ 'border-red-500': error }"
                    />
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700"
                        >Mật khẩu</label
                    >
                    <input
                        v-model="password"
                        type="password"
                        required
                        class="w-full border px-3 py-2 rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
                        :class="{ 'border-red-500': error }"
                    />
                </div>
                <div class="mb-4">
                    <label class="flex items-center">
                        <input
                            v-model="remember"
                            type="checkbox"
                            class="mr-2"
                        />
                        <span class="text-sm text-gray-700"
                            >Ghi nhớ đăng nhập</span
                        >
                    </label>
                </div>
                <button
                    type="submit"
                    :disabled="loading"
                    class="w-full bg-blue-600 text-white py-2 rounded hover:bg-blue-700 disabled:opacity-50"
                >
                    {{ loading ? "Đang đăng nhập..." : "Đăng nhập" }}
                </button>
                <p v-if="error" class="text-red-600 text-sm mt-4">
                    {{ error }}
                </p>
            </form>

            <!-- Demo accounts -->
            <div class="mt-6 p-4 bg-gray-50 rounded">
                <h4 class="text-sm font-medium text-gray-700 mb-2">
                    Tài khoản demo:
                </h4>
                <div class="text-xs text-gray-600 space-y-1">
                    <div>Super Admin: admin@banhangpro.com / admin123</div>
                    <div>Manager: manager.hn@banhangpro.com / 123456</div>
                    <div>Staff: staff.hn01@banhangpro.com / 123456</div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from "vue";

const email = ref("");
const password = ref("");
const remember = ref(false);
const error = ref("");
const loading = ref(false);

const handleLogin = async () => {
    error.value = "";
    loading.value = true;

    // Helper: get CSRF token from <meta> or XSRF cookie
    const getCsrfToken = () => {
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta?.getAttribute("content")) return meta.getAttribute("content");
        // Fallback to cookie 'XSRF-TOKEN' (Laravel/Sanctum)
        const m = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);
        if (m) {
            try {
                return decodeURIComponent(m[1]);
            } catch (_) {
                return m[1];
            }
        }
        return "";
    };

    try {
        error.value = "";
        loading.value = true;

        // ✅ Use WEB endpoint to maintain session for 2FA
        const csrfToken = getCsrfToken();
        const response = await fetch("/login", {
            method: "POST",
            body: JSON.stringify({
                email: email.value,
                password: password.value,
                remember: remember.value,
            }),
            credentials: "include",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
                "X-Requested-With": "XMLHttpRequest",
                "X-CSRF-TOKEN": csrfToken,
            },
        });

        if (response.ok) {
            const data = await response.json();

            if (data.success) {
                // Check if 2FA is required
                if (data.need_2fa || data.requires_2fa) {
                    // Will be redirected by server to 2FA challenge
                    window.location.href = "/2fa/challenge";
                    return;
                }

                // ✅ Normal login success - redirect to dashboard
                window.location.href = data.redirect || "/dashboard";
            } else {
                error.value = data.message || "Đăng nhập thất bại";
            }
        } else {
            // Handle validation errors
            const contentType = response.headers.get("content-type");
            if (contentType && contentType.includes("application/json")) {
                const data = await response.json();
                if (data.errors) {
                    error.value =
                        data.errors.email?.[0] || "Đăng nhập thất bại";
                } else {
                    error.value = data.message || "Đăng nhập thất bại";
                }
            } else {
                error.value = "Lỗi server (500)";
            }
        }
    } catch (err) {
        console.error("Login error:", err);
        error.value = "Có lỗi xảy ra khi đăng nhập";
    } finally {
        loading.value = false;
    }
};
</script>
