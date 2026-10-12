<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { ArrowLeft } from 'lucide-vue-next';
import SidebarLayout from '@/Layouts/SidebarLayout.vue';
import InputLabel from '@tessera/tessera-ui/Components/InputLabel.vue';
import TextInput from '@tessera/tessera-ui/Components/TextInput.vue';
import InputError from '@tessera/tessera-ui/Components/InputError.vue';
import PrimaryButton from '@tessera/tessera-ui/Components/PrimaryButton.vue';
import Card from '@tessera/tessera-ui/Components/Card.vue';

const props = defineProps({
    role: Object,
    groups: Array,
});

const { t } = useI18n();

const form = useForm({
    name: props.role?.name ?? '',
    permissions: props.role?.permissions ? [...props.role.permissions] : [],
});

const grantable = (group) => group.permissions.filter((permission) => permission.grantable).map((permission) => permission.name);

const setGroup = (group, on) => {
    const names = grantable(group);
    const rest = form.permissions.filter((name) => !names.includes(name));

    form.permissions = on ? [...rest, ...names] : rest;
};

const heldPermission = (name) => form.permissions.includes(name);

const toggle = (name) => {
    form.permissions = heldPermission(name) ? form.permissions.filter((value) => value !== name) : [...form.permissions, name];
};

const submit = () => {
    const payload = (data) => ({ ...data, permissions: data.permissions.filter((name) => props.groups.some((group) => group.permissions.some((permission) => permission.name === name && permission.grantable))) });

    if (props.role) {
        form.transform(payload).put(route('roles.update', props.role.id));
    } else {
        form.transform(payload).post(route('roles.store'));
    }
};

const title = computed(() => (props.role ? t('roles.editRole') : t('roles.addRole')));
</script>

<template>
    <Head :title="title" />

    <SidebarLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('roles.index')" class="text-muted hover:text-fg">
                    <ArrowLeft class="h-6 w-6 rtl:rotate-180" />
                </Link>
                <h1 class="font-display text-xl font-normal text-fg">{{ title }}</h1>
            </div>
        </template>

        <form class="mx-auto max-w-4xl space-y-6" @submit.prevent="submit">
            <Card>
                <InputLabel for="role_name" :value="t('roles.name')" />
                <TextInput id="role_name" v-model="form.name" type="text" class="mt-1 block w-full max-w-md" required autofocus />
                <InputError :message="form.errors.name" class="mt-2" />
            </Card>

            <p class="text-sm text-muted">{{ t('roles.permissionsHint') }}</p>
            <InputError :message="form.errors.permissions" />

            <div class="grid gap-4 md:grid-cols-2">
                <Card v-for="group in groups" :key="group.key">
                    <div class="mb-3 flex items-center justify-between gap-2">
                        <h2 class="font-display text-lg font-normal text-fg">{{ t(`roles.groups.${group.key}`) }}</h2>
                        <div class="flex gap-2 text-xs text-muted">
                            <button type="button" class="hover:text-fg" @click="setGroup(group, true)">{{ t('roles.selectAll') }}</button>
                            <button type="button" class="hover:text-fg" @click="setGroup(group, false)">{{ t('roles.clear') }}</button>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <label
                            v-for="permission in group.permissions"
                            :key="permission.name"
                            class="flex items-center gap-2 text-sm"
                            :class="permission.grantable ? 'text-fg' : 'text-muted opacity-60'"
                        >
                            <input
                                type="checkbox"
                                class="rounded border-line text-accent focus:ring-ring-accent"
                                :checked="heldPermission(permission.name)"
                                :disabled="!permission.grantable"
                                @change="toggle(permission.name)"
                            />
                            {{ permission.name }}
                        </label>
                    </div>
                </Card>
            </div>

            <div class="flex items-center justify-end gap-4">
                <Link :href="route('roles.index')" class="rounded-lg bg-hover-wash px-4 py-2.5 text-sm font-medium text-muted hover:bg-line">
                    {{ t('common.cancel') }}
                </Link>
                <PrimaryButton :disabled="form.processing">{{ t('common.save') }}</PrimaryButton>
            </div>
        </form>
    </SidebarLayout>
</template>
