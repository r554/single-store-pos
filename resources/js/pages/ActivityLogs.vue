<template>
    <DataTable
        :columns="columns"
        :items="logs"
        :loading="loading"
        :pagination="pagination"
        @page="fetchLogs"
    >
        <template #filters>
            <input
                v-model="search"
                type="search"
                placeholder="Cari deskripsi atau pengguna..."
                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200 sm:max-w-xs dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                @input="debouncedFetch"
            />
            <select
                v-model="actionFilter"
                class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                @change="fetchLogs(1)"
            >
                <option value="">Semua aksi</option>
                <option value="created">Dibuat</option>
                <option value="updated">Diperbarui</option>
                <option value="deleted">Dihapus</option>
                <option value="restored">Dipulihkan</option>
                <option value="force_deleted">Dihapus permanen</option>
            </select>
        </template>

        <template #cell-user_name="{ item }">
            <div>
                <p class="font-medium text-slate-900 dark:text-slate-100">{{ item.user_name ?? 'Sistem' }}</p>
                <p class="text-[11px] text-slate-400">{{ item.ip_address }}</p>
            </div>
        </template>

        <template #cell-action="{ item }">
            <span
                class="rounded-full px-2.5 py-1 text-[11px] font-semibold"
                :class="actionClass(item.action)"
            >
                {{ actionLabel(item.action) }}
            </span>
        </template>

        <template #cell-description="{ item }">
            <div class="min-w-0">
                <p class="truncate text-slate-800 dark:text-slate-200">{{ item.description }}</p>
                <p v-if="item.properties?.fields" class="truncate text-[11px] text-slate-400">
                    Kolom: {{ item.properties.fields.join(', ') }}
                </p>
            </div>
        </template>

        <template #cell-created_at="{ item }">
            <span class="text-xs text-slate-500 dark:text-slate-400">{{ formatDate(item.created_at) }}</span>
        </template>

        <template #empty>
            Belum ada aktivitas tercatat.
        </template>
    </DataTable>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import api from '../api';
import { useToast } from '../composables/useToast';
import DataTable from '../components/DataTable.vue';

const { error: toastError } = useToast();

const columns = [
    { key: 'user_name', label: 'Pengguna', sortable: false },
    { key: 'action', label: 'Aksi', sortable: false },
    { key: 'description', label: 'Deskripsi', sortable: false },
    { key: 'created_at', label: 'Waktu', sortable: false },
];

const logs = ref([]);
const loading = ref(true);
const search = ref('');
const actionFilter = ref('');
const pagination = reactive({ current_page: 1, last_page: 1, total: 0, from: 0, to: 0, next_page_url: null, prev_page_url: null });

let debounceTimer = null;

function debouncedFetch() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchLogs(1), 350);
}

async function fetchLogs(page = 1) {
    loading.value = true;
    try {
        const { data } = await api.get('/activity-logs', {
            params: {
                page,
                search: search.value || undefined,
                action: actionFilter.value || undefined,
            },
        });
        logs.value = data.data;
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
        toastError('Gagal memuat log aktivitas.');
    } finally {
        loading.value = false;
    }
}

function actionLabel(action) {
    return {
        created: 'Dibuat',
        updated: 'Diubah',
        deleted: 'Dihapus',
        restored: 'Dipulihkan',
        force_deleted: 'Hapus permanen',
    }[action] ?? action;
}

function actionClass(action) {
    return {
        created: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
        updated: 'bg-sky-50 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300',
        deleted: 'bg-rose-50 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300',
        restored: 'bg-amber-50 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
        force_deleted: 'bg-rose-100 text-rose-800 dark:bg-rose-900/60 dark:text-rose-200',
    }[action] ?? 'bg-slate-100 text-slate-600';
}

function formatDate(value) {
    if (! value) return '-';

    return new Date(value).toLocaleString('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

onMounted(() => fetchLogs());
</script>
