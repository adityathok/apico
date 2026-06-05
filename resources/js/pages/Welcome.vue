<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes';
import { store } from '@/routes/login';
import { Form, Head, Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
</script>

<template>
    <Head title="Login" />

    <main
        class="flex min-h-screen items-center justify-center bg-[url('https://images.unsplash.com/photo-1743742268741-1cca41270b84?q=80&w=2400&auto=format&fit=crop')] px-6 py-10"
    >
        <section class="w-full max-w-md">
            <div class="mb-8 text-center">
                <AppLogoIcon class="size-15 mx-auto fill-current text-red-500" />
                <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400 mt-2">
                    Welcome back
                </p>
                <h1 class="mt-2 text-3xl font-semibold tracking-normal">
                    Sign in
                </h1>
            </div>

            <div
                class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
            >
                <Link
                    v-if="$page.props.auth.user"
                    :href="dashboard()"
                    class="inline-flex h-10 w-full items-center justify-center rounded-md bg-zinc-900 px-4 text-sm font-medium text-white transition-colors hover:bg-zinc-800 dark:bg-zinc-50 dark:text-zinc-950 dark:hover:bg-zinc-200"
                >
                    Open Dashboard
                </Link>

                <Form
                    v-else
                    v-bind="store.form()"
                    :reset-on-success="['password']"
                    v-slot="{ errors, processing }"
                    class="grid gap-6"
                >
                    <div class="grid gap-2">
                        <Label for="email">Email address</Label>
                        <Input
                            id="email"
                            type="email"
                            name="email"
                            required
                            autofocus
                            autocomplete="email"
                            placeholder="email@example.com"
                        />
                        <InputError :message="errors.email" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="password">Password</Label>
                        <PasswordInput
                            id="password"
                            name="password"
                            required
                            autocomplete="current-password"
                            placeholder="Password"
                        />
                        <InputError :message="errors.password" />
                    </div>

                    <Label for="remember" class="flex items-center gap-3">
                        <Checkbox id="remember" name="remember" />
                        <span>Remember me</span>
                    </Label>

                    <Button
                        type="submit"
                        class="w-full"
                        :disabled="processing"
                        data-test="login-button"
                    >
                        <Spinner v-if="processing" />
                        Log in
                    </Button>
                </Form>
            </div>
        </section>
    </main>
</template>
