<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import TextLink from '@/components/TextLink.vue';
</script>

<template>
    <Head title="Terms and Conditions" />

    <main class="mx-auto w-full max-w-3xl px-6 py-10">
        <h1 class="text-2xl font-semibold">Terms and Conditions</h1>
        <p class="mt-2 text-sm text-muted-foreground">
            By using this system, you agree to follow company policies, security
            controls, and accounting compliance procedures.
        </p>

        <div class="mt-6 space-y-4 text-sm leading-6 text-muted-foreground">
            <p>
                Account access is restricted to authorized users. Credential sharing,
                unauthorized modifications, and bypassing controls are prohibited.
            </p>
            <p>
                All transactions may be monitored and retained as part of the official
                accounting and audit records.
            </p>
            <p>
                Management may suspend or revoke access for policy violations or
                compliance-related requirements.
            </p>
        </div>

        <div class="mt-8">
            <TextLink href="/login">Back to Login</TextLink>
        </div>
    </main>
</template>
