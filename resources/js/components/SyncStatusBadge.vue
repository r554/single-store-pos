<template>
    <div
        v-if="visible"
        class="fixed bottom-3 right-3 z-50 flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-medium shadow-lg"
        :class="badgeClass"
        role="status"
    >
        <span class="relative flex h-2 w-2">
            <span
                v-if="state.syncing || state.status === 'offline'"
                class="absolute inline-flex h-full w-full animate-ping rounded-full opacity-60"
                :class="state.status === 'offline' ? 'bg-amber-600' : 'bg-blue-600'"
            ></span>
            <span
                class="relative inline-flex h-2 w-2 rounded-full"
                :class="dotClass"
            ></span>
        </span>
        <span>{{ label }}</span>
        <button
            v-if="state.pending > 0 && state.status === 'online'"
            class="ml-1 underline underline-offset-2 hover:no-underline"
            @click="syncNow"
        >
            Sync sekarang
        </button>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useSyncStatus } from '../composables/useSyncStatus';

const { state } = useSyncStatus();

const visible = computed(() =>
    state.status === 'offline' || state.pending > 0 || state.syncing,
);

const label = computed(() => {
    if (state.status === 'offline') {
        return 'Mode offline';
    }
    if (state.syncing) {
        return 'Menyinkronkan...';
    }
    if (state.pending > 0) {
        return `${state.pending} transaksi menunggu sync`;
    }
    return 'Online';
});

const badgeClass = computed(() => {
    if (state.status === 'offline') {
        return 'bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-200';
    }
    if (state.syncing) {
        return 'bg-blue-100 text-blue-900 dark:bg-blue-900/40 dark:text-blue-200';
    }
    return 'bg-gray-100 text-gray-900 dark:bg-gray-800 dark:text-gray-200';
});

const dotClass = computed(() => {
    if (state.status === 'offline') return 'bg-amber-600';
    if (state.syncing) return 'bg-blue-600';
    return 'bg-emerald-600';
});
</script>
