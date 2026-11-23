<template>
    <AuthLayout>
        <template #hero>
            <h1 class="text-4xl font-bold leading-tight">
                Kelola akses aplikasi Anda dengan role &amp; permission.
            </h1>
            <p class="mt-4 text-slate-300">
                Fondasi sistem autentikasi modern: login aman, manajemen pengguna, dan role-based access control
                yang fleksibel.
            </p>

            <ul class="mt-8 space-y-3 text-sm text-slate-300">
                <li v-for="item in features" :key="item" class="flex items-center gap-3">
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-indigo-500/20 text-indigo-300">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-7.5 7.5a1 1 0 01-1.4 0l-3.5-3.5a1 1 0 111.4-1.4l2.8 2.8 6.8-6.8a1 1 0 011.4 0z" clip-rule="evenodd" />
                        </svg>
                    </span>
                    {{ item }}
                </li>
            </ul>
        </template>

        <h2 class="text-2xl font-bold text-slate-900">Selamat datang kembali</h2>
        <p class="mt-1 text-sm text-slate-500">Masuk untuk melanjutkan ke dashboard.</p>

        <div
            v-if="statusMessage"
            class="mt-5 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"
        >
            {{ statusMessage }}
        </div>

        <template v-if="! twoFactorStage">
            <form class="mt-6 space-y-5" @submit.prevent="submit">
                <BaseInput
                    v-model="form.email"
                    label="Email"
                    type="email"
                    placeholder="nama@example.com"
                    autocomplete="username"
                    required
                    :error="errors.email?.[0]"
                >
                    <template #icon>
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 7l9 6 9-6M4 5h16a1 1 0 011 1v12a1 1 0 01-1 1H4a1 1 0 01-1-1V6a1 1 0 011-1z" />
                        </svg>
                    </template>
                </BaseInput>

                <BaseInput
                    v-model="form.password"
                    label="Password"
                    type="password"
                    placeholder="Masukkan password"
                    autocomplete="current-password"
                    required
                    :error="errors.password?.[0]"
                >
                    <template #icon>
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-11V7a4 4 0 10-8 0v4h8z" />
                        </svg>
                    </template>
                </BaseInput>

                <div class="flex items-center justify-between">
                    <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-600">
                        <input v-model="form.remember" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                        Ingat saya
                    </label>
                    <RouterLink
                        :to="{ name: 'forgot-password' }"
                        class="text-sm font-semibold text-indigo-600 hover:text-indigo-700"
                    >
                        Lupa password?
                    </RouterLink>
                </div>

                <BaseButton type="submit" class="w-full py-3" :loading="loading">
                    Masuk
                </BaseButton>
            </form>
        </template>

        <template v-else>
            <form class="mt-6 space-y-5" @submit.prevent="submitTwoFactor">
                <div class="text-center">
                    <p class="text-sm text-slate-600">{{ statusMessage }}</p>
                    <p class="mt-1 text-xs text-slate-400">Buka aplikasi authenticator untuk melihat kode.</p>
                </div>

                <BaseInput
                    v-model="totpCode"
                    label="Kode autentikasi (6 digit)"
                    type="text"
                    inputmode="numeric"
                    maxlength="6"
                    placeholder="123456"
                    required
                    :error="errors.code?.[0]"
                />

                <BaseButton type="submit" class="w-full py-3" :loading="loading">
                    Verifikasi &amp; Masuk
                </BaseButton>

                <button
                    type="button"
                    class="w-full text-center text-xs font-semibold text-slate-500 hover:text-slate-700"
                    @click="resetTwoFactorStage"
                >
                    &larr; Kembali
                </button>
            </form>
        </template>

        <p v-if="! twoFactorStage" class="mt-6 text-center text-sm text-slate-500">
            Belum punya akun?
            <RouterLink :to="{ name: 'register' }" class="font-semibold text-indigo-600 hover:text-indigo-700">
                Daftar
            </RouterLink>
        </p>
    </AuthLayout>
</template>

<script setup>
import { reactive, ref } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import api from '../api';
import { useAuth } from '../auth';
import AuthLayout from '../layouts/AuthLayout.vue';
import BaseButton from '../components/BaseButton.vue';
import BaseInput from '../components/BaseInput.vue';

const router = useRouter();
const route = useRoute();
const { setUser } = useAuth();

const loading = ref(false);
const statusMessage = ref('');
const errors = ref({});
const form = reactive({
    email: '',
    password: '',
    remember: false,
});

// 2FA challenge stage
const twoFactorStage = ref(false);
const twoFactorUserId = ref(null);
const totpCode = ref('');

const features = [
    'Autentikasi berbasis session yang aman',
    'Role & permission fleksibel',
    'Kelola pengguna tanpa mengubah kode',
];

function resolveRedirect() {
    const redirect = route.query.redirect;

    if (typeof redirect === 'string' && redirect.startsWith('/') && !redirect.startsWith('//') && redirect !== '/') {
        return redirect;
    }

    return { name: 'dashboard' };
}

async function submit() {
    loading.value = true;
    statusMessage.value = '';
    errors.value = {};

    try {
        const response = await api.post('/login', form);

        // Server minta verifikasi 2FA (kredensial benar tapi belum login).
        if (response.data?.two_factor_required) {
            twoFactorStage.value = true;
            twoFactorUserId.value = response.data.user_id;
            statusMessage.value = response.data.message;
            return;
        }

        setUser(response.data.user);
        router.push(resolveRedirect());
    } catch (error) {
        if (error.response?.status === 422) {
            errors.value = error.response.data.errors ?? {};
            statusMessage.value = error.response.data.message ?? '';
        } else if (error.response?.status === 429) {
            statusMessage.value = 'Terlalu banyak percobaan. Coba lagi nanti.';
        } else {
            statusMessage.value = 'Terjadi kesalahan. Silakan coba lagi.';
        }
    } finally {
        loading.value = false;
    }
}

async function submitTwoFactor() {
    loading.value = true;
    errors.value = {};

    try {
        const { data } = await api.post('/two-factor/challenge', {
            user_id: twoFactorUserId.value,
            code: totpCode.value,
            remember: form.remember,
        });

        setUser(data.user);

        if (data.password_change_required) {
            router.push({ name: 'profile', query: { change: 'password' } });
            return;
        }

        router.push(resolveRedirect());
    } catch (error) {
        if (error.response?.status === 422) {
            errors.value = error.response.data.errors ?? {};
        } else {
            statusMessage.value = error.response?.data?.message ?? 'Terjadi kesalahan. Silakan coba lagi.';
        }
    } finally {
        loading.value = false;
    }
}

function resetTwoFactorStage() {
    twoFactorStage.value = false;
    twoFactorUserId.value = null;
    totpCode.value = '';
    statusMessage.value = '';
    errors.value = {};
}
</script>
