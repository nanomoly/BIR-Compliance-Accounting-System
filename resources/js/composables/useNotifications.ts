import { reactive } from 'vue';

export type NotificationType = 'success' | 'error' | 'info';

export type AppNotification = {
    id: number;
    type: NotificationType;
    message: string;
    visible: boolean;
};

const notifications = reactive<AppNotification[]>([]);

let notificationCounter = 0;
const displayDuration = 2800;
const fadeDuration = 350;

function addNotification(type: NotificationType, message: string): void {
    const normalizedMessage = message.trim();

    if (normalizedMessage.length === 0) {
        return;
    }

    const entry: AppNotification = {
        id: ++notificationCounter,
        type,
        message: normalizedMessage,
        visible: true,
    };

    notifications.push(entry);

    window.setTimeout(() => {
        entry.visible = false;

        window.setTimeout(() => {
            const index = notifications.findIndex((item) => item.id === entry.id);
            if (index >= 0) {
                notifications.splice(index, 1);
            }
        }, fadeDuration);
    }, displayDuration);
}

function removeNotification(id: number): void {
    const index = notifications.findIndex((item) => item.id === id);

    if (index >= 0) {
        notifications.splice(index, 1);
    }
}

export function useNotifications() {
    return {
        notifications,
        notifySuccess: (message: string) => addNotification('success', message),
        notifyError: (message: string) => addNotification('error', message),
        notifyInfo: (message: string) => addNotification('info', message),
        removeNotification,
    };
}
