import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

type AuthProps = {
    auth?: {
        permissions?: string[];
        ui_permissions?: string[];
    };
};

export function useUiPermissions() {
    const page = usePage<AuthProps>();

    const uiPermissions = computed(() => {
        const ui = page.props.auth?.ui_permissions;
        if (Array.isArray(ui)) {
            return ui;
        }

        return page.props.auth?.permissions ?? [];
    });

    function can(permission: string): boolean {
        return uiPermissions.value.includes(permission);
    }

    return {
        uiPermissions,
        can,
    };
}
