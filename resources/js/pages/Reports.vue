<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-slate-900 dark:text-slate-100">Laporan Penjualan</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">Rekap dari data server (transaksi tersinkron).</p>
            </div>
            <div class="flex items-center gap-2">
                <input
                    v-model="from"
                    type="date"
                    class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                />
                <span class="text-slate-400">s/d</span>
                <input
                    v-model="to"
                    type="date"
                    class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                />
                <BaseButton variant="secondary" @click="load">Terapkan</BaseButton>
            </div>
        </div>

        <div v-if="loading" class="grid gap-3 sm:grid-cols-4">
            <div v-for="i in 4" :key="i" class="h-24 animate-pulse rounded-xl bg-slate-200 dark:bg-slate-700" />
        </div>

        <template v-else-if="report">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Omzet</p>
                    <p class="mt-1 text-2xl font-bold text-slate-900 dark:text-slate-100">{{ formatRupiah(report.summary.gross_total) }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Transaksi</p>
                    <p class="mt-1 text-2xl font-bold text-slate-900 dark:text-slate-100">{{ report.summary.transaction_count }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Rata-rata/Transaksi</p>
                    <p class="mt-1 text-2xl font-bold text-slate-900 dark:text-slate-100">{{ formatRupiah(report.summary.avg_total) }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Total Diskon</p>
                    <p class="mt-1 text-2xl font-bold text-slate-900 dark:text-slate-100">{{ formatRupiah(report.summary.discount_total) }}</p>
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <div class="border-b border-slate-200 px-4 py-3 dark:border-slate-700">
                        <h2 class="text-sm font-bold text-slate-800 dark:text-slate-100">Penjualan Harian</h2>
                    </div>
                    <div class="max-h-72 overflow-y-auto">
                        <table class="w-full text-sm">
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                <tr v-for="row in report.daily" :key="row.day">
                                    <td class="px-4 py-2.5 text-slate-600 dark:text-slate-300">{{ formatDate(row.day) }}</td>
                                    <td class="px-4 py-2.5 text-center text-slate-400">{{ row.cnt }} trx</td>
                                    <td class="px-4 py-2.5 text-right font-semibold text-slate-800 dark:text-slate-100">{{ formatRupiah(row.total) }}</td>
                                </tr>
                                <tr v-if="report.daily.length === 0">
                                    <td colspan="3" class="px-4 py-8 text-center text-slate-400">Belum ada data.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
                        <div class="border-b border-slate-200 px-4 py-3 dark:border-slate-700">
                            <h2 class="text-sm font-bold text-slate-800 dark:text-slate-100">Produk Terlaris</h2>
                        </div>
                        <table class="w-full text-sm">
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                <tr v-for="(row, i) in report.top_products" :key="row.name">
                                    <td class="w-8 px-4 py-2 text-center text-xs font-bold text-slate-400">{{ i + 1 }}</td>
                                    <td class="px-2 py-2 text-slate-600 dark:text-slate-300">{{ row.name }}</td>
                                    <td class="px-4 py-2 text-right text-slate-400">{{ row.qty }} pcs</td>
                                    <td class="px-4 py-2 text-right font-semibold text-slate-800 dark:text-slate-100">{{ formatRupiah(row.revenue) }}</td>
                                </tr>
                                <tr v-if="report.top_products.length === 0">
                                    <td colspan="4" class="px-4 py-8 text-center text-slate-400">Belum ada data.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
                        <div class="border-b border-slate-200 px-4 py-3 dark:border-slate-700">
                            <h2 class="text-sm font-bold text-slate-800 dark:text-slate-100">Per Kasir</h2>
                        </div>
                        <table class="w-full text-sm">
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                <tr v-for="row in report.by_cashier" :key="row.cashier">
                                    <td class="px-4 py-2.5 text-slate-600 dark:text-slate-300">{{ row.cashier }}</td>
                                    <td class="px-4 py-2.5 text-center text-slate-400">{{ row.cnt }} trx</td>
                                    <td class="px-4 py-2.5 text-right font-semibold text-slate-800 dark:text-slate-100">{{ formatRupiah(row.total) }}</td>
                                </tr>
                                <tr v-if="report.by_cashier.length === 0">
                                    <td colspan="3" class="px-4 py-8 text-center text-slate-400">Belum ada data.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import api from '../api';
import BaseButton from '../components/BaseButton.vue';
import { useToast } from '../composables/useToast';

const toast = useToast();

const report = ref(null);
const loading = ref(false);

function isoDate(d) {
    return new Date(d).toISOString().slice(0, 10);
}

const from = ref(isoDate(new Date(Date.now() - 29 * 864e5)));
const to = ref(isoDate(new Date()));

async function load() {
    loading.value = true;
    try {
        const { data } = await api.get('/pos/reports', {
            params: { from: from.value, to: to.value },
        });
        report.value = data;
    } catch {
        toast.push('Gagal memuat laporan.', 'error');
    } finally {
        loading.value = false;
    }
}

function formatRupiah(value) {
    return 'Rp ' + Number(value ?? 0).toLocaleString('id-ID', { maximumFractionDigits: 0 });
}

function formatDate(value) {
    return new Date(value).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
}

onMounted(load);
</script>
