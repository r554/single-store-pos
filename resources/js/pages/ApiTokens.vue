<template>
    <div class="mx-auto max-w-3xl space-y-6">
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <div class="border-b border-slate-200 px-6 py-4 dark:border-slate-700">
                <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">Token API</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    Token Bearer untuk akses API dari mobile/integrasi. Kirim di header:
                    <code class="rounded bg-slate-100 px-1 text-[11px] dark:bg-slate-700">Authorization: Bearer &lt;token&gt;</code>
                </p>
            </div>

            <form class="flex flex-col gap-3 px-6 py-5 sm:flex-row sm:items-end" @submit.prevent="create">
                <div class="flex-1">
                    <BaseInput v-model="form.name" label="Nama Token" placeholder="Mobile App" required />
                </div>
                <div class="w-full sm:w-40">
                    <BaseInput v-model="form.expires_in_days" label="Berlaku (hari)" type="number" placeholder="Kosong = permanen" />
                </div>
                <BaseButton type="submit" :loading="creating">Buat Token</BaseButton>
            </form>

            <div v-if="newToken" class="mx-6 mb-5 rounded-lg border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-800 dark:bg-emerald-900/30">
                <p class="text-xs font-semibold text-emerald-700 dark:text-emerald-300">
                    Token baru (salin sekarang — tidak ditampilkan lagi):
                </p>
                <code class="mt-1 block break-all text-xs text-emerald-800 dark:text-emerald-200">{{ newToken }}</code>
            </div>

            <div class="overflow-x-auto border-t border-slate-200 dark:border-slate-700">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500 dark:bg-slate-900/50 dark:text-slate-400">
                        <tr>
                            <th class="px-6 py-3 font-semibold">Nama</th>
                            <th class="px-6 py-3 font-semibold">Terakhir dipakai</th>
                            <th class="px-6 py-3 font-semibold">Kedaluwarsa</th>
                            <th class="px-6 py-3" />
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                        <tr v-for="token in tokens" :key="token.id" class="hover:bg-slate-50 dark:hover:bg-slate-700/40">
                            <td class="px-6 py-3 font-medium text-slate-900 dark:text-slate-100">{{ token.name }}</td>
                            <td class="px-6 py-3 text-slate-500 dark:text-slate-400">{{ formatDate(token.last_used_at) ?? 'Belum pernah' }}</td>
                            <td class="px-6 py-3 text-slate-500 dark:text-slate-400">{{ formatDate(token.expires_at) ?? 'Permanen' }}</td>
                            <td class="px-6 py-3 text-right">
                                <button
                                    class="text-xs font-semibold text-rose-600 hover:text-rose-700"
                                    @click="revoke(token)"
                                >
                                    Hapus
                                </button>
                            </td>
                        </tr>
                        <tr v-if="tokens.length === 0">
                            <td colspan="4" class="px-6 py-8 text-center text-slate-500 dark:text-slate-400">
                                Belum ada token.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import api from '../api';
import { useToast } from '../composables/useToast';
import { useSwal } from '../composables/useSwal';
import BaseButton from '../components/BaseButton.vue';
import BaseInput from '../components/BaseInput.vue';

const { error: toastError, success: toastSuccess } = useToast();
const { success } = useSwal();

const tokens = ref([]);
const creating = ref(false);
const newToken = ref('');
const form = reactive({
    name: '',
    expires_in_days: '',
});

async function fetchTokens() {
    try {
        const { data } = await api.get('/api-tokens');
        tokens.value = data.tokens;
    } catch (error) {
        toastError('Gagal memuat token.');
    }
}

async function create() {
    creating.value = true;
    newToken.value = '';

    try {
        const { data } = await api.post('/api-tokens', {
            name: form.name,
            expires_in_days: form.expires_in_days || null,
        });
        newToken.value = data.token;
        form.name = '';
        form.expires_in_days = '';
        toastSuccess(data.message);
        await fetchTokens();
    } catch (error) {
        toastError(error.response?.data?.message ?? 'Gagal membuat token.');
    } finally {
        creating.value = false;
    }
}

async function revoke(token) {
    const confirmed = await success(`Hapus token "${token.name}"?`, 'Token tidak dapat dipulihkan.');

    if (! confirmed) return;

    try {
        await api.delete(`/api-tokens/${token.id}`);
        toastSuccess('Token berhasil dihapus.');
        await fetchTokens();
    } catch (error) {
        toastError(error.response?.data?.message ?? 'Gagal menghapus token.');
    }
}

function formatDate(value) {
    if (! value) return null;

    return new Date(value).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
}

onMounted(fetchTokens);
</script>
