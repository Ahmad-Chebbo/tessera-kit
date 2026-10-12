<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { Lock } from 'lucide-vue-next';
import GuestLayout from '@/Layouts/GuestLayout.vue';

defineProps({
    plan: String,
    status: String,
});

const { t } = useI18n();
</script>

<template>
    <Head :title="t('plans.expiredTitle')" />

    <GuestLayout>
        <div class="space-y-4 text-center">
            <Lock class="mx-auto h-10 w-10 text-muted" />
            <h1 class="font-display text-2xl font-normal text-fg">{{ t('plans.expiredTitle') }}</h1>
            <p v-if="plan" class="text-sm text-muted">{{ plan }} · {{ status ? t(`plans.statuses.${status}`) : '' }}</p>
            <p class="text-sm text-muted">{{ t('plans.expiredText') }}</p>
            <p class="text-sm text-muted">{{ t('plans.contactAdmin') }}</p>
            <Link :href="route('logout')" method="post" as="button" class="text-sm text-accent hover:underline">{{ t('nav.logout') }}</Link>
        </div>
    </GuestLayout>
</template>
