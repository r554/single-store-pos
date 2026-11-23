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
                class="flex items-start gap-3 rounded-lg border px-4 py-3 text-sm shadow-lg"
                :class="toast.type === 'error'
                    ? 'border-rose-200 bg-rose-50 text-rose-700'
                    : 'border-emerald-200 bg-emerald-50 text-emerald-700'"
            >
                <span class="mt-0.5 h-2 w-2 shrink-0 rounded-full"
                    :class="toast.type === 'error' ? 'bg-rose-500' : 'bg-emerald-500'" />
                <p class="flex-1">{{ toast.message }}</p>
                <button class="text-xs opacity-60 hover:opacity-100" @click="remove(toast.id)">x</button>
            </div>
        </TransitionGroup>
    </div>
</template>

<script setup>
import { useToast } from '../composables/useToast';

const { toasts, remove } = useToast();
</script>
