<template>
    <DataTable
        :columns="columns"
        :items="users"
        :loading="loading"
        :pagination="pagination"
        @page="fetchUsers"
    >
        <template #filters>
            <input
                v-model="search"
                type="search"
                placeholder="Cari nama, email, atau telepon..."
                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200 sm:max-w-xs dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                @input="debouncedFetch"
            />
            <select
                v-model="roleFilter"
                class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                @change="fetchUsers(1)"
            >
                <option value="">Semua role</option>
                <option v-for="role in roles" :key="role.id" :value="role.slug">{{ role.name }}</option>
            </select>
        </template>

        <template #actions>
            <RouterLink
                :to="{ name: 'trash' }"
                class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700"
            >
                Sampah
            </RouterLink>
            <button
                class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700"
                @click="exportCsv"
            >
                Ekspor
            </button>
            <label
                v-if="hasPermission('users.create')"
                class="cursor-pointer rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700"
            >
                Impor CSV
                <input type="file" accept=".csv,.txt" class="hidden" @change="importCsv" />
            </label>
            <BaseButton v-if="hasPermission('users.create')" @click="openCreate">
                + Tambah Pengguna
            </BaseButton>
        </template>

        <template #cell-name="{ item }">
            <div class="flex items-center gap-3">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xs font-bold text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300">
                    {{ initials(item.name) }}
                </div>
                <div class="min-w-0">
                    <p class="truncate font-medium text-slate-900 dark:text-slate-100">{{ item.name }}</p>
                    <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ item.email }}</p>
                </div>
            </div>
        </template>

        <template #cell-roles="{ item }">
            <div class="flex flex-wrap gap-1">
                <span
                    v-for="role in item.roles"
                    :key="role.id"
                    class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-medium text-slate-600 dark:bg-slate-700 dark:text-slate-300"
                >
                    {{ role.name }}
                </span>
                <span v-if="item.roles.length === 0" class="text-xs text-slate-400">Tanpa role</span>
            </div>
        </template>

        <template #cell-is_active="{ item }">
            <span
                class="rounded-full px-2.5 py-1 text-[11px] font-semibold"
                :class="item.is_active
                    ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'
                    : 'bg-rose-50 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300'"
            >
                {{ item.is_active ? 'Aktif' : 'Nonaktif' }}
            </span>
        </template>

        <template #cell-last_login_at="{ item }">
            <span class="text-xs text-slate-500 dark:text-slate-400">{{ formatDate(item.last_login_at) }}</span>
        </template>

        <template #cell-actions="{ item }">
            <div class="flex items-center justify-end gap-2">
                <button
                    v-if="hasPermission('users.update')"
                    class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700"
                    @click="openEdit(item)"
                >
                    Ubah
                </button>
                <button
                    v-if="hasPermission('users.delete') && !isSuperAdmin(item)"
                    class="rounded-lg border border-rose-200 px-2.5 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50 dark:border-rose-800 dark:hover:bg-rose-900/30"
                    @click="confirmDelete(item)"
                >
                    Hapus
                </button>
            </div>
        </template>
    </DataTable>

    <UserModal
        v-if="modalOpen"
        :user="editing"
        :roles="roles"
        :saving="saving"
        :errors="formErrors"
        @close="closeModal"
        @submit="saveUser"
    />

    <ConfirmDialog
        v-if="deleting"
        title="Hapus pengguna?"
        :message="`Akun ${deleting.name} akan dipindahkan ke sampah.`"
        :loading="deletingInProgress"
        @cancel="deleting = null"
        @confirm="deleteUser"
    />
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import { RouterLink } from 'vue-router';
import api from '../api';
import { useAuth } from '../auth';
import { useToast } from '../composables/useToast';
import { useSwal } from '../composables/useSwal';
import BaseButton from '../components/BaseButton.vue';
import DataTable from '../components/DataTable.vue';
import UserModal from '../components/UserModal.vue';
import ConfirmDialog from '../components/ConfirmDialog.vue';

const { hasPermission } = useAuth();
const { error: toastError, success: toastSuccess } = useToast();
const { success } = useSwal();

const columns = [
    { key: 'name', label: 'Pengguna', sortable: false },
    { key: 'roles', label: 'Role', sortable: false },
    { key: 'is_active', label: 'Status', sortable: false },
    { key: 'last_login_at', label: 'Login Terakhir', sortable: false },
    { key: 'actions', label: '', sortable: false },
];

