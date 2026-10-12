<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { ArrowLeft } from 'lucide-vue-next';
import SidebarLayout from '@/Layouts/SidebarLayout.vue';
import InputLabel from '@ahmad-chebbo/tessera-ui/Components/InputLabel.vue';
import TextInput from '@ahmad-chebbo/tessera-ui/Components/TextInput.vue';
import InputError from '@ahmad-chebbo/tessera-ui/Components/InputError.vue';
import PrimaryButton from '@ahmad-chebbo/tessera-ui/Components/PrimaryButton.vue';
import Select from '@ahmad-chebbo/tessera-ui/Components/Select.vue';
import DatePicker from '@ahmad-chebbo/tessera-ui/Components/DatePicker.vue';
import Card from '@ahmad-chebbo/tessera-ui/Components/Card.vue';
import ConditionBuilder from '@modules/Discounts/Resources/js/Components/ConditionBuilder.vue';
import MultiPick from '@modules/Discounts/Resources/js/Components/MultiPick.vue';
import { blankGroup } from '@modules/Discounts/Resources/js/Components/conditionTree';

const props = defineProps({
    rule: Object,
    catalogue: Object,
});

const { t } = useI18n();

const rule = props.rule;
const form = useForm({
    name: rule?.name ?? '',
    description: rule?.description ?? '',
    is_active: rule?.is_active ?? true,
    priority: rule?.priority ?? 100,
    stackable: rule?.stackable ?? true,
    starts_at: rule?.starts_at ?? '',
    ends_at: rule?.ends_at ?? '',
    conditions: rule?.conditions ?? blankGroup(),
    expression: rule?.expression ?? '',
    action_type: rule?.action_type ?? 'percent',
    action_value: rule?.action_value ?? 10,
    action_config: {
        apply_to: rule?.action_config?.apply_to ?? 'each_line',
        cap_amount: rule?.action_config?.cap_amount ?? '',
        buy_quantity: rule?.action_config?.buy_quantity ?? '',
        get_quantity: rule?.action_config?.get_quantity ?? '',
        get_discount_percent: rule?.action_config?.get_discount_percent ?? 100,
        buy_product_ids: rule?.action_config?.buy_product_ids ?? [],
        get_product_ids: rule?.action_config?.get_product_ids ?? [],
        max_sets: rule?.action_config?.max_sets ?? '',
    },
    coupon_code: rule?.coupon_code ?? '',
    usage_limit: rule?.usage_limit ?? '',
    per_customer_limit: rule?.per_customer_limit ?? '',
});

const title = computed(() => (rule ? t('discounts.editRule') : t('discounts.addRule')));
const needsValue = computed(() => !['buy_x_get_y', 'buy_get_item'].includes(form.action_type));
const products = computed(() => props.catalogue.lookups['item.product_id'] ?? []);

const submit = () => {
    const empty = (value) => (value === '' ? null : value);

    form
        .transform((data) => ({
            ...data,
            starts_at: empty(data.starts_at),
            ends_at: empty(data.ends_at),
            usage_limit: empty(data.usage_limit),
            per_customer_limit: empty(data.per_customer_limit),
            action_value: ['buy_x_get_y', 'buy_get_item'].includes(data.action_type) ? 0 : data.action_value,
            action_config: Object.fromEntries(Object.entries(data.action_config).map(([key, value]) => [key, Array.isArray(value) ? (value.length ? value : null) : empty(value)])),
        }))
        [rule ? 'put' : 'post'](rule ? route('discounts.update', rule.id) : route('discounts.store'));
};
</script>

