<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Select from '@tessera/tessera-ui/Components/Select.vue';

defineProps({
    modelValue: { type: [String, Number], default: '' },
});

defineEmits(['update:modelValue']);

const { t } = useI18n();
const page = usePage();

const scope = computed(() => page.props.branchScope);
const visible = computed(() => scope.value?.canSeeAll && scope.value.branches.length > 1);
</script>

<template>
    <Select v-if="visible" :model-value="modelValue" class="w-48" :aria-label="t('branchFilter.label')" @update:model-value="$emit('update:modelValue', $event)">
        <option value="">{{ t('branchFilter.all') }}</option>
        <option v-for="branch in scope.branches" :key="branch.id" :value="branch.id">{{ branch.name }}</option>
    </Select>
</template>
