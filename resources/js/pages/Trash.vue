<template>
    <DataTable
        :columns="columns"
        :items="users"
        :loading="loading"
        :pagination="pagination"
        @page="fetchTrash"
    >
        <template #filters>
            <input
                v-model="search"
                type="search"
                placeholder="Cari nama atau email..."
                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200 sm:max-w-xs dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                @input="debouncedFetch"
            />
        </template>

        <template #cell-name="{ item }">
            <div class="flex items-center gap-3">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-200 text-xs font-bold text-slate-600 dark:bg-slate-700 dark:text-slate-300">
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

        <template #cell-deleted_at="{ item }">
            <span class="text-xs text-slate-500 dark:text-slate-400">{{ formatDate(item.deleted_at) }}</span>
        </template>

        <template #cell-actions="{ item }">
            <div class="flex items-center justify-end gap-2">
                <button
                    v-if="hasPermission('users.update')"
                    class="rounded-lg border border-emerald-200 px-2.5 py-1.5 text-xs font-semibold text-emerald-600 hover:bg-emerald-50 dark:border-emerald-800 dark:hover:bg-emerald-900/30"
                    @click="restore(item)"
                >
                    Pulihkan
                </button>
                <button
                    v-if="hasPermission('users.delete')"
                    class="rounded-lg border border-rose-200 px-2.5 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50 dark:border-rose-800 dark:hover:bg-rose-900/30"
                    @click="confirmDelete(item)"
                >
                    Hapus Permanen
                </button>
            </div>
        </template>

        <template #empty>
            Sampah kosong.
        </template>
    </DataTable>

    <ConfirmDialog
        v-if="deleting"
        title="Hapus permanen?"
        message="Akun ini akan dihapus permanen dan tidak dapat dipulihkan lagi."
        :loading="deletingInProgress"
        @cancel="deleting = null"
        @confirm="forceDelete"
    />
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import api from '../api';
import { useAuth } from '../auth';
import { useToast } from '../composables/useToast';
import DataTable from '../components/DataTable.vue';
import ConfirmDialog from '../components/ConfirmDialog.vue';

const { hasPermission } = useAuth();
const { error: toastError, success: toastSuccess } = useToast();

const columns = [
    { key: 'name', label: 'Pengguna', sortable: false },
    { key: 'roles', label: 'Role', sortable: false },
    { key: 'deleted_at', label: 'Dihapus', sortable: false },
    { key: 'actions', label: '', sortable: false },
];

const users = ref([]);
const loading = ref(true);
const search = ref('');
const pagination = reactive({ current_page: 1, last_page: 1, total: 0, from: 0, to: 0, next_page_url: null, prev_page_url: null });

const deleting = ref(null);
const deletingInProgress = ref(false);

let debounceTimer = null;

function debouncedFetch() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchTrash(1), 350);
}

async function fetchTrash(page = 1) {
    loading.value = true;
    try {
        const { data } = await api.get('/users/trash', {
            params: { page, search: search.value || undefined },
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
        toastError('Gagal memuat sampah.');
    } finally {
        loading.value = false;
    }
}

async function restore(user) {
    try {
        const { data } = await api.post(`/users/${user.id}/restore`);
        toastSuccess(data.message);
        await fetchTrash(pagination.current_page);
    } catch (error) {
        toastError(error.response?.data?.message ?? 'Gagal memulihkan pengguna.');
    }
}

function confirmDelete(user) {
    deleting.value = user;
}

async function forceDelete() {
    deletingInProgress.value = true;
    try {
        const { data } = await api.delete(`/users/${deleting.value.id}/force`);
        toastSuccess(data.message);
        deleting.value = null;
        await fetchTrash(pagination.current_page);
    } catch (error) {
        toastError(error.response?.data?.message ?? 'Gagal menghapus permanen.');
    } finally {
        deletingInProgress.value = false;
    }
}

function initials(name = '') {
    return name.split(' ').filter(Boolean).slice(0, 2).map((part) => part[0].toUpperCase()).join('');
}

function formatDate(value) {
    if (! value) return '-';

    return new Date(value).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
}

onMounted(() => fetchTrash());
</script>
