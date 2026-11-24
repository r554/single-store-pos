<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-slate-900 dark:text-slate-100">Produk</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">Kelola katalog produk toko.</p>
            </div>
            <div class="flex items-center gap-2">
                <input
                    v-model="search"
                    type="search"
                    placeholder="Cari nama / SKU / barcode..."
                    class="w-64 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                    @keydown.enter.prevent="onSearchEnter"
                />
                <button
                    type="button"
                    class="rounded-lg border border-slate-300 p-2 text-slate-500 transition hover:bg-slate-100 disabled:opacity-40 dark:border-slate-600 dark:text-slate-400 dark:hover:bg-slate-700"
                    title="Scan barcode (scanner USB / kamera)"
                    :disabled="!cameraSupported && !false"
                    @click="cameraOpen = true"
                >
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 8.25V6a2.25 2.25 0 012.25-2.25h1.372c.516 0 1.017-.208 1.38-.576l.302-.302c.3-.3.707-.468 1.13-.468h3.128c.423 0 .83.168 1.13.468l.302.302c.363.368.864.576 1.38.576H18.75A2.25 2.25 0 0121 6v2.25M3 8.25v10.5A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V8.25m-18 0h18M7.5 13.5h.008v.008H7.5v-.008zm3.75 0h.008v.008h-.008v-.008zm3.75 0h.008v.008h-.008v-.008z" />
                    </svg>
                </button>
                <BaseButton variant="primary" @click="openModal()">+ Produk</BaseButton>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-900/50">
                    <tr>
                        <th class="px-4 py-3">Produk</th>
                        <th class="px-4 py-3">Kategori</th>
                        <th class="px-4 py-3 text-right">Harga</th>
                        <th class="px-4 py-3 text-center">Stok</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    <tr v-if="loading && products.length === 0">
                        <td colspan="6" class="px-4 py-10 text-center text-slate-400">Memuat...</td>
                    </tr>
                    <tr v-else-if="products.length === 0">
                        <td colspan="6" class="px-4 py-10 text-center text-slate-400">
                            {{ search
                                ? `Tidak ada produk yang cocok dengan "${search}".`
                                : 'Belum ada produk. Tambahkan produk pertama.' }}
                        </td>
                    </tr>
                    <tr v-for="product in products" :key="product.id" class="hover:bg-slate-50 dark:hover:bg-slate-700/40">
                        <td class="px-4 py-3">
                            <p class="font-medium text-slate-800 dark:text-slate-100">{{ product.name }}</p>
                            <p class="text-xs text-slate-400">
                                {{ product.sku ?? '-' }} • {{ product.barcode ?? 'tanpa barcode' }}
                            </p>
                        </td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">
                            {{ product.category?.name ?? '-' }}
                        </td>
                        <td class="px-4 py-3 text-right font-semibold text-slate-800 dark:text-slate-100">
                            {{ formatRupiah(product.price) }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span
                                class="rounded-full px-2 py-0.5 text-xs font-semibold"
                                :class="product.stock > product.stock_min
                                    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'
                                    : 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300'"
                            >
                                {{ product.stock }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span
                                class="rounded-full px-2 py-0.5 text-xs font-semibold"
                                :class="product.is_active
                                    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'
                                    : 'bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-400'"
                            >
                                {{ product.is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button
                                class="rounded-lg px-2 py-1 text-xs font-semibold text-indigo-600 hover:bg-indigo-50 dark:text-indigo-300 dark:hover:bg-indigo-900/30"
                                @click="openModal(product)"
                            >Ubah</button>
                            <button
                                v-if="product.is_active"
                                class="rounded-lg px-2 py-1 text-xs font-semibold text-rose-600 hover:bg-rose-50 dark:text-rose-300 dark:hover:bg-rose-900/30"
                                @click="deactivate(product)"
                            >Nonaktifkan</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <ProductModal
            v-if="modalOpen"
            :product="editing"
            :categories="categories"
            :scannedBarcode="scannedBarcode"
            @close="modalOpen = false"
            @saved="onSaved"
            @scan-request="onScanRequest"
            @scan-done="stopScanToForm"
        />

        <CameraScanOverlay
            v-if="cameraOpen"
            @close="cameraOpen = false"
            @error="(msg) => { toast.push(msg, 'error'); cameraOpen = false; }"
        />
    </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import api from '../api';
import BaseButton from '../components/BaseButton.vue';
import ProductModal from '../components/pos/ProductModal.vue';
import CameraScanOverlay from '../components/pos/CameraScanOverlay.vue';
import { onScan, isCameraSupported } from '../pos/scanner';
import { useToast } from '../composables/useToast';

const toast = useToast();

const products = ref([]);
const categories = ref([]);
const loading = ref(false);
const search = ref('');
const modalOpen = ref(false);
const editing = ref(null);
const cameraOpen = ref(false);
const cameraSupported = isCameraSupported();
const scanToForm = ref(false); // saat true, hasil scan masuk kolom barcode modal

// Guard anti-race: request lebih baru selalu menang atas yang lama.
let loadSeq = 0;
let searchAbort = null;
let searchDebounce = null;
let lastLoadedSearch = null;

/** Handler semua hasil scan di halaman Produk. */
function handleScanCode(code) {
    // Modal produk terbuka + mode scan-to-form: isi kolom barcode.
    if (modalOpen.value && scanToForm.value) {
        scannedBarcode.value = code;
        scanToForm.value = false;
        cameraOpen.value = false;
        return;
    }

    // Mode cari: isi kolom cari + filter langsung.
    search.value = code;
    if (searchDebounce) {
        clearTimeout(searchDebounce);
        searchDebounce = null;
    }
    if (lastLoadedSearch !== code) {
        void load();
    }
}

const scannedBarcode = ref('');

const offScan = onScan(handleScanCode);

function onSearchEnter() {
    // Filter sudah reaktif via watch(search); tidak ada aksi tambahan.
}

/** Modal minta scan → nyalakan mode scan-to-Form + kamera bila ada. */
async function onScanRequest() {
    scannedBarcode.value = '';
    scanToForm.value = true;
    if (cameraSupported) {
        cameraOpen.value = true;
    } else {
        toast.push('Scan dengan scanner USB atau ketik manual — kamera tidak tersedia di browser ini.', 'info');
    }
}

function stopScanToForm() {
    scanToForm.value = false;
    cameraOpen.value = false;
}

async function load() {
    // Request lebih baru membatalkan yang lama agar antrian tidak menumpuk
    // (penyebab loading lama saat scan: ~1 request per karakter barcode).
    searchAbort?.abort();

    const seq = ++loadSeq;

    loading.value = true;
    try {
        searchAbort = new AbortController();

        const [prodRes, catRes] = await Promise.all([
            api.get('/pos/products', {
                params: { search: search.value || undefined, with_inactive: 1 },
                signal: searchAbort.signal,
            }),
            categories.value.length === 0
                ? api.get('/pos/categories', { signal: searchAbort.signal })
                : Promise.resolve(null),
        ]);

        if (seq !== loadSeq) return;

        lastLoadedSearch = search.value;
        if (prodRes) products.value = prodRes.data.data ?? prodRes.data;
        if (catRes) categories.value = catRes.data.categories;
    } catch (err) {
        if (err?.name === 'CanceledError' || err?.code === 'ERR_CANCELED') return;
        toast.push('Gagal memuat produk.', 'error');
    } finally {
        if (seq === loadSeq) {
            searchAbort = null;
            loading.value = false;
        }
    }
}

// Debounce ketik manual: satu request per jeda 300ms, bukan per karakter.
// Abaikan perubahan yang hasilnya sudah dimuat (mis. langsung dari scan).
watch(search, (val) => {
    if (val === lastLoadedSearch) return;
    if (searchDebounce) clearTimeout(searchDebounce);
    searchDebounce = setTimeout(() => {
        searchDebounce = null;
        if (search.value === lastLoadedSearch) return;
        load();
    }, 300);
});

function openModal(product = null) {
    editing.value = product;
    modalOpen.value = true;
}

function onSaved() {
    modalOpen.value = false;
    load();
}

async function deactivate(product) {
    if (!confirm(`Nonaktifkan produk "${product.name}"?`)) return;
    try {
        await api.delete(`/pos/products/${product.id}`);
        toast.push('Produk dinonaktifkan.', 'success');
        load();
    } catch {
        toast.push('Gagal menonaktifkan produk.', 'error');
    }
}

function formatRupiah(value) {
    return 'Rp ' + Number(value ?? 0).toLocaleString('id-ID', { maximumFractionDigits: 0 });
}

onMounted(load);

onBeforeUnmount(() => {
    offScan();
    if (searchDebounce) clearTimeout(searchDebounce);
    if (searchAbort) searchAbort.abort();
});
</script>
