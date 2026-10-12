<script setup>
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { Check, Lock } from 'lucide-vue-next';
import SidebarLayout from '@/Layouts/SidebarLayout.vue';
import Card from '@tessera/tessera-ui/Components/Card.vue';
import StatusBadge from '@tessera/tessera-ui/Components/StatusBadge.vue';

defineProps({
    subscription: Object,
    modules: Array,
    usage: Object,
});

const { t } = useI18n();

const tone = (status) => ({ active: 'success', trialing: 'info', past_due: 'warn', canceled: 'neutral', expired: 'danger' })[status] ?? 'neutral';
const formatDate = (value) => (value ? new Date(value).toLocaleDateString() : null);
const limitLabel = (entry) => `${entry.used} / ${entry.limit ?? t('plans.unlimited')}`;
</script>

<template>
    <Head :title="t('plans.subscriptionNav')" />

    <SidebarLayout>
        <template #header>
            <div>
                <h1 class="font-display text-xl font-normal text-fg">{{ t('plans.subscriptionNav') }}</h1>
                <p class="text-sm text-muted">{{ t('plans.contactAdmin') }}</p>
            </div>
        </template>

        <p v-if="!subscription" class="rounded-2xl border border-line bg-surface p-8 text-center text-muted">{{ t('plans.noSubscription') }}</p>

        <div v-else class="grid gap-6 lg:grid-cols-3">
            <Card class="lg:col-span-2">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-xs text-muted">{{ t('plans.yourPlan') }}</p>
                        <h2 class="font-display text-2xl font-normal text-fg">{{ subscription.plan.name }}</h2>
                        <p v-if="subscription.plan.description" class="mt-1 text-sm text-muted">{{ subscription.plan.description }}</p>
                    </div>
                    <StatusBadge :status="tone(subscription.status)">{{ t(`plans.statuses.${subscription.status}`) }}</StatusBadge>
                </div>
                <p class="mt-4 text-sm text-muted">
                    <template v-if="subscription.status === 'trialing' && subscription.trial_ends_at">{{ t('plans.trialLeft', { date: formatDate(subscription.trial_ends_at) }) }}</template>
                    <template v-else-if="subscription.ends_at">{{ t('plans.renews', { date: formatDate(subscription.ends_at) }) }}</template>
                </p>

                <h3 class="mb-3 mt-6 font-display text-lg font-normal text-fg">{{ t('plans.modules') }}</h3>
                <ul class="grid gap-2 sm:grid-cols-2">
                    <li v-for="module in modules" :key="module.value" class="flex items-center gap-2 text-sm" :class="module.enabled ? 'text-fg' : 'text-muted'">
                        <component :is="module.enabled ? Check : Lock" class="h-4 w-4 shrink-0" :class="module.enabled ? 'text-status-success' : 'text-muted'" />
                        {{ t(`plans.modulesNames.${module.value}`, module.label) }}
                        <span v-if="!module.enabled" class="text-xs">({{ t('plans.notIncluded') }})</span>
                    </li>
                </ul>
            </Card>

            <Card>
                <h2 class="mb-4 font-display text-lg font-normal text-fg">{{ t('plans.usage') }}</h2>
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted">{{ t('plans.employees') }}</dt>
                        <dd class="text-fg">{{ limitLabel(usage.users) }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted">{{ t('plans.branches') }}</dt>
                        <dd class="text-fg">{{ limitLabel(usage.branches) }}</dd>
                    </div>
                </dl>
            </Card>
        </div>
    </SidebarLayout>
</template>
