<template>
    <AuthLayout>
        <template #hero>
            <h1 class="text-4xl font-bold leading-tight">
                Lupa password? Kami akan kirim tautan reset ke email Anda.
            </h1>
            <p class="mt-4 text-slate-300">
                Tautan reset berlaku selama 60 menit dan hanya dapat digunakan satu kali.
            </p>
        </template>

        <h2 class="text-2xl font-bold text-slate-900">Lupa password</h2>
        <p class="mt-1 text-sm text-slate-500">Masukkan email akun Anda untuk menerima tautan reset.</p>

        <div
            v-if="statusMessage"
            class="mt-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700"
        >
            {{ statusMessage }}
        </div>

        <form class="mt-6 space-y-5" @submit.prevent="submit">
            <BaseInput
                v-model="form.email"
                label="Email"
                type="email"
                placeholder="nama@example.com"
                autocomplete="email"
                required
                :error="errors.email?.[0]"
            />

            <BaseButton type="submit" class="w-full py-3" :loading="loading">
                Kirim Tautan Reset
            </BaseButton>
        </form>

        <p class="mt-6 text-center text-sm text-slate-500">
            <RouterLink :to="{ name: 'login' }" class="font-semibold text-indigo-600 hover:text-indigo-700">
                &larr; Kembali ke login
            </RouterLink>
        </p>
    </AuthLayout>
</template>

<script setup>
import { reactive, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { api } from '../api';
import { useToast } from '../composables/useToast';
import AuthLayout from '../layouts/AuthLayout.vue';
import BaseButton from '../components/BaseButton.vue';
import BaseInput from '../components/BaseInput.vue';

const toast = useToast();

const loading = ref(false);
const statusMessage = ref('');
const errors = ref({});
const form = reactive({
    email: '',
});

async function submit() {
    loading.value = true;
    statusMessage.value = '';
    errors.value = {};

    try {
        const { data } = await api.post('/forgot-password', form);
        statusMessage.value = data.message;
        toast.success(data.message);
    } catch (error) {
        if (error.response?.status === 422) {
            errors.value = error.response.data.errors ?? {};
        } else {
            toast.error(error.response?.data?.message ?? 'Terjadi kesalahan. Silakan coba lagi.');
        }
    } finally {
        loading.value = false;
    }
}
</script>
