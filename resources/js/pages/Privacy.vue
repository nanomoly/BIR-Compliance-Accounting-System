<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import TextLink from '@/components/TextLink.vue';
</script>

<template>
    <Head title="Privacy Policy" />

    <main class="mx-auto w-full max-w-3xl px-6 py-10">
        <h1 class="text-2xl font-semibold">Privacy Policy</h1>
        <p class="mt-2 text-sm text-muted-foreground">
            This system is for authorized internal use only. Personal and financial
            information is processed for accounting, compliance, audit, and reporting
            under company policy and applicable Philippine regulations.
        </p>

        <div class="mt-6 space-y-4 text-sm leading-6 text-muted-foreground">
            <p>
                Access to user data, accounting records, and audit information is limited
                to approved personnel based on assigned roles and permissions.
            </p>
            <p>
                Activities inside this platform may be logged for security, compliance,
                and audit trail purposes.
            </p>
            <p>
                For data privacy requests or concerns, contact your internal compliance
                or data protection officer.
            </p>
        </div>

        <div class="mt-8">
            <TextLink href="/login">Back to Login</TextLink>
        </div>
    </main>
</template>
