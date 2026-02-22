<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { onMounted, reactive } from 'vue';
import SectionCard from '@/components/cas/SectionCard.vue';
import { useAuthPermissions } from '@/composables/useAuthPermissions';
import { useCasApi } from '@/composables/useCasApi';
import { useStateNotifications } from '@/composables/useStateNotifications';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type BranchItem = {
    id: number;
    code: string;
    name: string;
    tin: string | null;
    address: string;
    is_main: boolean;
};

const api = useCasApi();
const { can } = useAuthPermissions();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'CAS Dashboard', href: '/cas' },
    { title: 'Branches', href: '/cas/branches' },
];

const state = reactive({
    branches: [] as BranchItem[],
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
    deleting: false,
    editingId: 0,
    error: '',
    success: '',
});

useStateNotifications(state);

const form = reactive({
    code: '',
    name: '',
    tin: '',
    address: '',
    is_main: false,
});

const editForm = reactive({
    code: '',
    name: '',
    tin: '',
    address: '',
    is_main: false,
});

async function loadData(page = 1) {
    state.loading = true;
    state.error = '';

    try {
        const response = await api.get<{
            data: BranchItem[];
            current_page: number;
            last_page: number;
            per_page: number;
            total: number;
        }>(`/api/branches?per_page=${state.perPage}&page=${page}`);

        state.branches = response.data;
        state.currentPage = response.current_page;
        state.lastPage = response.last_page;
        state.perPage = response.per_page;
        state.total = response.total;
    } catch (error) {
        state.error =
            error instanceof Error ? error.message : 'Failed to load branches.';
    } finally {
        state.loading = false;
    }
}

function exportBranches() {
    const query = new URLSearchParams({
        from_date: state.exportFromDate,
        to_date: state.exportToDate,
    });

    window.open(`/api/exports/branches?${query.toString()}`, '_blank');
}

async function createBranch() {
    if (!can('branches.create')) {
        return;
    }

    state.saving = true;
    state.error = '';
    state.success = '';

    try {
        await api.post('/api/branches', {
            code: form.code,
            name: form.name,
            tin: form.tin || null,
            address: form.address,
            is_main: form.is_main,
        });

        state.success = 'Branch created successfully.';
        form.code = '';
        form.name = '';
        form.tin = '';
        form.address = '';
        form.is_main = false;

        await loadData(state.currentPage);
    } catch (error) {
        state.error =
            error instanceof Error ? error.message : 'Failed to create branch.';
    } finally {
        state.saving = false;
    }
}

function startEdit(branch: BranchItem) {
    state.editingId = branch.id;
    editForm.code = branch.code;
    editForm.name = branch.name;
    editForm.tin = branch.tin ?? '';
    editForm.address = branch.address;
    editForm.is_main = branch.is_main;
}

function cancelEdit() {
    state.editingId = 0;
}

async function saveEdit(branchId: number) {
    if (!can('branches.update')) {
        return;
    }

    state.saving = true;
    state.error = '';
    state.success = '';

    try {
        await api.put(`/api/branches/${branchId}`, {
            code: editForm.code,
            name: editForm.name,
            tin: editForm.tin || null,
            address: editForm.address,
            is_main: editForm.is_main,
        });

        state.success = 'Branch updated successfully.';
        state.editingId = 0;
        await loadData(state.currentPage);
    } catch (error) {
        state.error =
            error instanceof Error ? error.message : 'Failed to update branch.';
    } finally {
        state.saving = false;
    }
}

async function deleteBranch(branchId: number) {
    if (!can('branches.delete')) {
        return;
    }

    state.deleting = true;
    state.error = '';
    state.success = '';

    try {
        await api.del(`/api/branches/${branchId}`);
        state.success = 'Branch deleted successfully.';
        await loadData(state.currentPage);
    } catch (error) {
        state.error =
            error instanceof Error ? error.message : 'Failed to delete branch.';
    } finally {
        state.deleting = false;
    }
}

onMounted(loadData);
</script>

