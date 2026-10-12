<script setup>
import { Languages } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import { setLocale } from '../i18n.js';

const props = defineProps({
    showLabel: {
        type: Boolean,
        default: true,
    },
});

const { locale } = useI18n();

function toggleLanguage() {
    const newLocale = locale.value === 'en' ? 'ar' : 'en';
    setLocale(newLocale);
}
</script>

<template>
    <button
        @click="toggleLanguage"
        type="button"
        class="inline-flex h-9 items-center justify-center gap-1.5 rounded-control px-3 text-sm font-medium text-muted transition duration-150 ease-qayema hover:bg-hover-wash hover:text-fg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gold-on"
    >
        <Languages class="h-4 w-4" />
        <span v-if="showLabel" class="font-semibold">{{ locale === 'en' ? 'عربي' : 'EN' }}</span>
    </button>
</template>
