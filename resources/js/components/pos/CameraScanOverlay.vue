<template>
    <div class="fixed inset-0 z-[60] flex flex-col items-center justify-center bg-slate-900/90 p-4">
        <div class="w-full max-w-md overflow-hidden rounded-xl bg-white shadow-2xl dark:bg-slate-800">
            <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3 dark:border-slate-700">
                <h2 class="text-sm font-bold text-slate-900 dark:text-slate-100">Scan Barcode (Kamera)</h2>
                <button class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200" @click="close">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="relative aspect-video bg-black">
                <video ref="videoEl" class="h-full w-full object-cover" muted playsinline />
                <div class="pointer-events-none absolute inset-0 flex items-center justify-center">
                    <div class="h-24 w-3/4 rounded-lg border-2 border-emerald-400/80 shadow-[0_0_0_9999px_rgba(15,23,42,0.45)]" />
                </div>
            </div>

            <p class="px-4 py-3 text-center text-xs text-slate-500 dark:text-slate-400">
                Arahkan barcode ke dalam kotak. Scan otomatis ditambahkan ke keranjang.
            </p>
        </div>
    </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { scanWithCamera } from '../../pos/scanner';

const emit = defineEmits(['close']);

const videoEl = ref(null);
let controller = null;

onMounted(async () => {
    try {
        controller = await scanWithCamera(videoEl.value, () => {
            // Hasil scan sudah masuk pipeline emit() scanner.js;
            // POS.vue mendengarkan via onScan.
        });
    } catch (err) {
        emit('error', err?.message ?? 'Kamera tidak dapat diakses.');
    }
});

function close() {
    controller?.stop();
    emit('close');
}

onBeforeUnmount(() => {
    controller?.stop();
});
</script>
