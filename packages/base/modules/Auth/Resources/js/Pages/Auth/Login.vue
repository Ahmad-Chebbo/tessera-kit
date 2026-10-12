<script setup>
import Checkbox from '@tessera/tessera-ui/Components/Checkbox.vue';
import InputError from '@tessera/tessera-ui/Components/InputError.vue';
import InputLabel from '@tessera/tessera-ui/Components/InputLabel.vue';
import PrimaryButton from '@tessera/tessera-ui/Components/PrimaryButton.vue';
import TextInput from '@tessera/tessera-ui/Components/TextInput.vue';
import LanguageToggle from '@tessera/tessera-ui/Components/LanguageToggle.vue';
import ThemeToggle from '@tessera/tessera-ui/Components/ThemeToggle.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { AtSign, ChartColumn, CircleDollarSign, ClipboardList, LoaderCircle, Lock, Zap } from 'lucide-vue-next';
import ToastContainer from '@tessera/tessera-ui/Components/ToastContainer.vue';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const { t } = useI18n();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head :title="t('auth.login')" />

    <div class="flex min-h-screen">
        <ToastContainer />
        <!-- Left Side - Branding -->
        <div class="hidden lg:flex lg:w-1/2 border-e border-line bg-surface relative overflow-hidden">
            <!-- Background Pattern -->
            <div class="absolute inset-0 text-fg opacity-5">
                <svg class="h-full w-full" viewBox="0 0 100 100" preserveAspectRatio="none">
                    <defs>
                        <pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse">
                            <path d="M 10 0 L 0 0 0 10" fill="none" stroke="currentColor" stroke-width="0.5"/>
                        </pattern>
                    </defs>
                    <rect width="100" height="100" fill="url(#grid)" />
                </svg>
            </div>

            <!-- Floating shapes -->
            <div class="absolute top-20 start-20 w-72 h-72 bg-accent-wash rounded-full blur-3xl"></div>
            <div class="absolute bottom-20 end-20 w-96 h-96 bg-accent-wash rounded-full blur-3xl"></div>

            <!-- Content -->
            <div class="relative z-10 flex flex-col justify-center px-12 xl:px-20">
                <div class="mb-8">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="w-12 h-12 bg-accent-wash rounded-xl flex items-center justify-center">
                            <Zap class="w-7 h-7 text-accent" />
                        </div>
                        <span class="font-display text-2xl font-normal uppercase tracking-[0.3em] text-accent">Tessera</span>
                    </div>
                    <h1 class="font-display text-4xl xl:text-5xl font-normal text-fg leading-tight mb-4">
                        {{ t('auth.welcome') }}
                    </h1>
                    <p class="text-lg text-muted leading-relaxed max-w-md">
                        Streamline your sales with our powerful point-of-sale system. Manage inventory, track orders, and grow your business.
                    </p>
                </div>

                <!-- Features -->
                <div class="space-y-4 mt-8">
                    <div class="flex items-center gap-3 text-muted">
                        <div class="w-10 h-10 bg-hover-wash text-accent rounded-lg flex items-center justify-center">
                            <ClipboardList class="w-5 h-5" />
                        </div>
                        <span>Real-time inventory tracking</span>
                    </div>
                    <div class="flex items-center gap-3 text-muted">
                        <div class="w-10 h-10 bg-hover-wash text-accent rounded-lg flex items-center justify-center">
                            <ChartColumn class="w-5 h-5" />
                        </div>
                        <span>Comprehensive sales reports</span>
                    </div>
                    <div class="flex items-center gap-3 text-muted">
                        <div class="w-10 h-10 bg-hover-wash text-accent rounded-lg flex items-center justify-center">
                            <CircleDollarSign class="w-5 h-5" />
                        </div>
                        <span>Multi-currency support</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Side - Login Form -->
        <div class="relative w-full lg:w-1/2 flex items-center justify-center bg-bg px-6 py-12">
            <div class="absolute end-4 top-4 flex items-center gap-1">
                <LanguageToggle />
                <ThemeToggle />
            </div>
            <div class="w-full max-w-md">
                <!-- Mobile Logo -->
                <div class="lg:hidden flex items-center justify-center gap-3 mb-8">
                    <div class="w-10 h-10 bg-gold rounded-xl flex items-center justify-center">
                        <Zap class="w-6 h-6 text-ink" />
                    </div>
                    <span class="font-display text-xl font-normal text-fg">Tessera</span>
                </div>

                <!-- Form Header -->
                <div class="text-center mb-8">
                    <h2 class="font-display text-2xl font-normal text-fg">
                        {{ t('auth.login') }}
                    </h2>
                    <p class="mt-2 text-sm text-muted">
                        Enter your credentials to access your account
                    </p>
                </div>

                <!-- Status Message -->
                <div v-if="status" class="mb-6 rounded-lg bg-status-success-wash p-4 text-sm font-medium text-status-success">
                    {{ status }}
                </div>

                <!-- Login Form -->
                <form @submit.prevent="submit" class="space-y-6">
                    <div>
                        <InputLabel for="email" :value="t('auth.email')" class="text-muted" />
                        <div class="relative mt-1">
                            <div class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3">
                                <AtSign class="h-5 w-5 text-faint" />
                            </div>
                            <TextInput
                                id="email"
                                type="email"
                                class="block w-full ps-10"
                                v-model="form.email"
                                required
                                autofocus
                                autocomplete="username"
                                placeholder="you@example.com"
                            />
                        </div>
                        <InputError class="mt-2" :message="form.errors.email" />
                    </div>

                    <div>
                        <InputLabel for="password" :value="t('auth.password')" class="text-muted" />
                        <div class="relative mt-1">
                            <div class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3">
                                <Lock class="h-5 w-5 text-faint" />
                            </div>
                            <TextInput
                                id="password"
                                type="password"
                                class="block w-full ps-10"
                                v-model="form.password"
                                required
                                autocomplete="current-password"
                                placeholder="Enter your password"
                            />
                        </div>
                        <InputError class="mt-2" :message="form.errors.password" />
                    </div>

                    <div class="flex items-center justify-between">
                        <label class="flex items-center">
                            <Checkbox name="remember" v-model:checked="form.remember" />
                            <span class="ms-2 text-sm text-muted">
                                {{ t('auth.rememberMe') }}
                            </span>
                        </label>

                        <Link
                            v-if="canResetPassword"
                            :href="route('password.request')"
                            class="text-sm font-medium text-accent hover:underline"
                        >
                            {{ t('auth.forgotPassword') }}
                        </Link>
                    </div>

                    <PrimaryButton
                        class="w-full justify-center py-3 text-base font-semibold"
                        :class="{ 'opacity-50 cursor-not-allowed': form.processing }"
                        :disabled="form.processing"
                    >
                        <LoaderCircle v-if="form.processing" class="me-2 h-5 w-5 animate-spin" />
                        {{ form.processing ? t('common.loading') : t('auth.login') }}
                    </PrimaryButton>
                </form>

                <!-- Footer -->
                <p class="mt-8 text-center text-xs text-muted">
                    &copy; {{ new Date().getFullYear() }} Tessera. All rights reserved.
                </p>
            </div>
        </div>
    </div>
</template>
