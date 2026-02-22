<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { onMounted, reactive, watch } from 'vue';
import SectionCard from '@/components/cas/SectionCard.vue';
import { useAuthPermissions } from '@/composables/useAuthPermissions';
import { useCasApi } from '@/composables/useCasApi';
import { useStateNotifications } from '@/composables/useStateNotifications';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Auth, BreadcrumbItem } from '@/types';

type UserOption = {
    id: number;
    name: string;
    email: string;
    has_access: boolean;
    is_default_admin: boolean;
};

const api = useCasApi();
const { can } = useAuthPermissions();
const page = usePage<{ auth: Auth }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'CAS Dashboard', href: '/cas' },
    { title: 'User Access', href: '/cas/user-access' },
];

const state = reactive({
    users: [] as UserOption[],
    roles: [] as string[],
    permissions: [] as string[],
    modules: {} as Record<string, string[]>,
    selectedUserId: 0,
    selectedRoles: [] as string[],
    selectedPermissions: [] as string[],
    rolePermissions: [] as string[],
    loading: false,
    saving: false,
    error: '',
    success: '',
});

useStateNotifications(state);

function selectedUser(): UserOption | undefined {
    return state.users.find((user) => user.id === state.selectedUserId);
}

function isRoleEditingLocked(): boolean {
    return selectedUser()?.is_default_admin ?? false;
}

function toggleRole(role: string) {
    if (isRoleEditingLocked()) {
        state.error = 'Role assignment is locked for the default CAS Admin user.';
        return;
    }

    if (state.selectedRoles.includes(role)) {
        state.selectedRoles = state.selectedRoles.filter((item) => item !== role);
        return;
    }

    state.selectedRoles = [...state.selectedRoles, role];
}

function togglePermission(permission: string) {
    const isInheritedOnly =
        state.rolePermissions.includes(permission)
        && !state.selectedPermissions.includes(permission);

    if (isInheritedOnly) {
        state.error = `Cannot uncheck ${permission} because it is inherited from selected role(s). Remove the role first.`;
        return;
    }

    if (state.selectedPermissions.includes(permission)) {
        state.selectedPermissions = state.selectedPermissions.filter(
            (item) => item !== permission,
        );
        return;
    }

    state.selectedPermissions = [...state.selectedPermissions, permission];
}

async function loadCatalog() {
    state.loading = true;
    state.error = '';

    try {
        const response = await api.get<{
            users: UserOption[];
            roles: string[];
            permissions: string[];
            modules: Record<string, string[]>;
        }>('/api/access/catalog');

        state.users = response.users;
        state.roles = response.roles;
        state.permissions = response.permissions;
        state.modules = response.modules;

        if (state.users.length > 0 && state.selectedUserId === 0) {
            state.selectedUserId = state.users[0].id;
        }
    } catch (error) {
        state.error =
            error instanceof Error ? error.message : 'Failed to load catalog.';
    } finally {
        state.loading = false;
    }
}

async function loadUserAccess() {
    if (state.selectedUserId === 0) {
        return;
    }

    state.loading = true;
    state.error = '';

    try {
        const response = await api.get<{
            access: {
                roles: string[];
                permissions: string[];
                role_permissions: string[];
                effective_permissions: string[];
            };
        }>(`/api/access/users/${state.selectedUserId}`);

        state.selectedRoles = response.access.roles;
        state.selectedPermissions = response.access.permissions;
        state.rolePermissions = response.access.role_permissions ?? [];
    } catch (error) {
        state.error =
            error instanceof Error
                ? error.message
                : 'Failed to load user access.';
    } finally {
        state.loading = false;
    }
}