<template>
    <Head :title="title" />

    <SidebarLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('discounts.index')" class="text-muted hover:text-fg">
                    <ArrowLeft class="h-6 w-6 rtl:rotate-180" />
                </Link>
                <h1 class="font-display text-xl font-normal text-fg">{{ title }}</h1>
            </div>
        </template>

        <form class="mx-auto max-w-4xl space-y-6" @submit.prevent="submit">
            <Card>
                <div class="grid gap-6 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <InputLabel for="rule_name" :value="t('discounts.name')" />
                        <TextInput id="rule_name" v-model="form.name" type="text" class="mt-1 block w-full" required autofocus />
                        <InputError :message="form.errors.name" class="mt-2" />
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel for="rule_description" :value="t('discounts.description')" />
                        <TextInput id="rule_description" v-model="form.description" type="text" class="mt-1 block w-full" />
                        <InputError :message="form.errors.description" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel for="rule_priority" :value="t('discounts.priority')" />
                        <TextInput id="rule_priority" v-model="form.priority" type="number" class="mt-1 block w-full" required />
                        <p class="mt-1 text-xs text-muted">{{ t('discounts.priorityHint') }}</p>
                        <InputError :message="form.errors.priority" class="mt-2" />
                    </div>
                    <div class="space-y-3 self-end">
                        <label class="flex items-start gap-2 text-sm text-fg">
                            <input v-model="form.is_active" type="checkbox" class="mt-0.5 rounded border-line text-accent focus:ring-ring-accent" />
                            {{ t('discounts.active') }}
                        </label>
                        <label class="flex items-start gap-2 text-sm text-fg">
                            <input v-model="form.stackable" type="checkbox" class="mt-0.5 rounded border-line text-accent focus:ring-ring-accent" />
                            <span>
                                {{ t('discounts.stackable') }}
                                <span class="block text-xs text-muted">{{ t('discounts.stackableHint') }}</span>
                            </span>
                        </label>
                    </div>
                </div>
            </Card>

            <Card>
                <h2 class="mb-1 font-display text-lg font-normal text-fg">{{ t('discounts.conditions') }}</h2>
                <p class="mb-4 text-sm text-muted">{{ t('discounts.conditionsHint') }}</p>
                <ConditionBuilder v-model="form.conditions" :facts="catalogue.facts" :lookups="catalogue.lookups" />
                <InputError :message="form.errors.conditions" class="mt-2" />

                <div class="mt-6">
                    <InputLabel for="rule_expression" :value="t('discounts.expression')" />
                    <textarea
                        id="rule_expression"
                        v-model="form.expression"
                        rows="2"
                        dir="ltr"
                        class="mt-1 block w-full rounded-lg border border-line bg-field p-2.5 font-mono text-sm focus:border-gold-on focus:ring-ring-accent"
                    />
                    <p class="mt-1 text-xs text-muted">{{ t('discounts.expressionHint') }}</p>
                    <p class="mt-1 text-xs text-muted">{{ t('discounts.expressionFacts') }}</p>
                    <InputError :message="form.errors.expression" class="mt-2" />
                </div>
            </Card>

            <Card>
                <h2 class="mb-4 font-display text-lg font-normal text-fg">{{ t('discounts.action') }}</h2>
                <div class="grid gap-6 sm:grid-cols-2">
                    <div>
                        <InputLabel for="rule_action" :value="t('discounts.actionType')" />
                        <Select id="rule_action" v-model="form.action_type" class="mt-1 w-full">
                            <option v-for="action in catalogue.actions" :key="action.value" :value="action.value">{{ t(`discounts.${action.value}`) }}</option>
                        </Select>
                        <InputError :message="form.errors.action_type" class="mt-2" />
                    </div>
                    <div v-if="needsValue">
                        <InputLabel for="rule_value" :value="t('discounts.actionValue')" />
                        <TextInput id="rule_value" v-model="form.action_value" type="number" step="0.01" min="0" class="mt-1 block w-full" required />
                        <InputError :message="form.errors.action_value" class="mt-2" />
                    </div>

                    <div v-if="form.action_type === 'fixed'" class="sm:col-span-2">
                        <InputLabel for="rule_apply" :value="t('discounts.applyTo')" />
                        <Select id="rule_apply" v-model="form.action_config.apply_to" class="mt-1 w-full">
                            <option value="each_unit">{{ t('discounts.each_unit') }}</option>
                            <option value="each_line">{{ t('discounts.each_line') }}</option>
                            <option value="total">{{ t('discounts.total') }}</option>
                        </Select>
                    </div>

                    <template v-if="form.action_type === 'buy_get_item'">
                        <div class="sm:col-span-2">
                            <InputLabel for="rule_buy_products" :value="t('discounts.buyProducts')" />
                            <MultiPick id="rule_buy_products" v-model="form.action_config.buy_product_ids" class="mt-1" :options="products" :placeholder="t('discounts.add')" />
                            <InputError :message="form.errors['action_config.buy_product_ids']" class="mt-2" />
                        </div>
                        <div>
                            <InputLabel for="rule_buy_item_qty" :value="t('discounts.buyQuantity')" />
                            <TextInput id="rule_buy_item_qty" v-model="form.action_config.buy_quantity" type="number" min="1" class="mt-1 block w-full" />
                            <InputError :message="form.errors['action_config.buy_quantity']" class="mt-2" />
                        </div>
                        <div class="sm:col-span-2">
                            <InputLabel for="rule_get_products" :value="t('discounts.getProducts')" />
                            <MultiPick id="rule_get_products" v-model="form.action_config.get_product_ids" class="mt-1" :options="products" :placeholder="t('discounts.add')" />
                            <InputError :message="form.errors['action_config.get_product_ids']" class="mt-2" />
                        </div>
                        <div>
                            <InputLabel for="rule_get_item_qty" :value="t('discounts.getQuantity')" />
                            <TextInput id="rule_get_item_qty" v-model="form.action_config.get_quantity" type="number" min="1" class="mt-1 block w-full" />
                            <InputError :message="form.errors['action_config.get_quantity']" class="mt-2" />
                        </div>
                        <div>
                            <InputLabel for="rule_get_item_percent" :value="t('discounts.getPercent')" />
                            <TextInput id="rule_get_item_percent" v-model="form.action_config.get_discount_percent" type="number" min="0" max="100" class="mt-1 block w-full" />
                        </div>
                        <div>
                            <InputLabel for="rule_max_sets" :value="t('discounts.maxSets')" />
                            <TextInput id="rule_max_sets" v-model="form.action_config.max_sets" type="number" min="1" class="mt-1 block w-full" />
                            <InputError :message="form.errors['action_config.max_sets']" class="mt-2" />
                        </div>
                    </template>

                    <template v-if="form.action_type === 'buy_x_get_y'">
                        <div>
                            <InputLabel for="rule_buy" :value="t('discounts.buyQuantity')" />
                            <TextInput id="rule_buy" v-model="form.action_config.buy_quantity" type="number" min="1" class="mt-1 block w-full" />
                            <InputError :message="form.errors['action_config.buy_quantity']" class="mt-2" />
                        </div>
                        <div>
                            <InputLabel for="rule_get" :value="t('discounts.getQuantity')" />
                            <TextInput id="rule_get" v-model="form.action_config.get_quantity" type="number" min="1" class="mt-1 block w-full" />
                            <InputError :message="form.errors['action_config.get_quantity']" class="mt-2" />
                        </div>
                        <div>
                            <InputLabel for="rule_get_percent" :value="t('discounts.getPercent')" />
                            <TextInput id="rule_get_percent" v-model="form.action_config.get_discount_percent" type="number" min="0" max="100" class="mt-1 block w-full" />
                        </div>
                    </template>

                    <div>
                        <InputLabel for="rule_cap" :value="t('discounts.cap')" />
                        <TextInput id="rule_cap" v-model="form.action_config.cap_amount" type="number" step="0.01" min="0" class="mt-1 block w-full" />
                        <InputError :message="form.errors['action_config.cap_amount']" class="mt-2" />
                    </div>
                </div>
            </Card>

            <Card>
                <h2 class="mb-1 font-display text-lg font-normal text-fg">{{ t('discounts.schedule') }}</h2>
                <p class="mb-4 text-sm text-muted">{{ t('discounts.scheduleHint') }}</p>
                <div class="grid gap-6 sm:grid-cols-2">
                    <div>
                        <InputLabel for="rule_starts" :value="t('discounts.startsAt')" />
                        <DatePicker id="rule_starts" v-model="form.starts_at" with-time class="mt-1 w-full" />
                        <InputError :message="form.errors.starts_at" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel for="rule_ends" :value="t('discounts.endsAt')" />
                        <DatePicker id="rule_ends" v-model="form.ends_at" with-time class="mt-1 w-full" />
                        <InputError :message="form.errors.ends_at" class="mt-2" />
                    </div>
                </div>
            </Card>

            <Card>
                <h2 class="mb-1 font-display text-lg font-normal text-fg">{{ t('discounts.coupon') }}</h2>
                <p class="mb-4 text-sm text-muted">{{ t('discounts.couponHint') }}</p>
                <div class="grid gap-6 sm:grid-cols-3">
                    <div>
                        <InputLabel for="rule_coupon" :value="t('discounts.couponCode')" />
                        <TextInput id="rule_coupon" v-model="form.coupon_code" type="text" maxlength="50" dir="ltr" class="mt-1 block w-full uppercase" />
                        <InputError :message="form.errors.coupon_code" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel for="rule_usage" :value="t('discounts.usageLimit')" />
                        <TextInput id="rule_usage" v-model="form.usage_limit" type="number" min="1" class="mt-1 block w-full" />
                        <InputError :message="form.errors.usage_limit" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel for="rule_customer_limit" :value="t('discounts.perCustomer')" />
                        <TextInput id="rule_customer_limit" v-model="form.per_customer_limit" type="number" min="1" class="mt-1 block w-full" />
                        <InputError :message="form.errors.per_customer_limit" class="mt-2" />
                    </div>
                </div>
            </Card>

            <div class="flex justify-end gap-4">
                <Link :href="route('discounts.index')" class="rounded-lg bg-hover-wash px-4 py-2.5 text-sm font-medium text-muted hover:bg-line">{{ t('common.cancel') }}</Link>
                <PrimaryButton :disabled="form.processing">{{ t('common.save') }}</PrimaryButton>
            </div>
        </form>
    </SidebarLayout>
</template>
