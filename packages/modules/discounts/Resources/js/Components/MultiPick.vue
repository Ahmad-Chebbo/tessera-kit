<script setup>
import { computed } from 'vue';
import { X } from 'lucide-vue-next';
import Select from '@ahmad-chebbo/tessera-ui/Components/Select.vue';

const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    options: { type: Array, required: true },
    placeholder: { type: String, default: '' },
    id: { type: String, default: undefined },
});

const emit = defineEmits(['update:modelValue']);

const sameId = (a, b) => String(a) === String(b);
const labelOf = (id) => props.options.find((option) => sameId(option.id, id))?.name ?? id;
const available = computed(() => props.options.filter((option) => !props.modelValue.some((picked) => sameId(picked, option.id))));

const add = (id) => {
    if (id !== '' && id !== null && id !== undefined) {
        emit('update:modelValue', [...props.modelValue, Number(id)]);
    }
};

const remove = (id) => emit('update:modelValue', props.modelValue.filter((picked) => !sameId(picked, id)));
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <span
            v-for="id in modelValue"
            :key="id"
            data-chip
            class="inline-flex items-center gap-1 rounded-full bg-accent-wash px-2.5 py-1 text-xs text-accent"
        >
            {{ labelOf(id) }}
            <button type="button" :aria-label="`Remove ${labelOf(id)}`" @click="remove(id)"><X class="h-3 w-3" /></button>
        </span>
        <Select v-if="available.length" :id="id" model-value="" class="w-56" :placeholder="placeholder" @update:model-value="add">
            <option v-for="option in available" :key="option.id" :value="option.id">{{ option.name }}</option>
        </Select>
    </div>
</template>
