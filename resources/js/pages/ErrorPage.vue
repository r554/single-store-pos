<template>
    <div class="flex min-h-screen items-center justify-center bg-slate-100 px-4">
        <div class="w-full max-w-md rounded-2xl bg-white p-8 text-center shadow-sm">
            <p class="text-6xl font-bold text-indigo-600">{{ code }}</p>
            <h1 class="mt-4 text-xl font-bold text-slate-900">{{ title }}</h1>
            <p class="mt-2 text-sm text-slate-500">{{ description }}</p>

            <div class="mt-6 flex justify-center gap-3">
                <BaseButton variant="secondary" @click="router.back()">Kembali</BaseButton>
                <BaseButton @click="router.push({ name: 'dashboard' })">Ke Dashboard</BaseButton>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import BaseButton from '../components/BaseButton.vue';

const props = defineProps({
    code: { type: String, required: true },
});

const router = useRouter();

const title = computed(
    () =>
        ({
            403: 'Akses Ditolak',
            404: 'Halaman Tidak Ditemukan',
            500: 'Terjadi Kesalahan Server',
        })[props.code] ?? 'Terjadi Kesalahan'
);

const description = computed(
    () =>
        ({
            403: 'Anda tidak memiliki izin untuk mengakses halaman ini.',
            404: 'Halaman yang Anda cari tidak ada atau sudah dipindahkan.',
            500: 'Server mengalami kendala. Coba beberapa saat lagi.',
        })[props.code] ?? 'Silakan coba lagi nanti.'
);
</script>
