<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4" @click.self="$emit('close')">
        <div class="w-full max-w-md overflow-hidden rounded-xl bg-white shadow-xl dark:bg-slate-800">
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-slate-700">
                <h2 class="text-base font-bold text-slate-900 dark:text-slate-100">Pengaturan Scanner</h2>
                <button class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200" @click="$emit('close')">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="space-y-4 px-5 py-4">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Mode</label>
                    <select
                        v-model="form.mode"
                        class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-800 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                    >
                        <option value="keyboard">Keyboard-wedge (default, semua scanner USB)</option>
                        <option value="hid" :disabled="!hidSupported">WebHID (scan tanpa perlu fokus input)</option>
                        <option value="camera" :disabled="!cameraSupported">Kamera (HP / tablet)</option>
                    </select>
                    <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">
                        Keyboard-wedge selalu aktif. Mode ini hanya mengaktifkan tambahan.
                    </p>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <BaseInput v-model.number="form.minLength" type="number" min="1" label="Panjang Min. Barcode" />
                    <BaseInput v-model.number="form.minSpeedMs" type="number" min="5" label="Kecepatan Ketik (ms)" hint="Semakin kecil = makin ketat" />
                </div>

                <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                    <input v-model="form.beep" type="checkbox" class="h-4 w-4 rounded" />
                    Bunyi beep saat scan berhasil
                </label>
                <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                    <input v-model="form.suffixEnter" type="checkbox" class="h-4 w-4 rounded" />
                    Scanner mengirim Enter di akhir (umumnya aktif)
                </label>

                <div v-if="form.mode === 'hid'" class="rounded-lg bg-slate-50 p-3 dark:bg-slate-900/50">
                    <p class="mb-2 text-xs text-slate-500 dark:text-slate-400">
                        Pilih scanner dari daftar device untuk mulai membaca langsung.
                    </p>
                    <BaseButton variant="secondary" :loading="pairing" @click="pairHid">
                        {{ hidName ? `Terhubung: ${hidName}` : 'Pasang Scanner HID' }}
                    </BaseButton>
                </div>
            </div>

            <div class="flex justify-end gap-2 border-t border-slate-200 px-5 py-4 dark:border-slate-700">
                <BaseButton variant="secondary" @click="$emit('close')">Batal</BaseButton>
                <BaseButton variant="primary" @click="save">Simpan</BaseButton>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, reactive, ref } from 'vue';
import BaseButton from '../BaseButton.vue';
import BaseInput from '../BaseInput.vue';
import { getScannerSettings, saveScannerSettings } from '../../pos/scanner';
import { isHidSupported, pairHidScanner, disconnectHid } from '../../pos/hid-scanner';
import { useToast } from '../../composables/useToast';

const emit = defineEmits(['close', 'saved']);

const toast = useToast();

const form = reactive({ ...getScannerSettings() });

const hidSupported = isHidSupported();
const pairing = ref(false);
const hidName = ref('');

const cameraSupported = computed(() => 'BarcodeDetector' in window && !!navigator.mediaDevices);

async function pairHid() {
    pairing.value = true;
    try {
        hidName.value = await pairHidScanner((code) => {
            window.dispatchEvent(new CustomEvent('pos-scan', { detail: code }));
        });
        toast.push(`Scanner "${hidName.value}" terpasang.`, 'success');
    } catch (err) {
        toast.push(err?.message ?? 'Gagal memasang scanner HID.', 'error');
    } finally {
        pairing.value = false;
    }
}

function save() {
    saveScannerSettings({
        ...form,
        minLength: Number(form.minLength),
        minSpeedMs: Number(form.minSpeedMs),
    });

    if (form.mode !== 'hid') {
        disconnectHid();
    }

    toast.push('Pengaturan scanner disimpan.', 'success');
    emit('saved');
    emit('close');
}

onBeforeUnmount(() => {
    if (form.mode !== 'hid') {
        disconnectHid();
    }
});
</script>
