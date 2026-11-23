<template>
    <div class="min-h-screen bg-slate-100 dark:bg-slate-900">
        <aside
            class="fixed inset-y-0 left-0 z-40 w-64 transform border-r border-slate-800 bg-slate-900 transition-transform duration-200"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
        >
            <div class="flex h-16 items-center gap-3 border-b border-slate-800 px-5">
                <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-500 text-sm font-bold text-white">
                    RB
                </div>
                <div>
                    <p class="text-sm font-semibold text-white">RBAC Studio</p>
                    <p class="text-[11px] text-slate-400">Access Control</p>
                </div>
            </div>

            <nav class="mt-4 space-y-4 px-3 pb-6">
                <div v-for="group in visibleMenuGroups" :key="group.caption">
                    <p class="px-3 pb-1.5 text-[10px] font-bold uppercase tracking-widest text-slate-500">
                        {{ group.caption }}
                    </p>

                    <div class="space-y-1">
                        <template v-for="item in group.items" :key="item.name">
                            <RouterLink
                                v-if="! item.children"
                                :to="{ name: item.name }"
                                class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition"
                                :class="route.name === item.name
                                    ? 'bg-indigo-600 text-white hover:bg-indigo-600'
                                    : 'text-slate-300 hover:bg-slate-800 hover:text-white'"
                                @click="onMenuClick"
                            >
                                <span v-html="item.icon" class="h-5 w-5" />
                                {{ item.label }}
                                <span
                                    v-if="item.badge"
                                    class="ml-auto rounded-full px-2 py-0.5 text-[10px] font-bold"
                                    :class="route.name === item.name ? 'bg-white/20 text-white' : 'bg-slate-700 text-slate-300'"
                                >
                                    {{ item.badge }}
                                </span>
                            </RouterLink>

                            <template v-else>
                                <button
                                    type="button"
                                    class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition"
                                    :class="isGroupActive(item)
                                        ? 'bg-indigo-600 text-white hover:bg-indigo-600'
                                        : 'text-slate-300 hover:bg-slate-800 hover:text-white'"
                                    @click="toggleSubmenu(item.name)"
                                >
                                    <span v-html="item.icon" class="h-5 w-5" />
                                    {{ item.label }}
                                    <span
                                        v-if="item.badge"
                                        class="ml-auto rounded-full px-2 py-0.5 text-[10px] font-bold"
                                        :class="isGroupActive(item) ? 'bg-white/20 text-white' : 'bg-slate-700 text-slate-300'"
                                    >
                                        {{ item.badge }}
                                    </span>
                                    <svg
                                        class="ml-auto h-4 w-4 transition-transform"
                                        :class="{ 'rotate-90': openSubmenus.has(item.name) }"
                                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                    </svg>
                                </button>

                                <div
                                    v-show="openSubmenus.has(item.name)"
                                    class="mt-1 space-y-1 border-l border-slate-700 pb-1 pl-3 ml-5"
                                >
                                    <RouterLink
                                        v-for="child in item.children.filter((c) => ! c.permission || hasPermission(c.permission))"
                                        :key="child.name"
                                        :to="{ name: child.name }"
                                        class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm transition"
                                        :class="route.name === child.name
                                            ? 'bg-indigo-600/20 font-medium text-indigo-300'
                                            : 'text-slate-400 hover:bg-slate-800 hover:text-white'"
                                        @click="onMenuClick"
                                    >
                                        <span class="h-1.5 w-1.5 rounded-full bg-current" />
                                        {{ child.label }}
                                    </RouterLink>
                                </div>
                            </template>
                        </template>
                    </div>
                </div>
            </nav>

        </aside>

        <div v-if="sidebarOpen" class="fixed inset-0 z-30 bg-slate-900/50 lg:hidden" @click="sidebarOpen = false" />

        <div :class="sidebarOpen ? 'lg:pl-64' : ''">
            <header class="sticky top-0 z-20 flex h-16 items-center justify-between gap-3 border-b border-slate-200 bg-white/90 px-4 backdrop-blur sm:px-6 dark:border-slate-700 dark:bg-slate-800/90">
                <div class="flex items-center gap-2">
                    <button
                        class="rounded-lg p-2 text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700"
                        :title="sidebarOpen ? 'Tutup sidebar' : 'Buka sidebar'"
                        @click="toggleSidebar"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            width="24" height="24" viewBox="0 0 24 24"
                            fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round"
                            class="h-5 w-5 lg:hidden" aria-hidden="true"
                        >
                            <path d="M4 5h16" />
                            <path d="M4 12h16" />
                            <path d="M4 19h16" />
                        </svg>

                        <svg
                            class="hidden h-5 w-5 transition-transform duration-300 lg:block dark:text-slate-300"
                            :class="sidebarOpen ? 'rotate-0' : 'rotate-180'"
                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6" />
                        </svg>
                    </button>

                    <label class="relative flex w-full max-w-md items-center">
                        <svg class="pointer-events-none absolute left-3 h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 10.5a6.5 6.5 0 11-13 0 6.5 6.5 0 0113 0z" />
                        </svg>
                        <input
                            v-model="search"
                            type="search"
                            placeholder="Cari menu atau halaman..."
                            class="w-full rounded-lg border border-slate-300 bg-white py-2 pl-9 pr-3 text-sm text-slate-800 placeholder:text-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 dark:placeholder:text-slate-400 dark:focus:ring-indigo-900"
                        />
                    </label>
                </div>

                <div class="flex shrink-0 items-center gap-2">
                    <div class="mx-1 hidden h-6 w-px bg-slate-200 sm:block dark:bg-slate-700" />

                    <button
                        aria-label="Toggle theme"
                        class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-700 dark:hover:text-slate-100"
                        @click="toggleTheme"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="hidden h-4 w-4 dark:block" aria-hidden="true">
                            <circle cx="12" cy="12" r="4" />
                            <path d="M12 2v2" />
                            <path d="M12 20v2" />
                            <path d="m4.93 4.93 1.41 1.41" />
                            <path d="m17.66 17.66 1.41 1.41" />
                            <path d="M2 12h2" />
                            <path d="M20 12h2" />
                            <path d="m6.34 17.66-1.41 1.41" />
                            <path d="m19.07 4.93-1.41 1.41" />
                        </svg>
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 dark:hidden" aria-hidden="true">
                            <path d="M20.985 12.486a9 9 0 1 1-9.473-9.472c.405-.022.617.46.402.803a6 6 0 0 0 8.268 8.268c.344-.215.825-.004.803.401" />
                        </svg>
                    </button>

                    <button
                        aria-label="Notifications"
                        type="button"
                        class="relative flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-700 dark:hover:text-slate-100"
                        @click="handleNotifications"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true">
                            <path d="M10.268 21a2 2 0 0 0 3.464 0" />
                            <path d="M3.262 15.326A1 1 0 0 0 4 17h16a1 1 0 0 0 .74-1.673C19.41 13.956 18 12.499 18 8A6 6 0 0 0 6 8c0 4.499-1.411 5.956-2.738 7.326" />
                        </svg>
                        <span class="absolute right-1.5 top-1.5 h-2 w-2 rounded-full bg-rose-500" />
                    </button>

                    <div class="relative ms-1">
                        <button
                            aria-label="User menu"
                            type="button"
                            class="flex h-8 w-8 items-center justify-center rounded-full bg-indigo-500/10 text-xs font-semibold text-indigo-600 transition-colors hover:bg-indigo-500/20 dark:text-indigo-300"
                            @click="userMenuOpen = ! userMenuOpen"
                        >
                            {{ initials }}
                        </button>

                        <div
                            v-if="userMenuOpen"
                            class="absolute right-0 top-10 z-30 w-48 overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-lg dark:border-slate-700 dark:bg-slate-800"
                        >
                            <div class="px-3 py-2">
                                <p class="truncate text-sm font-semibold text-slate-900 dark:text-slate-100">{{ user?.name }}</p>
                                <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ user?.email }}</p>
                            </div>
                            <div class="my-1 h-px bg-slate-200 dark:bg-slate-700" />
                            <RouterLink
                                :to="{ name: 'profile' }"
                                class="flex items-center gap-2 px-3 py-2 text-sm text-slate-700 transition-colors hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-700"
                                @click="userMenuOpen = false"
                            >
                                <svg class="h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                Profil Saya
                            </RouterLink>
                            <button
                                type="button"
                                class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-rose-600 transition-colors hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-900/30"
                                @click="handleLogout"
                            >
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12H3m0 0l4-4m-4 4l4 4m6-11h7a1 1 0 011 1v12a1 1 0 01-1 1h-7" />
                                </svg>
                                Keluar
                            </button>
                        </div>
                    </div>
                </div>
            </header>

            <main class="p-4 sm:p-6">
                <div class="mb-5 flex flex-col gap-1">
                    <h1 class="text-xl font-bold text-slate-900 dark:text-slate-100">{{ pageTitle }}</h1>
                    <p v-if="route.name === 'dashboard'" class="text-sm text-slate-500 dark:text-slate-400">
                        Selamat datang, {{ user?.name }}
                    </p>
                </div>

                <div v-if="searchMatches.length > 0" class="mb-5 rounded-xl border border-slate-200 bg-white p-2 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <p class="px-3 py-1.5 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Hasil pencarian</p>
                    <RouterLink
                        v-for="item in searchMatches"
                        :key="item.name"
                        :to="{ name: item.name }"
                        class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-700"
                        @click="search = ''"
                    >
                        <span v-html="item.icon" class="h-5 w-5 text-slate-400" />
                        {{ item.label }}
                    </RouterLink>
                </div>

                <RouterView />
            </main>
        </div>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useRoute, useRouter, RouterLink, RouterView } from 'vue-router';
