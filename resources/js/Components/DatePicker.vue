<script setup>
/**
 * DatePicker — locale-independent dd/MM/yyyy text input.
 *
 * v-model contract:
 *   - Reads any reasonable canonical date form ("yyyy-MM-dd", ISO datetime, Date, epoch).
 *   - Emits update:modelValue as canonical "yyyy-MM-dd" (or empty string when cleared).
 */
import { computed, ref, watch } from 'vue';
import {
    formatDateVN,
    parseVNDate,
    pad2,
} from '@/utils/dateTime.js';

const props = defineProps({
    modelValue: { type: [String, Date, Number, null], default: '' },
    placeholder: { type: String, default: 'dd/MM/yyyy' },
    disabled: { type: Boolean, default: false },
    required: { type: Boolean, default: false },
    label: { type: String, default: '' },
    inputClass: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue', 'blur']);

const text = ref(formatDateVN(props.modelValue));
const error = ref('');

watch(
    () => props.modelValue,
    (v) => {
        const formatted = formatDateVN(v);
        if (formatted !== text.value) {
            text.value = formatted;
            error.value = '';
        }
    }
);

const onInput = (e) => {
    text.value = e.target.value;
    error.value = '';
};

const commit = () => {
    const raw = (text.value || '').trim();
    if (!raw) {
        emit('update:modelValue', '');
        error.value = '';
        return;
    }
    const d = parseVNDate(raw);
    if (!d) {
        error.value = 'Định dạng phải là dd/MM/yyyy.';
        return;
    }
    error.value = '';
    const canonical = `${d.getFullYear()}-${pad2(d.getMonth() + 1)}-${pad2(d.getDate())}`;
    text.value = formatDateVN(d);
    emit('update:modelValue', canonical);
};

const onBlur = () => {
    commit();
    emit('blur');
};

const onKeydown = (e) => {
    if (e.key === 'Enter') {
        e.preventDefault();
        commit();
    }
};

const setToday = () => {
    const d = new Date();
    text.value = formatDateVN(d);
    error.value = '';
    const canonical = `${d.getFullYear()}-${pad2(d.getMonth() + 1)}-${pad2(d.getDate())}`;
    emit('update:modelValue', canonical);
};

const computedClass = computed(() => [
    'w-full border rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500',
    error.value ? 'border-red-400' : 'border-gray-300',
    props.disabled ? 'bg-gray-100 text-gray-500 cursor-not-allowed' : 'bg-white',
    props.inputClass,
]);
</script>

<template>
    <div class="w-full">
        <label v-if="label" class="block text-sm font-medium text-gray-700 mb-1">{{ label }}</label>
        <div class="relative">
            <input
                type="text"
                inputmode="numeric"
                :value="text"
                @input="onInput"
                @blur="onBlur"
                @keydown="onKeydown"
                :placeholder="placeholder"
                :disabled="disabled"
                :required="required"
                :class="computedClass"
                autocomplete="off"
            />
            <button
                v-if="!disabled"
                type="button"
                @click="setToday"
                class="absolute inset-y-0 right-1 my-1 px-2 text-xs text-blue-600 hover:bg-blue-50 rounded"
                tabindex="-1"
                title="Hôm nay"
            >Hôm nay</button>
        </div>
        <p v-if="error" class="mt-1 text-xs text-red-600">{{ error }}</p>
    </div>
</template>
