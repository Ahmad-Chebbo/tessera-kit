<script setup>
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { ArrowLeft, Banknote, BadgeCheck, Calendar, Clock, Gift, Heart, Layers, Receipt, Repeat, Sparkles, Tag, Ticket, Users } from 'lucide-vue-next';
import Modal from '@ahmad-chebbo/tessera-ui/Components/Modal.vue';
import Select from '@ahmad-chebbo/tessera-ui/Components/Select.vue';
import TextInput from '@ahmad-chebbo/tessera-ui/Components/TextInput.vue';
import InputLabel from '@ahmad-chebbo/tessera-ui/Components/InputLabel.vue';
import InputError from '@ahmad-chebbo/tessera-ui/Components/InputError.vue';
import PrimaryButton from '@ahmad-chebbo/tessera-ui/Components/PrimaryButton.vue';
import MultiPick from '@modules/Discounts/Resources/js/Components/MultiPick.vue';

const props = defineProps({
    show: Boolean,
    templates: { type: Array, required: true },
    lookups: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['close']);
const { t } = useI18n();

const icons = { clock: Clock, calendar: Calendar, layers: Layers, gift: Gift, receipt: Receipt, heart: Heart, repeat: Repeat, sparkles: Sparkles, tag: Tag, badge: BadgeCheck, users: Users, banknote: Banknote, ticket: Ticket };

const chosen = ref(null);
const form = useForm({});

const choose = (template) => {
    chosen.value = template;
    form.clearErrors();
    form.defaults({
        ...Object.fromEntries(template.params.map((param) => [param.name, param.type === 'products' ? [] : (param.default ?? '')])),
        is_active: true,
    }).reset();
};

const close = () => {
    chosen.value = null;
    emit('close');
};

const submit = () => {
    form
        .transform((data) => Object.fromEntries(Object.entries(data).filter(([, value]) => value !== '' && value !== null && !(Array.isArray(value) && value.length === 0))))
        .post(route('discounts.from-template', chosen.value.key), {
            preserveScroll: true,
            onSuccess: close,
        });
};

const inputType = (param) => ({ percent: 'number', amount: 'number', quantity: 'number', days: 'number', time: 'text', code: 'text' })[param.type];
const isLookup = (param) => ['category', 'group', 'product'].includes(param.type);
const optionsFor = (param) => props.lookups[param.type === 'products' ? 'product' : param.type] ?? [];
const title = computed(() => (chosen.value ? t(`discounts.templates.${chosen.value.key}.name`) : t('discounts.templatesTitle')));
</script>

<template>
    <Modal :show="show" max-width="2xl" @close="close">
        <div class="p-6">
            <div class="mb-4 flex items-center gap-3">
                <button v-if="chosen" type="button" class="text-muted hover:text-fg" :aria-label="t('discounts.back')" @click="chosen = null">
                    <ArrowLeft class="h-5 w-5 rtl:rotate-180" />
                </button>
                <h2 class="font-display text-lg font-normal text-fg">{{ title }}</h2>
            </div>

            <template v-if="!chosen">
                <p class="mb-4 text-sm text-muted">{{ t('discounts.templatesHint') }}</p>
                <div class="grid gap-3 sm:grid-cols-2">
                    <button
                        v-for="template in templates"
                        :key="template.key"
                        type="button"
                        class="flex items-start gap-3 rounded-xl border border-line bg-surface p-3 text-start transition-colors hover:bg-hover-wash"
                        @click="choose(template)"
                    >
                        <component :is="icons[template.icon] ?? Tag" class="mt-0.5 h-5 w-5 shrink-0 text-accent" />
                        <span>
                            <span class="block text-sm font-medium text-fg">{{ t(`discounts.templates.${template.key}.name`) }}</span>
                            <span class="block text-xs text-muted">{{ t(`discounts.templates.${template.key}.description`) }}</span>
                        </span>
                    </button>
                </div>
            </template>

            <form v-else class="space-y-4" @submit.prevent="submit">
                <p class="text-sm text-muted">{{ t(`discounts.templates.${chosen.key}.description`) }}</p>
                <div v-for="param in chosen.params" :key="param.name">
                    <InputLabel :for="`tpl_${param.name}`" :value="t(`discounts.templateParams.${param.name}`, t(`discounts.templateParams.${param.type}`))" />
                    <MultiPick v-if="param.type === 'products'" :id="`tpl_${param.name}`" v-model="form[param.name]" class="mt-1" :options="optionsFor(param)" :placeholder="t('discounts.add')" />
                    <Select v-else-if="isLookup(param)" :id="`tpl_${param.name}`" v-model="form[param.name]" class="mt-1 w-full" required>
                        <option value="">{{ t('discounts.pickValue') }}</option>
                        <option v-for="option in optionsFor(param)" :key="option.id" :value="option.id">{{ option.name }}</option>
                    </Select>
                    <TextInput
                        v-else
                        :id="`tpl_${param.name}`"
                        v-model="form[param.name]"
                        :type="inputType(param)"
                        :step="param.type === 'percent' || param.type === 'amount' ? '0.01' : undefined"
                        :placeholder="param.type === 'time' ? '16:00' : ''"
                        :dir="param.type === 'code' ? 'ltr' : undefined"
                        class="mt-1 block w-full"
                        :required="param.required"
                    />
                    <InputError :message="form.errors[param.name]" class="mt-2" />
                </div>
                <label class="flex items-center gap-2 text-sm text-fg">
                    <input v-model="form.is_active" type="checkbox" class="rounded border-line text-accent focus:ring-ring-accent" />
                    {{ t('discounts.activateNow') }}
                </label>
                <div class="flex justify-end">
                    <PrimaryButton :disabled="form.processing">{{ t('discounts.addTemplate') }}</PrimaryButton>
                </div>
            </form>
        </div>
    </Modal>
</template>
