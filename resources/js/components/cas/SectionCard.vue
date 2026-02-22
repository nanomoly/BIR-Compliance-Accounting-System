<script setup lang="ts">
defineProps<{
    title: string;
    description?: string;
}>();
</script>

<template>
    <section class="rounded-xl border border-sidebar-border/70 bg-background p-4">
        <div class="mb-3">
            <h3 class="text-sm font-semibold tracking-wide">{{ title }}</h3>
            <p v-if="description" class="text-xs text-muted-foreground">
                {{ description }}
            </p>
        </div>
        <slot />
    </section>
</template>
