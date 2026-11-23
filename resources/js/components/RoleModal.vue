<template>
    <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/60 p-4 sm:items-center">
        <div class="w-full max-w-2xl rounded-xl bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                <h2 class="text-sm font-semibold text-slate-900">{{ role ? 'Ubah Role' : 'Tambah Role' }}</h2>
                <button class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600" @click="$emit('close')">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form class="max-h-[75vh] space-y-4 overflow-y-auto p-5" @submit.prevent="submit">
                <div class="grid gap-4 sm:grid-cols-2">
                    <BaseInput v-model="form.name" label="Nama Role" required :error="errors.name?.[0]" />
                    <BaseInput
                        v-model="form.slug"
                        label="Slug"
                        required
                        hint="Huruf kecil, angka, tanda hubung"
                        :disabled="role?.is_system"
                        :error="errors.slug?.[0]"
                    />
                </div>
                <BaseInput v-model="form.description" label="Deskripsi" hint="Opsional" :error="errors.description?.[0]" />

                <div>
                    <div class="mb-2 flex items-center justify-between">
                        <p class="text-sm font-medium text-slate-700">Permission</p>
                        <button type="button" class="text-xs font-semibold text-indigo-600 hover:text-indigo-700" @click="toggleAll">
                            {{ allSelected ? 'Hapus semua' : 'Pilih semua' }}
                        </button>
                    </div>

                    <div class="space-y-4">
                        <div v-for="group in permissionGroups" :key="group.group">
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ group.group }}</p>
                            <div class="grid gap-2 sm:grid-cols-2">
                                <label
                                    v-for="permission in group.permissions"
                                    :key="permission.id"
                                    class="flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-xs transition"
                                    :class="form.permissions.includes(permission.id)
                                        ? 'border-indigo-500 bg-indigo-50 text-indigo-700'
                                        : 'border-slate-200 text-slate-600 hover:bg-slate-50'"
                                >
                                    <input v-model="form.permissions" type="checkbox" :value="permission.id" class="h-3.5 w-3.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                                    <span class="truncate">{{ permission.name }}</span>
                                </label>
                            </div>
                        </div>
                    </div>
                    <p v-if="errors.permissions?.[0]" class="mt-1.5 text-xs font-medium text-rose-600">{{ errors.permissions[0] }}</p>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-slate-200 pt-4">
                    <BaseButton variant="secondary" type="button" @click="$emit('close')">Batal</BaseButton>
                    <BaseButton type="submit" :loading="saving">Simpan</BaseButton>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive, watch } from 'vue';
import BaseButton from './BaseButton.vue';
import BaseInput from './BaseInput.vue';

const props = defineProps({
    role: { type: Object, default: null },
    permissionGroups: { type: Array, default: () => [] },
    saving: { type: Boolean, default: false },
    errors: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['close', 'submit']);

const form = reactive({
    name: '',
    slug: '',
    description: '',
    permissions: [],
});

const allPermissionIds = computed(() =>
    props.permissionGroups.flatMap((group) => group.permissions.map((permission) => permission.id))
);

const allSelected = computed(
    () => allPermissionIds.value.length > 0 && form.permissions.length === allPermissionIds.value.length
);

watch(
    () => props.role,
    (role) => {
        form.name = role?.name ?? '';
        form.slug = role?.slug ?? '';
        form.description = role?.description ?? '';
        form.permissions = role?.permissions?.map((permission) => permission.id) ?? [];
    },
    { immediate: true }
);

function toggleAll() {
    form.permissions = allSelected.value ? [] : [...allPermissionIds.value];
}

function submit() {
    emit('submit', {
        name: form.name,
        slug: form.slug,
        description: form.description || null,
        permissions: form.permissions,
    });
}
</script>
