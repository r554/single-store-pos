<template>
    <AuthLayout>
        <template #hero>
            <h1 class="text-4xl font-bold leading-tight">
                Buat akun dan mulai mengelola akses aplikasi Anda.
            </h1>
            <p class="mt-4 text-slate-300">
                Registrasi memberi Anda akun dengan role bawaan. Superadmin dapat menambahkan role lain setelahnya.
            </p>
        </template>

        <h2 class="text-2xl font-bold text-slate-900">Daftar akun baru</h2>
        <p class="mt-1 text-sm text-slate-500">Isi data di bawah untuk membuat akun.</p>

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
                v-model="form.name"
                label="Nama"
                type="text"
                placeholder="Nama lengkap"
                autocomplete="name"
                required
                :error="errors.name?.[0]"
            />

            <BaseInput
                v-model="form.email"
                label="Email"
                type="email"
                placeholder="nama@example.com"
                autocomplete="email"
                required
                :error="errors.email?.[0]"
            />

            <BaseInput
                v-model="form.phone"
                label="Telepon (opsional)"
                type="text"
                placeholder="08xxxxxxxxxx"
                autocomplete="tel"
                :error="errors.phone?.[0]"
            />

            <BaseInput
                v-model="form.password"
                label="Password"
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
                Daftar
            </BaseButton>
        </form>

        <p class="mt-6 text-center text-sm text-slate-500">
            Sudah punya akun?
            <RouterLink :to="{ name: 'login' }" class="font-semibold text-indigo-600 hover:text-indigo-700">
                Masuk
            </RouterLink>
        </p>
    </AuthLayout>
</template>

<script setup>
import { reactive, ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { api } from '../api';
import { useAuth } from '../auth';
import { useToast } from '../composables/useToast';
import AuthLayout from '../layouts/AuthLayout.vue';
import BaseButton from '../components/BaseButton.vue';
import BaseInput from '../components/BaseInput.vue';

const router = useRouter();
const { setUser } = useAuth();
const toast = useToast();

const loading = ref(false);
const statusMessage = ref('');
const statusType = ref('error');
const errors = ref({});
const form = reactive({
    name: '',
    email: '',
    phone: '',
    password: '',
    password_confirmation: '',
});

async function submit() {
    loading.value = true;
    statusMessage.value = '';
    errors.value = {};

    try {
        const { data } = await api.post('/register', form);
        setUser(data.user);
        toast.success(data.message);
        router.push({ name: 'dashboard' });
    } catch (error) {
        if (error.response?.status === 422) {
            errors.value = error.response.data.errors ?? {};
            statusMessage.value = error.response.data.message ?? '';
        } else {
            statusType.value = 'error';
            statusMessage.value = error.response?.data?.message ?? 'Terjadi kesalahan. Silakan coba lagi.';
        }
    } finally {
        loading.value = false;
    }
}
</script>
