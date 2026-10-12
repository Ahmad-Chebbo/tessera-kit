<script setup>
import { useI18n } from 'vue-i18n';
import InputLabel from '@tessera/tessera-ui/Components/InputLabel.vue';
import TextInput from '@tessera/tessera-ui/Components/TextInput.vue';
import InputError from '@tessera/tessera-ui/Components/InputError.vue';
import DatePicker from '@tessera/tessera-ui/Components/DatePicker.vue';

defineProps({
    form: { type: Object, required: true },
});

const { t } = useI18n();

const textFields = [
    { key: 'employee_number', label: 'employeeNumber', hint: 'numberHint' },
    { key: 'job_title', label: 'jobTitle' },
    { key: 'phone', label: 'phone' },
    { key: 'national_id', label: 'nationalId' },
    { key: 'emergency_contact_name', label: 'emergencyContactName' },
    { key: 'emergency_contact_phone', label: 'emergencyContactPhone' },
];
</script>

<template>
    <div class="space-y-6 border-t border-line pt-6">
        <h2 class="font-display text-lg font-normal text-fg">{{ t('employees.profileSection') }}</h2>

        <div class="grid gap-6 sm:grid-cols-2">
            <div v-for="field in textFields" :key="field.key">
                <InputLabel :for="field.key" :value="t(`employees.${field.label}`)" />
                <TextInput
                    :id="field.key"
                    v-model="form[field.key]"
                    type="text"
                    class="mt-1 block w-full"
                    :placeholder="field.hint ? t(`employees.${field.hint}`) : ''"
                />
                <InputError :message="form.errors[field.key]" class="mt-2" />
            </div>

            <div>
                <InputLabel for="hire_date" :value="t('employees.hireDate')" />
                <DatePicker id="hire_date" v-model="form.hire_date" class="mt-1 w-full" />
                <InputError :message="form.errors.hire_date" class="mt-2" />
            </div>

            <div>
                <InputLabel for="birth_date" :value="t('employees.birthDate')" />
                <DatePicker id="birth_date" v-model="form.birth_date" class="mt-1 w-full" />
                <InputError :message="form.errors.birth_date" class="mt-2" />
            </div>
        </div>

        <div>
            <InputLabel for="address" :value="t('employees.address')" />
            <textarea
                id="address"
                v-model="form.address"
                rows="2"
                class="mt-1 block w-full rounded-lg border border-line bg-field p-2.5 text-sm focus:border-gold-on focus:ring-ring-accent"
            />
            <InputError :message="form.errors.address" class="mt-2" />
        </div>

        <div>
            <InputLabel for="notes" :value="t('employees.notes')" />
            <textarea
                id="notes"
                v-model="form.notes"
                rows="3"
                class="mt-1 block w-full rounded-lg border border-line bg-field p-2.5 text-sm focus:border-gold-on focus:ring-ring-accent"
            />
            <InputError :message="form.errors.notes" class="mt-2" />
        </div>
    </div>
</template>
