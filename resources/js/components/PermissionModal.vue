<template>
    <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/60 p-4 sm:items-center">
        <div class="w-full max-w-md rounded-xl bg-white shadow-2xl dark:bg-slate-800">
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-slate-700">
                <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">
                    {{ permission ? 'Ubah Permission' : 'Tambah Permission' }}
                </h2>
                <button class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-700" @click="$emit('close')">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form class="space-y-4 p-5" @submit.prevent="submit">
                <BaseInput v-model="form.name" label="Nama" required :error="errors.name?.[0]" />
                <BaseInput
                    v-model="form.slug"
                    label="Slug"
                    required
                    hint="Format: grup.aksi, mis. users.view"
                    :error="errors.slug?.[0]"
                />
                <BaseInput v-model="form.group" label="Grup" required hint="Mis. Pengguna, Role, Sistem" :error="errors.group?.[0]" />
                <BaseInput v-model="form.description" label="Deskripsi" hint="Opsional" :error="errors.description?.[0]" />

                <div class="flex items-center justify-end gap-2 border-t border-slate-200 pt-4 dark:border-slate-700">
                    <BaseButton variant="secondary" type="button" @click="$emit('close')">Batal</BaseButton>
                    <BaseButton type="submit" :loading="saving">Simpan</BaseButton>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup>
import { reactive, watch } from 'vue';
import BaseButton from './BaseButton.vue';
import BaseInput from './BaseInput.vue';

const props = defineProps({
    permission: { type: Object, default: null },
    saving: { type: Boolean, default: false },
    errors: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['close', 'submit']);

const form = reactive({
    name: '',
    slug: '',
    group: '',
    description: '',
});

watch(
    () => props.permission,
    (permission) => {
        form.name = permission?.name ?? '';
        form.slug = permission?.slug ?? '';
        form.group = permission?.group ?? '';
        form.description = permission?.description ?? '';
    },
    { immediate: true }
);

function submit() {
    emit('submit', {
        name: form.name,
        slug: form.slug,
        group: form.group,
        description: form.description || null,
    });
}
</script>