import { useAuth } from '../auth';
import { useTheme } from '../composables/useTheme';
import { useToast } from '../composables/useToast';

const route = useRoute();
const router = useRouter();
const { state, logout, hasPermission } = useAuth();
const { toggle: toggleTheme } = useTheme();
const toast = useToast();

const isDark = computed(() => document.documentElement.classList.contains('dark'));

const sidebarOpen = ref(false);
const loggingOut = ref(false);
const search = ref('');
const userMenuOpen = ref(false);
const user = computed(() => state.user);

// Desktop: sidebar terbuka secara default. Mobile: tertutup.
const largeScreenQuery = window.matchMedia('(min-width: 64rem)');
const isLargeScreen = ref(largeScreenQuery.matches);

function syncSidebarWithViewport(event) {
    isLargeScreen.value = event.matches;
    sidebarOpen.value = event.matches;
}

function toggleSidebar() {
    sidebarOpen.value = ! sidebarOpen.value;
}

function onMenuClick() {
    // Tutup sidebar hanya di mobile (drawer);
    // di desktop sidebar tetap terbuka.
    if (! largeScreenQuery.matches) {
        sidebarOpen.value = false;
    }
}

onMounted(() => {
    sidebarOpen.value = largeScreenQuery.matches;
    largeScreenQuery.addEventListener('change', syncSidebarWithViewport);
    document.addEventListener('click', onDocumentClick);
});

