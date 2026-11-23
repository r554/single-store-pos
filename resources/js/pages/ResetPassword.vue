<template>
    <AuthLayout>
        <template #hero>
            <h1 class="text-4xl font-bold leading-tight">
                Atur password baru Anda dan lanjutkan pekerjaan.
            </h1>
            <p class="mt-4 text-slate-300">
                Gunakan password yang kuat: minimal 8 karakter, kombinasi huruf, angka, dan simbol.
            </p>
        </template>

        <h2 class="text-2xl font-bold text-slate-900">Reset password</h2>
        <p class="mt-1 text-sm text-slate-500">Reset untuk akun: {{ form.email || '(isi email)' }}</p>

        <div
            v-if="statusMessage"
            class="mt-5 rounded-lg border px-4 py-3 text-sm"
            :class="statusType === 'error'
                ? 'border-rose-200 bg-rose-50 text-rose-700'
                : 'border-emerald-200 bg-emerald-50 text-emerald-700'"
        >
            {{ statusMessage }}
        </div>

        <form class="mt-6 space-y-5" @submit.prevent="submit">
            <BaseInput
                v-model="form.email"
                label="Email"
                type="email"
                autocomplete="email"
                required
                :error="errors.email?.[0]"
            />

            <BaseInput
                v-model="form.password"
                label="Password baru"
                type="password"
                placeholder="Minimal 8 karakter"
                autocomplete="new-password"
                required
                :error="errors.password?.[0]"
            />

            <BaseInput
                v-model="form.password_confirmation"
                label="Konfirmasi Password"
                type="password"
                placeholder="Ulangi password"
                autocomplete="new-password"
                required
                :error="errors.password_confirmation?.[0]"
            />

            <BaseButton type="submit" class="w-full py-3" :loading="loading">
                Reset Password
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
import { onMounted, reactive, ref } from 'vue';
import { RouterLink, useRouter, useRoute } from 'vue-router';
import { api } from '../api';
import { useToast } from '../composables/useToast';
import AuthLayout from '../layouts/AuthLayout.vue';
import BaseButton from '../components/BaseButton.vue';
import BaseInput from '../components/BaseInput.vue';

const router = useRouter();
const route = useRoute();
const toast = useToast();

const loading = ref(false);
const statusMessage = ref('');
const statusType = ref('error');
const errors = ref({});
const form = reactive({
    token: '',
    email: '',
    password: '',
    password_confirmation: '',
});

onMounted(() => {
    form.token = typeof route.query.token === 'string' ? route.query.token : '';
    form.email = typeof route.query.email === 'string' ? route.query.email : '';
});

async function submit() {
    loading.value = true;
    statusMessage.value = '';
    errors.value = {};

    try {
        const { data } = await api.post('/reset-password', form);
        statusType.value = 'success';
        statusMessage.value = data.message;
        toast.success(data.message);
        router.push({ name: 'login' });
    } catch (error) {
        if (error.response?.status === 422) {
            errors.value = error.response.data.errors ?? {};
            statusMessage.value = error.response.data.message ?? '';
        } else {
            statusMessage.value = error.response?.data?.message ?? 'Terjadi kesalahan. Silakan coba lagi.';
        }
    } finally {
        loading.value = false;
    }
}
</script>
