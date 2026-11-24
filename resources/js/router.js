import { createRouter, createWebHistory } from 'vue-router';
import { useAuth } from './auth';

const routes = [
    {
        path: '/login',
        name: 'login',
        component: () => import('./pages/Login.vue'),
        meta: { guestOnly: true },
    },
    {
        path: '/register',
        name: 'register',
        component: () => import('./pages/Register.vue'),
        meta: { guestOnly: true },
    },
    {
        path: '/forgot-password',
        name: 'forgot-password',
        component: () => import('./pages/ForgotPassword.vue'),
        meta: { guestOnly: true },
    },
    {
        path: '/reset-password',
        name: 'reset-password',
        component: () => import('./pages/ResetPassword.vue'),
        meta: { guestOnly: true },
    },
    {
        path: '/email-verify',
        name: 'email-verify',
        component: () => import('./pages/EmailVerify.vue'),
    },
    {
        path: '/',
        component: () => import('./layouts/AppLayout.vue'),
        meta: { requiresAuth: true },
        children: [
            {
                path: '',
                redirect: { name: 'dashboard' },
            },
            {
                path: 'dashboard',
                name: 'dashboard',
                component: () => import('./pages/Dashboard.vue'),
                meta: { title: 'Dashboard' },
            },
            {
                path: 'pos',
                name: 'pos',
                component: () => import('./pages/POS.vue'),
                meta: { title: 'Kasir', permission: 'pos.sell' },
            },
            {
                path: 'products',
                name: 'products',
                component: () => import('./pages/Products.vue'),
                meta: { title: 'Produk', permission: 'products.view' },
            },
            {
                path: 'reports',
                name: 'reports',
                component: () => import('./pages/Reports.vue'),
                meta: { title: 'Laporan', permission: 'pos.sell' },
            },
            {
                path: 'profile',
                name: 'profile',
                component: () => import('./pages/Profile.vue'),
                meta: { title: 'Profil Saya' },
            },
            {
                path: 'users',
                name: 'users',
                component: () => import('./pages/Users.vue'),
                meta: { title: 'Pengguna', permission: 'users.view' },
            },
            {
                path: 'roles',
                name: 'roles',
                component: () => import('./pages/Roles.vue'),
                meta: { title: 'Role & Permission', permission: 'roles.view' },
            },
            {
                path: 'permissions',
                name: 'permissions',
                component: () => import('./pages/Permissions.vue'),
                meta: { title: 'Permission', permission: 'permissions.view' },
            },
            {
                path: 'activity-logs',
                name: 'activity-logs',
                component: () => import('./pages/ActivityLogs.vue'),
                meta: { title: 'Log Aktivitas', permission: 'activity.view' },
            },
            {
                path: 'trash',
                name: 'trash',
                component: () => import('./pages/Trash.vue'),
                meta: { title: 'Sampah', permission: 'users.view' },
            },
            {
                path: 'settings',
                name: 'settings',
                component: () => import('./pages/Settings.vue'),
                meta: { title: 'Pengaturan', permission: 'settings.view' },
            },
            {
                path: 'api-tokens',
                name: 'api-tokens',
                component: () => import('./pages/ApiTokens.vue'),
                meta: { title: 'Token API' },
            },
        ],
    },
    {
        path: '/403',
        name: 'forbidden',
        component: () => import('./pages/ErrorPage.vue'),
        props: { code: '403' },
    },
    {
        path: '/:pathMatch(.*)*',
        name: 'not-found',
        component: () => import('./pages/ErrorPage.vue'),
        props: { code: '404' },
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach(async (to) => {
    const { state, fetchUser, hasPermission } = useAuth();

    if (!state.initialized) {
        await fetchUser();
    }

    if (to.meta.requiresAuth && !state.user) {
        if (to.name === 'dashboard' || to.path === '/') {
            return { name: 'login' };
        }

        return {
            name: 'login',
            query: { redirect: to.fullPath },
        };
    }

    if (to.meta.guestOnly && state.user) {
        return { name: 'dashboard' };
    }

    if (to.meta.permission && !hasPermission(to.meta.permission)) {
        return { name: 'forbidden' };
    }

    // Force change password first login: izinkan hanya profil & logout.
    if (
        state.user?.password_change_required
        && !['profile', 'forbidden', 'not-found'].includes(to.name)
    ) {
        return {
            name: 'profile',
            query: { change: 'password' },
        };
    }

    return true;
});

export default router;
