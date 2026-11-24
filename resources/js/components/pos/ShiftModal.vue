<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4" @click.self="$emit('close')">
        <div class="w-full max-w-md overflow-hidden rounded-xl bg-white shadow-xl dark:bg-slate-800">
            <!-- BUKA SHIFT -->
            <template v-if="mode === 'open'">
                <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-700">
                    <h2 class="text-base font-bold text-slate-900 dark:text-slate-100">Buka Shift</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Nyatakan uang awal di laci kas.</p>
                </div>
                <div class="px-5 py-4">
                    <BaseRupiahInput
                        v-model="openingCash"
                        label="Uang Awal (Rp)"
                        required
                    />
                </div>
                <div class="flex justify-end gap-2 border-t border-slate-200 px-5 py-4 dark:border-slate-700">
                    <BaseButton variant="secondary" @click="$emit('close')">Batal</BaseButton>
                    <BaseButton variant="primary" :loading="processing" :disabled="openingCash === null || openingCash < 0" @click="confirmOpen">
                        Buka Shift
                    </BaseButton>
                </div>
            </template>

            <!-- TUTUP SHIFT -->
            <template v-else>
                <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-700">
                    <h2 class="text-base font-bold text-slate-900 dark:text-slate-100">Tutup Shift</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Dibuka {{ formatTime(shift?.opened_at) }} — kas awal {{ formatRupiah(shift?.opening_cash) }}
                    </p>
                </div>
                <div class="space-y-3 px-5 py-4">
                    <BaseRupiahInput
                        v-model="closingCash"
                        label="Uang Fisik di Laci (Rp)"
                        required
                    />
                    <div v-if="summary" class="rounded-lg bg-slate-50 p-3 text-sm dark:bg-slate-900/50">
                        <div class="flex justify-between text-slate-500 dark:text-slate-400">
                            <span>Transaksi</span><span>{{ summary.transaction_count }}</span>
                        </div>
                        <div class="flex justify-between text-slate-500 dark:text-slate-400">
                            <span>Omzet</span><span>{{ formatRupiah(summary.gross_total) }}</span>
                        </div>
                        <div class="flex justify-between text-slate-500 dark:text-slate-400">
                            <span>Tunai</span><span>{{ formatRupiah(summary.cash_total) }}</span>
                        </div>
                        <div v-if="expectedCash !== null" class="mt-1 flex justify-between font-semibold text-slate-700 dark:text-slate-200">
                            <span>Diharapkan di laci</span><span>{{ formatRupiah(expectedCash) }}</span>
                        </div>
                    </div>
                </div>
                <div class="flex justify-end gap-2 border-t border-slate-200 px-5 py-4 dark:border-slate-700">
                    <BaseButton variant="secondary" @click="$emit('close')">Batal</BaseButton>
                    <BaseButton variant="danger" :loading="processing" :disabled="closingCash === null || closingCash < 0" @click="confirmClose">
                        Tutup Shift
                    </BaseButton>
                </div>
            </template>

            <!-- HASIL TUTUP SHIFT -->
            <div v-if="result" class="border-t border-slate-200 px-5 py-4 dark:border-slate-700">
                <div class="rounded-lg p-3 text-sm" :class="result.difference === 0
                    ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'
                    : 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300'">
                    <p class="font-semibold">Selisih kas: {{ formatRupiah(result.difference) }}</p>
                    <p v-if="result.offline" class="mt-1 text-xs">
                        Rekap dihitung dari data lokal{{ result.offline ? ' — sinkron penuh akan menyusul saat online' : '' }}.
                    </p>
                </div>
                <div class="mt-3 flex justify-end">
                    <BaseButton variant="primary" @click="$emit('close')">Selesai</BaseButton>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, onMounted } from 'vue';
import BaseButton from '../BaseButton.vue';
import BaseRupiahInput from '../BaseRupiahInput.vue';
import { useShift } from '../../composables/useShift';
import { useToast } from '../../composables/useToast';

const props = defineProps({
    mode: { type: String, required: true }, // 'open' | 'close'
    shift: { type: Object, default: null },
});

const emit = defineEmits(['close', 'changed']);

const toast = useToast();
const { open, close } = useShift();

const openingCash = ref(0);
const closingCash = ref(0);
const processing = ref(false);
const result = ref(null);
const summary = ref(null);

const expectedCash = computed(() => {
    if (!summary.value || !props.shift) return null;
    return Number(props.shift.opening_cash) + Number(summary.value.cash_total);
});

onMounted(async () => {
    if (props.mode === 'close') {
        // Ambil ringkasan lokal cepat (transaksi lokal sejak shift dibuka).
        try {
            const { db } = await import('../../pos/db');
            const all = await db.transactions.toArray();
            const mine = all.filter((t) => t.transacted_at >= props.shift.opened_at);
            summary.value = {
                transaction_count: mine.length,
                gross_total: mine.reduce((n, t) => n + Number(t.total), 0),
                cash_total: mine
                    .filter((t) => t.payment_method === 'cash')
                    .reduce((n, t) => n + Number(t.total), 0),
            };
        } catch {
            summary.value = null;
        }
    }
});

async function confirmOpen() {
    processing.value = true;
    try {
        await open(Number(openingCash.value));
        emit('changed');
        emit('close');
    } catch (err) {
        toast.push(err?.response?.data?.message ?? 'Gagal membuka shift.', 'error');
    } finally {
        processing.value = false;
    }
}

async function confirmClose() {
    processing.value = true;
    try {
        result.value = await close(Number(closingCash.value));
        emit('changed');
        toast.push('Shift ditutup.', 'success');
    } catch (err) {
        toast.push(err?.response?.data?.message ?? 'Gagal menutup shift.', 'error');
    } finally {
        processing.value = false;
    }
}

function formatRupiah(value) {
    return 'Rp ' + Number(value ?? 0).toLocaleString('id-ID', { maximumFractionDigits: 0 });
}

function formatTime(value) {
    return value ? new Date(value).toLocaleString('id-ID') : '-';
}
</script>