async function saveUserAccess() {
    if (!can('user_access.assign')) {
        return;
    }

    if (state.selectedUserId === 0) {
        return;
    }

    if (!state.users.some((user) => user.id === state.selectedUserId)) {
        state.error = 'Selected user does not exist. Access changes were not saved.';
        return;
    }

    state.saving = true;
    state.error = '';
    state.success = '';

    try {
        const response = await api.post<{
            access: {
                roles: string[];
                permissions: string[];
                role_permissions: string[];
                effective_permissions: string[];
            };
        }>(`/api/access/users/${state.selectedUserId}/assign`, {
            roles: state.selectedRoles,
            permissions: state.selectedPermissions,
        });

        state.selectedRoles = response.access.roles;
        state.selectedPermissions = response.access.permissions;
        state.rolePermissions = response.access.role_permissions ?? [];

        const selectedUser = state.users.find(
            (user) => user.id === state.selectedUserId,
        );
        if (selectedUser) {
            selectedUser.has_access =
                state.selectedRoles.length > 0 ||
                state.selectedPermissions.length > 0;
        }

        const currentUserId = page.props.auth?.user?.id;
        if (currentUserId === state.selectedUserId) {
            const canStillManageAccess =
                state.selectedPermissions.includes('user_access.view')
                || state.rolePermissions.includes('user_access.view');

            if (!canStillManageAccess) {
                router.visit('/cas', { replace: true });
                return;
            }

            router.reload({
                only: ['auth'],
                preserveState: true,
                preserveScroll: true,
            });
        }

        state.success = 'User access updated.';
    } catch (error) {
        state.error =
            error instanceof Error
                ? error.message
                : 'Failed to update user access.';
    } finally {
        state.saving = false;
    }
}

watch(
    () => state.selectedUserId,
    () => {
        void loadUserAccess();
    },
);

onMounted(async () => {
    await loadCatalog();
    await loadUserAccess();
});
</script>

<template>
    <Head title="User Access" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-4 p-4">
            <SectionCard
                title="Select User"
                description="Assign module permissions and role sets per user."
            >
                <div class="grid gap-3 md:grid-cols-2">
                    <select
                        v-model.number="state.selectedUserId"
                        class="rounded border px-3 py-2 text-sm"
                    >
                        <option :value="0" disabled>Select user</option>
                        <option
                            v-for="user in state.users"
                            :key="user.id"
                            :value="user.id"
                        >
                            {{ user.name }} ({{ user.email }})
                            {{ user.has_access ? ' - Access Granted' : ' - No Access' }}
                        </option>
                    </select>
                    <button
                        v-if="can('user_access.assign')"
                        type="button"
                        class="rounded bg-primary px-3 py-2 text-sm font-medium text-primary-foreground"
                        :disabled="state.saving || state.selectedUserId === 0"
                        @click="saveUserAccess"
                    >
                        {{ state.saving ? 'Saving...' : 'Save Access' }}
                    </button>
                </div>
            </SectionCard>

            <SectionCard title="User Access Status">
                <div class="grid gap-2 md:grid-cols-2">
                    <label
                        v-for="user in state.users"
                        :key="`status-${user.id}`"
                        class="flex items-center gap-2 rounded border p-2 text-sm"
                    >
                        <input type="checkbox" :checked="user.has_access" disabled />
                        <span>{{ user.name }} ({{ user.email }})</span>
                    </label>
                </div>
            </SectionCard>

            <SectionCard title="Role Assignment">
                <p
                    v-if="isRoleEditingLocked()"
                    class="mb-2 text-sm text-muted-foreground"
                >
                    Role assignment is locked for the default CAS Admin user.
                </p>
                <div class="grid gap-2 md:grid-cols-3">
                    <label
                        v-for="role in state.roles"
                        :key="role"
                        class="flex items-center gap-2 rounded border p-2 text-sm"
                    >
                        <input
                            type="checkbox"
                            :checked="state.selectedRoles.includes(role)"
                            :disabled="isRoleEditingLocked()"
                            @change="toggleRole(role)"
                        />
                        {{ role }}
                    </label>
                </div>
            </SectionCard>

            <SectionCard title="Module Permission Matrix">
                <div class="grid gap-3">
                    <div
                        v-for="(actions, moduleName) in state.modules"
                        :key="moduleName"
                        class="rounded border p-3"
                    >
                        <p class="mb-2 text-sm font-semibold uppercase">
                            {{ moduleName }}
                        </p>
                        <div class="grid gap-2 md:grid-cols-3">
                            <label
                                v-for="action in actions"
                                :key="`${moduleName}.${action}`"
                                class="flex items-center gap-2 rounded border p-2 text-sm"
                            >
                                <input
                                    type="checkbox"
                                    :checked="
                                        state.selectedPermissions.includes(
                                            `${moduleName}.${action}`,
                                        )
                                        || state.rolePermissions.includes(
                                            `${moduleName}.${action}`,
                                        )
                                    "
                                    @change="
                                        togglePermission(
                                            `${moduleName}.${action}`,
                                        )
                                    "
                                />
                                {{ moduleName }}.{{ action }}
                            </label>
                        </div>
                    </div>
                </div>
            </SectionCard>

            <p v-if="state.loading" class="text-sm text-muted-foreground">
                Loading access data...
            </p>
        </div>
    </AppLayout>
</template>
