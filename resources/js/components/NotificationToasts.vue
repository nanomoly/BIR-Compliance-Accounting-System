<script setup lang="ts">
import { computed } from 'vue';
import { useNotifications } from '@/composables/useNotifications';

const { notifications, removeNotification } = useNotifications();

function toastClasses(type: 'success' | 'error' | 'info'): string {
    if (type === 'error') {
        return 'border-destructive/40 bg-background text-destructive';
    }

    if (type === 'success') {
        return 'border-primary/40 bg-background text-foreground';
    }

    return 'border-border bg-background text-foreground';
}

const visibleNotifications = computed(() => notifications);
</script>

<template>
    <div class="pointer-events-none fixed right-4 top-4 z-50 flex w-full max-w-sm flex-col gap-2">
        <TransitionGroup name="toast">
            <div
                v-for="item in visibleNotifications"
                :key="item.id"
                class="pointer-events-auto rounded-md border px-4 py-3 text-sm shadow-sm"
                :class="[toastClasses(item.type), item.visible ? 'opacity-100' : 'opacity-0']"
            >
                <div class="flex items-start justify-between gap-3">
                    <p class="leading-snug">{{ item.message }}</p>
                    <button
                        type="button"
                        class="text-xs text-muted-foreground hover:text-foreground"
                        @click="removeNotification(item.id)"
                    >
                        Close
                    </button>
                </div>
            </div>
        </TransitionGroup>
    </div>
</template>

<style scoped>
.toast-enter-active,
.toast-leave-active {
    transition: all 0.35s ease;
}

.toast-enter-from,
.toast-leave-to {
    opacity: 0;
    transform: translateY(-8px);
}
</style>
