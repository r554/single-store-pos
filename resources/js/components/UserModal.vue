<template>
    <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/60 p-4 sm:items-center">
        <div class="w-full max-w-lg rounded-xl bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                <h2 class="text-sm font-semibold text-slate-900">
                    {{ user ? 'Ubah Pengguna' : 'Tambah Pengguna' }}
                </h2>
                <button class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600" @click="$emit('close')">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form class="space-y-4 p-5" @submit.prevent="submit">
                <BaseInput v-model="form.name" label="Nama" required :error="errors.name?.[0]" />
                <BaseInput v-model="form.email" label="Email" type="email" required :error="errors.email?.[0]" />
                <BaseInput v-model="form.phone" label="Telepon" hint="Opsional" :error="errors.phone?.[0]" />
                <BaseInput
                    v-model="form.password"
                    label="Password"
                    type="password"
                    :required="!user"
                    :hint="user ? 'Kosongkan jika tidak ingin mengubah password' : 'Minimal 8 karakter'"
                    :error="errors.password?.[0]"
                />

                <div>
                    <p class="mb-2 text-sm font-medium text-slate-700">Role</p>
                    <div class="flex flex-wrap gap-2">
                        <label
                            v-for="role in roles"
                            :key="role.id"
                            class="flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-xs font-medium transition"
                            :class="form.roles.includes(role.slug)
                                ? 'border-indigo-500 bg-indigo-50 text-indigo-700'
                                : 'border-slate-300 text-slate-600 hover:bg-slate-50'"
                        >
                            <input v-model="form.roles" type="checkbox" :value="role.slug" class="h-3.5 w-3.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                            {{ role.name }}
                        </label>
                    </div>
                    <p v-if="errors.roles?.[0]" class="mt-1.5 text-xs font-medium text-rose-600">{{ errors.roles[0] }}</p>
                </div>

                <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-600">
                    <input v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                    Akun aktif (dapat login)
                </label>

                <div class="flex items-center justify-end gap-2 border-t border-slate-200 pt-4">
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
    user: { type: Object, default: null },
    roles: { type: Array, default: () => [] },
    saving: { type: Boolean, default: false },
    errors: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['close', 'submit']);

const form = reactive({
    name: '',
    email: '',
    phone: '',
    password: '',
    is_active: true,
    roles: [],
});

watch(
    () => props.user,
    (user) => {
        form.name = user?.name ?? '';
        form.email = user?.email ?? '';
        form.phone = user?.phone ?? '';
        form.password = '';
        form.is_active = user?.is_active ?? true;
        form.roles = user?.roles?.map((role) => role.slug) ?? [];
    },
    { immediate: true }
);

function submit() {
    const payload = {
        name: form.name,
        email: form.email,
        phone: form.phone || null,
        is_active: form.is_active,
        roles: form.roles,
    };

    if (form.password) {
        payload.password = form.password;
    }

    emit('submit', payload);
}
</script>
