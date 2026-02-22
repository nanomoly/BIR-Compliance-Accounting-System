import { watch } from 'vue';
import { useNotifications } from '@/composables/useNotifications';

export function useStateNotifications(state: { error?: string; success?: string }): void {
    const { notifyError, notifySuccess } = useNotifications();

    watch(
        () => state.error,
        (message) => {
            if (typeof message === 'string' && message.trim().length > 0) {
                notifyError(message);
            }
        },
    );

    watch(
        () => state.success,
        (message) => {
            if (typeof message === 'string' && message.trim().length > 0) {
                notifySuccess(message);
            }
        },
    );
}
