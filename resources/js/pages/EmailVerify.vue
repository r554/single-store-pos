<template>
    <AuthLayout>
        <template #hero>
            <h1 class="text-4xl font-bold leading-tight">
                Satu langkah lagi: verifikasi email Anda.
            </h1>
            <p class="mt-4 text-slate-300">
                Verifikasi memastikan akun Anda dapat dipulihkan dan menerima notifikasi penting.
            </p>
        </template>

        <div class="text-center">
            <div
                v-if="state === 'loading'"
                class="mx-auto flex h-12 w-12 items-center justify-center"
            >
                <svg class="h-8 w-8 animate-spin text-indigo-600" viewBox="0 0 24 24" fill="none">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                </svg>
            </div>

            <template v-else>
                <div
                    class="mx-auto flex h-12 w-12 items-center justify-center rounded-full text-white"
                    :class="state === 'success' ? 'bg-emerald-500' : 'bg-rose-500'"
                >
                    <svg v-if="state === 'success'" class="h-6 w-6" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-7.5 7.5a1 1 0 01-1.4 0l-3.5-3.5a1 1 0 111.4-1.4l2.8 2.8 6.8-6.8a1 1 0 011.4 0z" clip-rule="evenodd" />
                    </svg>
                    <svg v-else class="h-6 w-6" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M4.3 4.3a1 1 0 011.4 0L10 8.6l4.3-4.3a1 1 0 111.4 1.4L11.4 10l4.3 4.3a1 1 0 01-1.4 1.4L10 11.4l-4.3 4.3a1 1 0 01-1.4-1.4L8.6 10 4.3 5.7a1 1 0 010-1.4z" clip-rule="evenodd" />
                    </svg>
                </div>

                <h2 class="mt-4 text-2xl font-bold text-slate-900">
                    {{ state === 'success' ? 'Email terverifikasi' : 'Verifikasi gagal' }}
                </h2>
                <p class="mt-2 text-sm text-slate-500">{{ message }}</p>

                <BaseButton class="mt-6" @click="router.push({ name: 'login' })">
                    Ke halaman login
                </BaseButton>
            </template>
        </div>
    </AuthLayout>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { api } from '../api';
import AuthLayout from '../layouts/AuthLayout.vue';
import BaseButton from '../components/BaseButton.vue';

const router = useRouter();
const route = useRoute();

const state = ref('loading');
const message = ref('Memverifikasi email...');

onMounted(async () => {
    const apiPath = typeof route.query.api === 'string' ? route.query.api : null;

    if (! apiPath) {
        state.value = 'error';
        message.value = 'Tautan verifikasi tidak valid.';
        return;
    }

    try {
        // Tautan berisi path API signed, contoh: /api/email/verify/1?expires=...&signature=...
        const { data } = await api.get(apiPath);
        state.value = 'success';
        message.value = data.message;
    } catch (error) {
        state.value = 'error';
        message.value = error.response?.data?.message ?? 'Tautan verifikasi tidak valid atau sudah kedaluwarsa.';
    }
});
</script>
