<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import FilterTabs from './FilterTabs.vue';

const props = defineProps({
    presets: { type: Array, required: true },
    from: { type: String, default: '' },
    to: { type: String, default: '' },
});

const emit = defineEmits(['select']);
const { t } = useI18n();

const active = computed(() => props.presets.find((preset) => preset.from === props.from && preset.to === props.to)?.key ?? null);
const tabs = computed(() => props.presets.map((preset) => ({ value: preset.key, label: t(`periods.${preset.key}`) })));

const choose = (key) => {
    const preset = props.presets.find((candidate) => candidate.key === key);

    if (preset) {
        emit('select', { from: preset.from, to: preset.to });
    }
};
</script>

<template>
    <FilterTabs :model-value="active" :tabs="tabs" @update:model-value="choose" />
</template>
