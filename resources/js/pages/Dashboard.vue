<template>
    <div class="space-y-6">
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div v-for="card in cards" :key="card.label" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ card.label }}</p>
                        <p class="mt-2 text-3xl font-bold text-slate-900">{{ card.value }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ card.hint }}</p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-lg" :class="card.color" v-html="card.icon" />
                </div>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm lg:col-span-2">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">Pengguna Terbaru</h2>
                        <p class="text-xs text-slate-500">5 akun yang paling baru dibuat</p>
                    </div>
                    <RouterLink v-if="hasPermission('users.view')" :to="{ name: 'users' }" class="text-xs font-semibold text-indigo-600 hover:text-indigo-700">
                        Lihat semua
                    </RouterLink>
                </div>

                <div v-if="loading" class="space-y-3 p-5">
                    <div v-for="n in 4" :key="n" class="h-12 animate-pulse rounded-lg bg-slate-100" />
                </div>

                <div v-else-if="recentUsers.length === 0" class="p-8 text-center text-sm text-slate-500">
                    Belum ada pengguna.
                </div>

                <ul v-else class="divide-y divide-slate-100">
                    <li v-for="item in recentUsers" :key="item.id" class="flex items-center justify-between gap-4 px-5 py-3.5">
                        <div class="flex min-w-0 items-center gap-3">
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xs font-bold text-indigo-700">
                                {{ initials(item.name) }}
                            </div>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-slate-900">{{ item.name }}</p>
                                <p class="truncate text-xs text-slate-500">{{ item.email }}</p>
                            </div>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <span v-for="role in item.roles" :key="role" class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-medium text-slate-600">
                                {{ role }}
                            </span>
                            <span
                                class="rounded-full px-2.5 py-1 text-[11px] font-semibold"
                                :class="item.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'"
                            >
                                {{ item.is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </div>
                    </li>
                </ul>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="text-sm font-semibold text-slate-900">Role</h2>
                    <p class="text-xs text-slate-500">Distribusi pengguna per role</p>
                </div>
                <ul class="divide-y divide-slate-100">
                    <li v-for="role in roles" :key="role.id" class="px-5 py-3.5">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-slate-900">{{ role.name }}</p>
                            <span class="text-xs font-semibold text-slate-500">{{ role.users_count }} pengguna</span>
                        </div>
                        <div class="mt-2 flex items-center gap-2">
                            <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full bg-indigo-500" :style="{ width: barWidth(role.users_count) }" />
                            </div>
                            <span class="text-[11px] text-slate-500">{{ role.permissions_count }} permission</span>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import api from '../api';
import { useAuth } from '../auth';
import { useToast } from '../composables/useToast';

const { hasPermission } = useAuth();
const { error: toastError } = useToast();

const loading = ref(true);
const stats = ref({ users: 0, active_users: 0, roles: 0, permissions: 0 });
const recentUsers = ref([]);
const roles = ref([]);

const maxUsers = computed(() => Math.max(1, ...roles.value.map((role) => role.users_count)));

const cards = computed(() => [
    {
        label: 'Total Pengguna',
        value: stats.value.users,
        hint: `${stats.value.active_users} akun aktif`,
        color: 'bg-indigo-50 text-indigo-600',
        icon: '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87M16 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>',
    },
    {
        label: 'Akun Aktif',
        value: stats.value.active_users,
        hint: 'Dapat login sekarang',
        color: 'bg-emerald-50 text-emerald-600',
        icon: '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>',
    },
    {
        label: 'Role',
        value: stats.value.roles,
        hint: 'Grup hak akses',
        color: 'bg-amber-50 text-amber-600',
        icon: '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3l7 4v5c0 4.4-3 8.4-7 9-4-.6-7-4.6-7-9V7l7-4z" /></svg>',
    },
    {
        label: 'Permission',
        value: stats.value.permissions,
        hint: 'Hak akses terdaftar',
        color: 'bg-sky-50 text-sky-600',
        icon: '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15 7a4 4 0 11-8 0 4 4 0 018 0zM12 11v10m0 0l-3-3m3 3l3-3" /></svg>',
    },
]);

function initials(name = '') {
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0].toUpperCase())
        .join('');
}

function barWidth(count) {
    return `${Math.round((count / maxUsers.value) * 100)}%`;
}

onMounted(async () => {
    try {
        const { data } = await api.get('/dashboard');
        stats.value = data.stats;
        recentUsers.value = data.recent_users;
        roles.value = data.roles;
    } catch (error) {
        toastError('Gagal memuat data dashboard.');
    } finally {
        loading.value = false;
    }
});
</script>