onBeforeUnmount(() => {
    largeScreenQuery.removeEventListener('change', syncSidebarWithViewport);
    document.removeEventListener('click', onDocumentClick);
});
const initials = computed(() =>
    (user.value?.name ?? '')
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0].toUpperCase())
        .join('')
);

function handleNotifications() {
    toast.push('Belum ada notifikasi baru.', 'success');
}

function onDocumentClick(event) {
    if (! event.target.closest('[aria-label="User menu"]') && ! event.target.closest('.relative.ms-1')) {
        userMenuOpen.value = false;
    }
}

const menuGroups = [
    {
        caption: 'Navigation',
        items: [
            {
                name: 'dashboard',
                label: 'Dashboard',
                icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l9-9 9 9M5 10v10a1 1 0 001 1h4v-6h4v6h4a1 1 0 001-1V10" /></svg>',
            },
        ],
    },
    {
        caption: 'User Interface',
        items: [
            {
                name: 'profile',
                label: 'Profil Saya',
                icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>',
            },
        ],
    },
    {
        caption: 'Manajemen',
        items: [
            {
                name: 'master-users',
                label: 'Pengguna',
                icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87M16 7a4 4 0 11-8 0 4 4 0 018 0zm6 3a3 3 0 11-6 0 3 3 0 016 0zM6 10a3 3 0 11-6 0 3 3 0 016 0z" /></svg>',
                children: [
                    { name: 'users', label: 'Daftar Pengguna', permission: 'users.view' },
                    { name: 'trash', label: 'Sampah', permission: 'users.view' },
                ],
            },
            {
                name: 'master-roles',
                label: 'Role & Permission',
                icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3l7 4v5c0 4.4-3 8.4-7 9-4-0.6-7-4.6-7-9V7l7-4zm0 5v5m0 4h.01" /></svg>',
                children: [
                    { name: 'roles', label: 'Daftar Role', permission: 'roles.view' },
                    { name: 'permissions', label: 'Daftar Permission', permission: 'permissions.view' },
                ],
            },
        ],
    },
    {
        caption: 'Sistem',
        items: [
            {
                name: 'activity-logs',
                label: 'Log Aktivitas',
                permission: 'activity.view',
                icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6M9 8h6M5 21h14a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v15a1 1 0 001 1z" /></svg>',
            },
            {
                name: 'settings',
                label: 'Pengaturan',
                permission: 'settings.view',
                icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>',
            },
        ],
    },
];