const users = ref([]);
const roles = ref([]);
const loading = ref(true);
const search = ref('');
const roleFilter = ref('');
const pagination = reactive({ current_page: 1, last_page: 1, total: 0, from: 0, to: 0, next_page_url: null, prev_page_url: null });

const modalOpen = ref(false);
const editing = ref(null);
const saving = ref(false);
const formErrors = ref({});
const deleting = ref(null);
const deletingInProgress = ref(false);

let debounceTimer = null;

function debouncedFetch() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchUsers(1), 350);
}

async function fetchUsers(page = 1) {
    loading.value = true;
    try {
        const { data } = await api.get('/users', {
            params: { page, search: search.value || undefined, role: roleFilter.value || undefined },
        });
        users.value = data.data;
        Object.assign(pagination, {
            current_page: data.current_page,
            last_page: data.last_page,
            total: data.total,
            from: data.from,
            to: data.to,
            next_page_url: data.next_page_url,
            prev_page_url: data.prev_page_url,
        });
    } catch (error) {
        toastError('Gagal memuat daftar pengguna.');
    } finally {
        loading.value = false;
    }
}

async function fetchRoles() {
    try {
        const { data } = await api.get('/roles');
        roles.value = data.roles;
    } catch (error) {
        // diamkan; role hanya pelengkap filter
    }
}

function openCreate() {
    editing.value = null;
    formErrors.value = {};
    modalOpen.value = true;
}

function openEdit(user) {
    editing.value = user;
    formErrors.value = {};
    modalOpen.value = true;
}

function closeModal() {
    modalOpen.value = false;
    editing.value = null;
}

async function saveUser(payload) {
    saving.value = true;
    formErrors.value = {};
    try {
        if (editing.value) {
            await api.put(`/users/${editing.value.id}`, payload);
            success('Pengguna berhasil diperbarui.');
        } else {
            await api.post('/users', payload);
            success('Pengguna berhasil dibuat.');
        }
        closeModal();
        await fetchUsers(pagination.current_page);
    } catch (error) {
        if (error.response?.status === 422) {
            formErrors.value = error.response.data.errors ?? {};
        } else {
            toastError(error.response?.data?.message ?? 'Gagal menyimpan pengguna.');
        }
    } finally {
        saving.value = false;
    }
}

function confirmDelete(user) {
    deleting.value = user;
}

async function deleteUser() {
    deletingInProgress.value = true;
    try {
        await api.delete(`/users/${deleting.value.id}`);
        success('Pengguna berhasil dihapus.');
        deleting.value = null;
        await fetchUsers(pagination.current_page);
    } catch (error) {
        toastError(error.response?.data?.message ?? 'Gagal menghapus pengguna.');
    } finally {
        deletingInProgress.value = false;
    }
}

async function exportCsv() {
    try {
        const response = await api.get('/users/export', { responseType: 'blob' });
        const url = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement('a');
        link.href = url;
        link.download = `users-${new Date().toISOString().slice(0, 10)}.csv`;
        link.click();
        window.URL.revokeObjectURL(url);
    } catch (error) {
        toastError('Gagal mengekspor data.');
    }
}

async function importCsv(event) {
    const file = event.target.files?.[0];
    event.target.value = '';

    if (! file) return;

    const formData = new FormData();
    formData.append('file', file);

    try {
        const { data } = await api.post('/users/import', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        toastSuccess(data.message);
        await fetchUsers(1);
    } catch (error) {
        if (error.response?.status === 422) {
            toastError(Object.values(error.response.data.errors ?? {})[0]?.[0] ?? 'File tidak valid.');
        } else {
            toastError(error.response?.data?.message ?? 'Gagal mengimpor data.');
        }
    }
}

function isSuperAdmin(user) {
    return user.roles?.some((role) => role.slug === 'superadmin');
}

function initials(name = '') {
    return name.split(' ').filter(Boolean).slice(0, 2).map((part) => part[0].toUpperCase()).join('');
}

function formatDate(value) {
    if (!value) return 'Belum pernah';
    return new Date(value).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
}

onMounted(async () => {
    await Promise.all([fetchUsers(), fetchRoles()]);
});
</script>
