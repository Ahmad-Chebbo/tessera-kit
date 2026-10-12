<script setup>
import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { LogIn, LogOut } from 'lucide-vue-next';

const { t } = useI18n();
const page = usePage();

const record = computed(() => page.props.attendance);
const canRecord = computed(() => page.props.auth?.permissions?.includes('record attendance'));

const since = computed(() =>
    record.value ? new Date(record.value.check_in_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '',
);

const toggle = () => {
    router.post(route(record.value ? 'attendance.check-out' : 'attendance.check-in'), {}, { preserveScroll: true });
};
</script>

<template>
    <button
        v-if="canRecord"
        type="button"
        class="hidden items-center gap-2 rounded-full border px-3 py-1.5 text-sm transition-colors duration-150 ease-qayema md:flex"
        :class="record
            ? 'border-status-success-border bg-status-success-wash text-status-success'
            : 'border-line bg-surface text-muted hover:bg-hover-wash'"
        :title="record ? t('attendance.checkedInSince', { time: since }) : t('attendance.notCheckedIn')"
        @click="toggle"
    >
        <component :is="record ? LogOut : LogIn" class="h-4 w-4" />
        {{ record ? t('attendance.checkOut') : t('attendance.checkIn') }}
    </button>
</template>