// Submenu yang dibuka manual oleh user + submenu yang berisi rute aktif
// (agar tetap terbuka saat refresh langsung di URL halaman tersebut).
const manuallyOpenedSubmenus = ref(new Set());

const openSubmenus = computed(() => {
    const autoOpened = menuGroups
        .flatMap((group) => group.items)
        .filter((item) => item.children?.some((child) => route.name === child.name))
        .map((item) => item.name);

    return new Set([...autoOpened, ...manuallyOpenedSubmenus.value]);
});

function toggleSubmenu(name) {
    const next = new Set(manuallyOpenedSubmenus.value);

    next.has(name) ? next.delete(name) : next.add(name);
    manuallyOpenedSubmenus.value = next;
}

function isGroupActive(item) {
    return item.children?.some((child) => route.name === child.name) ?? false;
}

function flattenAccessibleItems() {
    return menuGroups.flatMap((group) =>
        group.items.flatMap((item) => {
            if (! item.children) {
                return (! item.permission || hasPermission(item.permission)) ? [item] : [];
            }

            const children = item.children.filter(
                (child) => ! child.permission || hasPermission(child.permission)
            );

            return children.length > 0 ? [{ name: children[0].name, label: item.label, icon: item.icon }] : [];
        })
    );
}

const visibleMenuGroups = computed(() =>
    menuGroups
        .map((group) => ({
            caption: group.caption,
            items: group.items.filter((item) => {
                if (! item.children) {
                    return ! item.permission || hasPermission(item.permission);
                }

                return item.children.some(
                    (child) => ! child.permission || hasPermission(child.permission)
                );
            }),
        }))
        .filter((group) => group.items.length > 0)
);

const searchMatches = computed(() => {
    const query = search.value.trim().toLowerCase();

    if (! query) {
        return [];
    }

    return flattenAccessibleItems().filter((menu) =>
        menu.label.toLowerCase().includes(query)
    );
});

const pageTitle = computed(() => route.meta.title ?? 'Dashboard');

async function handleLogout() {
    userMenuOpen.value = false;
    loggingOut.value = true;
    try {
        await logout();
        router.push({ name: 'login' });
    } finally {
        loggingOut.value = false;
    }
}
</script>
