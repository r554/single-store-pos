<template>
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
        <div
            v-if="$slots.filters || $slots.actions"
            class="flex flex-col gap-3 border-b border-slate-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-slate-700"
        >
            <div class="flex flex-1 flex-col gap-2 sm:flex-row">
                <slot name="filters" />
            </div>
            <div class="flex items-center gap-2">
                <slot name="actions" />
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-900/50 dark:text-slate-400">
                    <tr>
                        <th
                            v-for="column in columns"
                            :key="column.key"
                            class="px-4 py-3 font-semibold"
                            :class="column.sortable ? 'cursor-pointer select-none hover:text-slate-700 dark:hover:text-slate-200' : ''"
                            @click="column.sortable ? $emit('sort', column.key) : null"
                        >
                            <span class="inline-flex items-center gap-1">
                                {{ column.label }}
                                <svg
                                    v-if="column.sortable"
                                    class="h-3 w-3 transition"
                                    :class="sortKey === column.key ? 'text-indigo-500' : 'text-slate-300'"
                                    :style="sortDir === 'desc' && sortKey === column.key ? 'transform:rotate(180deg)' : ''"
                                    viewBox="0 0 12 12" fill="currentColor"
                                >
                                    <path d="M6 9L1.5 3h9L6 9z" />
                                </svg>
                            </span>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    <tr v-if="loading">
                        <td :colspan="columns.length" class="px-4 py-10 text-center text-slate-500 dark:text-slate-400">
                            Memuat data...
                        </td>
                    </tr>
                    <tr v-else-if="items.length === 0">
                        <td :colspan="columns.length" class="px-4 py-10 text-center text-slate-500 dark:text-slate-400">
                            <slot name="empty">Tidak ada data ditemukan.</slot>
                        </td>
                    </tr>
                    <template v-else>
                        <tr
                            v-for="(item, index) in items"
                            :key="item.id ?? index"
                            class="hover:bg-slate-50 dark:hover:bg-slate-700/40"
                        >
                            <td
                                v-for="column in columns"
                                :key="column.key"
                                class="px-4 py-3 align-middle"
                            >
                                <slot :name="`cell-${column.key}`" :item="item" :value="resolve(item, column.key)">
                                    {{ resolve(item, column.key) }}
                                </slot>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <div
            v-if="pagination"
            class="flex flex-col gap-3 border-t border-slate-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-slate-700"
        >
            <p class="text-xs text-slate-500 dark:text-slate-400">
                Menampilkan {{ pagination.from ?? 0 }}-{{ pagination.to ?? 0 }} dari {{ pagination.total ?? 0 }} data
            </p>
            <div class="flex items-center gap-2">
                <button
                    class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 disabled:opacity-40 dark:border-slate-600 dark:text-slate-200"
                    :disabled="!pagination.prev_page_url"
                    @click="$emit('page', pagination.current_page - 1)"
                >
                    Sebelumnya
                </button>
                <span class="text-xs text-slate-500 dark:text-slate-400">
                    Halaman {{ pagination.current_page ?? 1 }} / {{ pagination.last_page ?? 1 }}
                </span>
                <button
                    class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 disabled:opacity-40 dark:border-slate-600 dark:text-slate-200"
                    :disabled="!pagination.next_page_url"
                    @click="$emit('page', pagination.current_page + 1)"
                >
                    Berikutnya
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
defineProps({
    columns: { type: Array, required: true },
    items: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    pagination: { type: Object, default: null },
    sortKey: { type: String, default: '' },
    sortDir: { type: String, default: 'asc' },
});

defineEmits(['sort', 'page']);

function resolve(item, key) {
    return key.split('.').reduce((value, part) => value?.[part], item) ?? '';
}
</script>
