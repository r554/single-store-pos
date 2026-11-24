<template>
    <div class="fixed top-4 right-4 z-50 flex w-80 flex-col gap-2">
        <TransitionGroup
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0 translate-x-4"
            enter-to-class="opacity-100 translate-x-0"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-for="toast in toasts"
                :key="toast.id"
                class="flex items-center gap-3 rounded-lg border py-3 pr-2 pl-3 text-sm shadow-lg"
                :class="variantClass(toast.type)"
            >
                <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-white"
                    :class="dotClass(toast.type)">
                    <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                        <path v-if="toast.type === 'error'" stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        <path v-else-if="toast.type === 'info'" stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01" />
                        <path v-else stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                </span>
                <p class="min-w-0 flex-1 leading-snug break-words">{{ toast.message }}</p>
                <button
                    type="button"
                    class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md opacity-50 transition hover:bg-black/5 hover:opacity-100 dark:hover:bg-white/10"
                    aria-label="Tutup"
                    @click="remove(toast.id)"
                >
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </TransitionGroup>
    </div>
</template>

<script setup>
import { useToast } from '../composables/useToast';

const { toasts, remove } = useToast();

function variantClass(type) {
    if (type === 'error') return 'border-rose-200 bg-rose-50 text-rose-700';
    if (type === 'info') return 'border-sky-200 bg-sky-50 text-sky-700';
    return 'border-emerald-200 bg-emerald-50 text-emerald-700';
}

function dotClass(type) {
    if (type === 'error') return 'bg-rose-500';
    if (type === 'info') return 'bg-sky-500';
    return 'bg-emerald-500';
}
</script>
