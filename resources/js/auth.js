import { reactive, computed } from 'vue';
import api from './api';

const USER_CACHE_KEY = 'pos_user_cache';

const state = reactive({
    user: null,
    loading: false,
    initialized: false,
});

// Sesi offline: user terakhir yang login di-cache di localStorage.
// Dipakai bila koneksi tidak tersedia supaya kasir tetap bisa bekerja.
function readCachedUser() {
    try {
        const raw = localStorage.getItem(USER_CACHE_KEY);
        return raw ? JSON.parse(raw) : null;
    } catch {
        return null;
    }
}

function writeCachedUser(user) {
    try {
        if (user) {
            localStorage.setItem(USER_CACHE_KEY, JSON.stringify(user));
        } else {
            localStorage.removeItem(USER_CACHE_KEY);
        }
    } catch {
        // localStorage penuh/di-blok: abaikan cache.
    }
}

export function useAuth() {
    const isAuthenticated = computed(() => !!state.user);

    async function fetchUser() {
        if (navigator.onLine === false) {
            // Offline: gunakan cache agar sesi kasir tetap aktif.
            state.user = readCachedUser();
            state.initialized = true;
            return state.user;
        }

        try {
            const { data } = await api.get('/me');
            state.user = data.user;
            writeCachedUser(data.user);
        } catch (error) {
            // Network error (server tidak terjangkau) ≠ sesi berakhir:
            // fallback ke cache supaya mode offline tidak me-logout paksa.
            if (!error.response && readCachedUser()) {
                state.user = readCachedUser();
            } else {
                state.user = null;
                writeCachedUser(null);
            }
        } finally {
            state.initialized = true;
        }
        return state.user;
    }

    async function login(credentials) {
        state.loading = true;
        try {
            const { data } = await api.post('/login', credentials);
            state.user = data.user;
            writeCachedUser(data.user);
            state.initialized = true;
            return data.user;
        } finally {
            state.loading = false;
        }
    }

    function setUser(user) {
        state.user = user;
        writeCachedUser(user);
        state.initialized = true;
    }

    async function logout() {
        writeCachedUser(null);
        try {
            await api.post('/logout');
        } finally {
            state.user = null;
        }
    }

    function hasPermission(permission) {
        if (!state.user) return false;
        if (state.user.is_superadmin) return true;
        return state.user.permissions?.includes(permission) ?? false;
    }

    function hasRole(role) {
        return state.user?.roles?.some((item) => item.slug === role) ?? false;
    }

    return {
        state,
        isAuthenticated,
        fetchUser,
        login,
        setUser,
        logout,
        hasPermission,
        hasRole,
    };
}
