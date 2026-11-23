<template>
    <div class="mx-auto max-w-4xl space-y-6">
        <div
            v-if="mustChangePassword"
            class="rounded-xl border border-amber-200 bg-amber-50 px-6 py-4 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-900/30 dark:text-amber-200"
        >
            Anda wajib mengganti password sebelum melanjutkan. Password harus minimal 8 karakter
            dengan huruf besar, angka, dan simbol.
        </div>

        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-4">
                <h2 class="text-base font-semibold text-slate-900">Profil Saya</h2>
                <p class="text-xs text-slate-500">Kelola informasi akun Anda.</p>
            </div>

            <div class="flex items-center gap-4 px-6 py-5">
                <div class="relative">
                    <img
                        v-if="avatarUrl"
                        :src="avatarUrl"
                        alt="Avatar"
                        class="h-16 w-16 rounded-full object-cover"
                    />
                    <div
                        v-else
                        class="flex h-16 w-16 items-center justify-center rounded-full bg-indigo-100 text-lg font-bold text-indigo-700"
                    >
                        {{ initials }}
                    </div>
                </div>
                <div class="flex flex-col gap-2">
                    <label
                        class="cursor-pointer rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                    >
                        Ubah Avatar
                        <input
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            class="hidden"
                            @change="uploadAvatar"
                        />
                    </label>
                    <button
                        v-if="avatarUrl"
                        class="text-left text-xs font-semibold text-rose-600 hover:text-rose-700"
                        @click="removeAvatar"
                    >
                        Hapus avatar
                    </button>
                    <p class="text-[11px] text-slate-400">JPG/PNG/WebP, maksimal 2MB.</p>
                </div>
            </div>

            <form class="space-y-5 border-t border-slate-100 px-6 py-5" @submit.prevent="submitProfile">
                <div class="grid gap-5 sm:grid-cols-2">
                    <BaseInput
                        v-model="profileForm.name"
                        label="Nama"
                        type="text"
                        required
                        :error="profileErrors.name?.[0]"
                    />
                    <BaseInput
                        v-model="profileForm.email"
                        label="Email"
                        type="email"
                        required
                        :error="profileErrors.email?.[0]"
                    />
                    <BaseInput
                        v-model="profileForm.phone"
                        label="Telepon"
                        type="text"
                        :error="profileErrors.phone?.[0]"
                    />
                </div>

                <div class="flex justify-end">
                    <BaseButton type="submit" :loading="savingProfile">Simpan Profil</BaseButton>
                </div>
            </form>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-4">
                <h2 class="text-base font-semibold text-slate-900">Ganti Password</h2>
                <p class="text-xs text-slate-500">Gunakan password minimal 8 karakter yang belum pernah dipakai.</p>
            </div>

            <form class="space-y-5 px-6 py-5" @submit.prevent="submitPassword">
                <div class="grid gap-5 sm:grid-cols-3">
                    <BaseInput
                        v-model="passwordForm.current_password"
                        label="Password Saat Ini"
                        type="password"
                        autocomplete="current-password"
                        required
                        :error="passwordErrors.current_password?.[0]"
                    />
                    <BaseInput
                        v-model="passwordForm.password"
                        label="Password Baru"
                        type="password"
                        autocomplete="new-password"
                        required
                        :error="passwordErrors.password?.[0]"
                    />
                    <BaseInput
                        v-model="passwordForm.password_confirmation"
                        label="Konfirmasi Password"
                        type="password"
                        autocomplete="new-password"
                        required
                        :error="passwordErrors.password_confirmation?.[0]"
                    />
                </div>

                <div class="flex justify-end">
                    <BaseButton type="submit" :loading="savingPassword">Ganti Password</BaseButton>
                </div>
            </form>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-4">
                <h2 class="text-base font-semibold text-slate-900">Two-Factor Authentication (2FA)</h2>
                <p class="text-xs text-slate-500">Lapisan keamanan tambahan via aplikasi authenticator (Google Authenticator, Authy, dsb).</p>
            </div>

            <div class="space-y-4 px-6 py-5">
                <div v-if="user?.two_factor_enabled" class="flex items-center gap-2 text-sm text-emerald-600">
                    <span class="h-2 w-2 rounded-full bg-emerald-500" />
                    2FA aktif
                </div>
                <div v-else class="flex items-center gap-2 text-sm text-slate-500">
                    <span class="h-2 w-2 rounded-full bg-slate-300" />
                    2FA belum aktif
                </div>

                <template v-if="!user?.two_factor_enabled && !setupStarted">
                    <BaseButton variant="secondary" @click="startSetup">Aktifkan 2FA</BaseButton>
                </template>

                <template v-if="setupStarted && !user?.two_factor_enabled">
                    <div class="flex flex-col items-start gap-4 sm:flex-row">
                        <div class="rounded-lg bg-white p-2" v-html="qrSvg" />
                        <div class="space-y-1 text-sm">
                            <p class="text-slate-700">1. Scan QR dengan aplikasi authenticator.</p>
                            <p class="text-slate-700">2. Atau masukkan secret manual:</p>
                            <code class="block break-all rounded bg-slate-100 px-2 py-1 text-xs text-slate-800">{{ setupSecret }}</code>
                        </div>
                    </div>

                    <div class="flex items-end gap-3">
                        <BaseInput
                            v-model="totpCode"
                            label="Kode 6 digit"
                            type="text"
                            inputmode="numeric"
                            maxlength="6"
                            placeholder="123456"
                            class="max-w-[10rem]"
                        />
                        <BaseButton :loading="confirming" @click="confirmSetup">Konfirmasi</BaseButton>
                    </div>
                </template>

                <template v-if="user?.two_factor_enabled">
                    <div class="flex items-end gap-3">
                        <BaseInput
                            v-model="disableCode"
                            label="Kode 6 digit untuk menonaktifkan"
                            type="text"
                            inputmode="numeric"
                            maxlength="6"
                            placeholder="123456"
                            class="max-w-[12rem]"
                        />
                        <BaseButton variant="danger" :loading="disabling" @click="disableTwoFactor">Nonaktifkan 2FA</BaseButton>
                    </div>
                </template>
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-4">
                <h2 class="text-base font-semibold text-slate-900">Info Akun</h2>
            </div>
            <dl class="grid gap-4 px-6 py-5 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-xs text-slate-500">Role</dt>
                    <dd class="mt-1 font-medium text-slate-800">
                        {{ user?.roles?.map((r) => r.name).join(', ') || 'Tanpa role' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-500">Login terakhir</dt>
                    <dd class="mt-1 font-medium text-slate-800">{{ formatDate(user?.last_login_at) }}</dd>
                </div>
            </dl>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute } from 'vue-router';
import { api } from '../api';
import { useAuth } from '../auth';
import { useToast } from '../composables/useToast';
import BaseButton from '../components/BaseButton.vue';
import BaseInput from '../components/BaseInput.vue';

const { state, setUser } = useAuth();
const toast = useToast();
const route = useRoute();

const mustChangePassword = computed(() => Boolean(state.user?.password_change_required));

const user = computed(() => state.user);
const initials = computed(() =>
    (user.value?.name ?? '')
        .split(' ')
        .map((part) => part[0])
        .slice(0, 2)
        .join('')
        .toUpperCase()
);
const avatarUrl = computed(() => user.value?.avatar_url ?? '');

const savingProfile = ref(false);
const savingPassword = ref(false);
const profileErrors = ref({});
const passwordErrors = ref({});

// 2FA
const setupStarted = ref(false);
const setupSecret = ref('');
const qrSvg = ref('');
const totpCode = ref('');
const confirming = ref(false);
const disableCode = ref('');
const disabling = ref(false);

const profileForm = reactive({
    name: user.value?.name ?? '',
    email: user.value?.email ?? '',
    phone: user.value?.phone ?? '',
});

const passwordForm = reactive({
    current_password: '',
    password: '',
    password_confirmation: '',
});

function formatDate(value) {
    if (! value) return '-';

    return new Date(value).toLocaleString('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

async function submitProfile() {
    savingProfile.value = true;
    profileErrors.value = {};

    try {
        const { data } = await api.put('/profile', profileForm);
        setUser({ ...user.value, ...data.user });
        toast.success(data.message);
    } catch (error) {
        if (error.response?.status === 422) {
            profileErrors.value = error.response.data.errors ?? {};
        } else {
            toast.error(error.response?.data?.message ?? 'Terjadi kesalahan.');
        }
    } finally {
        savingProfile.value = false;
    }
}

async function submitPassword() {
    savingPassword.value = true;
    passwordErrors.value = {};

    try {
        const { data } = await api.put('/profile/password', passwordForm);
        passwordForm.current_password = '';
        passwordForm.password = '';
        passwordForm.password_confirmation = '';
        toast.success(data.message);
    } catch (error) {
        if (error.response?.status === 422) {
            passwordErrors.value = error.response.data.errors ?? {};
        } else {
            toast.error(error.response?.data?.message ?? 'Terjadi kesalahan.');
        }
    } finally {
        savingPassword.value = false;
    }
}

async function uploadAvatar(event) {
    const file = event.target.files?.[0];
    event.target.value = '';

    if (! file) return;

    const formData = new FormData();
    formData.append('avatar', file);

    try {
        const { data } = await api.post('/profile/avatar', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        setUser({ ...user.value, avatar_url: data.avatar_url });
        toast.success(data.message);
    } catch (error) {
        if (error.response?.status === 422) {
            toast.error(Object.values(error.response.data.errors ?? {})[0]?.[0] ?? 'File tidak valid.');
        } else {
            toast.error(error.response?.data?.message ?? 'Gagal mengunggah avatar.');
        }
    }
}

async function removeAvatar() {
    try {
        const { data } = await api.delete('/profile/avatar');
        setUser({ ...user.value, avatar_url: '' });
        toast.success(data.message);
    } catch (error) {
        toast.error(error.response?.data?.message ?? 'Gagal menghapus avatar.');
    }
}

async function startSetup() {
    try {
        const { data } = await api.get('/two-factor/setup');
        setupSecret.value = data.secret;
        qrSvg.value = data.qr_svg;
        setupStarted.value = true;
        toast.success(data.message);
    } catch (error) {
        toast.error(error.response?.data?.message ?? 'Gagal memulai setup 2FA.');
    }
}

async function confirmSetup() {
    confirming.value = true;

    try {
        const { data } = await api.post('/two-factor/confirm', { code: totpCode.value });
        setUser({ ...user.value, two_factor_enabled: true });
        setupStarted.value = false;
        totpCode.value = '';
        toast.success(data.message);
    } catch (error) {
        toast.error(Object.values(error.response?.data?.errors ?? {})[0]?.[0]
            ?? error.response?.data?.message
            ?? 'Kode TOTP tidak valid.');
    } finally {
        confirming.value = false;
    }
}

async function disableTwoFactor() {
    disabling.value = true;

    try {
        const { data } = await api.delete('/two-factor', {
            data: { code: disableCode.value },
        });
        setUser({ ...user.value, two_factor_enabled: false });
        disableCode.value = '';
        toast.success(data.message);
    } catch (error) {
        toast.error(Object.values(error.response?.data?.errors ?? {})[0]?.[0]
            ?? error.response?.data?.message
            ?? 'Kode TOTP tidak valid.');
    } finally {
        disabling.value = false;
    }
}

// Auto-scroll ke form ganti password saat diarahkan wajib ganti ( ?change=password ).
onMounted(() => {
    if (route.query.change === 'password') {
        setTimeout(() => {
            document.querySelector('input[autocomplete="current-password"]')
                ?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 300);
    }
});
</script>
