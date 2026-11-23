<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4">
        <div class="w-full max-w-sm rounded-xl bg-white p-5 shadow-2xl">
            <h3 class="text-base font-semibold text-slate-900">{{ title }}</h3>
            <p class="mt-2 text-sm text-slate-600">{{ message }}</p>
            <div class="mt-5 flex justify-end gap-2">
                <BaseButton variant="secondary" :disabled="loading" @click="$emit('cancel')">Batal</BaseButton>
                <BaseButton variant="danger" :loading="loading" @click="$emit('confirm')">Hapus</BaseButton>
            </div>
        </div>
    </div>
</template>

<script setup>
import BaseButton from './BaseButton.vue';

defineProps({
    title: { type: String, default: 'Konfirmasi' },
    message: { type: String, default: '' },
    loading: { type: Boolean, default: false },
});

defineEmits(['cancel', 'confirm']);
</script>
