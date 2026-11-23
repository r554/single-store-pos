import axios from 'axios';
import { useToast } from './composables/useToast';
import router from './router';

function readCookie(name) {
    const match = document.cookie.match(new RegExp('(^|;\\s*)' + name + '=([^;]*)'));
    return match ? decodeURIComponent(match[2]) : null;
}

const api = axios.create({
    baseURL: '/api',
    withCredentials: true,
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});

api.interceptors.request.use((config) => {
    const token = readCookie('XSRF-TOKEN');
    if (token) {
        config.headers['X-XSRF-TOKEN'] = token;
    }
    return config;
});

let redirectingToLogin = false;

api.interceptors.response.use(
    (response) => response,
    (error) => {
        const status = error.response?.status;
        const path = error.config?.url ?? '';

        // Sesi berakhir (419 CSRF / 401) pada request di luar endpoint auth:
        // kembalikan user ke halaman login dengan pesan yang jelas.
        if (
            (status === 419 || status === 401) &&
            !path.includes('/login') &&
            !path.includes('/register') &&
            !path.includes('/me') &&
            !redirectingToLogin
        ) {
            redirectingToLogin = true;

            const { push } = useToast();
            push('Sesi Anda telah berakhir. Silakan login kembali.', 'error');

            const current = router.currentRoute.value;
            router
                .push({
                    name: 'login',
                    query: current.name !== 'login' ? { redirect: current.fullPath } : {},
                })
                .finally(() => {
                    redirectingToLogin = false;
                });
        }

        // 500: tampilkan toast generik agar pengguna tahu server bermasalah.
        if (status >= 500) {
            const { push } = useToast();
            push('Terjadi kesalahan pada server. Silakan coba lagi.', 'error');
        }

        return Promise.reject(error);
    }
);

export default api;

// Named export agar bisa dipakai tanpa default import di beberapa kasus.
export { api };
