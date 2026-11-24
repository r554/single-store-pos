<template>
    <div>
        <label v-if="label" :for="inputId" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">
            {{ label }}
            <span v-if="required" class="text-rose-500">*</span>
        </label>
        <div class="relative">
            <span
                v-if="showRpPrefix"
                class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm font-semibold text-slate-400"
            >Rp</span>
            <input
                :id="inputId"
                ref="inputEl"
                type="text"
                inputmode="numeric"
                autocomplete="off"
                :value="display"
                :placeholder="placeholder"
                :class="[
                    baseClasses,
                    showRpPrefix ? 'pl-10' : '',
                    error ? 'border-rose-400 focus:border-rose-500 focus:ring-rose-200' : 'border-slate-300 focus:border-indigo-500 focus:ring-indigo-200',
                ]"
                @input="onInput"
                @keydown.enter.prevent="$emit('submit')"
                @focus="selectAll"
            >
        </div>
        <p v-if="error" class="mt-1.5 text-xs font-medium text-rose-600">{{ error }}</p>
        <p v-else-if="hint" class="mt-1.5 text-xs text-slate-500">{{ hint }}</p>
    </div>
</template>

<script setup>
import { computed, nextTick, onMounted, ref, useAttrs } from 'vue';

/**
 * Input angka Rupiah dengan pemisah ribuan otomatis:
 * ketik 10000 → tampil "10.000", ketik 200000 → "200.000".
 * Model tetap angka (Number / null).
 */
const props = defineProps({
    modelValue: { type: [Number, String], default: null },
    label: { type: String, default: '' },
    placeholder: { type: String, default: '0' },
    error: { type: String, default: '' },
    hint: { type: String, default: '' },
    required: { type: Boolean, default: false },
    showRpPrefix: { type: Boolean, default: true },
});

const emit = defineEmits(['update:modelValue', 'submit']);

const attrs = useAttrs();
const inputId = computed(() => attrs.id || `rp-${Math.random().toString(36).slice(2, 9)}`);
const inputEl = ref(null);
const autoFocus = attrs.autofocus !== undefined;

onMounted(() => {
    if (autoFocus) {
        nextTick(() => inputEl.value?.focus());
    }
});

const baseClasses = [
    'w-full rounded-lg border bg-white py-2.5 pr-3 text-right text-sm font-semibold text-slate-900 shadow-sm',
    'placeholder:text-slate-400 placeholder:font-normal focus:outline-none focus:ring-2',
    'dark:bg-slate-700 dark:text-slate-100',
].join(' ');

const digits = computed(() => {
    const s = String(props.modelValue ?? '');
    return s.replace(/\D/g, '').replace(/^0+(?=\d)/, '');
});

// Nilai 0 tampil kosong (placeholder "0" di belakangnya) — bukan "0".
const display = computed(() => {
    if (digits.value === '' || Number(digits.value) === 0) return '';
    return Number(digits.value).toLocaleString('id-ID');
});

function onInput(event) {
    let raw = event.target.value.replace(/\D/g, '');

    // Nol di depan dibuang (ketik "0" lalu "5" → jadi "5", bukan "05").
    raw = raw.replace(/^0+(?=\d)/, '');

    // Nol sendirian = kosongkan (ketik 0 → tampil kosong, placeholder muncul).
    if (raw === '' || Number(raw) === 0) {
        emit('update:modelValue', 0);
        requestAnimationFrame(() => {
            event.target.value = '';
        });
        return;
    }
    // Batasi panjang wajar (maks 12 digit) supaya tidak overflow.
    const safe = raw.slice(0, 12);
    emit('update:modelValue', Number(safe));

    // Jaga tampilan setelah reformat.
    requestAnimationFrame(() => {
        event.target.value = Number(safe).toLocaleString('id-ID');
    });
}

function selectAll(event) {
    event.target.select();
}
</script>
