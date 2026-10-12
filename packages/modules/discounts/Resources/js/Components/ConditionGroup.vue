<script setup>
import { FolderPlus, Plus, Trash2 } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import Select from '@ahmad-chebbo/tessera-ui/Components/Select.vue';
import ConditionRow from '@modules/Discounts/Resources/js/Components/ConditionRow.vue';
import { blankCondition, blankGroup } from '@modules/Discounts/Resources/js/Components/conditionTree';

defineOptions({ name: 'ConditionGroup' });

const props = defineProps({
    node: { type: Object, required: true },
    facts: { type: Array, required: true },
    lookups: { type: Object, default: () => ({}) },
    depth: { type: Number, default: 0 },
});

const emit = defineEmits(['update:node', 'remove']);
const { t } = useI18n();

const MAX_DEPTH = 3;

const update = (changes) => emit('update:node', { ...props.node, ...changes });
const children = () => props.node.children ?? [];

const setChild = (index, value) => update({ children: children().map((child, i) => (i === index ? value : child)) });
const removeChild = (index) => update({ children: children().filter((_, i) => i !== index) });
const addCondition = () => update({ children: [...children(), blankCondition(props.facts)] });
const addGroup = () => update({ children: [...children(), blankGroup()] });
</script>

<template>
    <div class="space-y-3 rounded-xl border border-line bg-surface p-3" :class="depth > 0 ? 'bg-hover-wash' : ''">
        <div class="flex flex-wrap items-center gap-2 text-sm text-muted">
            <span>{{ t('discounts.matchPrefix') }}</span>
            <Select :model-value="node.logic" size="sm" class="w-28" @update:model-value="update({ logic: $event })">
                <option value="all">{{ t('discounts.matchAll') }}</option>
                <option value="any">{{ t('discounts.matchAny') }}</option>
            </Select>
            <span>{{ t('discounts.matchSuffix') }}</span>
            <button
                v-if="depth > 0"
                type="button"
                class="ms-auto text-muted hover:text-status-danger"
                :aria-label="t('discounts.removeGroup')"
                @click="emit('remove')"
            >
                <Trash2 class="h-4 w-4" />
            </button>
        </div>

        <div v-for="(child, index) in children()" :key="index">
            <ConditionGroup
                v-if="child.type === 'group'"
                :node="child"
                :facts="facts"
                :lookups="lookups"
                :depth="depth + 1"
                @update:node="setChild(index, $event)"
                @remove="removeChild(index)"
            />
            <ConditionRow
                v-else
                :node="child"
                :facts="facts"
                :lookups="lookups"
                @update:node="setChild(index, $event)"
                @remove="removeChild(index)"
            />
        </div>

        <div class="flex flex-wrap gap-2">
            <button
                type="button"
                class="inline-flex items-center gap-1 rounded-lg border border-dashed border-line px-3 py-1.5 text-xs text-muted hover:bg-hover-wash"
                @click="addCondition"
            >
                <Plus class="h-3.5 w-3.5" />
                {{ t('discounts.addCondition') }}
            </button>
            <button
                v-if="depth < MAX_DEPTH"
                type="button"
                class="inline-flex items-center gap-1 rounded-lg border border-dashed border-line px-3 py-1.5 text-xs text-muted hover:bg-hover-wash"
                @click="addGroup"
            >
                <FolderPlus class="h-3.5 w-3.5" />
                {{ t('discounts.addGroup') }}
            </button>
        </div>
    </div>
</template>
