<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4" @click.self="$emit('close')">
        <div class="w-full max-w-md overflow-hidden rounded-xl bg-white shadow-xl dark:bg-slate-800">
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-slate-700">
                <h2 class="text-base font-bold text-slate-900 dark:text-slate-100">Pengaturan Cetak Struk</h2>
                <button class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200" @click="$emit('close')">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="space-y-4 px-5 py-4">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Mode Cetak</label>
                    <select
                        v-model="form.mode"
                        class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-800 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                    >
                        <option value="auto">Otomatis (printer kasir, fallback browser)</option>
                        <option value="agent">Print Agent saja</option>
                        <option value="browser">Dialog print browser</option>
                    </select>
                    <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">
                        Agent status:
                        <span :class="agentOnline ? 'text-emerald-600' : 'text-rose-600'" class="font-semibold">
                            {{ agentChecked ? (agentOnline ? 'aktif' : 'tidak ditemukan') : 'memeriksa...' }}
                        </span>
                    </p>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <BaseInput v-model="form.storeName" label="Nama Toko" />
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Lebar Kertas</label>
                        <select
                            v-model="form.paperWidth"
                            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-800 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                        >
                            <option :value="58">58 mm</option>
                            <option :value="80">80 mm</option>
                        </select>
                    </div>
                    <BaseInput v-model="form.storeAddress" label="Alamat (opsional)" class="col-span-2" />
                    <BaseInput v-model="form.storePhone" label="Telepon (opsional)" class="col-span-2" />
                    <BaseInput v-model="form.footer" label="Footer Struk" class="col-span-2" />
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 px-5 py-4 dark:border-slate-700">
                <BaseButton
                    variant="secondary"
                    :loading="testing"
                    :disabled="!agentOnline"
                    @click="testPrint"
                >
                    Tes Cetak
                </BaseButton>
                <div class="flex gap-2">
                    <BaseButton variant="secondary" @click="$emit('close')">Batal</BaseButton>
                    <BaseButton variant="primary" @click="save">Simpan</BaseButton>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import BaseButton from '../BaseButton.vue';
import BaseInput from '../BaseInput.vue';
import { getPrintSettings, savePrintSettings, isAgentReachable, testPrintAgent } from '../../pos/printer';
import { useToast } from '../../composables/useToast';

const emit = defineEmits(['close', 'saved']);

const toast = useToast();

const form = reactive({ ...getPrintSettings() });

const agentChecked = ref(false);
const agentOnline = ref(false);
const testing = ref(false);

const agentStatus = computed(() =>
    !agentChecked.value ? 'memeriksa...' : agentOnline.value ? 'aktif' : 'tidak ditemukan',
);

onMounted(async () => {
    agentOnline.value = await isAgentReachable();
    agentChecked.value = true;
});

async function testPrint() {
    testing.value = true;
    try {
        await testPrintAgent();
        toast.push('Perintah tes cetak terkirim. Periksa printer Anda.', 'success');
    } catch (err) {
        toast.push(err?.message ?? 'Tes cetak gagal.', 'error');
    } finally {
        testing.value = false;
    }
}

function save() {
    savePrintSettings({
        ...form,
        paperWidth: Number(form.paperWidth),
    });
    toast.push('Pengaturan cetak disimpan.', 'success');
    emit('saved');
    emit('close');
}
</script>
