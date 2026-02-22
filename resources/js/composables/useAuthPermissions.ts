import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

type AuthProps = {
    auth?: {
        permissions?: string[];
    };
};

export function useAuthPermissions() {
    const page = usePage<AuthProps>();

    const permissions = computed(() => page.props.auth?.permissions ?? []);

    function can(permission: string): boolean {
        return permissions.value.includes(permission);
    }

    return {
        permissions,
        can,
    };
}
