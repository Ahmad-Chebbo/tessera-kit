<script setup>
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

defineProps({
    paginator: { type: Object, required: true },
    bordered: { type: Boolean, default: true },
});

const { t } = useI18n();
</script>

<template>
    <div v-if="paginator.links.length > 3" :class="bordered ? 'border-t border-line px-6 py-4' : 'pt-4'">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="text-sm text-muted">
                {{ t('common.showing') }} {{ paginator.from }} {{ t('common.to') }} {{ paginator.to }}
                {{ t('common.of') }} {{ paginator.total }}
            </div>
            <nav class="flex flex-wrap gap-2">
                <template v-for="link in paginator.links" :key="link.label">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        class="rounded-control px-3 py-2 text-sm transition-colors duration-150 ease-qayema"
                        :class="link.active ? 'bg-gold text-ink' : 'bg-hover-wash text-muted hover:bg-line'"
                        v-html="link.label"
                    />
                    <span
                        v-else
                        aria-disabled="true"
                        class="rounded-control bg-hover-wash px-3 py-2 text-sm text-faint"
                        v-html="link.label"
                    />
                </template>
            </nav>
        </div>
    </div>
</template>
