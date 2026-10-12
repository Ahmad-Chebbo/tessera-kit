<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import LanguageToggle from '@tessera/tessera-ui/Components/LanguageToggle.vue';
import ThemeToggle from '@tessera/tessera-ui/Components/ThemeToggle.vue';

defineProps({
    canLogin: {
        type: Boolean,
    },
    canRegister: {
        type: Boolean,
    },
    laravelVersion: {
        type: String,
        required: true,
    },
    phpVersion: {
        type: String,
        required: true,
    },
});

const { t } = useI18n();
</script>

<template>
    <Head title="Welcome" />

    <div class="relative flex min-h-screen flex-col items-center justify-center bg-bg px-6 text-center">
        <div class="absolute end-4 top-4 flex items-center gap-1">
            <LanguageToggle />
            <ThemeToggle />
        </div>

        <span class="font-display text-3xl font-normal uppercase tracking-[0.3em] text-accent">Tessera</span>
        <p class="mt-4 max-w-md text-muted">
            Point of sale, inventory and reports in one place.
        </p>

        <div class="mt-8 flex items-center gap-3">
            <Link
                v-if="canLogin"
                :href="route('login')"
                class="inline-flex items-center justify-center gap-2 rounded-control bg-gold px-5 py-2.5 text-sm font-semibold text-ink transition duration-150 ease-qayema hover:bg-gold-deep"
            >
                {{ t('auth.login') }}
                <ArrowRight class="h-4 w-4 rtl:rotate-180" />
            </Link>
            <Link
                v-if="canRegister"
                :href="route('register')"
                class="inline-flex items-center justify-center gap-2 rounded-control border border-line-strong px-5 py-2.5 text-sm font-semibold text-fg transition duration-150 ease-qayema hover:bg-hover-wash"
            >
                {{ t('auth.register') }}
            </Link>
        </div>

        <p class="mt-12 text-xs text-faint">Laravel v{{ laravelVersion }} (PHP v{{ phpVersion }})</p>
    </div>
</template>
