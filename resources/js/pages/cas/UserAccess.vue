<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { onMounted, reactive, watch } from 'vue';
import SectionCard from '@/components/cas/SectionCard.vue';
import { useCasApi } from '@/composables/useCasApi';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type UserOption = {
    id: number;
    name: string;
    email: string;
    has_access: boolean;
};

const api = useCasApi();

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
    loading: false,
    saving: false,
    error: '',
    success: '',
});

function toggleRole(role: string) {
    if (state.selectedRoles.includes(role)) {
        state.selectedRoles = state.selectedRoles.filter((item) => item !== role);
        return;
    }

    state.selectedRoles = [...state.selectedRoles, role];
}

function togglePermission(permission: string) {
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
            };
        }>(`/api/access/users/${state.selectedUserId}`);

        state.selectedRoles = response.access.roles;
        state.selectedPermissions = response.access.permissions;
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
        await api.post(`/api/access/users/${state.selectedUserId}/assign`, {
            roles: state.selectedRoles,
            permissions: state.selectedPermissions,
        });

        const selectedUser = state.users.find(
            (user) => user.id === state.selectedUserId,
        );
        if (selectedUser) {
            selectedUser.has_access =
                state.selectedRoles.length > 0 ||
                state.selectedPermissions.length > 0;
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
                <div class="grid gap-2 md:grid-cols-3">
                    <label
                        v-for="role in state.roles"
                        :key="role"
                        class="flex items-center gap-2 rounded border p-2 text-sm"
                    >
                        <input
                            type="checkbox"
                            :checked="state.selectedRoles.includes(role)"
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
            <p v-if="state.error" class="text-sm text-destructive">
                {{ state.error }}
            </p>
            <p v-if="state.success" class="text-sm text-emerald-600">
                {{ state.success }}
            </p>
        </div>
    </AppLayout>
</template>
