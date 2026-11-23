import { reactive, computed } from 'vue';
import api from './api';

const state = reactive({
    user: null,
    loading: false,
    initialized: false,
});

export function useAuth() {
    const isAuthenticated = computed(() => !!state.user);

    async function fetchUser() {
        try {
            const { data } = await api.get('/me');
            state.user = data.user;
        } catch (error) {
            state.user = null;
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
            state.initialized = true;
            return data.user;
        } finally {
            state.loading = false;
        }
    }

    function setUser(user) {
        state.user = user;
        state.initialized = true;
    }

    async function logout() {
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
