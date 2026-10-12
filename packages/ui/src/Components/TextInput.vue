<script setup>
import { onMounted, ref } from 'vue';

const model = defineModel({
    type: String,
    required: true,
});

const input = ref(null);

onMounted(() => {
    if (input.value.hasAttribute('autofocus')) {
        input.value.focus();
    }
});

defineExpose({ focus: () => input.value.focus() });
</script>

<template>
    <input
        class="rounded-field border border-line bg-field p-3 text-sm text-fg shadow-none transition duration-150 ease-qayema placeholder:text-placeholder focus:border-gold-on focus:outline-hidden focus:ring-2 focus:ring-ring-accent disabled:bg-disabled-wash"
        v-model="model"
        ref="input"
    />
</template>
