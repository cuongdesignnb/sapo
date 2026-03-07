// Centralized auth error handling to avoid multiple redirect loops
// Usage: import { attachAuthInterceptors } from '@/api/authInterceptor'; attachAuthInterceptors(axiosInstance);

let redirecting = false;

function redirectToForcedLogout() {
    if (window.__authRedirecting) return;
    window.__authRedirecting = true;
    window.location.href = "/force-logout?reason=unauthorized";
}

export function attachAuthInterceptors(instance) {
    if (instance.__authInterceptorAttached) return;
    instance.__authInterceptorAttached = true;

    instance.interceptors.response.use(
        (resp) => resp,
        (error) => {
            const status = error.response?.status;
            if ((status === 401 || status === 419) && !redirecting) {
                redirecting = true;
                // Clear tokens
                sessionStorage.removeItem("api_token");
                localStorage.removeItem("api_token");
                // Force logout on server to avoid /login <-> /dashboard loops
                redirectToForcedLogout();
                return Promise.reject(new Error("Session ended"));
            }
            return Promise.reject(error);
        }
    );
}
