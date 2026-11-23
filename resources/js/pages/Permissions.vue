<template>
    <DataTable
        :columns="columns"
        :items="permissions"
        :loading="loading"
        :pagination="pagination"
        :sort-key="sortKey"
        :sort-dir="sortDir"
        @page="fetchPermissions"
        @sort="toggleSort"
    >
        <template #filters>
            <input
                v-model="search"
                type="search"
                placeholder="Cari nama atau slug..."
                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200 sm:max-w-xs dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                @input="debouncedFetch"
            />
        </template>

        <template #actions>
            <BaseButton v-if="hasPermission('permissions.create')" @click="openCreate">
                + Permission
            </BaseButton>
        </template>

        <template #cell-name="{ item }">
            <div>
                <p class="font-medium text-slate-900 dark:text-slate-100">{{ item.name }}</p>
                <p class="font-mono text-[11px] text-slate-400">{{ item.slug }}</p>
            </div>
        </template>

        <template #cell-group="{ item }">
            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-medium text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                {{ item.group }}
            </span>
        </template>

        <template #cell-roles_count="{ item }">
            <span class="text-xs text-slate-500 dark:text-slate-400">{{ item.roles_count }} role</span>
        </template>

        <template #cell-actions="{ item }">
            <div class="flex items-center justify-end gap-2">
                <button
                    v-if="hasPermission('permissions.update')"
                    class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700"
                    @click="openEdit(item)"
                >
                    Ubah
                </button>
                <button
                    v-if="hasPermission('permissions.delete')"
                    class="rounded-lg border border-rose-200 px-2.5 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50 dark:border-rose-800 dark:hover:bg-rose-900/30"
                    @click="confirmDelete(item)"
                >
                    Hapus
                </button>
            </div>
        </template>
    </DataTable>

    <PermissionModal
        v-if="modalOpen"
        :permission="editing"
        :saving="saving"
        :errors="formErrors"
        @close="closeModal"
        @submit="savePermission"
    />

    <ConfirmDialog
        v-if="deleting"
        title="Hapus permission?"
        :message="`Permission ${deleting.name} akan dihapus dari semua role.`"
        :loading="deletingInProgress"
        @cancel="deleting = null"
        @confirm="deletePermission"
    />
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import api from '../api';
import { useAuth } from '../auth';
import { useToast } from '../composables/useToast';
import BaseButton from '../components/BaseButton.vue';
import DataTable from '../components/DataTable.vue';
import PermissionModal from '../components/PermissionModal.vue';
import ConfirmDialog from '../components/ConfirmDialog.vue';

const { hasPermission } = useAuth();
const { error: toastError, success: toastSuccess } = useToast();

const columns = [
    { key: 'name', label: 'Permission', sortable: true },
    { key: 'group', label: 'Grup', sortable: true },
    { key: 'roles_count', label: 'Dipakai', sortable: false },
    { key: 'actions', label: '', sortable: false },
];

const permissions = ref([]);
const loading = ref(true);
const search = ref('');
const sortKey = ref('group');
const sortDir = ref('asc');
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
    debounceTimer = setTimeout(() => fetchPermissions(1), 350);
}

function toggleSort(key) {
    if (sortKey.value === key) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortKey.value = key;
        sortDir.value = 'asc';
    }
    fetchPermissions(1);
}

async function fetchPermissions(page = 1) {
    loading.value = true;
    try {
        const { data } = await api.get('/permissions', {
            params: {
                page,
                search: search.value || undefined,
                sort: sortKey.value,
                dir: sortDir.value,
            },
        });
        permissions.value = data.data;
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
        toastError('Gagal memuat daftar permission.');
    } finally {
        loading.value = false;
    }
}

function openCreate() {
    editing.value = null;
    formErrors.value = {};
    modalOpen.value = true;
}

function openEdit(permission) {
    editing.value = permission;
    formErrors.value = {};
    modalOpen.value = true;
}

function closeModal() {
    modalOpen.value = false;
    editing.value = null;
}

async function savePermission(payload) {
    saving.value = true;
    formErrors.value = {};
    try {
        if (editing.value) {
            await api.put(`/permissions/${editing.value.id}`, payload);
            toastSuccess('Permission berhasil diperbarui.');
        } else {
            await api.post('/permissions', payload);
            toastSuccess('Permission berhasil dibuat.');
        }
        closeModal();
        await fetchPermissions(pagination.current_page);
    } catch (error) {
        if (error.response?.status === 422) {
            formErrors.value = error.response.data.errors ?? {};
        } else {
            toastError(error.response?.data?.message ?? 'Gagal menyimpan permission.');
        }
    } finally {
        saving.value = false;
    }
}

async function deletePermission() {
    deletingInProgress.value = true;
    try {
        await api.delete(`/permissions/${deleting.value.id}`);
        toastSuccess('Permission berhasil dihapus.');
        deleting.value = null;
        await fetchPermissions(pagination.current_page);
    } catch (error) {
        toastError(error.response?.data?.message ?? 'Gagal menghapus permission.');
    } finally {
        deletingInProgress.value = false;
    }
}

onMounted(() => fetchPermissions());
</script>