<template>
    <Head title="Branches" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-4 p-4">
            <SectionCard
                title="Add Branch"
                description="Create and maintain company branch records."
            >
                <form class="grid gap-3 md:grid-cols-3" @submit.prevent="createBranch">
                    <input
                        v-model="form.code"
                        required
                        placeholder="Code"
                        class="rounded border px-3 py-2 text-sm"
                    />
                    <input
                        v-model="form.name"
                        required
                        placeholder="Branch name"
                        class="rounded border px-3 py-2 text-sm"
                    />
                    <input
                        v-model="form.tin"
                        placeholder="TIN"
                        class="rounded border px-3 py-2 text-sm"
                    />
                    <input
                        v-model="form.address"
                        required
                        placeholder="Address"
                        class="rounded border px-3 py-2 text-sm md:col-span-2"
                    />
                    <label class="flex items-center gap-2 rounded border px-3 py-2 text-sm">
                        <input v-model="form.is_main" type="checkbox" />
                        Main branch
                    </label>
                    <button
                        v-if="can('branches.create')"
                        type="submit"
                        class="rounded bg-primary px-3 py-2 text-sm font-medium text-primary-foreground"
                        :disabled="state.saving"
                    >
                        {{ state.saving ? 'Saving...' : 'Create Branch' }}
                    </button>
                </form>
            </SectionCard>

            <SectionCard title="Branch List">
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
                            v-if="can('branches.view')"
                            type="button"
                            class="rounded border px-3 py-2 text-sm"
                            @click="exportBranches"
                        >
                            Export Excel
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b">
                                <th class="px-2 py-2 text-left">Code</th>
                                <th class="px-2 py-2 text-left">Name</th>
                                <th class="px-2 py-2 text-left">TIN</th>
                                <th class="px-2 py-2 text-left">Address</th>
                                <th class="px-2 py-2 text-left">Main</th>
                                <th class="px-2 py-2 text-left">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="branch in state.branches"
                                :key="branch.id"
                                class="border-b"
                            >
                                <template v-if="state.editingId === branch.id">
                                    <td class="px-2 py-2 align-top">
                                        <input v-model="editForm.code" class="w-full rounded border px-2 py-1" />
                                    </td>
                                    <td class="px-2 py-2 align-top">
                                        <input v-model="editForm.name" class="w-full rounded border px-2 py-1" />
                                    </td>
                                    <td class="px-2 py-2 align-top">
                                        <input v-model="editForm.tin" class="w-full rounded border px-2 py-1" />
                                    </td>
                                    <td class="px-2 py-2 align-top">
                                        <input v-model="editForm.address" class="w-full rounded border px-2 py-1" />
                                    </td>
                                    <td class="px-2 py-2 align-top">
                                        <label class="flex items-center gap-2 text-sm">
                                            <input v-model="editForm.is_main" type="checkbox" />
                                            Yes
                                        </label>
                                    </td>
                                    <td class="px-2 py-2 align-top">
                                        <div class="flex gap-2">
                                            <button
                                                v-if="can('branches.update')"
                                                type="button"
                                                class="rounded border px-2 py-1"
                                                :disabled="state.saving"
                                                @click="saveEdit(branch.id)"
                                            >
                                                Save
                                            </button>
                                            <button
                                                type="button"
                                                class="rounded border px-2 py-1"
                                                @click="cancelEdit"
                                            >
                                                Cancel
                                            </button>
                                        </div>
                                    </td>
                                </template>

                                <template v-else>
                                    <td class="px-2 py-2 align-top">{{ branch.code }}</td>
                                    <td class="px-2 py-2 align-top">{{ branch.name }}</td>
                                    <td class="px-2 py-2 align-top">{{ branch.tin ?? '-' }}</td>
                                    <td class="px-2 py-2 align-top">{{ branch.address }}</td>
                                    <td class="px-2 py-2 align-top">{{ branch.is_main ? 'Yes' : 'No' }}</td>
                                    <td class="px-2 py-2 align-top">
                                        <div class="flex gap-2">
                                            <button
                                                v-if="can('branches.update')"
                                                type="button"
                                                class="rounded border px-2 py-1"
                                                @click="startEdit(branch)"
                                            >
                                                Edit
                                            </button>
                                            <button
                                                v-if="can('branches.delete')"
                                                type="button"
                                                class="rounded border px-2 py-1"
                                                :disabled="state.deleting"
                                                @click="deleteBranch(branch.id)"
                                            >
                                                Delete
                                            </button>
                                        </div>
                                    </td>
                                </template>
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
                Loading branches...
            </p>
        </div>
    </AppLayout>
</template>
