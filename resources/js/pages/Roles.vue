<template>
    <div class="space-y-6">
        <div class="grid gap-6 lg:grid-cols-3">
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm lg:col-span-1">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">Daftar Role</h2>
                        <p class="text-xs text-slate-500">{{ roles.length }} role terdaftar</p>
                    </div>
                    <BaseButton v-if="hasPermission('roles.create')" class="px-3 py-1.5 text-xs" @click="openCreate">
                        + Role
                    </BaseButton>
                </div>

                <ul class="divide-y divide-slate-100">
                    <li v-for="role in roles" :key="role.id">
                        <button
                            class="flex w-full items-start justify-between gap-3 px-5 py-3.5 text-left transition hover:bg-slate-50"
                            :class="selected?.id === role.id ? 'bg-indigo-50/70' : ''"
                            @click="selected = role"
                        >
                            <div class="min-w-0">
                                <p class="flex items-center gap-2 text-sm font-semibold text-slate-900">
                                    {{ role.name }}
                                    <span v-if="role.is_system" class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-slate-500">sistem</span>
                                </p>
                                <p class="mt-0.5 truncate text-xs text-slate-500">{{ role.description || 'Tanpa deskripsi' }}</p>
                                <p class="mt-1 text-[11px] text-slate-400">
                                    {{ role.users_count }} pengguna &middot; {{ role.permissions_count }} permission
                                </p>
                            </div>
                            <svg class="mt-1 h-4 w-4 shrink-0 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                    </li>
                </ul>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white shadow-sm lg:col-span-2">
                <div v-if="!selected" class="flex h-64 items-center justify-center text-sm text-slate-500">
                    Pilih role untuk melihat detail permission.
                </div>

                <template v-else>
                    <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h2 class="text-base font-semibold text-slate-900">{{ selected.name }}</h2>
                            <p class="mt-0.5 text-xs text-slate-500">{{ selected.description || 'Tanpa deskripsi' }}</p>
                            <p class="mt-1 font-mono text-[11px] text-slate-400">{{ selected.slug }}</p>
                        </div>
                        <div class="flex gap-2">
                            <BaseButton v-if="hasPermission('roles.update')" variant="secondary" class="px-3 py-1.5 text-xs" @click="openEdit">
                                Ubah
                            </BaseButton>
                            <BaseButton
                                v-if="hasPermission('roles.delete') && !selected.is_system"
                                variant="danger"
                                class="px-3 py-1.5 text-xs"
                                @click="deleting = true"
                            >
                                Hapus
                            </BaseButton>
                        </div>
                    </div>

                    <div class="space-y-5 p-5">
                        <div v-for="group in permissionGroups" :key="group.group">
                            <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ group.group }}</h3>
                            <div class="grid gap-2 sm:grid-cols-2">
                                <div
                                    v-for="permission in group.permissions"
                                    :key="permission.id"
                                    class="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2"
                                >
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium text-slate-800">{{ permission.name }}</p>
                                        <p class="truncate font-mono text-[11px] text-slate-400">{{ permission.slug }}</p>
                                    </div>
                                    <span
                                        class="ml-2 shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold"
                                        :class="hasPermissionInRole(permission.id)
                                            ? 'bg-emerald-50 text-emerald-700'
                                            : 'bg-slate-100 text-slate-400'"
                                    >
                                        {{ hasPermissionInRole(permission.id) ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <RoleModal
            v-if="modalOpen"
            :role="editing"
            :permission-groups="permissionGroups"
            :saving="saving"
            :errors="formErrors"
            @close="modalOpen = false"
            @submit="saveRole"
        />

        <ConfirmDialog
            v-if="deleting"
            title="Hapus role?"
            :message="`Role ${selected?.name} akan dihapus permanen.`"
            :loading="deletingInProgress"
            @cancel="deleting = false"
            @confirm="deleteRole"
        />
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import api from '../api';
import { useAuth } from '../auth';
import { useToast } from '../composables/useToast';
import { useSwal } from '../composables/useSwal';
import BaseButton from '../components/BaseButton.vue';
import RoleModal from '../components/RoleModal.vue';
import ConfirmDialog from '../components/ConfirmDialog.vue';

const { hasPermission } = useAuth();
const { error: toastError } = useToast();
const { success } = useSwal();

const roles = ref([]);
const permissionGroups = ref([]);
const selected = ref(null);
const modalOpen = ref(false);
const editing = ref(null);
const saving = ref(false);
const formErrors = ref({});
const deleting = ref(false);
const deletingInProgress = ref(false);

const selectedPermissionIds = computed(() => new Set((selected.value?.permissions ?? []).map((p) => p.id)));

function hasPermissionInRole(id) {
    return selectedPermissionIds.value.has(id);
}

async function fetchRoles() {
    try {
        const { data } = await api.get('/roles');
        roles.value = data.roles;
        permissionGroups.value = data.permissions;

        if (selected.value) {
            selected.value = roles.value.find((role) => role.id === selected.value.id) ?? null;
        }
        if (!selected.value && roles.value.length) {
            selected.value = roles.value[0];
        }
    } catch (error) {
        toastError('Gagal memuat data role.');
    }
}

function openCreate() {
    editing.value = null;
    formErrors.value = {};
    modalOpen.value = true;
}

function openEdit() {
    editing.value = selected.value;
    formErrors.value = {};
    modalOpen.value = true;
}

async function saveRole(payload) {
    saving.value = true;
    formErrors.value = {};
    try {
        if (editing.value) {
            await api.put(`/roles/${editing.value.id}`, payload);
            success('Role berhasil diperbarui.');
        } else {
            await api.post('/roles', payload);
            success('Role berhasil dibuat.');
        }
        modalOpen.value = false;
        await fetchRoles();
    } catch (error) {
        if (error.response?.status === 422) {
            formErrors.value = error.response.data.errors ?? {};
        } else {
            toastError(error.response?.data?.message ?? 'Gagal menyimpan role.');
        }
    } finally {
        saving.value = false;
    }
}

async function deleteRole() {
    deletingInProgress.value = true;
    try {
        await api.delete(`/roles/${selected.value.id}`);
        success('Role berhasil dihapus.');
        deleting.value = false;
        selected.value = null;
        await fetchRoles();
    } catch (error) {
        toastError(error.response?.data?.message ?? 'Gagal menghapus role.');
    } finally {
        deletingInProgress.value = false;
    }
}

onMounted(fetchRoles);
</script>
