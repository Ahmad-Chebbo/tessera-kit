<script setup>
import { Moon, Sun } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import { useTheme } from '../Composables/useTheme.js';

const { t } = useI18n();
const { theme, toggleTheme } = useTheme();
</script>

<template>
    <button
        type="button"
        :aria-label="t('nav.toggleTheme')"
        :title="t('nav.toggleTheme')"
        class="inline-flex h-9 w-9 items-center justify-center rounded-control text-muted transition duration-150 ease-qayema hover:bg-hover-wash hover:text-fg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gold-on"
        @click="toggleTheme"
    >
        <Sun v-if="theme === 'dark'" class="h-4 w-4" />
        <Moon v-else class="h-4 w-4" />
    </button>
</template>
