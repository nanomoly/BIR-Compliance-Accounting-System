<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { onMounted, reactive } from 'vue';
import SectionCard from '@/components/cas/SectionCard.vue';
import { useAuthPermissions } from '@/composables/useAuthPermissions';
import { useCasApi } from '@/composables/useCasApi';
import { useStateNotifications } from '@/composables/useStateNotifications';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

const api = useCasApi();
const { can } = useAuthPermissions();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'CAS Dashboard', href: '/cas' },
    { title: 'System Users', href: '/cas/users' },
];

const state = reactive({
    users: [] as Array<any>,
    roles: [] as string[],
    branches: [] as Array<{ id: number; name: string; code: string }>,
    exportFromDate: new Date(new Date().getFullYear(), new Date().getMonth(), 1)
        .toISOString()
        .slice(0, 10),
    exportToDate: new Date().toISOString().slice(0, 10),
    currentPage: 1,
    lastPage: 1,
    perPage: 15,
    total: 0,
    loading: false,
    saving: false,
    error: '',
    success: '',
});

useStateNotifications(state);

const form = reactive({
    name: '',
    email: '',
    password: '',
    role: '',
    branch_id: null as number | null,
});

async function loadData(page = 1) {
    state.loading = true;
    state.error = '';

    try {
        const [usersResponse, catalogResponse] = await Promise.all([
            api.get<{
                data: Array<any>;
                current_page: number;
                last_page: number;
                per_page: number;
                total: number;
            }>(`/api/system-users?per_page=${state.perPage}&page=${page}`),
            api.get<{
                roles: string[];
                branches: Array<{ id: number; name: string; code: string }>;
            }>('/api/system-users/catalog'),
        ]);

        state.users = usersResponse.data;
        state.currentPage = usersResponse.current_page;
        state.lastPage = usersResponse.last_page;
        state.perPage = usersResponse.per_page;
        state.total = usersResponse.total;
        state.roles = catalogResponse.roles;
        state.branches = catalogResponse.branches;

        if (state.roles.length > 0 && form.role.length === 0) {
            form.role = state.roles[0];
        }
    } catch (error) {
        state.error =
            error instanceof Error ? error.message : 'Failed to load users.';
    } finally {
        state.loading = false;
    }
}

function exportUsers() {
    const query = new URLSearchParams({
        from_date: state.exportFromDate,
        to_date: state.exportToDate,
    });

    window.open(`/api/exports/system-users?${query.toString()}`, '_blank');
}

async function createUser() {
    if (!can('users.create')) {
        return;
    }

    state.saving = true;
    state.error = '';
    state.success = '';

    try {
        await api.post('/api/system-users', {
            ...form,
            branch_id: form.branch_id,
        });

        state.success = 'System user created.';
        form.name = '';
        form.email = '';
        form.password = '';
        form.branch_id = null;
        await loadData();
    } catch (error) {
        state.error =
            error instanceof Error ? error.message : 'Failed to create user.';
    } finally {
        state.saving = false;
    }
}

onMounted(loadData);

function hasGrantedAccess(user: any): boolean {
    const roleCount = Array.isArray(user.roles) ? user.roles.length : 0;
    const permissionCount = Array.isArray(user.permissions)
        ? user.permissions.length
        : 0;

    return roleCount > 0 || permissionCount > 0;
}
</script>

<template>
    <Head title="System Users" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-4 p-4">
            <SectionCard
                title="Add System User"
                description="Create a new account and assign default role for module access."
            >
                <form class="grid gap-3 md:grid-cols-3" @submit.prevent="createUser">
                    <input
                        v-model="form.name"
                        required
                        placeholder="Full name"
                        class="rounded border px-3 py-2 text-sm"
                    />
                    <input
                        v-model="form.email"
                        type="email"
                        required
                        placeholder="Email"
                        class="rounded border px-3 py-2 text-sm"
                    />
                    <input
                        v-model="form.password"
                        type="password"
                        required
                        placeholder="Password"
                        class="rounded border px-3 py-2 text-sm"
                    />

                    <select
                        v-model="form.role"
                        required
                        class="rounded border px-3 py-2 text-sm"
                    >
                        <option value="" disabled>Select role</option>
                        <option v-for="role in state.roles" :key="role" :value="role">
                            {{ role }}
                        </option>
                    </select>

                    <select
                        v-model.number="form.branch_id"
                        class="rounded border px-3 py-2 text-sm"
                    >
                        <option :value="null">No branch</option>
                        <option
                            v-for="branch in state.branches"
                            :key="branch.id"
                            :value="branch.id"
                        >
                            {{ branch.code }} - {{ branch.name }}
                        </option>
                    </select>

                    <button
                        v-if="can('users.create')"
                        type="submit"
                        class="rounded bg-primary px-3 py-2 text-sm font-medium text-primary-foreground"
                        :disabled="state.saving"
                    >
                        {{ state.saving ? 'Saving...' : 'Create User' }}
                    </button>
                </form>
            </SectionCard>

            <SectionCard title="System Users List">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <p class="text-sm text-muted-foreground">Total: {{ state.total }}</p>
                    <div class="flex items-center gap-2">
                        <input
                            v-model="state.exportFromDate"
                            type="date"
                            class="rounded border px-2 py-2 text-sm"
                        />
                        <input
                            v-model="state.exportToDate"
                            type="date"
                            class="rounded border px-2 py-2 text-sm"
                        />
                        <button
                            v-if="can('users.view')"
                            type="button"
                            class="rounded border px-3 py-2 text-sm"
                            @click="exportUsers"
                        >
                            Export Excel
                        </button>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b">
                                <th class="px-2 py-2 text-left">Name</th>
                                <th class="px-2 py-2 text-left">Email</th>
                                <th class="px-2 py-2 text-left">Role</th>
                                <th class="px-2 py-2 text-left">Branch</th>
                                <th class="px-2 py-2 text-left">Access Granted</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="user in state.users"
                                :key="`user-${user.id}`"
                                class="border-b"
                            >
                                <td class="px-2 py-2">{{ user.name }}</td>
                                <td class="px-2 py-2">{{ user.email }}</td>
                                <td class="px-2 py-2">
                                    {{ user.role ?? user.roles?.[0]?.name ?? '-' }}
                                </td>
                                <td class="px-2 py-2">
                                    {{ user.branch?.name ?? '-' }}
                                </td>
                                <td class="px-2 py-2">
                                    {{ hasGrantedAccess(user) ? 'Yes' : 'No' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="mt-3 flex items-center gap-2">
                    <button
                        type="button"
                        class="rounded border px-3 py-1 text-sm"
                        :disabled="state.currentPage <= 1"
                        @click="loadData(state.currentPage - 1)"
                    >
                        Previous
                    </button>
                    <span class="text-sm text-muted-foreground">
                        Page {{ state.currentPage }} of {{ state.lastPage }}
                    </span>
                    <button
                        type="button"
                        class="rounded border px-3 py-1 text-sm"
                        :disabled="state.currentPage >= state.lastPage"
                        @click="loadData(state.currentPage + 1)"
                    >
                        Next
                    </button>
                </div>
            </SectionCard>

            <p v-if="state.loading" class="text-sm text-muted-foreground">
                Loading users...
            </p>
        </div>
    </AppLayout>
</template>
