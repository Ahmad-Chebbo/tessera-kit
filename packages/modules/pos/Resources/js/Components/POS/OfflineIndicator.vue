<script setup>
import { useI18n } from 'vue-i18n';
import { useOfflineQueue } from '@modules/Pos/Resources/js/Composables/useOfflineQueue';
import { LoaderCircle } from 'lucide-vue-next';

const { t } = useI18n();
const { isOnline, pendingCount, isSyncing, syncSales } = useOfflineQueue();
</script>

<template>
    <div class="flex items-center gap-2">
        <!-- Online/Offline Status -->
        <div
            class="flex items-center gap-1.5 rounded-full px-3 py-1 text-sm font-medium"
            :class="isOnline
                ? 'bg-status-success-wash text-status-success'
                : 'bg-status-danger-wash text-status-danger'"
        >
            <span
                class="h-2 w-2 rounded-full"
                :class="isOnline ? 'bg-status-success' : 'bg-danger'"
            ></span>
            {{ isOnline ? t('offline.online') : t('offline.status') }}
        </div>

        <!-- Pending Sales Badge -->
        <div
            v-if="pendingCount > 0"
            class="flex items-center gap-2"
        >
            <span class="rounded-full bg-status-warn-wash px-3 py-1 text-sm font-medium text-status-warn">
                {{ pendingCount }} {{ t('offline.pendingSales') }}
            </span>

            <!-- Sync Button -->
            <button
                v-if="isOnline"
                type="button"
                @click="syncSales"
                :disabled="isSyncing"
                class="rounded-lg bg-gold px-3 py-1 text-sm font-medium text-ink hover:bg-gold-deep disabled:opacity-50"
            >
                <span v-if="isSyncing" class="flex items-center gap-1">
                    <LoaderCircle class="h-4 w-4 animate-spin" />
                    {{ t('offline.syncing') }}
                </span>
                <span v-else>{{ t('offline.syncNow') }}</span>
            </button>
        </div>
    </div>
</template>
