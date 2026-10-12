<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import LanguageToggle from '@tessera/tessera-ui/Components/LanguageToggle.vue';
import ThemeToggle from '@tessera/tessera-ui/Components/ThemeToggle.vue';
import { Link } from '@inertiajs/vue3';
import ToastContainer from '@tessera/tessera-ui/Components/ToastContainer.vue';
import ConfirmDialog from '@tessera/tessera-ui/Components/ConfirmDialog.vue';
</script>

<template>
    <div class="relative flex min-h-screen flex-col items-center justify-center bg-bg px-4 py-10">
        <div class="absolute end-4 top-4 flex items-center gap-1">
            <LanguageToggle />
            <ThemeToggle />
        </div>

        <Link href="/" class="mb-6 flex flex-col items-center gap-3">
            <ApplicationLogo class="h-14 w-14 fill-current text-accent" />
            <span class="font-display text-xl font-normal uppercase tracking-[0.3em] text-accent">Tessera</span>
        </Link>

        <div class="w-full max-w-md rounded-2xl border border-line bg-surface px-6 py-6 shadow-lift">
            <slot />
        </div>
        <ToastContainer />
        <ConfirmDialog />
    </div>
</template>
