<template>
    <div class="mx-auto max-w-3xl space-y-6">
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <div class="border-b border-slate-200 px-6 py-4 dark:border-slate-700">
                <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">Pengaturan Aplikasi</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">Pengaturan disimpan di database dan berlaku global.</p>
            </div>

            <form class="space-y-5 px-6 py-5" @submit.prevent="save">
                <BaseInput
                    v-model="form.app_name"
                    label="Nama Aplikasi"
                    type="text"
                    required
                />

                <label class="flex cursor-pointer items-center gap-3 text-sm text-slate-700 dark:text-slate-200">
                    <input v-model="form.maintenance_mode" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                    Mode pemeliharaan
                </label>

                <BaseInput
                    v-model.number="form.session_timeout_minutes"
                    label="Timeout Sesi (menit)"
                    type="number"
                    :hint="`Session lifetime saat ini: ${settings.session_timeout_minutes ?? 120} menit — ubah juga SESSION_LIFETIME di .env untuk hasil penuh.`"
                />

                <BaseInput
                    v-model.number="form.password_policy_min_length"
                    label="Panjang Minimum Password"
                    type="number"
                />

                <div class="flex justify-end border-t border-slate-100 pt-4 dark:border-slate-700">
                    <BaseButton type="submit" :loading="saving">Simpan Pengaturan</BaseButton>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import api from '../api';
import { useToast } from '../composables/useToast';
import BaseButton from '../components/BaseButton.vue';
import BaseInput from '../components/BaseInput.vue';

const { error: toastError, success: toastSuccess } = useToast();

const settings = ref({});
const saving = ref(false);
const form = reactive({
    app_name: '',
    maintenance_mode: false,
    session_timeout_minutes: 120,
    password_policy_min_length: 8,
});

async function fetchSettings() {
    try {
        const { data } = await api.get('/settings');
        settings.value = data.settings;
        form.app_name = data.settings.app_name ?? '';
        form.maintenance_mode = data.settings.maintenance_mode ?? false;
        form.session_timeout_minutes = data.settings.session_timeout_minutes ?? 120;
        form.password_policy_min_length = data.settings.password_policy_min_length ?? 8;
    } catch (error) {
        toastError('Gagal memuat pengaturan.');
    }
}

async function save() {
    saving.value = true;

    try {
        const { data } = await api.put('/settings', {
            settings: [
                { key: 'app_name', value: form.app_name, type: 'text' },
                { key: 'maintenance_mode', value: form.maintenance_mode, type: 'boolean' },
                { key: 'session_timeout_minutes', value: form.session_timeout_minutes, type: 'integer' },
                { key: 'password_policy_min_length', value: form.password_policy_min_length, type: 'integer' },
            ],
        });
        settings.value = data.settings;
        toastSuccess(data.message);
    } catch (error) {
        toastError(error.response?.data?.message ?? 'Gagal menyimpan pengaturan.');
    } finally {
        saving.value = false;
    }
}

onMounted(fetchSettings);
</script>
