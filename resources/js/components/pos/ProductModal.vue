<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4" @click.self="$emit('close')">
        <div class="w-full max-w-lg overflow-hidden rounded-xl bg-white shadow-xl dark:bg-slate-800">
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-slate-700">
                <h2 class="text-base font-bold text-slate-900 dark:text-slate-100">
                    {{ product ? 'Ubah Produk' : 'Tambah Produk' }}
                </h2>
                <button class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200" @click="$emit('close')">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form class="space-y-4 px-5 py-4" @submit.prevent="submit">
                <div class="grid grid-cols-2 gap-4">
                    <BaseInput v-model="form.name" label="Nama Produk" required :error="errors.name" />
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Kategori</label>
                        <select
                            v-model="form.category_id"
                            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-800 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                        >
                            <option :value="null">- Tanpa kategori -</option>
                            <option v-for="category in categories" :key="category.id" :value="category.id">
                                {{ category.name }}
                            </option>
                        </select>
                    </div>
                    <BaseInput v-model="form.sku" label="SKU" :error="errors.sku" placeholder="MNM-001" />
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">
                            Barcode
                            <button
                                type="button"
                                class="ml-1 text-xs font-semibold text-indigo-600 hover:underline dark:text-indigo-300"
                                title="Scan via kamera — hasil masuk ke kolom ini"
                                @click="enableScan"
                            >scan</button>
                        </label>
                        <input
                            :value="form.barcode"
                            type="text"
                            class="w-full rounded-lg border bg-white px-3 py-2.5 text-sm text-slate-800 shadow-sm transition placeholder:text-slate-400 focus:outline-none focus:ring-2 dark:bg-slate-700 dark:text-slate-100"
                            :class="scanActive
                                ? 'border-indigo-400 ring-2 ring-indigo-200 animate-pulse dark:ring-indigo-900'
                                : 'border-slate-300 focus:border-indigo-500 focus:ring-indigo-200'"
                            placeholder="8991001"
                            @input="form.barcode = $event.target.value"
                        >
                        <p v-if="scanActive" class="mt-1.5 text-xs font-medium text-indigo-600 dark:text-indigo-300">
                            Menunggu scan... (kamera terbuka bila tidak ada scanner USB)
                        </p>
                    </div>
                    <BaseInput v-model="form.price" label="Harga Jual" type="number" required min="0" :error="errors.price" />
                    <BaseInput v-model="form.cost" label="Harga Beli" type="number" min="0" :error="errors.cost" />
                    <BaseInput v-model="form.stock" label="Stok" type="number" min="0" :error="errors.stock" />
                    <BaseInput v-model="form.stock_min" label="Stok Minimum" type="number" min="0" :error="errors.stock_min" />
                    <label class="col-span-2 flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                        <input v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded" />
                        Produk aktif (tampil di kasir)
                    </label>
                </div>

                <div class="flex justify-end gap-2 border-t border-slate-200 pt-4 dark:border-slate-700">
                    <BaseButton variant="secondary" type="button" @click="$emit('close')">Batal</BaseButton>
                    <BaseButton variant="primary" type="submit" :loading="saving">Simpan</BaseButton>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup>
import { onBeforeUnmount, reactive, ref, watch } from 'vue';
import api from '../../api';
import BaseButton from '../BaseButton.vue';
import BaseInput from '../BaseInput.vue';
import { useToast } from '../../composables/useToast';

const props = defineProps({
    product: { type: Object, default: null },
    categories: { type: Array, default: () => [] },
    /** Nilai barcode hasil scan dari halaman induk (v-model-ish). */
    scannedBarcode: { type: String, default: '' },
});

const emit = defineEmits(['close', 'saved', 'scan-request', 'scan-done']);

const toast = useToast();
const saving = ref(false);
const errors = ref({});
const scanActive = ref(false);

const form = reactive({
    name: '',
    category_id: null,
    sku: '',
    barcode: '',
    price: '',
    cost: '',
    stock: 0,
    stock_min: 0,
    is_active: true,
});

/** Mulai tunggu scan barcode → kolom barcode terisi hasil scan. */
function enableScan() {
    scanActive.value = true;
    // Beri tahu induk untuk membuka kamera (-overlay) bila tersedia;
    // scanner USB keyboard-wedge sudah aktif global.
    emit('scan-request');
}

function stopCamera() {
    emit('scan-done');
}

onBeforeUnmount(() => {
    emit('scan-done');
});

watch(
    () => props.product,
    (product) => {
        if (product) {
            Object.assign(form, {
                name: product.name,
                category_id: product.category_id,
                sku: product.sku ?? '',
                barcode: product.barcode ?? '',
                price: product.price,
                cost: product.cost ?? '',
                stock: product.stock,
                stock_min: product.stock_min ?? 0,
                is_active: product.is_active,
            });
        }
    },
    { immediate: true },
);

// Hasil masuk dari scan (keyboard-wedge / kamera) dikirim induk
// lewat scannedBarcode → isi kolom barcode.
watch(
    () => props.scannedBarcode,
    (code) => {
        if (code) {
            form.barcode = code;
            scanActive.value = false;
            stopCamera();
        }
    },
);

async function submit() {
    saving.value = true;
    errors.value = {};

    const payload = {
        ...form,
        price: form.price === '' ? null : Number(form.price),
        cost: form.cost === '' ? null : Number(form.cost),
        stock: form.stock === '' ? null : Number(form.stock),
        stock_min: form.stock_min === '' ? null : Number(form.stock_min),
        category_id: form.category_id ?? null,
    };

    try {
        if (props.product) {
            await api.put(`/pos/products/${props.product.id}`, payload);
            toast.push('Produk berhasil diperbarui.', 'success');
        } else {
            await api.post('/pos/products', payload);
            toast.push('Produk berhasil ditambahkan.', 'success');
        }
        emit('saved');
    } catch (err) {
        errors.value = err?.response?.data?.errors ?? {};
        toast.push(err?.response?.data?.message ?? 'Gagal menyimpan produk.', 'error');
    } finally {
        saving.value = false;
    }
}
</script>
