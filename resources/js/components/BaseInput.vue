<template>
    <div>
        <label v-if="label" :for="inputId" class="mb-1.5 block text-sm font-medium text-slate-700">
            {{ label }}
            <span v-if="required" class="text-rose-500">*</span>
        </label>
        <div class="relative">
            <div v-if="$slots.icon" class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                <slot name="icon" />
            </div>
            <input
                :id="inputId"
                ref="inputRef"
                v-bind="$attrs"
                :type="effectiveType"
                :value="modelValue"
                :class="[baseClasses, $slots.icon ? 'pl-10' : '', showToggleButton ? 'pr-20' : '', errorClasses]"
                @input="$emit('update:modelValue', $event.target.value)"
            />
            <button
                v-if="showToggleButton"
                type="button"
                :aria-label="revealed ? 'Sembunyikan password' : 'Lihat password'"
                class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200"
                @click="revealed = !revealed"
            >
                <svg v-if="revealed" class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243" />
                </svg>
                <svg v-else class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
            </button>
        </div>
        <p v-if="error" class="mt-1.5 text-xs font-medium text-rose-600">{{ error }}</p>
        <p v-else-if="hint" class="mt-1.5 text-xs text-slate-500">{{ hint }}</p>
    </div>
</template>

<script setup>
import { computed, ref, useAttrs } from 'vue';

defineOptions({ inheritAttrs: false });

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    label: { type: String, default: '' },
    type: { type: String, default: 'text' },
    error: { type: String, default: '' },
    hint: { type: String, default: '' },
    required: { type: Boolean, default: false },
    showToggle: { type: Boolean, default: true },
});

defineEmits(['update:modelValue']);

const attrs = useAttrs();
const revealed = ref(false);
const inputRef = ref(null);

const inputId = computed(() => attrs.id || `input-${Math.random().toString(36).slice(2, 9)}`);
const showToggleButton = computed(() => props.type === 'password' && props.showToggle);
const effectiveType = computed(() => (showToggleButton.value && revealed.value ? 'text' : props.type));

const baseClasses =
    'w-full rounded-lg border bg-white px-3 py-2.5 text-sm text-slate-800 shadow-sm transition placeholder:text-slate-400 focus:outline-none focus:ring-2';

const errorClasses = computed(() =>
    props.error
        ? 'border-rose-400 focus:border-rose-500 focus:ring-rose-200'
        : 'border-slate-300 focus:border-indigo-500 focus:ring-indigo-200'
);
</script>
